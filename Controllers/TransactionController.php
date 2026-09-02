<?php
require_once __DIR__ . '/../Models/Transaction.php';
require_once __DIR__ . '/../Models/InventoryItem.php';
require_once __DIR__ . '/../Models/ItemStock.php';

/**
 * TransactionController.php
 * Handles all five transaction types. Item Request, Borrow, and Return
 * are plain activity-log entries - creating one never touches a
 * product's stock level. Stock In/Out are the exception: each one is
 * paired with an ItemStock::adjust() call here to keep that location's
 * quantity in sync (see item_stock, migration_add_stock_by_location.sql).
 * Item Request keeps its pending -> approve/decline workflow, but
 * approving one still doesn't move any stock - only Stock In/Out do.
 */
class TransactionController
{
    private Transaction $transaction;
    private InventoryItem $item;
    private ItemStock $itemStock;

    private const STOCK_TYPES = ['stock_in', 'stock_out'];

    public function __construct()
    {
        $this->transaction = new Transaction();
        $this->item = new InventoryItem();
        $this->itemStock = new ItemStock();
    }

    private const PER_PAGE = 10;

    /** List all transactions, optionally filtered by product / type / date range, sorted, and paginated */
    public function index(): void
    {
        $filterItemId = !empty($_GET['item_id']) ? (int) $_GET['item_id'] : null;
        $filterType = $_GET['type'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $sort = $_GET['sort'] ?? 'date_desc';

        $error = null;
        $transactions = [];
        $pagination = ['page' => 1, 'perPage' => self::PER_PAGE, 'totalCount' => 0, 'totalPages' => 1];

        try {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $totalCount = $this->transaction->countFiltered($filterItemId, $filterType, null, $dateFrom, $dateTo);
            $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * self::PER_PAGE;

            $transactions = $this->transaction->readAll($filterItemId, $filterType, null, $dateFrom, $dateTo, $sort, self::PER_PAGE, $offset);

            $pagination = [
                'page' => $page,
                'perPage' => self::PER_PAGE,
                'totalCount' => $totalCount,
                'totalPages' => $totalPages,
            ];
        } catch (PDOException $e) {
            $error = "Could not load transactions: " . $e->getMessage()
                . " — make sure the 'transactions' table exists (run database/migration_add_transactions.sql).";
        }
        $items = $this->item->readAll();
        require __DIR__ . '/../Views/transactions/index.php';
    }

    /** Show + handle the "log transaction" form, and the Products page's
     *  Stock In/Out modal (both POST here; the modal also sets
     *  redirect_to=products and location_id). */
    public function create(): void
    {
        $error = null;
        $items = $this->item->readAll();
        $prefillItemId = $_GET['item_id'] ?? null;
        $prefillType = $_GET['type'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $type = $_POST['transaction_type'] ?? '';
            $isStock = in_array($type, self::STOCK_TYPES, true);
            $toProducts = ($_POST['redirect_to'] ?? '') === 'products';

            $error = $this->validate($_POST);
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $locationId = (int) ($_POST['location_id'] ?? 0);
            $qty = (int) ($_POST['quantity'] ?? 0);

            // Stock Out can't take a location below zero - checked separately
            // from validate() so the message can include how much is available.
            if (!$error && $type === 'stock_out') {
                $available = $this->itemStock->getQuantity($itemId, $locationId);
                if ($qty > $available) {
                    if ($toProducts) {
                        header("Location: index.php?module=products&action=index&status=stock_out_insufficient&available=$available");
                        exit;
                    }
                    $error = "Not enough stock at that location: only $available available.";
                }
            }

            if (!$error) {
                $isRequest = $type === 'item_request';

                $this->transaction->item_id = $itemId;
                $this->transaction->location_id = $isStock ? $locationId : null;
                $this->transaction->transaction_type = $type;
                $this->transaction->quantity = $qty;
                $this->transaction->transaction_date = trim($_POST['transaction_date'] ?? '') ?: date('Y-m-d');
                $this->transaction->technician_name = trim($_POST['technician_name'] ?? '') ?: null;
                $this->transaction->notes = trim($_POST['notes'] ?? '');
                $this->transaction->status = $isRequest ? 'pending' : 'completed';

                if ($this->transaction->create()) {
                    if ($isStock) {
                        $delta = $type === 'stock_in' ? $qty : -$qty;
                        $this->itemStock->adjust($itemId, $locationId, $delta);
                    }

                    if ($toProducts) {
                        header("Location: index.php?module=products&action=index&status=$type");
                        exit;
                    }

                    $status = $isRequest ? 'requested' : 'created';
                    header("Location: index.php?module=transactions&action=index&status=$status");
                    exit;
                }
                $error = "Something went wrong while logging the transaction.";
            }
        }

        require __DIR__ . '/../Views/transactions/create.php';
    }

    /** Approves a pending Item Request: marks it completed. No stock effect - there's no stock level to deduct. */
    public function approve(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $record = $this->transaction->readOne($id);

            if (!$record || $record['transaction_type'] !== 'item_request' || $record['status'] !== 'pending') {
                header("Location: index.php?module=transactions&action=index&status=approve_invalid");
                exit;
            }

            $this->transaction->markCompleted($id);
        }

        header("Location: index.php?module=transactions&action=index&status=approved");
        exit;
    }

    /** Declines a pending Item Request. No stock effect (never deducted in
     *  the first place) - the row stays as a record that it was refused. */
    public function decline(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $record = $this->transaction->readOne($id);
            if ($record && $record['transaction_type'] === 'item_request' && $record['status'] === 'pending') {
                $this->transaction->markDeclined($id);
                header("Location: index.php?module=transactions&action=index&status=declined");
                exit;
            }
        }

        header("Location: index.php?module=transactions&action=index&status=approve_invalid");
        exit;
    }

    /** Bulk approve - only affects pending Item Requests among the selection;
     *  anything else (already handled, or a different type) is silently skipped */
    public function bulkApprove(): void
    {
        $ids = array_filter(array_map('intval', $_POST['selected_ids'] ?? []));
        $approved = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $record = $this->transaction->readOne($id);
            if (!$record || $record['transaction_type'] !== 'item_request' || $record['status'] !== 'pending') {
                $skipped++;
                continue;
            }

            $this->transaction->markCompleted($id);
            $approved++;
        }

        $status = $skipped > 0 ? 'bulk_approve_partial' : 'bulk_approved';
        header("Location: index.php?module=transactions&action=index&status=$status&count=$approved&skipped=$skipped");
        exit;
    }

    private function validate(array $input): ?string
    {
        if (empty($input['item_id'])) {
            return "Please select a product.";
        }
        if (empty($input['transaction_type']) || !in_array($input['transaction_type'], Transaction::TYPES, true)) {
            return "Please select a valid transaction type.";
        }
        if (!is_numeric($input['quantity'] ?? '') || (int) $input['quantity'] <= 0) {
            return "Quantity must be a positive number.";
        }
        if (in_array($input['transaction_type'], self::STOCK_TYPES, true) && empty($input['location_id'])) {
            return "Please select a location.";
        }
        if (trim($input['technician_name'] ?? '') === '') {
            return "Technician name is required.";
        }
        return null;
    }
}
