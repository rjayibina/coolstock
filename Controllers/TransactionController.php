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

    /** List all transactions, optionally filtered by product / type */
    public function index(): void
    {
        $filterItemId = !empty($_GET['item_id']) ? (int) $_GET['item_id'] : null;
        $filterType = $_GET['type'] ?? null;

        $error = null;
        $transactions = [];
        try {
            $transactions = $this->transaction->readAll($filterItemId, $filterType);
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
                $delta = Transaction::stockDelta($type, $qty);

                $current = $this->item->readOne($itemId);

                if ($delta < 0 && $current && ($current['quantity_on_hand'] + $delta) < 0) {
                    $error = "Not enough stock: only {$current['quantity_on_hand']} {$current['unit_of_measure']} available.";
                } else {
                    $this->transaction->item_id = $itemId;
                    $this->transaction->transaction_type = $type;
                    $this->transaction->quantity = $qty;
                    $this->transaction->technician_name = trim($_POST['technician_name'] ?? '') ?: null;
                    $this->transaction->notes = trim($_POST['notes'] ?? '');

                    if ($this->transaction->create()) {
                        $this->item->adjustQuantity($itemId, $delta);
                        header("Location: index.php?module=transactions&action=index&status=created");
                        exit;
                    }
                    $error = "Something went wrong while logging the transaction.";
                }
            }
        }

        require __DIR__ . '/../Views/transactions/create.php';
    }

    /** Delete a transaction and reverse its stock effect */
    public function delete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $record = $this->transaction->readOne($id);
            if ($record) {
                $reverseDelta = -Transaction::stockDelta($record['transaction_type'], (int) $record['quantity']);
                $this->item->adjustQuantity((int) $record['item_id'], $reverseDelta);
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
