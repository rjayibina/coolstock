<?php
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/InventoryItem.php';

/**
 * CategoryController.php
 * Sits between the router (index.php), the Category model, and the views.
 * Every action loads the model, does whatever it needs, then requires a view.
 */
class CategoryController
{
    private const PER_PAGE = 10;

    private Category $category;

    public function __construct()
    {
        $this->category = new Category();
    }

    /** List all categories, each with its product count, filtered/sorted/paginated */
    public function index(): void
    {
        $productFilter = $_GET['has_products'] ?? null;
        $sort = $_GET['sort'] ?? 'newest';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalCount = $this->category->countFiltered($productFilter);
        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $categories = $this->category->readAllWithCounts($productFilter, $sort, self::PER_PAGE, $offset);

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

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['category_name'] ?? '');
            $description = trim($_POST['category_description'] ?? '');

            if ($name === '') {
                $error = "Category name is required.";
            } else {
                $this->category->category_name = $name;
                $this->category->category_description = $description;

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

        if ($id <= 0) {
            header("Location: index.php?module=categories&action=index");
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['category_name'] ?? '');
            $description = trim($_POST['category_description'] ?? '');

            if ($name === '') {
                $error = "Category name is required.";
                $data = ['category_id' => $id, 'category_name' => $name, 'category_description' => $description];
            } else {
                $this->category->category_id = $id;
                $this->category->category_name = $name;
                $this->category->category_description = $description;

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
