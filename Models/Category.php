<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Category.php (Model)
 * Represents a single row of the item_categories table: just an ID and
 * a Name, matching the ERD's tblCategories exactly.
 */
class Category
{
    private PDO $conn;
    private string $table = "item_categories";

    public ?int $category_id = null;
    public ?string $category_name = null;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /** CREATE - insert a new category */
    public function create(): bool
    {
        $query = "INSERT INTO {$this->table} (category_name) VALUES (:category_name)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category_name', $this->category_name);
        return $stmt->execute();
    }

    /** READ - get every category, most recent first */
    public function readAll(): array
    {
        $query = "SELECT * FROM {$this->table} ORDER BY category_id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public const SORT_OPTIONS = [
        'newest' => 'c.category_id DESC',
        'oldest' => 'c.category_id ASC',
        'name_asc' => 'c.category_name ASC',
        'name_desc' => 'c.category_name DESC',
        'products_desc' => 'product_count DESC',
        'products_asc' => 'product_count ASC',
    ];

    /** $sort picks an ORDER BY from self::SORT_OPTIONS (defaults to newest first) */
    public function readAllWithCounts(?string $sort = null, ?int $limit = null, ?int $offset = null): array
    {
        $query = "SELECT c.*, COUNT(i.item_id) AS product_count
                  FROM {$this->table} c
                  LEFT JOIN inventory_items i ON i.category_id = c.category_id
                  GROUP BY c.category_id";

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

    /** Product count per category - used for the Dashboard bar chart */
    public function countProductsByCategory(): array
    {
        $query = "SELECT c.category_name, COUNT(i.item_id) AS total
                  FROM {$this->table} c
                  LEFT JOIN inventory_items i ON i.category_id = c.category_id
                  GROUP BY c.category_id, c.category_name
                  ORDER BY total DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** READ - get a single category by id */
    public function readOne(int $id): array|false
    {
        $query = "SELECT * FROM {$this->table} WHERE category_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /** UPDATE - edit an existing category */
    public function update(): bool
    {
        $query = "UPDATE {$this->table} SET category_name = :category_name WHERE category_id = :category_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category_name', $this->category_name);
        $stmt->bindParam(':category_id', $this->category_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** DELETE - remove a category by id */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM {$this->table} WHERE category_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Count of all categories - used on the Dashboard and to power pagination on the Categories list page */
    public function count(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }

    /** Bulk delete - skips any category that still has products, returns [deleted, skipped] ids */
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

    /** Helper - check whether a category still has inventory items attached
     *  (prevents violating the FK constraint on inventory_items) */
    public function hasLinkedItems(int $id): bool
    {
        $query = "SELECT COUNT(*) AS total FROM inventory_items WHERE category_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['total'] > 0;
    }
}
