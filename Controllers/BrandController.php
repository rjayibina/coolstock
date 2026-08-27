<?php
require_once __DIR__ . '/../Models/Brand.php';

/**
 * BrandController.php
 * Sits between the router (index.php), the Brand model, and the views.
 */
class BrandController
{
    private const PER_PAGE = 10;

    private Brand $brand;

    public function __construct()
    {
        $this->brand = new Brand();
    }

    /** List all brands, filtered by product count, sorted, and paginated */
    public function index(): void
    {
        $productFilter = $_GET['has_products'] ?? null;
        $sort = $_GET['sort'] ?? 'newest';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalCount = $this->brand->countFiltered($productFilter);
        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $brands = $this->brand->readAllWithCounts($productFilter, $sort, self::PER_PAGE, $offset);

        $pagination = [
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
        ];

        require __DIR__ . '/../Views/brands/index.php';
    }

    /** Create a brand (called from the Add Brand modal) */
    public function create(): void
    {
        $name = trim($_POST['brand_name'] ?? '');

        if ($name === '') {
            header("Location: index.php?module=brands&action=index&status=name_required");
            exit;
        }

        $this->brand->brand_name = $name;

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
