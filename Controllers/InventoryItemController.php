<?php
require_once __DIR__ . '/../Models/InventoryItem.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Brand.php';
require_once __DIR__ . '/../Models/ItemType.php';
require_once __DIR__ . '/../Models/Location.php';
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

    public function __construct()
    {
        $this->item = new InventoryItem();
        $this->category = new Category();
        $this->brand = new Brand();
        $this->itemType = new ItemType();
        $this->location = new Location();
    }

    private const PER_PAGE = 10;

    /** List all inventory items, optionally filtered by category / location, and paginated */
    public function index(): void
    {
        $categoryId = $_GET['category_id'] ?? null;
        $categoryId = ($categoryId === '') ? null : $categoryId;
        $locationId = $_GET['location_id'] ?? null;
        $locationId = ($locationId === '') ? null : $locationId;
        $sort = $_GET['sort'] ?? 'newest';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalCount = $this->item->countFiltered($categoryId, $locationId);
        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $items = $this->item->readAll($categoryId, $sort, self::PER_PAGE, $offset, $locationId);
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
        $locationId = $_GET['location_id'] ?? null;
        $locationId = ($locationId === '') ? null : $locationId;

        $items = $this->item->readAll($categoryId, null, null, null, $locationId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_' . date('Y-m-d_His') . '.csv"');

        $out = fopen('php://output', 'w');
        // Same column set the Import feature expects, so export/import round-trip cleanly.
        // brand_name / type_name / location_name are lookup columns (like category_name) -
        // the actual FKs are brand_id / item_type_id / location_id.
        fputcsv($out, ['category_name', 'brand_name', 'type_name', 'location_name', 'model', 'energy_rating', 'monthly_consumption', 'cooling_capacity', 'refrigerant', 'installation_type', 'power_input', 'year']);
        foreach ($items as $it) {
            fputcsv($out, [
                $it['category_name'] ?? '',
                $it['brand_name'] ?? '',
                $it['type_name'] ?? '',
                $it['location_name'] ?? '',
                $it['model'],
                $it['energy_rating'] ?? '',
                $it['monthly_consumption'] ?? '',
                $it['cooling_capacity'] ?? '',
                $it['refrigerant'] ?? '',
                $it['installation_type'] ?? '',
                $it['power_input'] ?? '',
                $it['year'] ?? '',
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
                $this->hydrate($this->item, $_POST);

                if ($this->item->create()) {
                    header("Location: index.php?module=products&action=index&status=created");
                    exit;
                }
                $error = "Something went wrong while saving the product.";
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
            $data = array_merge(['item_id' => $id], $_POST);

            if (!$error) {
                $this->item->item_id = $id;
                $this->hydrate($this->item, $_POST);

                if ($this->item->update()) {
                    header("Location: index.php?module=products&action=index&status=updated");
                    exit;
                }
                $error = "Something went wrong while updating the product.";
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

    /** Delete a product */
    public function delete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
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

        // Expected header: category_name, brand_name, type_name, location_name,
        //                   model, energy_rating, monthly_consumption, cooling_capacity,
        //                   refrigerant, installation_type, power_input, year
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
            $model = trim($row[$col['model']] ?? '');

            if ($model === '') {
                $skipped[] = "Row $rowNum: missing model.";
                continue;
            }
            if ($categoryName === '') {
                $skipped[] = "Row $rowNum: missing category_name (category is required).";
                continue;
            }
            if (!isset($categoryByName[$categoryName])) {
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

            $isAsset = $typeName === 'asset';
            if ($isAsset) {
                $missingSpec = null;
                foreach (self::SPEC_FIELDS as $field => $label) {
                    if (trim((string) ($row[$col[$field] ?? null] ?? '')) === '') {
                        $missingSpec = $label;
                        break;
                    }
                }
                if ($missingSpec !== null) {
                    $skipped[] = "Row $rowNum: $missingSpec is required when type_name is Asset.";
                    continue;
                }
                if (!is_numeric($row[$col['monthly_consumption']] ?? '')) {
                    $skipped[] = "Row $rowNum: monthly_consumption must be a number when type_name is Asset.";
                    continue;
                }
            }

            $this->item->item_id = null;
            $this->item->category_id = $categoryByName[$categoryName] ?? null;
            $this->item->brand_id = $brandByName[$brandName] ?? null;
            $this->item->item_type_id = $itemTypeByName[$typeName] ?? null;
            $this->item->location_id = $locationByName[$locationName] ?? null;
            $this->item->model = $model;
            $this->item->energy_rating = $isAsset ? trim($row[$col['energy_rating']] ?? '') : null;
            $this->item->monthly_consumption = $isAsset ? (float) $row[$col['monthly_consumption']] : null;
            $this->item->cooling_capacity = $isAsset ? trim($row[$col['cooling_capacity']] ?? '') : null;
            $this->item->refrigerant = $isAsset ? trim($row[$col['refrigerant']] ?? '') : null;
            $this->item->installation_type = $isAsset ? trim($row[$col['installation_type']] ?? '') : null;
            $this->item->power_input = $isAsset ? trim($row[$col['power_input']] ?? '') : null;
            $this->item->year = $isAsset ? (int) $row[$col['year']] : null;

            if ($this->item->create()) {
                $imported++;
            } else {
                $skipped[] = "Row $rowNum: database insert failed.";
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /** Whether the given item_type_id (nullable/blank allowed) is the "Asset" item type.
     *  Technical Specifications are required and shown only for Asset - hidden and
     *  optional for Consumable or when no item type is set. */
    private function isAssetType(mixed $itemTypeId): bool
    {
        if (empty($itemTypeId)) {
            return false;
        }
        $type = $this->itemType->readOne((int) $itemTypeId);
        return $type && $type['type_name'] === 'Asset';
    }

    private const SPEC_FIELDS = [
        'energy_rating' => 'Energy Rating',
        'monthly_consumption' => 'Monthly Consumption',
        'cooling_capacity' => 'Cooling Capacity',
        'refrigerant' => 'Refrigerant',
        'installation_type' => 'Installation Type',
        'power_input' => 'Power Input',
        'year' => 'Year',
    ];

    /** Shared validation for create + edit */
    private function validate(array $input): ?string
    {
        if (trim($input['model'] ?? '') === '') {
            return "Model is required.";
        }
        if (empty($input['category_id'])) {
            return "Category is required.";
        }
        if ($this->isAssetType($input['item_type_id'] ?? null)) {
            foreach (self::SPEC_FIELDS as $field => $label) {
                if (trim((string) ($input[$field] ?? '')) === '') {
                    return "$label is required for Asset item types.";
                }
            }
            if (!is_numeric($input['monthly_consumption'] ?? '')) {
                return "Monthly Consumption must be a number.";
            }
        }
        if (!empty($input['year']) && (!is_numeric($input['year']) || $input['year'] < 1990 || $input['year'] > (int) date('Y') + 1)) {
            return "Year must be a valid model year.";
        }
        return null;
    }

    /** Copies POST data onto an InventoryItem model instance. Technical
     *  Specifications are only kept for the Asset item type - Consumable
     *  (or no item type) always saves them as null, regardless of what
     *  was submitted, since the form hides that section for those cases. */
    private function hydrate(InventoryItem $item, array $input): void
    {
        $isAsset = $this->isAssetType($input['item_type_id'] ?? null);

        $item->category_id = !empty($input['category_id']) ? (int) $input['category_id'] : null;
        $item->brand_id = !empty($input['brand_id']) ? (int) $input['brand_id'] : null;
        $item->item_type_id = !empty($input['item_type_id']) ? (int) $input['item_type_id'] : null;
        $item->location_id = !empty($input['location_id']) ? (int) $input['location_id'] : null;
        $item->model = trim($input['model']);
        $item->energy_rating = $isAsset ? (trim($input['energy_rating'] ?? '') ?: null) : null;
        $item->monthly_consumption = $isAsset && is_numeric($input['monthly_consumption'] ?? '') ? (float) $input['monthly_consumption'] : null;
        $item->cooling_capacity = $isAsset ? (trim($input['cooling_capacity'] ?? '') ?: null) : null;
        $item->refrigerant = $isAsset ? (trim($input['refrigerant'] ?? '') ?: null) : null;
        $item->installation_type = $isAsset ? (trim($input['installation_type'] ?? '') ?: null) : null;
        $item->power_input = $isAsset ? (trim($input['power_input'] ?? '') ?: null) : null;
        $item->year = $isAsset && is_numeric($input['year'] ?? '') ? (int) $input['year'] : null;
    }
}
