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

    /** READ - every location, alphabetical (unfiltered, for dropdowns elsewhere) */
    public function readAll(): array
    {
        $query = "SELECT * FROM {$this->table} ORDER BY location_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private const SORT_OPTIONS = [
        'newest' => 'l.created_at DESC',
        'oldest' => 'l.created_at ASC',
        'name_asc' => 'l.location_name ASC',
        'name_desc' => 'l.location_name DESC',
        'products_desc' => 'product_count DESC',
        'products_asc' => 'product_count ASC',
    ];

    /** READ - every location with its product count, filtered/sorted/paginated
     *  for the Locations list page. $sort picks an ORDER BY from
     *  self::SORT_OPTIONS (defaults to newest first). */
    public function readAllWithCounts(?string $productFilter = null, ?string $sort = null, ?int $limit = null, ?int $offset = null): array
    {
        $query = "SELECT l.*, COUNT(i.item_id) AS product_count
                  FROM {$this->table} l
                  LEFT JOIN inventory_items i ON i.location_id = l.location_id
                  GROUP BY l.location_id";

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

    /** Count of locations matching the same filter as readAllWithCounts() - powers pagination */
    public function countFiltered(?string $productFilter = null): int
    {
        $query = "SELECT COUNT(*) AS total FROM (
                    SELECT l.location_id, COUNT(i.item_id) AS product_count
                    FROM {$this->table} l
                    LEFT JOIN inventory_items i ON i.location_id = l.location_id
                    GROUP BY l.location_id";

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
