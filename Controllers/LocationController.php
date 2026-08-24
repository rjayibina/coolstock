<?php
require_once __DIR__ . '/../Models/Location.php';

/**
 * LocationController.php
 * Sits between the router (index.php), the Location model, and the views.
 */
class LocationController
{
    private Location $location;

    public function __construct()
    {
        $this->location = new Location();
    }

    /** List all locations */
    public function index(): void
    {
        $locations = $this->location->readAll();
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
}
