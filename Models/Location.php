<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Location.php (Model)
 * Represents a single row of the locations table (e.g. "Main Store", "Warehouse").
 */
class Location
{
    private PDO $conn;
    private string $table = "locations";

    public ?int $location_id = null;
    public ?string $location_name = null;
    public ?string $created_at = null;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /** CREATE - insert a new location */
    public function create(): bool
    {
        $query = "INSERT INTO {$this->table} (location_name) VALUES (:location_name)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':location_name', $this->location_name);
        return $stmt->execute();
    }

    /** READ - every location, alphabetical */
    public function readAll(): array
    {
        $query = "SELECT * FROM {$this->table} ORDER BY location_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** READ - single location by id */
    public function readOne(int $id): array|false
    {
        $query = "SELECT * FROM {$this->table} WHERE location_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /** UPDATE - edit an existing location */
    public function update(): bool
    {
        $query = "UPDATE {$this->table} SET location_name = :location_name WHERE location_id = :location_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':location_name', $this->location_name);
        $stmt->bindParam(':location_id', $this->location_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** DELETE - remove a location by id */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM {$this->table} WHERE location_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Count of all locations - used on the Dashboard */
    public function count(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }

    /** Helper - check whether a location still has inventory items attached
     *  (prevents violating the FK constraint on inventory_items) */
    public function hasLinkedItems(int $id): bool
    {
        $query = "SELECT COUNT(*) AS total FROM inventory_items WHERE location_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['total'] > 0;
    }
}
