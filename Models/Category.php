<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Category.php (Model)
 * Represents a single row of the item_categories table and
 * contains all the SQL logic for Create, Read, Update, Delete.
 */
class Category
{
    private PDO $conn;
    private string $table = "item_categories";

    // Public properties map directly to table columns
    public ?int $category_id = null;
    public ?string $category_name = null;
    public ?string $category_description = null;
    public ?string $created_at = null;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /** CREATE - insert a new category */
    public function create(): bool
    {
        $query = "INSERT INTO {$this->table} (category_name, category_description)
                  VALUES (:category_name, :category_description)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category_name', $this->category_name);
        $stmt->bindParam(':category_description', $this->category_description);

        return $stmt->execute();
    }

    /** The auto-increment id of the row just inserted by create() */
    public function lastInsertId(): int
    {
        return (int) $this->conn->lastInsertId();
    }

    /** READ - get every category, most recent first */
    public function readAll(): array
    {
        $query = "SELECT * FROM {$this->table} ORDER BY category_id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** READ - every category with its product count, most recent first (Categories page card layout) */
    public function readAllWithCounts(): array
    {
        $query = "SELECT c.*, COUNT(i.item_id) AS product_count
                  FROM {$this->table} c
                  LEFT JOIN inventory_items i ON i.category_id = c.category_id
                  GROUP BY c.category_id
                  ORDER BY c.category_id DESC";
        $stmt = $this->conn->prepare($query);
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
        $query = "UPDATE {$this->table}
                  SET category_name = :category_name,
                      category_description = :category_description
                  WHERE category_id = :category_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category_name', $this->category_name);
        $stmt->bindParam(':category_description', $this->category_description);
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

    /** Count of all categories - used on the Dashboard */
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
