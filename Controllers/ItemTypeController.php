<?php
require_once __DIR__ . '/../Models/ItemType.php';

/**
 * ItemTypeController.php
 * Sits between the router (index.php), the ItemType model, and the views.
 */
class ItemTypeController
{
    private const PER_PAGE = 10;

    private ItemType $itemType;

    public function __construct()
    {
        $this->itemType = new ItemType();
    }

    /** List all item types, filtered by product count, sorted, and paginated */
    public function index(): void
    {
        $productFilter = $_GET['has_products'] ?? null;
        $sort = $_GET['sort'] ?? 'newest';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalCount = $this->itemType->countFiltered($productFilter);
        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $itemTypes = $this->itemType->readAllWithCounts($productFilter, $sort, self::PER_PAGE, $offset);

        $pagination = [
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
        ];
        require __DIR__ . '/../Views/itemtypes/index.php';
    }

    /** Create an item type (called from the Add Item Type modal) */
    public function create(): void
    {
        $name = trim($_POST['type_name'] ?? '');

        if ($name === '') {
            header("Location: index.php?module=itemtypes&action=index&status=name_required");
            exit;
        }

        $this->itemType->type_name = $name;

        if ($this->itemType->create()) {
            header("Location: index.php?module=itemtypes&action=index&status=created");
            exit;
        }

        header("Location: index.php?module=itemtypes&action=index&status=error");
        exit;
    }

    /** Update an item type (called from the Edit Item Type modal) */
    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['item_type_id'] ?? 0);
        $name = trim($_POST['type_name'] ?? '');

        if ($id <= 0) {
            header("Location: index.php?module=itemtypes&action=index");
            exit;
        }

        if ($name === '') {
            header("Location: index.php?module=itemtypes&action=index&status=name_required");
            exit;
        }

        $this->itemType->item_type_id = $id;
        $this->itemType->type_name = $name;

        if ($this->itemType->update()) {
            header("Location: index.php?module=itemtypes&action=index&status=updated");
            exit;
        }

        header("Location: index.php?module=itemtypes&action=index&status=error");
        exit;
    }

    /** Delete an item type */
    public function delete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            if ($this->itemType->hasLinkedItems($id)) {
                header("Location: index.php?module=itemtypes&action=index&status=has_items");
                exit;
            }
            $this->itemType->delete($id);
        }

        header("Location: index.php?module=itemtypes&action=index&status=deleted");
        exit;
    }
}
