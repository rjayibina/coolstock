<?php
require_once __DIR__ . '/../Models/InventoryItem.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Brand.php';
require_once __DIR__ . '/../Models/ItemType.php';
require_once __DIR__ . '/../Models/Location.php';
require_once __DIR__ . '/../Models/Transaction.php';
require_once __DIR__ . '/../Helpers/SpreadsheetReader.php';

/**
 * InventoryItemController.php
 * Same pattern as CategoryController: validates input, talks to the
 * InventoryItem model, then hands off to a view. No SQL lives here.
 */
class InventoryItemController
{
    private InventoryItem $item;
    private Category $category;
    private Brand $brand;
    private ItemType $itemType;
    private Location $location;
    private Transaction $transaction;
    private string $uploadDir;

    public function __construct()
    {
        $this->item = new InventoryItem();
        $this->category = new Category();
        $this->brand = new Brand();
        $this->itemType = new ItemType();
        $this->location = new Location();
        $this->transaction = new Transaction();
        $this->uploadDir = __DIR__ . '/../assets/uploads/products/';
    }

    private const PER_PAGE = 10;

    /** List all inventory items, optionally filtered by category / stock status, and paginated */
    public function index(): void
    {
        $categoryId = $_GET['category_id'] ?? null;
        $categoryId = ($categoryId === '') ? null : $categoryId;
        $stockStatus = $_GET['stock_status'] ?? null;
        $locationId = $_GET['location_id'] ?? null;
        $locationId = ($locationId === '') ? null : $locationId;
        $sort = $_GET['sort'] ?? 'newest';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalCount = $this->item->countFiltered($categoryId, $stockStatus, $locationId);
        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $items = $this->item->readAll($categoryId, $stockStatus, $sort, self::PER_PAGE, $offset, $locationId);
        $categories = $this->category->readAll();
        $brands = $this->brand->readAll();
        $itemTypes = $this->itemType->readAll();
        $locations = $this->location->readAll();

        $pagination = [
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
        ];

        require __DIR__ . '/../Views/products/index.php';
    }

    /** Export the current filtered product list as a downloadable CSV */
    public function export(): void
    {
        $categoryId = $_GET['category_id'] ?? null;
        $categoryId = ($categoryId === '') ? null : $categoryId;
        $stockStatus = $_GET['stock_status'] ?? null;
        $locationId = $_GET['location_id'] ?? null;
        $locationId = ($locationId === '') ? null : $locationId;

        $items = $this->item->readAll($categoryId, $stockStatus, null, null, null, $locationId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_' . date('Y-m-d_His') . '.csv"');

        $out = fopen('php://output', 'w');
        // Same column set the Import feature expects, so export/import round-trip cleanly.
        // brand_name / type_name / location_name are lookup columns (like category_name) -
        // the actual FKs are brand_id / item_type_id / location_id. brand_code is
        // read-only/informational (it lives on the brands table, not the item) and is
        // ignored on import - only brand_name is used to resolve brand_id.
        fputcsv($out, ['category_name', 'item_name', 'description', 'unit_of_measure', 'brand_name', 'brand_code', 'type_name', 'location_name', 'model', 'energy_rating', 'monthly_consumption', 'cooling_capacity', 'refrigerant', 'installation_type', 'power_input', 'year', 'quantity_on_hand', 'minimum_stock_level']);
        foreach ($items as $it) {
            fputcsv($out, [
                $it['category_name'] ?? '',
                $it['item_name'],
                $it['description'],
                $it['unit_of_measure'],
                $it['brand_name'] ?? '',
                $it['brand_code'] ?? '',
                $it['type_name'] ?? '',
                $it['location_name'] ?? '',
                $it['model'] ?? '',
                $it['energy_rating'] ?? '',
                $it['monthly_consumption'] ?? '',
                $it['cooling_capacity'] ?? '',
                $it['refrigerant'] ?? '',
                $it['installation_type'] ?? '',
                $it['power_input'] ?? '',
                $it['year'] ?? '',
                $it['quantity_on_hand'],
                $it['minimum_stock_level'],
            ]);
        }
        fclose($out);
        exit;
    }

    /** Show + handle the "add product" form */
    public function create(): void
    {
        $error = null;
        $categories = $this->category->readAll();
        $brands = $this->brand->readAll();
        $itemTypes = $this->itemType->readAll();
        $locations = $this->location->readAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $error = $this->validate($_POST);

            if (!$error) {
                [$imagePath, $uploadError] = $this->handleImageUpload();
                if ($uploadError) {
                    $error = $uploadError;
                } else {
                    $this->hydrate($this->item, $_POST);
                    $this->item->image_path = $imagePath;

                    if ($this->item->create()) {
                        $newItemId = $this->item->lastInsertId();
                        if ($this->item->quantity_on_hand > 0) {
                            $this->logAutoTransaction($newItemId, 'stock_in', $this->item->quantity_on_hand, 'Initial stock on product creation.');
                        }
                        header("Location: index.php?module=products&action=index&status=created");
                        exit;
                    }
                    $error = "Something went wrong while saving the product.";
                }
            }
        }

        require __DIR__ . '/../Views/products/create.php';
    }

    /** Show + handle the "edit product" form */
    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['item_id'] ?? 0);
        $error = null;
        $categories = $this->category->readAll();
        $brands = $this->brand->readAll();
        $itemTypes = $this->itemType->readAll();
        $locations = $this->location->readAll();

        if ($id <= 0) {
            header("Location: index.php?module=products&action=index");
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $error = $this->validate($_POST);
            $existing = $this->item->readOne($id);
            $data = array_merge($existing ?: [], ['item_id' => $id], $_POST);

            if (!$error) {
                [$imagePath, $uploadError] = $this->handleImageUpload($existing['image_path'] ?? null);
                if ($uploadError) {
                    $error = $uploadError;
                } else {
                    $this->item->item_id = $id;
                    $this->hydrate($this->item, $_POST);
                    $this->item->image_path = $imagePath;

                    $oldQuantity = (int) ($existing['quantity_on_hand'] ?? 0);
                    $newQuantity = (int) $this->item->quantity_on_hand;

                    if ($this->item->update()) {
                        if ($newQuantity !== $oldQuantity) {
                            $delta = $newQuantity - $oldQuantity;
                            $type = $delta > 0 ? 'stock_in' : 'stock_out';
                            $this->logAutoTransaction($id, $type, abs($delta), 'Stock adjusted via product edit.');
                        }
                        header("Location: index.php?module=products&action=index&status=updated");
                        exit;
                    }
                    $error = "Something went wrong while updating the product.";
                }
            }
        } else {
            $data = $this->item->readOne($id);
            if (!$data) {
                header("Location: index.php?module=products&action=index");
                exit;
            }
        }

        require __DIR__ . '/../Views/products/edit.php';
    }

    /** Bulk-reassign category for a set of products */
    public function bulkUpdateCategory(): void
    {
        $ids = array_filter(array_map('intval', $_POST['selected_ids'] ?? []));
        $categoryId = (int) ($_POST['bulk_category_id'] ?? 0);

        if (!empty($ids) && $categoryId > 0) {
            $updated = $this->item->bulkUpdateCategory($ids, $categoryId);
            header("Location: index.php?module=products&action=index&status=bulk_updated&count=$updated");
            exit;
        }

        header("Location: index.php?module=products&action=index");
        exit;
    }

    /** Bulk Stock In - only for products that already exist on the Products
     *  page (selected via the bulk checkboxes). Quantity only, per product;
     *  rows left blank or 0 are skipped. Always logged as today - same rule
     *  as the single Stock In/Out modal. */
    public function bulkStockIn(): void
    {
        $quantities = $_POST['quantities'] ?? [];
        $notes = trim($_POST['notes'] ?? '');
        $created = 0;

        foreach ($quantities as $itemId => $qty) {
            $itemId = (int) $itemId;
            $qty = is_numeric($qty) ? (int) $qty : 0;

            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }

            $this->transaction->item_id = $itemId;
            $this->transaction->transaction_type = 'stock_in';
            $this->transaction->quantity = $qty;
            $this->transaction->serial_number = null;
            $this->transaction->transaction_date = date('Y-m-d');
            $this->transaction->technician_name = null;
            $this->transaction->notes = $notes;
            $this->transaction->source = 'manual';
            $this->transaction->status = 'completed';

            if ($this->transaction->create()) {
                $this->item->adjustQuantity($itemId, $qty);
                $created++;
            }
        }

        $status = $created > 0 ? 'bulk_stock_in' : 'bulk_stock_in_empty';
        header("Location: index.php?module=products&action=index&status=$status&count=$created");
        exit;
    }

    /** Delete a product (and its uploaded image, if any) */
    public function delete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $existing = $this->item->readOne($id);
            if ($existing && !empty($existing['image_path'])) {
                $this->deleteImageFile($existing['image_path']);
            }
            $this->item->delete($id);
        }

        header("Location: index.php?module=products&action=index&status=deleted");
        exit;
    }

    /** Show + handle the CSV/XLSX bulk import form */
    public function import(): void
    {
        $error = null;
        $results = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['import_file']['name'])) {
            try {
                $results = $this->processImport($_FILES['import_file']);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        require __DIR__ . '/../Views/products/import.php';
    }

    /** Reads the uploaded spreadsheet and inserts a product per valid row */
    private function processImport(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("File upload failed. Please try again.");
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $rows = SpreadsheetReader::read($file['tmp_name'], $extension);

        if (count($rows) < 2) {
            throw new RuntimeException("The file has no data rows below the header.");
        }

        // Expected header: category_name, item_name, description, unit_of_measure,
        //                   brand_name, type_name, location_name, quantity_on_hand, minimum_stock_level
        $header = array_map(fn($h) => strtolower(trim((string) $h)), array_shift($rows));
        $col = array_flip($header);

        $categories = $this->category->readAll();
        $categoryByName = [];
        foreach ($categories as $cat) {
            $categoryByName[strtolower($cat['category_name'])] = $cat['category_id'];
        }

        $brands = $this->brand->readAll();
        $brandByName = [];
        foreach ($brands as $b) {
            $brandByName[strtolower($b['brand_name'])] = $b['brand_id'];
        }

        $itemTypes = $this->itemType->readAll();
        $itemTypeByName = [];
        foreach ($itemTypes as $t) {
            $itemTypeByName[strtolower($t['type_name'])] = $t['item_type_id'];
        }

        $locations = $this->location->readAll();
        $locationByName = [];
        foreach ($locations as $loc) {
            $locationByName[strtolower($loc['location_name'])] = $loc['location_id'];
        }

        $imported = 0;
        $skipped = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // account for header + 0-index
            if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                continue; // skip fully blank rows
            }

            $categoryName = strtolower(trim($row[$col['category_name']] ?? ''));
            $brandName = strtolower(trim($row[$col['brand_name']] ?? ''));
            $typeName = strtolower(trim($row[$col['type_name']] ?? ''));
            $locationName = strtolower(trim($row[$col['location_name']] ?? ''));
            $itemName = trim($row[$col['item_name']] ?? '');

            if ($itemName === '') {
                $skipped[] = "Row $rowNum: missing item_name.";
                continue;
            }
            if ($categoryName !== '' && !isset($categoryByName[$categoryName])) {
                $skipped[] = "Row $rowNum: category \"" . ($row[$col['category_name']] ?? '') . "\" not found.";
                continue;
            }
            if ($brandName !== '' && !isset($brandByName[$brandName])) {
                $skipped[] = "Row $rowNum: brand \"" . ($row[$col['brand_name']] ?? '') . "\" not found.";
                continue;
            }
            if ($typeName !== '' && !isset($itemTypeByName[$typeName])) {
                $skipped[] = "Row $rowNum: item type \"" . ($row[$col['type_name']] ?? '') . "\" not found.";
                continue;
            }
            if ($locationName !== '' && !isset($locationByName[$locationName])) {
                $skipped[] = "Row $rowNum: location \"" . ($row[$col['location_name']] ?? '') . "\" not found.";
                continue;
            }

            $this->item->item_id = null;
            $this->item->category_id = $categoryByName[$categoryName] ?? null;
            $this->item->item_name = $itemName;
            $this->item->description = trim($row[$col['description']] ?? '');
            $this->item->unit_of_measure = trim($row[$col['unit_of_measure']] ?? '');
            $this->item->brand_id = $brandByName[$brandName] ?? null;
            $this->item->item_type_id = $itemTypeByName[$typeName] ?? null;
            $this->item->location_id = $locationByName[$locationName] ?? null;
            $this->item->model = isset($col['model']) ? (trim($row[$col['model']] ?? '') ?: null) : null;
            $this->item->energy_rating = isset($col['energy_rating']) ? (trim($row[$col['energy_rating']] ?? '') ?: null) : null;
            $this->item->monthly_consumption = isset($col['monthly_consumption']) && is_numeric($row[$col['monthly_consumption']] ?? '') ? (float) $row[$col['monthly_consumption']] : null;
            $this->item->cooling_capacity = isset($col['cooling_capacity']) ? (trim($row[$col['cooling_capacity']] ?? '') ?: null) : null;
            $this->item->refrigerant = isset($col['refrigerant']) ? (trim($row[$col['refrigerant']] ?? '') ?: null) : null;
            $this->item->installation_type = isset($col['installation_type']) ? (trim($row[$col['installation_type']] ?? '') ?: null) : null;
            $this->item->power_input = isset($col['power_input']) ? (trim($row[$col['power_input']] ?? '') ?: null) : null;
            $this->item->year = isset($col['year']) && is_numeric($row[$col['year']] ?? '') ? (int) $row[$col['year']] : null;
            $this->item->quantity_on_hand = (int) ($row[$col['quantity_on_hand']] ?? 0);
            $this->item->minimum_stock_level = (int) ($row[$col['minimum_stock_level']] ?? 0);
            $this->item->image_path = null;

            if ($this->item->create()) {
                $imported++;
            } else {
                $skipped[] = "Row $rowNum: database insert failed.";
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /** Shared validation for create + edit */
    /** Logs a system-generated transaction (source='auto') - used when a product
     *  is created with starting stock, or its quantity changes via direct edit.
     *  Unlike manually logged transactions, these don't adjust stock themselves
     *  (the create/update call already did that) - they're a record only. */
    private function logAutoTransaction(int $itemId, string $type, int $quantity, string $notes): void
    {
        $this->transaction->item_id = $itemId;
        $this->transaction->transaction_type = $type;
        $this->transaction->quantity = $quantity;
        $this->transaction->technician_name = null;
        $this->transaction->notes = $notes;
        $this->transaction->source = 'auto';
        $this->transaction->create();
    }

    private function validate(array $input): ?string
    {
        if (trim($input['item_name'] ?? '') === '') {
            return "Product name is required.";
        }
        if (!is_numeric($input['quantity_on_hand'] ?? '') || $input['quantity_on_hand'] < 0) {
            return "Quantity on hand must be a non-negative number.";
        }
        if (!is_numeric($input['minimum_stock_level'] ?? '') || $input['minimum_stock_level'] < 0) {
            return "Minimum stock level must be a non-negative number.";
        }
        if (!empty($input['year']) && (!is_numeric($input['year']) || $input['year'] < 1990 || $input['year'] > (int) date('Y') + 1)) {
            return "Year must be a valid model year.";
        }
        return null;
    }

    /** Copies POST data onto an InventoryItem model instance */
    private function hydrate(InventoryItem $item, array $input): void
    {
        $item->category_id = !empty($input['category_id']) ? (int) $input['category_id'] : null;
        $item->item_name = trim($input['item_name']);
        $item->description = trim($input['description'] ?? '');
        $item->unit_of_measure = trim($input['unit_of_measure'] ?? '');
        $item->brand_id = !empty($input['brand_id']) ? (int) $input['brand_id'] : null;
        $item->item_type_id = !empty($input['item_type_id']) ? (int) $input['item_type_id'] : null;
        $item->location_id = !empty($input['location_id']) ? (int) $input['location_id'] : null;
        $item->model = trim($input['model'] ?? '') ?: null;
        $item->energy_rating = trim($input['energy_rating'] ?? '') ?: null;
        $item->monthly_consumption = is_numeric($input['monthly_consumption'] ?? '') ? (float) $input['monthly_consumption'] : null;
        $item->cooling_capacity = trim($input['cooling_capacity'] ?? '') ?: null;
        $item->refrigerant = trim($input['refrigerant'] ?? '') ?: null;
        $item->installation_type = trim($input['installation_type'] ?? '') ?: null;
        $item->power_input = trim($input['power_input'] ?? '') ?: null;
        $item->year = is_numeric($input['year'] ?? '') ? (int) $input['year'] : null;
        $item->quantity_on_hand = (int) $input['quantity_on_hand'];
        $item->minimum_stock_level = (int) $input['minimum_stock_level'];
    }

    /**
     * Handles an optional product_image upload. Returns [imagePath, error].
     * $keepExisting is the current image_path (edit form) to fall back to
     * when no new file was chosen.
     */
    private function handleImageUpload(?string $keepExisting = null): array
    {
        // "Remove image" checkbox takes effect only when no new file is chosen
        // (uploading a new file always wins over a stale "remove" checkbox state)
        if (empty($_FILES['product_image']['name']) && !empty($_POST['remove_image'])) {
            if ($keepExisting) {
                $this->deleteImageFile($keepExisting);
            }
            return [null, null];
        }

        if (empty($_FILES['product_image']['name'])) {
            return [$keepExisting, null];
        }

        $file = $_FILES['product_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                    "That image is larger than this server allows (PHP's upload_max_filesize / post_max_size in php.ini — often 2MB by default on XAMPP). "
                    . "Either use a smaller image or raise those two values in php.ini and restart Apache.",
                UPLOAD_ERR_PARTIAL => "The image only partially uploaded. Please try again.",
                UPLOAD_ERR_NO_TMP_DIR => "The server has no temporary folder configured for uploads.",
                UPLOAD_ERR_CANT_WRITE => "The server couldn't write the uploaded file to disk.",
                default => "Image upload failed (error code {$file['error']}). Please try again.",
            };
            return [$keepExisting, $message];
        }

        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!array_key_exists($extension, $allowed)) {
            return [$keepExisting, "Product image must be a JPG, PNG, GIF, or WEBP file."];
        }

        // Verify the file's actual content matches an image, not just its extension
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return [$keepExisting, "That file doesn't look like a valid image. Please choose a JPG, PNG, GIF, or WEBP file."];
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            return [$keepExisting, "Product image must be smaller than 5MB."];
        }

        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true) && !is_dir($this->uploadDir)) {
                $absolutePath = realpath(__DIR__ . '/../assets/uploads') ?: (__DIR__ . '/../assets/uploads');
                return [$keepExisting, "The upload folder doesn't exist and couldn't be created automatically. "
                    . "Create it manually at: {$absolutePath}/products"];
            }
        }

        if (!is_writable($this->uploadDir)) {
            $absolutePath = realpath($this->uploadDir) ?: $this->uploadDir;
            return [$keepExisting, "The server can't write to the upload folder ({$absolutePath}). "
                . "On XAMPP/macOS, run this in Terminal: chmod -R 775 \"{$absolutePath}\" "
                . "— and make sure it's owned by the user Apache runs as (often _www)."];
        }

        $filename = uniqid('product_', true) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $this->uploadDir . $filename)) {
            $absolutePath = realpath($this->uploadDir) ?: $this->uploadDir;
            return [$keepExisting, "Could not save the uploaded image to {$absolutePath}. Check its permissions and that PHP's temp upload folder is accessible."];
        }

        // Replacing an image on edit - clean up the old file
        if ($keepExisting) {
            $this->deleteImageFile($keepExisting);
        }

        return ['assets/uploads/products/' . $filename, null];
    }

    private function deleteImageFile(string $imagePath): void
    {
        $fullPath = __DIR__ . '/../' . $imagePath;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
