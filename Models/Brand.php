<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Brand.php (Model)
 * Represents a single row of the brands table (e.g. "Carrier", code "088").
 * The code belongs to the brand itself, not to individual products - an
 * item just references a BrandID and the code comes along with it.
 */
class Brand
{
    private PDO $conn;
    private string $table = "brands";

    public ?int $brand_id = null;
    public ?string $brand_name = null;
    public ?string $brand_code = null;
    public ?string $created_at = null;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /** CREATE - insert a new brand */
    public function create(): bool
    {
        $query = "INSERT INTO {$this->table} (brand_name, brand_code) VALUES (:brand_name, :brand_code)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':brand_name', $this->brand_name);
        $stmt->bindParam(':brand_code', $this->brand_code);
        return $stmt->execute();
    }

    /** READ - every brand, alphabetical */
    public function readAll(): array
    {
        $query = "SELECT * FROM {$this->table} ORDER BY brand_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** READ - single brand by id */
    public function readOne(int $id): array|false
    {
        $query = "SELECT * FROM {$this->table} WHERE brand_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /** UPDATE - edit an existing brand */
    public function update(): bool
    {
        $query = "UPDATE {$this->table} SET brand_name = :brand_name, brand_code = :brand_code WHERE brand_id = :brand_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':brand_name', $this->brand_name);
        $stmt->bindParam(':brand_code', $this->brand_code);
        $stmt->bindParam(':brand_id', $this->brand_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** DELETE - remove a brand by id */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM {$this->table} WHERE brand_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Count of all brands - used on the Dashboard */
    public function count(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }

    /** Helper - check whether a brand still has inventory items attached
     *  (prevents violating the FK constraint on inventory_items) */
    public function hasLinkedItems(int $id): bool
    {
        $query = "SELECT COUNT(*) AS total FROM inventory_items WHERE brand_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['total'] > 0;
    }
}
