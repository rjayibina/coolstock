<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * InventoryItem.php (Model)
 * Maps to the inventory_items table from the ERD:
 * item_id(PK), category_id(FK), item_name, description,
 * unit_of_measure, quantity_on_hand, minimum_stock_level, serial_number
 */
class InventoryItem
{
    private PDO $conn;
    private string $table = "inventory_items";

    public ?int $item_id = null;
    public ?int $category_id = null;
    public ?string $item_name = null;
    public ?string $description = null;
    public ?string $unit_of_measure = null;
    public ?int $quantity_on_hand = null;
    public ?int $minimum_stock_level = null;
    public ?string $serial_number = null;
    public ?string $image_path = null;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /** CREATE - insert a new inventory item */
    public function create(): bool
    {
        $query = "INSERT INTO {$this->table}
                    (category_id, item_name, description, unit_of_measure,
                     quantity_on_hand, minimum_stock_level, serial_number, image_path)
                  VALUES
                    (:category_id, :item_name, :description, :unit_of_measure,
                     :quantity_on_hand, :minimum_stock_level, :serial_number, :image_path)";

        $stmt = $this->conn->prepare($query);
        if ($this->category_id === null) {
            $stmt->bindValue(':category_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':category_id', $this->category_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':item_name', $this->item_name);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':unit_of_measure', $this->unit_of_measure);
        $stmt->bindParam(':quantity_on_hand', $this->quantity_on_hand, PDO::PARAM_INT);
        $stmt->bindParam(':minimum_stock_level', $this->minimum_stock_level, PDO::PARAM_INT);
        $stmt->bindParam(':serial_number', $this->serial_number);
        $stmt->bindParam(':image_path', $this->image_path);

        return $stmt->execute();
    }

    /** READ - all items, joined with category name, most recent first.
     *  $categoryId / $stockStatus ('low'|'in_stock') / $hasSerial ('1'|'0') optionally filter the results.
     *  $limit/$offset optionally page the results (pass both, or leave both null for everything). */
    public function readAll(?string $categoryId = null, ?string $stockStatus = null, ?string $hasSerial = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildFilterClause($categoryId, $stockStatus, $hasSerial);

        $query = "SELECT i.*, c.category_name
                  FROM {$this->table} i
                  LEFT JOIN item_categories c ON i.category_id = c.category_id
                  WHERE {$where}
                  ORDER BY i.item_id DESC";

        if ($limit !== null && $offset !== null) {
            $query .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        if ($limit !== null && $offset !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Count of items matching the same filters as readAll() - powers pagination */
    public function countFiltered(?string $categoryId = null, ?string $stockStatus = null, ?string $hasSerial = null): int
    {
        [$where, $params] = $this->buildFilterClause($categoryId, $stockStatus, $hasSerial);
        $query = "SELECT COUNT(*) AS total FROM {$this->table} i WHERE {$where}";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /** Shared WHERE-clause builder for readAll() and countFiltered() so the two never drift apart */
    private function buildFilterClause(?string $categoryId, ?string $stockStatus, ?string $hasSerial): array
    {
        $where = "1=1";
        $params = [];

        if ($categoryId === 'none') {
            $where .= " AND i.category_id IS NULL";
        } elseif ($categoryId !== null && $categoryId !== '') {
            $where .= " AND i.category_id = :category_id";
            $params[':category_id'] = (int) $categoryId;
        }
        if ($stockStatus === 'low') {
            $where .= " AND i.quantity_on_hand <= i.minimum_stock_level";
        } elseif ($stockStatus === 'in_stock') {
            $where .= " AND i.quantity_on_hand > i.minimum_stock_level";
        }
        if ($hasSerial === '1') {
            $where .= " AND i.serial_number IS NOT NULL AND i.serial_number <> ''";
        } elseif ($hasSerial === '0') {
            $where .= " AND (i.serial_number IS NULL OR i.serial_number = '')";
        }

        return [$where, $params];
    }

    /** READ - all items belonging to one category (used by the Category "View" page) */
    public function readAllByCategory(int $categoryId): array
    {
        $query = "SELECT * FROM {$this->table} WHERE category_id = :category_id ORDER BY item_id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** READ - single item by id, joined with category name */
    public function readOne(int $id): array|false
    {
        $query = "SELECT i.*, c.category_name
                  FROM {$this->table} i
                  LEFT JOIN item_categories c ON i.category_id = c.category_id
                  WHERE i.item_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /** UPDATE - edit an existing item */
    public function update(): bool
    {
        $query = "UPDATE {$this->table} SET
                    category_id = :category_id,
                    item_name = :item_name,
                    description = :description,
                    unit_of_measure = :unit_of_measure,
                    quantity_on_hand = :quantity_on_hand,
                    minimum_stock_level = :minimum_stock_level,
                    serial_number = :serial_number,
                    image_path = :image_path
                  WHERE item_id = :item_id";

        $stmt = $this->conn->prepare($query);
        if ($this->category_id === null) {
            $stmt->bindValue(':category_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':category_id', $this->category_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':item_name', $this->item_name);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':unit_of_measure', $this->unit_of_measure);
        $stmt->bindParam(':quantity_on_hand', $this->quantity_on_hand, PDO::PARAM_INT);
        $stmt->bindParam(':minimum_stock_level', $this->minimum_stock_level, PDO::PARAM_INT);
        $stmt->bindParam(':serial_number', $this->serial_number);
        $stmt->bindParam(':image_path', $this->image_path);
        $stmt->bindParam(':item_id', $this->item_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /** DELETE - remove an item by id */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM {$this->table} WHERE item_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Helper - check whether a product has transaction history attached
     *  (prevents violating the FK constraint on transactions) */
    public function hasTransactions(int $id): bool
    {
        $query = "SELECT COUNT(*) AS total FROM transactions WHERE item_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['total'] > 0;
    }

    /** Adds (or subtracts, with a negative delta) stock for an item. Used by Transaction. */
    public function adjustQuantity(int $id, int $delta): bool
    {
        $query = "UPDATE {$this->table} SET quantity_on_hand = quantity_on_hand + :delta WHERE item_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':delta', $delta, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** READ - multiple items by id (used before a bulk delete, to clean up their images) */
    public function readByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE item_id IN ($placeholders)");
        $stmt->execute(array_map('intval', $ids));
        return $stmt->fetchAll();
    }

    /** Bulk delete - returns the number of rows actually deleted */
    /** Bulk delete - skips any product that has transaction history, returns [deleted, skipped] ids */
    public function bulkDelete(array $ids): array
    {
        $deleted = [];
        $skipped = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($this->hasTransactions($id)) {
                $skipped[] = $id;
                continue;
            }
            if ($this->delete($id)) {
                $deleted[] = $id;
            }
        }
        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    /** Bulk-reassign category for a set of products - returns the number of rows updated */
    public function bulkUpdateCategory(array $ids, int $categoryId): int
    {
        if (empty($ids)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET category_id = ? WHERE item_id IN ($placeholders)");
        $stmt->execute(array_merge([$categoryId], array_map('intval', $ids)));
        return $stmt->rowCount();
    }

    /** Count of all items - used on the Dashboard */
    public function count(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }

    /** Count of items at/under their minimum stock level - used on the Dashboard */
    public function countLowStock(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM {$this->table} WHERE quantity_on_hand <= minimum_stock_level");
        return (int) $stmt->fetch()['total'];
    }
}
