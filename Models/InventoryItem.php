<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * InventoryItem.php (Model)
 * Maps to the inventory_items table: the ERD's tblItem fields
 * (item_id(PK), category_id(FK), brand_id(FK), item_type_id(FK), model,
 * energy_rating, monthly_consumption, cooling_capacity, refrigerant,
 * installation_type, power_input, year) plus location_id(FK), which was
 * dropped in the strict-ERD-compliance rework and then re-added (see
 * migration_readd_location_to_products.sql) - one location per product.
 *
 * item_name, description, unit_of_measure, and image_path stay dropped.
 * There is no name field left, so `model` is the de-facto display name
 * everywhere - it's required, unlike the rest of the spec fields which
 * stay optional.
 */
class InventoryItem
{
    private PDO $conn;
    private string $table = "inventory_items";

    public ?int $item_id = null;
    public ?int $category_id = null;
    public ?int $brand_id = null;
    public ?int $item_type_id = null;
    public ?int $location_id = null;
    public ?string $model = null;
    public ?string $energy_rating = null;
    public ?float $monthly_consumption = null;
    public ?string $cooling_capacity = null;
    public ?string $refrigerant = null;
    public ?string $installation_type = null;
    public ?string $power_input = null;
    public ?int $year = null;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /** CREATE - insert a new inventory item */
    public function create(): bool
    {
        $query = "INSERT INTO {$this->table}
                    (category_id, brand_id, item_type_id, location_id, model, energy_rating,
                     monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
                  VALUES
                    (:category_id, :brand_id, :item_type_id, :location_id, :model, :energy_rating,
                     :monthly_consumption, :cooling_capacity, :refrigerant, :installation_type, :power_input, :year)";

        $stmt = $this->conn->prepare($query);
        if ($this->category_id === null) {
            $stmt->bindValue(':category_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':category_id', $this->category_id, PDO::PARAM_INT);
        }
        if ($this->brand_id === null) {
            $stmt->bindValue(':brand_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':brand_id', $this->brand_id, PDO::PARAM_INT);
        }
        if ($this->item_type_id === null) {
            $stmt->bindValue(':item_type_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':item_type_id', $this->item_type_id, PDO::PARAM_INT);
        }
        if ($this->location_id === null) {
            $stmt->bindValue(':location_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':location_id', $this->location_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':model', $this->model);
        $stmt->bindParam(':energy_rating', $this->energy_rating);
        if ($this->monthly_consumption === null) {
            $stmt->bindValue(':monthly_consumption', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':monthly_consumption', $this->monthly_consumption);
        }
        $stmt->bindParam(':cooling_capacity', $this->cooling_capacity);
        $stmt->bindParam(':refrigerant', $this->refrigerant);
        $stmt->bindParam(':installation_type', $this->installation_type);
        $stmt->bindParam(':power_input', $this->power_input);
        if ($this->year === null) {
            $stmt->bindValue(':year', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':year', $this->year, PDO::PARAM_INT);
        }

        return $stmt->execute();
    }

    /** The auto-increment id of the row just inserted by create() */
    public function lastInsertId(): int
    {
        return (int) $this->conn->lastInsertId();
    }

    /** READ - all items, joined with category/brand/item-type/location names, most recent first.
     *  $categoryId / $locationId optionally filter the results.
     *  $limit/$offset optionally page the results (pass both, or leave both null for everything). */
    public const SORT_OPTIONS = [
        'name_asc' => 'i.model ASC',
        'name_desc' => 'i.model DESC',
        'category_asc' => 'c.category_name ASC',
        'newest' => 'i.item_id DESC',
        'oldest' => 'i.item_id ASC',
    ];

    /** $sort picks an ORDER BY from self::SORT_OPTIONS (defaults to newest first) */
    public function readAll(?string $categoryId = null, ?string $sort = null, ?int $limit = null, ?int $offset = null, ?string $locationId = null): array
    {
        [$where, $params] = $this->buildFilterClause($categoryId, $locationId);
        $orderBy = self::SORT_OPTIONS[$sort] ?? self::SORT_OPTIONS['newest'];

        $query = "SELECT i.*, c.category_name, b.brand_name, t.type_name, l.location_name
                  FROM {$this->table} i
                  LEFT JOIN item_categories c ON i.category_id = c.category_id
                  LEFT JOIN brands b ON i.brand_id = b.brand_id
                  LEFT JOIN item_types t ON i.item_type_id = t.item_type_id
                  LEFT JOIN locations l ON i.location_id = l.location_id
                  WHERE {$where}
                  ORDER BY {$orderBy}";

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
    public function countFiltered(?string $categoryId = null, ?string $locationId = null): int
    {
        [$where, $params] = $this->buildFilterClause($categoryId, $locationId);
        $query = "SELECT COUNT(*) AS total FROM {$this->table} i WHERE {$where}";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /** Shared WHERE-clause builder for readAll() and countFiltered() so the two never drift apart */
    private function buildFilterClause(?string $categoryId, ?string $locationId = null): array
    {
        $where = "1=1";
        $params = [];

        if ($categoryId === 'none') {
            $where .= " AND i.category_id IS NULL";
        } elseif ($categoryId !== null && $categoryId !== '') {
            $where .= " AND i.category_id = :category_id";
            $params[':category_id'] = (int) $categoryId;
        }
        if ($locationId === 'none') {
            $where .= " AND i.location_id IS NULL";
        } elseif ($locationId !== null && $locationId !== '') {
            $where .= " AND i.location_id = :location_id";
            $params[':location_id'] = (int) $locationId;
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

    /** READ - single item by id, joined with category/brand/item-type/location names */
    public function readOne(int $id): array|false
    {
        $query = "SELECT i.*, c.category_name, b.brand_name, t.type_name, l.location_name
                  FROM {$this->table} i
                  LEFT JOIN item_categories c ON i.category_id = c.category_id
                  LEFT JOIN brands b ON i.brand_id = b.brand_id
                  LEFT JOIN item_types t ON i.item_type_id = t.item_type_id
                  LEFT JOIN locations l ON i.location_id = l.location_id
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
                    brand_id = :brand_id,
                    item_type_id = :item_type_id,
                    location_id = :location_id,
                    model = :model,
                    energy_rating = :energy_rating,
                    monthly_consumption = :monthly_consumption,
                    cooling_capacity = :cooling_capacity,
                    refrigerant = :refrigerant,
                    installation_type = :installation_type,
                    power_input = :power_input,
                    year = :year
                  WHERE item_id = :item_id";

        $stmt = $this->conn->prepare($query);
        if ($this->category_id === null) {
            $stmt->bindValue(':category_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':category_id', $this->category_id, PDO::PARAM_INT);
        }
        if ($this->brand_id === null) {
            $stmt->bindValue(':brand_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':brand_id', $this->brand_id, PDO::PARAM_INT);
        }
        if ($this->item_type_id === null) {
            $stmt->bindValue(':item_type_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':item_type_id', $this->item_type_id, PDO::PARAM_INT);
        }
        if ($this->location_id === null) {
            $stmt->bindValue(':location_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':location_id', $this->location_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':model', $this->model);
        $stmt->bindParam(':energy_rating', $this->energy_rating);
        if ($this->monthly_consumption === null) {
            $stmt->bindValue(':monthly_consumption', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':monthly_consumption', $this->monthly_consumption);
        }
        $stmt->bindParam(':cooling_capacity', $this->cooling_capacity);
        $stmt->bindParam(':refrigerant', $this->refrigerant);
        $stmt->bindParam(':installation_type', $this->installation_type);
        $stmt->bindParam(':power_input', $this->power_input);
        if ($this->year === null) {
            $stmt->bindValue(':year', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':year', $this->year, PDO::PARAM_INT);
        }
        $stmt->bindParam(':item_id', $this->item_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /** DELETE - remove an item by id. Its past transactions are kept (not
     *  deleted) for the Logs/audit trail - the FK sets their item_id to
     *  NULL instead of restricting or cascading. See
     *  migration_transactions_survive_product_delete.sql. */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM {$this->table} WHERE item_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
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
}
