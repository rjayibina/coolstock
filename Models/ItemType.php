<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * ItemType.php (Model)
 * Represents a single row of the item_types table: just an ID and a
 * Name, matching the ERD's tblItemType exactly.
 */
class ItemType
{
    private PDO $conn;
    private string $table = "item_types";

    public ?int $item_type_id = null;
    public ?string $type_name = null;

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

    /** READ - every item type, alphabetical (unfiltered, for dropdowns elsewhere) */
    public function readAll(): array
    {
        $query = "SELECT * FROM {$this->table} ORDER BY type_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private const SORT_OPTIONS = [
        'newest' => 't.item_type_id DESC',
        'oldest' => 't.item_type_id ASC',
        'name_asc' => 't.type_name ASC',
        'name_desc' => 't.type_name DESC',
        'products_desc' => 'product_count DESC',
        'products_asc' => 'product_count ASC',
    ];

    /** READ - every item type with its product count, filtered/sorted/paginated
     *  for the Item Types list page. $sort picks an ORDER BY from
     *  self::SORT_OPTIONS (defaults to newest first). */
    public function readAllWithCounts(?string $productFilter = null, ?string $sort = null, ?int $limit = null, ?int $offset = null): array
    {
        $query = "SELECT t.*, COUNT(i.item_id) AS product_count
                  FROM {$this->table} t
                  LEFT JOIN inventory_items i ON i.item_type_id = t.item_type_id
                  GROUP BY t.item_type_id";

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

    /** Count of item types matching the same filter as readAllWithCounts() - powers pagination */
    public function countFiltered(?string $productFilter = null): int
    {
        $query = "SELECT COUNT(*) AS total FROM (
                    SELECT t.item_type_id, COUNT(i.item_id) AS product_count
                    FROM {$this->table} t
                    LEFT JOIN inventory_items i ON i.item_type_id = t.item_type_id
                    GROUP BY t.item_type_id";

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
