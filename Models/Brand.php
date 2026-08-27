<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Brand.php (Model)
 * Represents a single row of the brands table: just an ID and a Name,
 * matching the ERD's tblBrand exactly.
 */
class Brand
{
    private PDO $conn;
    private string $table = "brands";

    public ?int $brand_id = null;
    public ?string $brand_name = null;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /** CREATE - insert a new brand */
    public function create(): bool
    {
        $query = "INSERT INTO {$this->table} (brand_name) VALUES (:brand_name)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':brand_name', $this->brand_name);
        return $stmt->execute();
    }

    /** READ - every brand, alphabetical (unfiltered, for dropdowns elsewhere) */
    public function readAll(): array
    {
        $query = "SELECT * FROM {$this->table} ORDER BY brand_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private const SORT_OPTIONS = [
        'newest' => 'b.brand_id DESC',
        'oldest' => 'b.brand_id ASC',
        'name_asc' => 'b.brand_name ASC',
        'name_desc' => 'b.brand_name DESC',
        'products_desc' => 'product_count DESC',
        'products_asc' => 'product_count ASC',
    ];

    /** READ - every brand with its product count, filtered/sorted/paginated
     *  for the Brands list page. $sort picks an ORDER BY from
     *  self::SORT_OPTIONS (defaults to newest first). */
    public function readAllWithCounts(?string $productFilter = null, ?string $sort = null, ?int $limit = null, ?int $offset = null): array
    {
        $query = "SELECT b.*, COUNT(i.item_id) AS product_count
                  FROM {$this->table} b
                  LEFT JOIN inventory_items i ON i.brand_id = b.brand_id
                  GROUP BY b.brand_id";

        if ($productFilter === 'has') {
            $query .= " HAVING product_count > 0";
        } elseif ($productFilter === 'empty') {
            $query .= " HAVING product_count = 0";
        }

        $orderBy = self::SORT_OPTIONS[$sort] ?? self::SORT_OPTIONS['newest'];
        $query .= " ORDER BY {$orderBy}";

        if ($limit !== null) {
            $query .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->conn->prepare($query);
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Count of brands matching the same filter as readAllWithCounts() - powers pagination */
    public function countFiltered(?string $productFilter = null): int
    {
        $query = "SELECT COUNT(*) AS total FROM (
                    SELECT b.brand_id, COUNT(i.item_id) AS product_count
                    FROM {$this->table} b
                    LEFT JOIN inventory_items i ON i.brand_id = b.brand_id
                    GROUP BY b.brand_id";

        if ($productFilter === 'has') {
            $query .= " HAVING product_count > 0";
        } elseif ($productFilter === 'empty') {
            $query .= " HAVING product_count = 0";
        }

        $query .= ") AS sub";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
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
        $query = "UPDATE {$this->table} SET brand_name = :brand_name WHERE brand_id = :brand_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':brand_name', $this->brand_name);
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
