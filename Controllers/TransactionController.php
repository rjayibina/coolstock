<?php
require_once __DIR__ . '/../Models/Transaction.php';
require_once __DIR__ . '/../Models/InventoryItem.php';

/**
 * TransactionController.php
 * Handles Stock-In, Stock-Out, Item Request, Borrow, and Return.
 * Every create() call also adjusts inventory_items.quantity_on_hand so
 * the Products list always reflects the ledger. Delete reverses that
 * same adjustment before removing the row, so the two stay in sync.
 */
class TransactionController
{
    private Transaction $transaction;
    private InventoryItem $item;

    public function __construct()
    {
        $this->transaction = new Transaction();
        $this->item = new InventoryItem();
    }

    private const PER_PAGE = 10;

    /** List all transactions, optionally filtered by product / type, and paginated */
    public function index(): void
    {
        $filterItemId = !empty($_GET['item_id']) ? (int) $_GET['item_id'] : null;
        $filterType = $_GET['type'] ?? null;

        $error = null;
        $transactions = [];
        $pagination = ['page' => 1, 'perPage' => self::PER_PAGE, 'totalCount' => 0, 'totalPages' => 1];

        try {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $totalCount = $this->transaction->countFiltered($filterItemId, $filterType);
            $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * self::PER_PAGE;

            $transactions = $this->transaction->readAll($filterItemId, $filterType, null, self::PER_PAGE, $offset);

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

    /** Show + handle the "log transaction" form */
    public function create(): void
    {
        $error = null;
        $items = $this->item->readAll();
        $prefillItemId = $_GET['item_id'] ?? null;
        $prefillType = $_GET['type'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $error = $this->validate($_POST);

            if (!$error) {
                $itemId = (int) $_POST['item_id'];
                $type = $_POST['transaction_type'];
                $qty = (int) $_POST['quantity'];
                $isRequest = $type === 'item_request';
                $delta = Transaction::stockDelta($type, $qty);

                $current = $this->item->readOne($itemId);

                // Item Requests don't touch stock at creation time - the
                // sufficiency check happens later, when the request is approved.
                if (!$isRequest && $delta < 0 && $current && ($current['quantity_on_hand'] + $delta) < 0) {
                    $error = "Not enough stock: only {$current['quantity_on_hand']} {$current['unit_of_measure']} available.";
                } else {
                    $this->transaction->item_id = $itemId;
                    $this->transaction->transaction_type = $type;
                    $this->transaction->quantity = $qty;
                    $this->transaction->technician_name = trim($_POST['technician_name'] ?? '') ?: null;
                    $this->transaction->notes = trim($_POST['notes'] ?? '');
                    $this->transaction->status = $isRequest ? 'pending' : 'completed';

                    if ($this->transaction->create()) {
                        if (!$isRequest) {
                            $this->item->adjustQuantity($itemId, $delta);
                        }
                        $status = $isRequest ? 'requested' : 'created';
                        header("Location: index.php?module=transactions&action=index&status=$status");
                        exit;
                    }
                    $error = "Something went wrong while logging the transaction.";
                }
            }
        }

        require __DIR__ . '/../Views/transactions/create.php';
    }

    /** Delete a transaction and reverse its stock effect. Auto-generated
     *  transactions (product creation / direct edits) can't be deleted here -
     *  they're a record of something that already happened elsewhere. */
    /** Approves a pending Item Request: deducts stock now and marks it completed */
    public function approve(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $record = $this->transaction->readOne($id);

            if (!$record || $record['transaction_type'] !== 'item_request' || $record['status'] !== 'pending') {
                header("Location: index.php?module=transactions&action=index&status=approve_invalid");
                exit;
            }

            $current = $this->item->readOne((int) $record['item_id']);
            $quantity = (int) $record['quantity'];

            if (!$current || $current['quantity_on_hand'] < $quantity) {
                $available = $current['quantity_on_hand'] ?? 0;
                header("Location: index.php?module=transactions&action=index&status=approve_insufficient&available=$available");
                exit;
            }

            $this->item->adjustQuantity((int) $record['item_id'], -$quantity);
            $this->transaction->markCompleted($id);
        }

        header("Location: index.php?module=transactions&action=index&status=approved");
        exit;
    }

    /** Delete a transaction and reverse its stock effect. Auto-generated
     *  transactions (product creation / direct edits) can't be deleted here -
     *  they're a record of something that already happened elsewhere. */
    public function delete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $record = $this->transaction->readOne($id);
            if ($record && $record['source'] === 'auto') {
                header("Location: index.php?module=transactions&action=index&status=auto_locked");
                exit;
            }
            if ($record) {
                // Pending Item Requests never deducted stock in the first place -
                // reversing them would incorrectly add stock that was never removed.
                if ($record['status'] === 'completed') {
                    $reverseDelta = -Transaction::stockDelta($record['transaction_type'], (int) $record['quantity']);
                    $this->item->adjustQuantity((int) $record['item_id'], $reverseDelta);
                }
                $this->transaction->delete($id);
            }
        }

        header("Location: index.php?module=transactions&action=index&status=deleted");
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
        if (in_array($input['transaction_type'], ['item_request', 'borrow', 'return'], true)
            && trim($input['technician_name'] ?? '') === '') {
            return "Technician name is required for item requests, borrows, and returns.";
        }
        return null;
    }
}
