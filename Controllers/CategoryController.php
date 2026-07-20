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
    private Category $category;

    public function __construct()
    {
        $this->category = new Category();
    }

    /** AJAX endpoint - creates a category from the inline "Add new category"
     *  control in the Product form's category combobox, returns JSON */
    public function quickCreate(): void
    {
        header('Content-Type: application/json');
        $name = trim($_POST['category_name'] ?? '');

        if ($name === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Category name is required.']);
            exit;
        }

        $existing = array_filter($this->category->readAll(), fn($c) => strcasecmp($c['category_name'], $name) === 0);
        if (!empty($existing)) {
            $match = array_values($existing)[0];
            echo json_encode(['id' => (int) $match['category_id'], 'name' => $match['category_name']]);
            exit;
        }

        $this->category->category_name = $name;
        $this->category->category_description = '';

        if ($this->category->create()) {
            echo json_encode(['id' => $this->category->lastInsertId(), 'name' => $name]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Could not create the category.']);
        }
        exit;
    }

    /** List all categories, each with its product count */
    public function index(): void
    {
        $categories = $this->category->readAllWithCounts();
        require __DIR__ . '/../Views/categories/index.php';
    }

    /** View a single category's details + the products in it */
    public function view(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $category = $this->category->readOne($id);

        if (!$category) {
            header("Location: index.php?module=categories&action=index");
            exit;
        }

        $itemModel = new InventoryItem();
        $products = $itemModel->readAllByCategory($id);

        require __DIR__ . '/../Views/categories/view.php';
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
