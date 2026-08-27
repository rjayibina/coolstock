<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Transaction.php (Model)
 * Every row is a plain activity-log entry - Item Request, Borrow, or
 * Return - matching the Item Request and Return Monitoring Module from
 * the thesis scope. Stock In/Out and all inventory quantity math were
 * removed in the strict-ERD-compliance rework (see
 * migration_remove_stock_tracking.sql) - a transaction no longer adjusts
 * any product's stock level, it just records that a movement happened.
 *
 * Transactions are treated as an immutable ledger: there is no "update",
 * only create (Item Request additionally supports approve/decline).
 */
class Transaction
{
    private PDO $conn;
    private string $table = "transactions";

    public const TYPES = ['item_request', 'borrow', 'return'];

    public ?int $transaction_id = null;
    public ?int $item_id = null;
    public ?string $transaction_type = null;
    public ?int $quantity = null;
    // The date the movement actually happened (defaults to today if
    // not supplied). Separate from created_at, which is just the audit
    // timestamp of when the row was logged. See migration_transaction_date.sql.
    public ?string $transaction_date = null;
    // TODO: once the User Access and Roles Module exists, replace this free-text
    // field with a technician_id FK into a users/technicians table.
    public ?string $technician_name = null;
    public ?string $notes = null;
    // 'manual' = logged from the Transactions page. 'auto' only ever existed
    // for system-generated Stock In/Out rows, which are gone along with
    // stock tracking itself - new rows are always 'manual'. Historical
    // 'auto' rows from before this rework may still exist in the data.
    public string $source = 'manual';
    // 'pending' = an Item Request that hasn't been approved yet. 'completed'
    // = everything else, and approved requests. 'declined' = a refused
    // request. None of these move any product's stock level anymore.
    public string $status = 'completed';

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'item_request' => 'Item Request',
            'borrow' => 'Borrow',
            'return' => 'Return',
            default => ucfirst($type),
        };
    }

    /** CREATE - insert a new transaction record */
    public function create(): bool
    {
        // Defaults to today if the caller didn't set one explicitly
        $this->transaction_date = $this->transaction_date ?: date('Y-m-d');

        $query = "INSERT INTO {$this->table}
                    (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status)
                  VALUES
                    (:item_id, :transaction_type, :quantity, :transaction_date, :technician_name, :notes, :source, :status)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':item_id', $this->item_id, PDO::PARAM_INT);
        $stmt->bindParam(':transaction_type', $this->transaction_type);
        $stmt->bindParam(':quantity', $this->quantity, PDO::PARAM_INT);
        $stmt->bindParam(':transaction_date', $this->transaction_date);
        $stmt->bindParam(':technician_name', $this->technician_name);
        $stmt->bindParam(':notes', $this->notes);
        $stmt->bindParam(':source', $this->source);
        $stmt->bindParam(':status', $this->status);

        return $stmt->execute();
    }

    /** Marks a pending Item Request as completed (called when it's approved) */
    public function markCompleted(int $id): bool
    {
        $query = "UPDATE {$this->table} SET status = 'completed' WHERE transaction_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Marks a pending Item Request as declined (called when warehouse staff reject it).
     *  Stock was never deducted for a pending request, so declining doesn't touch it either -
     *  the row just stays as a record of what was asked for and refused. */
    public function markDeclined(int $id): bool
    {
        $query = "UPDATE {$this->table} SET status = 'declined' WHERE transaction_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public const SORT_OPTIONS = [
        'date_desc' => 't.transaction_id DESC',
        'date_asc' => 't.transaction_id ASC',
        'quantity_desc' => 't.quantity DESC',
        'quantity_asc' => 't.quantity ASC',
        'product_asc' => 'i.model ASC',
        'product_desc' => 'i.model DESC',
    ];

    /** READ - all transactions, joined with the item's model (the item's
     *  de-facto display name - see InventoryItem.php).
     *  $itemId / $type / $search / $dateFrom / $dateTo optionally filter the results.
     *  $sort picks an ORDER BY from self::SORT_OPTIONS (defaults to newest first).
     *  $limit/$offset optionally page the results (pass both, or leave both null for everything). */
    public function readAll(?int $itemId = null, ?string $type = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, ?string $sort = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildFilterClause($itemId, $type, $search, $dateFrom, $dateTo);
        $orderBy = self::SORT_OPTIONS[$sort] ?? self::SORT_OPTIONS['date_desc'];

        $query = "SELECT t.*, i.model
                  FROM {$this->table} t
                  LEFT JOIN inventory_items i ON t.item_id = i.item_id
                  WHERE {$where}
                  ORDER BY {$orderBy}";

        if ($limit !== null && $offset !== null) {
            $query .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null && $offset !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Count of transactions matching the same filters as readAll() - for pagination */
    public function countFiltered(?int $itemId = null, ?string $type = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        [$where, $params] = $this->buildFilterClause($itemId, $type, $search, $dateFrom, $dateTo);

        $query = "SELECT COUNT(*) AS total
                  FROM {$this->table} t
                  LEFT JOIN inventory_items i ON t.item_id = i.item_id
                  WHERE {$where}";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /** Shared WHERE-clause builder for readAll() and countFiltered() so the two never drift apart */
    private function buildFilterClause(?int $itemId, ?string $type, ?string $search, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $where = "1=1";
        $params = [];

        if ($itemId) {
            $where .= " AND t.item_id = :item_id";
            $params[':item_id'] = $itemId;
        }
        if ($type && in_array($type, self::TYPES, true)) {
            $where .= " AND t.transaction_type = :type";
            $params[':type'] = $type;
        }
        if ($search !== null && trim($search) !== '') {
            $where .= " AND (i.model LIKE :search OR t.technician_name LIKE :search OR t.notes LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $where .= " AND DATE(t.created_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $where .= " AND DATE(t.created_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        return [$where, $params];
    }

    /** READ - most recent N transactions, for the Dashboard */
    public function readRecent(int $limit = 5): array
    {
        $query = "SELECT t.*, i.model
                  FROM {$this->table} t
                  LEFT JOIN inventory_items i ON t.item_id = i.item_id
                  ORDER BY t.transaction_id DESC
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** READ - single transaction by id (used before reversing/deleting) */
    public function readOne(int $id): array|false
    {
        $query = "SELECT * FROM {$this->table} WHERE transaction_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /** Count of all transactions - used on the Dashboard */
    public function count(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }

    /** Count grouped by transaction_type - used for the Dashboard bar chart */
    public function countByType(): array
    {
        $query = "SELECT transaction_type, COUNT(*) AS total FROM {$this->table} GROUP BY transaction_type";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Normalize so every type always appears, even with a zero count
        $counts = array_fill_keys(self::TYPES, 0);
        foreach ($rows as $row) {
            $counts[$row['transaction_type']] = (int) $row['total'];
        }
        return $counts;
    }
}
