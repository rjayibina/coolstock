<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Location.php (Model)
 * Represents a single row of the locations table (e.g. "Main Store", "Warehouse").
 *
 * No longer a single location_id on inventory_items (that concept doesn't
 * fit once one product can have stock split across locations) - a
 * location is linked to products via item_stock rows instead (see
 * migration_add_stock_by_location.sql and ItemStock.php). The list page
 * itself stays simplified (ID + Name only, no Products column/filter/sort).
 */
class Location
{
    private PDO $conn;
    private string $table = "locations";

    public ?int $location_id = null;
    public ?string $location_name = null;

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
        'newest' => 'location_id DESC',
        'oldest' => 'location_id ASC',
        'name_asc' => 'location_name ASC',
        'name_desc' => 'location_name DESC',
    ];

    /** READ - every location, sorted/paginated for the Locations list page.
     *  $sort picks an ORDER BY from self::SORT_OPTIONS (defaults to newest first). */
    public function readAllPaged(?string $sort = null, ?int $limit = null, ?int $offset = null): array
    {
        $orderBy = self::SORT_OPTIONS[$sort] ?? self::SORT_OPTIONS['newest'];
        $query = "SELECT * FROM {$this->table} ORDER BY {$orderBy}";

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

    /** DELETE - remove a location by id. Caller should check
     *  hasLinkedItems() first - a location still referenced by a product
     *  will fail on the FK constraint otherwise. */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM {$this->table} WHERE location_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Count of all locations - powers pagination on the Locations list page */
    public function count(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }

    /** Helper - check whether a location still has any stock recorded
     *  against it (prevents violating the FK constraint on item_stock) */
    public function hasLinkedItems(int $id): bool
    {
        $query = "SELECT COUNT(*) AS total FROM item_stock WHERE location_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['total'] > 0;
    }

    /** Bulk delete - skips any location that still has products, returns [deleted, skipped] ids */
    public function bulkDelete(array $ids): array
    {
        $deleted = [];
        $skipped = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($this->hasLinkedItems($id)) {
                $skipped[] = $id;
                continue;
            }
            if ($this->delete($id)) {
                $deleted[] = $id;
            }
        }
        return ['deleted' => $deleted, 'skipped' => $skipped];
    }
}
