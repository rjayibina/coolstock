<?php
require_once __DIR__ . '/../Models/Brand.php';

/**
 * BrandController.php
 * Sits between the router (index.php), the Brand model, and the views.
 */
class BrandController
{
    private Brand $brand;

    public function __construct()
    {
        $this->brand = new Brand();
    }

    /** List all brands */
    public function index(): void
    {
        $brands = $this->brand->readAll();
        require __DIR__ . '/../Views/brands/index.php';
    }

    /** Create a brand (called from the Add Brand modal) */
    public function create(): void
    {
        $name = trim($_POST['brand_name'] ?? '');
        $code = trim($_POST['brand_code'] ?? '');

        if ($name === '') {
            header("Location: index.php?module=brands&action=index&status=name_required");
            exit;
        }

        $this->brand->brand_name = $name;
        $this->brand->brand_code = $code ?: null;

        if ($this->brand->create()) {
            header("Location: index.php?module=brands&action=index&status=created");
            exit;
        }

        header("Location: index.php?module=brands&action=index&status=error");
        exit;
    }

    /** Update a brand (called from the Edit Brand modal) */
    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['brand_id'] ?? 0);
        $name = trim($_POST['brand_name'] ?? '');
        $code = trim($_POST['brand_code'] ?? '');

        if ($id <= 0) {
            header("Location: index.php?module=brands&action=index");
            exit;
        }

        if ($name === '') {
            header("Location: index.php?module=brands&action=index&status=name_required");
            exit;
        }

        $this->brand->brand_id = $id;
        $this->brand->brand_name = $name;
        $this->brand->brand_code = $code ?: null;

        if ($this->brand->update()) {
            header("Location: index.php?module=brands&action=index&status=updated");
            exit;
        }

        header("Location: index.php?module=brands&action=index&status=error");
        exit;
    }

    /** Delete a brand */
    public function delete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            if ($this->brand->hasLinkedItems($id)) {
                header("Location: index.php?module=brands&action=index&status=has_items");
                exit;
            }
            $this->brand->delete($id);
        }

        header("Location: index.php?module=brands&action=index&status=deleted");
        exit;
    }
}
