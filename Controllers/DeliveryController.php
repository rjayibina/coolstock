<?php
require_once __DIR__ . '/../Models/Transaction.php';
require_once __DIR__ . '/../Models/InventoryItem.php';
require_once __DIR__ . '/../Models/ItemStock.php';
require_once __DIR__ . '/../Models/Location.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/ItemType.php';

/**
 * DeliveryController.php
 * A Delivery receives several products from a supplier into one location
 * in a single submission. Logged as one 'delivery'-type transaction row
 * per product (same table Stock In/Out already use, see
 * migration_add_delivery_transfer.sql), each paired with an
 * ItemStock::adjust() call to add that quantity at the chosen location -
 * functionally a bulk Stock In with a supplier attached. There's no
 * separate Delivery list view: past deliveries show up in Product
 * Movement filtered by Type = Delivery, same as every other movement type.
 *
 * A line can also be a brand-new product that isn't in the catalog yet
 * (manual_products[] - see Views/delivery/index.php's "Add Product
 * Manually" section): each one is created via InventoryItem::create()
 * first, then logged exactly like a catalog line using the new item_id.
 */
class DeliveryController
{
    private Transaction $transaction;
    private InventoryItem $item;
    private ItemStock $itemStock;
    private Location $location;
    private Category $category;
    private ItemType $itemType;

    public function __construct()
    {
        $this->transaction = new Transaction();
        $this->item = new InventoryItem();
        $this->itemStock = new ItemStock();
        $this->location = new Location();
        $this->category = new Category();
        $this->itemType = new ItemType();
    }

    public function index(): void
    {
        $error = null;
        $items = $this->item->readAll();
        $locations = $this->location->readAll();
        $categories = $this->category->readAll();
        $itemTypes = $this->itemType->readAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $receivedBy = trim($_POST['technician_name'] ?? '');
            $supplierName = trim($_POST['supplier_name'] ?? '');
            $locationId = (int) ($_POST['location_id'] ?? 0);
            $date = trim($_POST['transaction_date'] ?? '') ?: date('Y-m-d');
            $notes = trim($_POST['notes'] ?? '');
            $quantities = $_POST['quantities'] ?? [];
            $manualProducts = $this->cleanManualProducts($_POST['manual_products'] ?? []);

            $error = $this->validate($receivedBy, $supplierName, $locationId, $quantities, $manualProducts);

            if (!$error) {
                $logged = 0;
                $referenceNumber = $this->transaction->nextReferenceNumber('DO');

                foreach ($quantities as $itemId => $qty) {
                    $qty = (int) $qty;
                    if ($qty <= 0) {
                        continue;
                    }
                    if ($this->logDeliveryLine((int) $itemId, $qty, $locationId, $referenceNumber, $date, $receivedBy, $supplierName, $notes, false)) {
                        $logged++;
                    }
                }

                foreach ($manualProducts as $mp) {
                    $newItemId = $this->createManualProduct($mp);
                    if ($newItemId !== null && $this->logDeliveryLine($newItemId, $mp['quantity'], $locationId, $referenceNumber, $date, $receivedBy, $supplierName, $notes, true)) {
                        $logged++;
                    }
                }

                header("Location: index.php?module=transactions&action=index&status=delivery_logged&count=$logged&reference=$referenceNumber");
                exit;
            }
        }

        require __DIR__ . '/../Views/delivery/index.php';
    }

    /** Logs one delivery line (transaction row + stock adjustment) for an
     *  item that already has an item_id - either an existing catalog
     *  product or one just created by createManualProduct(). $manuallyAdded
     *  flags the latter case, for the "New" badge on the batch modal. */
    private function logDeliveryLine(int $itemId, int $qty, int $locationId, string $referenceNumber, string $date, string $receivedBy, string $supplierName, string $notes, bool $manuallyAdded): bool
    {
        $this->transaction->transaction_id = null;
        $this->transaction->item_id = $itemId;
        $this->transaction->location_id = $locationId;
        $this->transaction->to_location_id = null;
        $this->transaction->transaction_type = 'delivery';
        $this->transaction->reference_number = $referenceNumber;
        $this->transaction->manually_added = $manuallyAdded;
        $this->transaction->quantity = $qty;
        $this->transaction->serial_number = null;
        $this->transaction->transaction_date = $date;
        $this->transaction->technician_name = $receivedBy;
        $this->transaction->supplier_name = $supplierName !== '' ? $supplierName : null;
        $this->transaction->notes = $notes;
        $this->transaction->status = 'completed';

        if (!$this->transaction->create()) {
            return false;
        }
        $this->itemStock->adjust($itemId, $locationId, $qty);
        return true;
    }

    /** Creates a new catalog product from one manual_products[] entry.
     *  Only Model is required - Category/Item Type are optional, and every
     *  AC-spec field (energy rating, cooling capacity, etc.) is left null,
     *  same as any other optional-spec product added through Products ->
     *  Add Product. Returns the new item_id, or null if the insert failed. */
    private function createManualProduct(array $mp): ?int
    {
        $this->item->item_id = null;
        $this->item->model = $mp['model'];
        $this->item->category_id = $mp['category_id'];
        $this->item->brand_id = null;
        $this->item->item_type_id = $mp['item_type_id'];
        $this->item->energy_rating = null;
        $this->item->monthly_consumption = null;
        $this->item->cooling_capacity = null;
        $this->item->refrigerant = null;
        $this->item->installation_type = null;
        $this->item->power_input = null;
        $this->item->year = null;

        if (!$this->item->create()) {
            return null;
        }
        return $this->item->lastInsertId();
    }

    /** Trims/validates the raw manual_products[] POST array down to only
     *  the rows that have both a Model name and a positive quantity -
     *  a row left blank (the user clicked "+ Add Product Manually" but
     *  didn't fill it in) is silently dropped rather than erroring. */
    private function cleanManualProducts(array $raw): array
    {
        $cleaned = [];
        foreach ($raw as $mp) {
            $model = trim($mp['model'] ?? '');
            $qty = (int) ($mp['quantity'] ?? 0);
            if ($model === '' || $qty <= 0) {
                continue;
            }
            $cleaned[] = [
                'model' => $model,
                'quantity' => $qty,
                'category_id' => !empty($mp['category_id']) ? (int) $mp['category_id'] : null,
                'item_type_id' => !empty($mp['item_type_id']) ? (int) $mp['item_type_id'] : null,
            ];
        }
        return $cleaned;
    }

    private function validate(string $receivedBy, string $supplierName, int $locationId, array $quantities, array $manualProducts): ?string
    {
        if ($receivedBy === '') {
            return "Received By is required.";
        }
        if ($supplierName === '') {
            return "Supplier is required.";
        }
        if ($locationId <= 0) {
            return "Please select a location.";
        }
        $hasLine = !empty($manualProducts);
        if (!$hasLine) {
            foreach ($quantities as $qty) {
                if ((int) $qty > 0) {
                    $hasLine = true;
                    break;
                }
            }
        }
        if (!$hasLine) {
            return "Enter a quantity for at least one product.";
        }
        return null;
    }
}
