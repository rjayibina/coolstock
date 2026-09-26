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
            // Delivery Date is a read-only field in the form (always
            // today) - always today here too, regardless of what's
            // POSTed, rather than trusting a value the UI never lets
            // anyone actually change.
            $date = date('Y-m-d');
            $notes = trim($_POST['notes'] ?? '');
            $quantities = $_POST['quantities'] ?? [];
            $manualProducts = $this->cleanManualProducts($_POST['manual_products'] ?? []);

            $error = $this->validate($receivedBy, $supplierName, $locationId, $quantities, $manualProducts, $date);

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
        $this->transaction->user_id = current_user()['user_id'] ?? null;
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
     *  Model, Category and Item Type are all required (see validate()) -
     *  every AC-spec field (energy rating, cooling capacity, etc.) is
     *  left null though, same as any Consumable/non-Asset product added
     *  through Products -> Add Product. Returns the new item_id, or null
     *  if the insert failed. */
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

    /** Trims the raw manual_products[] POST array down to only the rows
     *  that have SOMETHING in them - a row left completely blank (the user
     *  clicked "+ Add Product Manually" but never touched it) is dropped
     *  silently, since that's just an unused placeholder row. A row with
     *  only ONE of Model/Quantity filled in is a genuine mistake, so it's
     *  kept here on purpose rather than being silently discarded like a
     *  blank row - validate() below catches it and returns a specific
     *  error instead of the caller falling through to a generic "enter a
     *  quantity" message that doesn't mention the missing Model. */
    private function cleanManualProducts(array $raw): array
    {
        $cleaned = [];
        foreach ($raw as $mp) {
            $model = trim($mp['model'] ?? '');
            $qty = (int) ($mp['quantity'] ?? 0);
            if ($model === '' && $qty <= 0) {
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

    private function validate(string $receivedBy, string $supplierName, int $locationId, array $quantities, array $manualProducts, string $date = ''): ?string
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
        if ($date !== '' && $date > date('Y-m-d')) {
            return "Delivery Date cannot be in the future.";
        }

        // A manually-added row that made it past cleanManualProducts() has
        // at least one of Model/Quantity filled in - if it's not both, say
        // exactly which one is missing rather than letting it silently
        // fail the "at least one product" check below with no explanation.
        foreach ($manualProducts as $mp) {
            if ($mp['model'] === '') {
                return "Enter a Model name for each manually-added product (or remove the empty row).";
            }
            if (empty($mp['category_id'])) {
                return "Select a Category for \"{$mp['model']}\".";
            }
            if (empty($mp['item_type_id'])) {
                return "Select an Item Type for \"{$mp['model']}\".";
            }
            if ($mp['quantity'] <= 0) {
                return "Enter a quantity for \"{$mp['model']}\".";
            }
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
