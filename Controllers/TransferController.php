<?php
require_once __DIR__ . '/../Models/Transaction.php';
require_once __DIR__ . '/../Models/InventoryItem.php';
require_once __DIR__ . '/../Models/ItemStock.php';
require_once __DIR__ . '/../Models/Location.php';

/**
 * TransferController.php
 * A Transfer moves several products from one location to another in a
 * single submission. Logged as one 'transfer'-type transaction row per
 * product (location_id = FROM, to_location_id = TO - see
 * migration_add_delivery_transfer.sql), each paired with two
 * ItemStock::adjust() calls (subtract at FROM, add at TO) to keep both
 * locations' quantities in sync. Every requested line is checked against
 * available stock at the FROM location before anything is written, so a
 * transfer either fully applies or fully fails - no partial transfers.
 */
class TransferController
{
    private Transaction $transaction;
    private InventoryItem $item;
    private ItemStock $itemStock;
    private Location $location;

    public function __construct()
    {
        $this->transaction = new Transaction();
        $this->item = new InventoryItem();
        $this->itemStock = new ItemStock();
        $this->location = new Location();
    }

    public function index(): void
    {
        $error = null;
        $items = $this->item->readAll();
        $locations = $this->location->readAll();
        $stockBreakdown = $this->itemStock->breakdownForItems(array_column($items, 'item_id'));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $movedBy = trim($_POST['technician_name'] ?? '');
            $fromLocationId = (int) ($_POST['from_location_id'] ?? 0);
            $toLocationId = (int) ($_POST['to_location_id'] ?? 0);
            $date = trim($_POST['transaction_date'] ?? '') ?: date('Y-m-d');
            $notes = trim($_POST['notes'] ?? '');
            $quantities = $_POST['quantities'] ?? [];

            $error = $this->validate($movedBy, $fromLocationId, $toLocationId, $quantities, $items);

            if (!$error) {
                $logged = 0;
                $referenceNumber = $this->transaction->nextReferenceNumber('TR');

                foreach ($quantities as $itemId => $qty) {
                    $qty = (int) $qty;
                    if ($qty <= 0) {
                        continue;
                    }
                    $itemId = (int) $itemId;

                    $this->transaction->transaction_id = null;
                    $this->transaction->item_id = $itemId;
                    $this->transaction->location_id = $fromLocationId;
                    $this->transaction->to_location_id = $toLocationId;
                    $this->transaction->transaction_type = 'transfer';
                    $this->transaction->reference_number = $referenceNumber;
                    $this->transaction->quantity = $qty;
                    $this->transaction->serial_number = null;
                    $this->transaction->transaction_date = $date;
                    $this->transaction->technician_name = $movedBy;
                    $this->transaction->supplier_name = null;
                    $this->transaction->notes = $notes;
                    $this->transaction->status = 'completed';

                    if ($this->transaction->create()) {
                        $this->itemStock->adjust($itemId, $fromLocationId, -$qty);
                        $this->itemStock->adjust($itemId, $toLocationId, $qty);
                        $logged++;
                    }
                }

                header("Location: index.php?module=transactions&action=index&status=transfer_logged&count=$logged&reference=$referenceNumber");
                exit;
            }
        }

        require __DIR__ . '/../Views/transfer/index.php';
    }

    private function validate(string $movedBy, int $fromLocationId, int $toLocationId, array $quantities, array $items): ?string
    {
        if ($movedBy === '') {
            return "Moved By is required.";
        }
        if ($fromLocationId <= 0 || $toLocationId <= 0) {
            return "Please select both a From and a To location.";
        }
        if ($fromLocationId === $toLocationId) {
            return "From and To locations must be different.";
        }

        $itemNames = array_column($items, 'model', 'item_id');
        $hasLine = false;
        $shortfalls = [];
        foreach ($quantities as $itemId => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) {
                continue;
            }
            $hasLine = true;
            $itemId = (int) $itemId;
            $available = $this->itemStock->getQuantity($itemId, $fromLocationId);
            if ($qty > $available) {
                $name = $itemNames[$itemId] ?? "item #$itemId";
                $shortfalls[] = "$name (need $qty, only $available available)";
            }
        }

        if (!$hasLine) {
            return "Enter a quantity for at least one product.";
        }
        if (!empty($shortfalls)) {
            return "Not enough stock at the From location for: " . implode('; ', $shortfalls) . ".";
        }
        return null;
    }
}
