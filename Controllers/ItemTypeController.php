<?php
require_once __DIR__ . '/../Models/ItemType.php';

/**
 * ItemTypeController.php
 * Sits between the router (index.php), the ItemType model, and the views.
 */
class ItemTypeController
{
    private ItemType $itemType;

    public function __construct()
    {
        $this->itemType = new ItemType();
    }

    /** List all item types */
    public function index(): void
    {
        $itemTypes = $this->itemType->readAll();
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
        $this->itemType->requires_serial = isset($_POST['requires_serial']) ? 1 : 0;

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
        $this->itemType->requires_serial = isset($_POST['requires_serial']) ? 1 : 0;

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
