<?php
require_once __DIR__ . '/../Models/Transaction.php';
require_once __DIR__ . '/../Models/InventoryItem.php';
require_once __DIR__ . '/../Models/ItemStock.php';

/**
 * TransactionController.php
 * Item Request, Borrow, and Return still exist as historical transaction
 * types (old seed data uses them, and the Product Movement filter/table
 * still display them) but there's currently no UI to create new ones or
 * to approve/decline a pending one - that whole workflow is on pause for
 * now. Stock In/Out are the exception: each one is paired with an
 * ItemStock::adjust() call here to keep that location's quantity in sync
 * (see item_stock, migration_add_stock_by_location.sql).
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
            $totalCount = $this->transaction->countGroups($filterItemId, $filterType, null, $dateFrom, $dateTo);
            $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * self::PER_PAGE;

            $transactions = $this->transaction->readGrouped($filterItemId, $filterType, null, $dateFrom, $dateTo, $sort, self::PER_PAGE, $offset);

            $pagination = [
                'page' => $page,
                'perPage' => self::PER_PAGE,
                'totalCount' => $totalCount,
                'totalPages' => $totalPages,
            ];
        } catch (PDOException $e) {
            $error = "Could not load transactions: " . $e->getMessage()
                . " — make sure the 'transactions' table exists (run database/coolstock_full_setup.sql).";
        }
        $items = $this->item->readAll();
        require __DIR__ . '/../Views/transactions/index.php';
    }

    /** Handles the Products page's single-product Stock Out (see
     *  Views/products/index.php's stockForm - the only remaining caller).
     *  Used to also power a standalone "Log Transaction" page for Item
     *  Request/Borrow/Return; that page is retired for now, so a GET here
     *  (no form submission) just bounces back to Products instead of
     *  rendering a form that no longer exists. */
    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?module=products&action=index");
            exit;
        }

        $type = $_POST['transaction_type'] ?? '';
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $locationId = (int) ($_POST['location_id'] ?? 0);

        // A serialized Stock Out submits one text input per unit instead
        // of a quantity number - see Views/products/index.php's stock
        // modal. When present, the count of filled-in serials *is* the
        // quantity; the hidden quantity field is ignored so the two
        // can never disagree.
        $serials = $this->cleanSerials($_POST['serial_numbers'] ?? []);
        $isSerialized = $type === 'stock_out' && !empty($serials);
        $qty = $isSerialized ? count($serials) : (int) ($_POST['quantity'] ?? 0);

        $error = $this->validate($_POST, $isSerialized ? $qty : null);

        if (!$error && $isSerialized && count($serials) !== count(array_unique($serials))) {
            $error = "Each serial number can only be used once per Stock Out.";
        }

        // Stock Out can't take a location below zero - checked separately
        // from validate() so the message can include how much is available.
        if (!$error && $type === 'stock_out') {
            $available = $this->itemStock->getQuantity($itemId, $locationId);
            if ($qty > $available) {
                header("Location: index.php?module=products&action=index&status=stock_out_insufficient&available=$available");
                exit;
            }
        }

        if ($error) {
            header("Location: index.php?module=products&action=index&status=stock_out_error&message=" . urlencode($error));
            exit;
        }

        $this->transaction->item_id = $itemId;
        $this->transaction->location_id = $locationId;
        $this->transaction->transaction_type = $type;
        $this->transaction->transaction_date = trim($_POST['transaction_date'] ?? '') ?: date('Y-m-d');
        $this->transaction->technician_name = trim($_POST['technician_name'] ?? '') ?: null;
        $this->transaction->notes = trim($_POST['notes'] ?? '');
        $this->transaction->status = 'completed';

        $created = $isSerialized
            ? $this->createSerializedStockOut($itemId, $serials)
            : $this->createSingle($qty);

        if (!$created) {
            header("Location: index.php?module=products&action=index&status=stock_out_error&message=" . urlencode('Something went wrong while logging the transaction.'));
            exit;
        }

        $this->itemStock->adjust($itemId, $locationId, -$qty);
        header("Location: index.php?module=products&action=index&status=$type");
        exit;
    }

    /** Inserts one transaction row using whatever $this->transaction is
     *  already populated with, at the given quantity. Used by create()
     *  for a non-serialized Stock Out. */
    private function createSingle(int $qty): bool
    {
        $this->transaction->quantity = $qty;
        $this->transaction->serial_number = null;
        return $this->transaction->create();
    }

    /** Inserts one transaction row per serial number (quantity = 1 each),
     *  reusing every other field already set on $this->transaction. Stops
     *  and returns false on the first failed insert. */
    private function createSerializedStockOut(int $itemId, array $serials): bool
    {
        foreach ($serials as $serial) {
            $this->transaction->transaction_id = null;
            $this->transaction->item_id = $itemId;
            $this->transaction->quantity = 1;
            $this->transaction->serial_number = $serial;
            if (!$this->transaction->create()) {
                return false;
            }
        }
        return true;
    }

    /** Trims and drops empty entries from a posted serial_numbers[] array */
    private function cleanSerials(array $raw): array
    {
        $cleaned = array_map('trim', $raw);
        return array_values(array_filter($cleaned, fn($s) => $s !== ''));
    }

    /** Returns every line item sharing one Delivery/Transfer reference
     *  number as JSON - powers the Product Movement "view products in
     *  this order/transfer" modal (see Transaction::readByReferenceNumber()). */
    public function batch(): void
    {
        header('Content-Type: application/json');

        $referenceNumber = trim($_GET['reference_number'] ?? '');
        if ($referenceNumber === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing reference number.']);
            exit;
        }

        $rows = $this->transaction->readByReferenceNumber($referenceNumber);
        echo json_encode(array_map(function ($row) {
            return [
                'model' => $row['model'] ?? 'Unknown product',
                'quantity' => (int) $row['quantity'],
                'serial_number' => $row['serial_number'],
                'location_name' => $row['location_name'],
                'to_location_name' => $row['to_location_name'],
                'manually_added' => (int) $row['manually_added'] === 1,
            ];
        }, $rows));
        exit;
    }

    /** Handles the Products page's "Stock Out Selected" bulk action: takes
     *  several products out of one location in a single submission. Mirrors
     *  Delivery/Transfer's convention - one 'stock_out' transaction row per
     *  unit removed (quantity = 1, serial_number set) for products whose
     *  item type requires a serial number, or one row per product
     *  (quantity = entered amount, serial_number null) otherwise. Every
     *  line is checked against available stock before anything is written,
     *  same as Transfer - no partial bulk stock-outs. */
    public function bulkStockOut(): void
    {
        $locationId = (int) ($_POST['location_id'] ?? 0);
        $releasedBy = trim($_POST['technician_name'] ?? '');
        $date = trim($_POST['transaction_date'] ?? '') ?: date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');
        $quantities = $_POST['quantities'] ?? [];
        $serialsByItem = $_POST['serials'] ?? [];

        $items = $this->item->readAll();
        $itemNames = array_column($items, 'model', 'item_id');
        $itemRequiresSerial = [];
        foreach ($items as $it) {
            $itemRequiresSerial[(int) $it['item_id']] = (int) $it['requires_serial'] === 1;
        }

        $error = $this->validateBulkStockOut($locationId, $releasedBy, $quantities, $serialsByItem, $itemNames, $itemRequiresSerial);

        if (!$error) {
            $lines = $this->buildBulkStockOutLines($quantities, $serialsByItem);
            $logged = 0;

            foreach ($lines as $itemId => $line) {
                $this->transaction->transaction_id = null;
                $this->transaction->item_id = $itemId;
                $this->transaction->location_id = $locationId;
                $this->transaction->to_location_id = null;
                $this->transaction->transaction_type = 'stock_out';
                $this->transaction->transaction_date = $date;
                $this->transaction->technician_name = $releasedBy;
                $this->transaction->supplier_name = null;
                $this->transaction->notes = $notes;
                $this->transaction->status = 'completed';

                if (!empty($line['serials'])) {
                    if (!$this->createSerializedStockOut($itemId, $line['serials'])) {
                        continue;
                    }
                } elseif (!$this->createSingle($line['qty'])) {
                    continue;
                }

                $this->itemStock->adjust($itemId, $locationId, -$line['qty']);
                $logged++;
            }

            header("Location: index.php?module=products&action=index&status=bulk_stock_out&count=$logged");
            exit;
        }

        header("Location: index.php?module=products&action=index&status=bulk_stock_out_error&message=" . urlencode($error));
        exit;
    }

    /** Merges quantities[] and serials[] into one line per item: qty is
     *  either the posted number (non-serialized) or the serial count
     *  (serialized) - so buildBulkStockOutLines() and the stock-availability
     *  check always agree on how many units a line actually removes. */
    private function buildBulkStockOutLines(array $quantities, array $serialsByItem): array
    {
        $lines = [];

        foreach ($serialsByItem as $itemId => $rawSerials) {
            $serials = $this->cleanSerials((array) $rawSerials);
            if (empty($serials)) {
                continue;
            }
            $lines[(int) $itemId] = ['qty' => count($serials), 'serials' => array_unique($serials)];
        }

        foreach ($quantities as $itemId => $qty) {
            $itemId = (int) $itemId;
            if (isset($lines[$itemId])) {
                continue; // already covered by serials above
            }
            $qty = (int) $qty;
            if ($qty > 0) {
                $lines[$itemId] = ['qty' => $qty, 'serials' => []];
            }
        }

        return $lines;
    }

    private function validateBulkStockOut(int $locationId, string $releasedBy, array $quantities, array $serialsByItem, array $itemNames, array $itemRequiresSerial): ?string
    {
        if ($releasedBy === '') {
            return "Released By is required.";
        }
        if ($locationId <= 0) {
            return "Please select a location.";
        }

        $lines = $this->buildBulkStockOutLines($quantities, $serialsByItem);
        if (empty($lines)) {
            return "Enter a quantity or serial numbers for at least one product.";
        }

        $shortfalls = [];
        foreach ($lines as $itemId => $line) {
            $name = $itemNames[$itemId] ?? "item #$itemId";

            if (($itemRequiresSerial[$itemId] ?? true) && empty($line['serials'])) {
                return "$name requires a serial number for each unit on Stock Out.";
            }
            if (!empty($line['serials']) && count($line['serials']) !== $line['qty']) {
                return "$name has a duplicate serial number - each one can only be used once per Stock Out.";
            }

            $available = $this->itemStock->getQuantity($itemId, $locationId);
            if ($line['qty'] > $available) {
                $shortfalls[] = "$name (need {$line['qty']}, only $available available)";
            }
        }

        if (!empty($shortfalls)) {
            return "Not enough stock at that location for: " . implode('; ', $shortfalls) . ".";
        }
        return null;
    }

    /** $serializedQty, when passed, is the count of entered serial numbers
     *  for a serialized Stock Out - checked instead of the posted quantity
     *  field, which that form doesn't send. */
    private function validate(array $input, ?int $serializedQty = null): ?string
    {
        if (empty($input['item_id'])) {
            return "Please select a product.";
        }
        if (empty($input['transaction_type']) || !in_array($input['transaction_type'], Transaction::TYPES, true)) {
            return "Please select a valid transaction type.";
        }
        if ($serializedQty !== null) {
            if ($serializedQty <= 0) {
                return "Enter at least one serial number.";
            }
        } elseif (!is_numeric($input['quantity'] ?? '') || (int) $input['quantity'] <= 0) {
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
