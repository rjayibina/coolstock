<?php
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/InventoryItem.php';
require_once __DIR__ . '/../Models/ItemType.php';

/**
 * CategoryController.php
 * Sits between the router (index.php), the Category model, and the views.
 * Every action loads the model, does whatever it needs, then requires a view.
 */
class CategoryController
{
    private const PER_PAGE = 10;

    private Category $category;
    private ItemType $itemType;

    public function __construct()
    {
        $this->category = new Category();
        $this->itemType = new ItemType();
    }

    /** Validates the submitted "Locks Item Type" select - blank/absent is
     *  valid (null - no auto-lock), otherwise it must be a real
     *  item_type_id. Returns [value, error]. */
    private function parseItemTypeId(): array
    {
        $raw = trim($_POST['item_type_id'] ?? '');
        if ($raw === '') {
            return [null, null];
        }
        $id = (int) $raw;
        if ($id <= 0 || !$this->itemType->readOne($id)) {
            return [null, "Please choose a valid Item Type to lock to, or leave it blank."];
        }
        return [$id, null];
    }

    /** List all categories, each with its product count, sorted and paginated */
    public function index(): void
    {
        $sort = $_GET['sort'] ?? 'newest';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalCount = $this->category->count();
        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $categories = $this->category->readAllWithCounts($sort, self::PER_PAGE, $offset);
        $itemTypes = $this->itemType->readAll();

        $pagination = [
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
        ];

        require __DIR__ . '/../Views/categories/index.php';
    }

    /** Show + handle the "create category" form */
    public function create(): void
    {
        $error = null;
        $itemTypes = $this->itemType->readAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['category_name'] ?? '');
            [$itemTypeId, $itemTypeError] = $this->parseItemTypeId();

            if ($name === '') {
                $error = "Category name is required.";
            } elseif (strlen($name) > 100) {
                $error = "Category name must be at most 100 characters.";
            } elseif ($itemTypeError) {
                $error = $itemTypeError;
            } else {
                $this->category->category_name = $name;
                $this->category->item_type_id = $itemTypeId;

                if ($this->category->create()) {
                    header("Location: index.php?module=categories&action=index&status=created");
                    exit;
                }
                $error = "Something went wrong while saving the category.";
            }
        }

        require __DIR__ . '/../Views/categories/create.php';
    }

    /** Show + handle the "edit category" form */
    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['category_id'] ?? 0);
        $error = null;
        $itemTypes = $this->itemType->readAll();

        if ($id <= 0) {
            header("Location: index.php?module=categories&action=index");
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['category_name'] ?? '');
            [$itemTypeId, $itemTypeError] = $this->parseItemTypeId();

            if ($name === '') {
                $error = "Category name is required.";
                $data = ['category_id' => $id, 'category_name' => $name, 'item_type_id' => $itemTypeId];
            } elseif (strlen($name) > 100) {
                $error = "Category name must be at most 100 characters.";
                $data = ['category_id' => $id, 'category_name' => $name, 'item_type_id' => $itemTypeId];
            } elseif ($itemTypeError) {
                $error = $itemTypeError;
                $data = ['category_id' => $id, 'category_name' => $name, 'item_type_id' => null];
            } else {
                $this->category->category_id = $id;
                $this->category->category_name = $name;
                $this->category->item_type_id = $itemTypeId;

                if ($this->category->update()) {
                    header("Location: index.php?module=categories&action=index&status=updated");
                    exit;
                }
                $error = "Something went wrong while updating the category.";
                $data = $this->category->readOne($id);
            }
        } else {
            $data = $this->category->readOne($id);
            if (!$data) {
                header("Location: index.php?module=categories&action=index");
                exit;
            }
        }

        require __DIR__ . '/../Views/categories/edit.php';
    }

    /** Bulk delete a set of categories - skips any still holding products */
    public function bulkDelete(): void
    {
        $ids = array_filter(array_map('intval', $_POST['selected_ids'] ?? []));

        if (!empty($ids)) {
            $result = $this->category->bulkDelete($ids);
            $status = !empty($result['skipped']) ? 'bulk_partial' : 'bulk_deleted';
            header("Location: index.php?module=categories&action=index&status=$status&count=" . count($result['deleted']) . "&skipped=" . count($result['skipped']));
            exit;
        }

        header("Location: index.php?module=categories&action=index");
        exit;
    }

    /** Delete a category */
    public function delete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            if ($this->category->hasLinkedItems($id)) {
                header("Location: index.php?module=categories&action=index&status=has_items");
                exit;
            }
            $this->category->delete($id);
        }

        header("Location: index.php?module=categories&action=index&status=deleted");
        exit;
    }
}
