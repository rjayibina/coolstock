<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * ItemType.php (Model)
 * Represents a single row of the item_types table (e.g. "Asset", "Consumable").
 */
class ItemType
{
    private PDO $conn;
    private string $table = "item_types";

    public ?int $item_type_id = null;
    public ?string $type_name = null;
    public ?string $created_at = null;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /** CREATE - insert a new item type */
    public function create(): bool
    {
        $query = "INSERT INTO {$this->table} (type_name) VALUES (:type_name)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':type_name', $this->type_name);
        return $stmt->execute();
    }

    /** READ - every item type, alphabetical */
    public function readAll(): array
    {
        $query = "SELECT * FROM {$this->table} ORDER BY type_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** READ - single item type by id */
    public function readOne(int $id): array|false
    {
        $query = "SELECT * FROM {$this->table} WHERE item_type_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /** UPDATE - edit an existing item type */
    public function update(): bool
    {
        $query = "UPDATE {$this->table} SET type_name = :type_name WHERE item_type_id = :item_type_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':type_name', $this->type_name);
        $stmt->bindParam(':item_type_id', $this->item_type_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** DELETE - remove an item type by id */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM {$this->table} WHERE item_type_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Count of all item types - used on the Dashboard */
    public function count(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }

    /** Helper - check whether an item type still has inventory items attached
     *  (prevents violating the FK constraint on inventory_items) */
    public function hasLinkedItems(int $id): bool
    {
        $query = "SELECT COUNT(*) AS total FROM inventory_items WHERE item_type_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['total'] > 0;
    }
}
