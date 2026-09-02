<?php
require_once __DIR__ . '/../Models/Location.php';

/**
 * LocationController.php
 * Sits between the router (index.php), the Location model, and the views.
 */
class LocationController
{
    private const PER_PAGE = 10;

    private Location $location;

    public function __construct()
    {
        $this->location = new Location();
    }

    /** List all locations, sorted and paginated */
    public function index(): void
    {
        $sort = $_GET['sort'] ?? 'newest';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalCount = $this->location->count();
        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $locations = $this->location->readAllPaged($sort, self::PER_PAGE, $offset);

        $pagination = [
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
        ];
        require __DIR__ . '/../Views/locations/index.php';
    }

    /** Create a location (called from the Add Location modal) */
    public function create(): void
    {
        $name = trim($_POST['location_name'] ?? '');

        if ($name === '') {
            header("Location: index.php?module=locations&action=index&status=name_required");
            exit;
        }

        $this->location->location_name = $name;

        if ($this->location->create()) {
            header("Location: index.php?module=locations&action=index&status=created");
            exit;
        }

        header("Location: index.php?module=locations&action=index&status=error");
        exit;
    }

    /** Update a location (called from the Edit Location modal) */
    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['location_id'] ?? 0);
        $name = trim($_POST['location_name'] ?? '');

        if ($id <= 0) {
            header("Location: index.php?module=locations&action=index");
            exit;
        }

        if ($name === '') {
            header("Location: index.php?module=locations&action=index&status=name_required");
            exit;
        }

        $this->location->location_id = $id;
        $this->location->location_name = $name;

        if ($this->location->update()) {
            header("Location: index.php?module=locations&action=index&status=updated");
            exit;
        }

        header("Location: index.php?module=locations&action=index&status=error");
        exit;
    }

    /** Delete a location */
    public function delete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            if ($this->location->hasLinkedItems($id)) {
                header("Location: index.php?module=locations&action=index&status=has_items");
                exit;
            }
            $this->location->delete($id);
        }

        header("Location: index.php?module=locations&action=index&status=deleted");
        exit;
    }

    /** Bulk delete a set of locations - skips any still holding products */
    public function bulkDelete(): void
    {
        $ids = array_filter(array_map('intval', $_POST['selected_ids'] ?? []));

        if (!empty($ids)) {
            $result = $this->location->bulkDelete($ids);
            $status = !empty($result['skipped']) ? 'bulk_partial' : 'bulk_deleted';
            header("Location: index.php?module=locations&action=index&status=$status&count=" . count($result['deleted']) . "&skipped=" . count($result['skipped']));
            exit;
        }

        header("Location: index.php?module=locations&action=index");
        exit;
    }
}
