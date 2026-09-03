<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Transaction.php (Model)
 * Every row is an activity-log entry: Item Request, Borrow, Return, Stock
 * In / Stock Out (migration_add_stock_by_location.sql), or Delivery /
 * Transfer (migration_add_delivery_transfer.sql). Item Request/Borrow/
 * Return never touch stock. The other four do - they're the only types
 * with a location_id (Transfer also uses to_location_id), and creating
 * one is paired with ItemStock::adjust() call(s) by the owning controller
 * to keep those locations' quantities in sync (this model itself has no
 * stock side effects; see TransactionController::create(),
 * DeliveryController::index(), TransferController::index()).
 *
 * Transactions are treated as an immutable ledger: there is no "update",
 * only create (Item Request additionally supports approve/decline).
 */
class Transaction
{
    private PDO $conn;
    private string $table = "transactions";

    public const TYPES = ['item_request', 'borrow', 'return', 'stock_in', 'stock_out', 'delivery', 'transfer'];

    /** The Product Movement "Remarks" filter dropdown's option list - a
     *  deliberately smaller set than TYPES. 'stock_in' here also matches
     *  'delivery' rows (see buildFilterClause()) since they're presented
     *  as one "Stock In" concept. Item Request/Borrow/Return aren't
     *  offered as filters - that workflow is on pause for now and there's
     *  no seed/demo data of those types anymore (TYPES itself is
     *  untouched though, so historical rows of those types, if any exist,
     *  still display correctly - they're just not filterable from here). */
    public const MOVEMENT_FILTERS = [
        'stock_in' => 'Stock In',
        'stock_out' => 'Stock Out',
        'transfer' => 'Transfer',
    ];

    public ?int $transaction_id = null;
    public ?int $item_id = null;
    // Which location a Stock In/Out/Delivery happened at, or the FROM
    // location for a Transfer. Always null for Item Request/Borrow/Return.
    public ?int $location_id = null;
    // The TO location for a Transfer only. Null for every other type.
    public ?int $to_location_id = null;
    public ?string $transaction_type = null;
    // 'DO-000001', 'TR-000001', etc. - shared by every row written in one
    // Delivery/Transfer submission (see nextReferenceNumber()). Null for
    // every other transaction type - see
    // migration_add_transaction_reference_number.sql.
    public ?string $reference_number = null;
    // True only for a Delivery line whose product didn't exist in the
    // catalog yet and was created on the spot via "Add Product Manually"
    // (see DeliveryController::createManualProduct()). Powers the "New"
    // badge on the Product Movement batch modal. False for everything
    // else, including a Delivery of an existing catalog product.
    public bool $manually_added = false;
    public ?int $quantity = null;
    // Which unit's serial number this row logs, for a Stock Out on a
    // product whose item type requires one (see
    // migration_transaction_serial_number.sql). One serial per row - a
    // multi-unit serialized Stock Out is logged as several quantity=1 rows,
    // one per serial, rather than packing multiple serials into one row.
    // Null for Stock In, non-serialized Stock Out, and every other type.
    public ?string $serial_number = null;
    // The date the movement actually happened (defaults to today if
    // not supplied). Separate from created_at, which is just the audit
    // timestamp of when the row was logged. See migration_transaction_date.sql.
    public ?string $transaction_date = null;
    // TODO: once the User Access and Roles Module exists, replace this free-text
    // field with a technician_id FK into a users/technicians table. Doubles as
    // "Received By" (Stock In/Delivery) / "Released By" (Stock Out) / "Moved By"
    // (Transfer) in the UI, same column.
    public ?string $technician_name = null;
    // Free-text supplier name, Delivery only. Null for every other type.
    public ?string $supplier_name = null;
    public ?string $notes = null;
    // 'manual' = logged from the Transactions page or a product's Stock In/Out
    // form. 'auto' is historical only - no code path sets it anymore.
    public string $source = 'manual';
    // 'pending' = an Item Request that hasn't been approved yet. 'completed'
    // = everything else, and approved requests. 'declined' = a refused
    // request. Only Stock In/Out ever move a product's stock level (via
    // ItemStock::adjust() in the controller) - the other three never do.
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
            'stock_in' => 'Stock In',
            'stock_out' => 'Stock Out',
            'delivery' => 'Delivery',
            'transfer' => 'Transfer',
            default => ucfirst($type),
        };
    }

    /** Same as typeLabel(), but for the Product Movement page specifically:
     *  a Delivery is stock arriving, so it's labeled "Stock In" there even
     *  though the underlying transaction_type/badge class stay 'delivery'
     *  (Dashboard and everywhere else still say "Delivery"). */
    public static function movementLabel(string $type): string
    {
        return $type === 'delivery' ? 'Stock In' : self::typeLabel($type);
    }

    /** Next sequential reference number for a Delivery ('DO') or Transfer
     *  ('TR') submission, e.g. 'DO-000001' then 'DO-000002'. Looks at the
     *  highest existing number for that prefix and adds one - every row in
     *  one submission shares the value this returns (see
     *  DeliveryController::index() / TransferController::index()). */
    public function nextReferenceNumber(string $prefix): string
    {
        $query = "SELECT reference_number FROM {$this->table}
                  WHERE reference_number LIKE :pattern
                  ORDER BY CAST(SUBSTRING(reference_number, :offset) AS UNSIGNED) DESC
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':pattern', $prefix . '-%');
        $stmt->bindValue(':offset', strlen($prefix) + 2, PDO::PARAM_INT);
        $stmt->execute();
        $last = $stmt->fetch();

        $next = 1;
        if ($last && preg_match('/-(\d+)$/', $last['reference_number'], $m)) {
            $next = (int) $m[1] + 1;
        }

        return $prefix . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /** READ - every line item sharing one Delivery/Transfer reference
     *  number, joined the same way readAll() is - powers the Product
     *  Movement "view products in this order/transfer" modal. */
    public function readByReferenceNumber(string $referenceNumber): array
    {
        $query = "SELECT t.*, i.model, l.location_name, tl.location_name AS to_location_name
                  FROM {$this->table} t
                  LEFT JOIN inventory_items i ON t.item_id = i.item_id
                  LEFT JOIN locations l ON t.location_id = l.location_id
                  LEFT JOIN locations tl ON t.to_location_id = tl.location_id
                  WHERE t.reference_number = :reference_number
                  ORDER BY i.model ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reference_number', $referenceNumber);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** CREATE - insert a new transaction record */
    public function create(): bool
    {
        // Defaults to today if the caller didn't set one explicitly
        $this->transaction_date = $this->transaction_date ?: date('Y-m-d');

        $query = "INSERT INTO {$this->table}
                    (item_id, location_id, to_location_id, transaction_type, reference_number, manually_added, quantity, serial_number, transaction_date, technician_name, supplier_name, notes, source, status)
                  VALUES
                    (:item_id, :location_id, :to_location_id, :transaction_type, :reference_number, :manually_added, :quantity, :serial_number, :transaction_date, :technician_name, :supplier_name, :notes, :source, :status)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':item_id', $this->item_id, PDO::PARAM_INT);
        if ($this->location_id === null) {
            $stmt->bindValue(':location_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':location_id', $this->location_id, PDO::PARAM_INT);
        }
        if ($this->to_location_id === null) {
            $stmt->bindValue(':to_location_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':to_location_id', $this->to_location_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':transaction_type', $this->transaction_type);
        if ($this->reference_number === null) {
            $stmt->bindValue(':reference_number', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':reference_number', $this->reference_number);
        }
        $stmt->bindValue(':manually_added', $this->manually_added ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindParam(':quantity', $this->quantity, PDO::PARAM_INT);
        if ($this->serial_number === null) {
            $stmt->bindValue(':serial_number', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':serial_number', $this->serial_number);
        }
        $stmt->bindParam(':transaction_date', $this->transaction_date);
        $stmt->bindParam(':technician_name', $this->technician_name);
        $stmt->bindParam(':supplier_name', $this->supplier_name);
        $stmt->bindParam(':notes', $this->notes);
        $stmt->bindParam(':source', $this->source);
        $stmt->bindParam(':status', $this->status);

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

    /** Shared WHERE-clause builder for countGroups()/readGrouped().
     *  $tableAlias/$itemAlias let those two reuse this against a
     *  differently-aliased subquery without colliding with the outer
     *  query's own 't'/'i' aliases.
     *
     *  $type = 'stock_in' matches BOTH 'stock_in' and 'delivery' rows -
     *  Product Movement treats them as one "Stock In" concept (a Delivery
     *  is stock arriving too, just with an order number and supplier
     *  attached - see movementLabel()), so the filter and the display
     *  agree with each other instead of "Stock In" in the dropdown
     *  silently excluding Delivery rows. Every other $type still matches
     *  exactly one transaction_type. */
    private function buildFilterClause(?int $itemId, ?string $type, ?string $search, ?string $dateFrom = null, ?string $dateTo = null, string $tableAlias = 't', string $itemAlias = 'i'): array
    {
        $where = "1=1";
        $params = [];

        if ($itemId) {
            $where .= " AND {$tableAlias}.item_id = :item_id";
            $params[':item_id'] = $itemId;
        }
        if ($type === 'stock_in') {
            $where .= " AND {$tableAlias}.transaction_type IN ('stock_in', 'delivery')";
        } elseif ($type && in_array($type, self::TYPES, true)) {
            $where .= " AND {$tableAlias}.transaction_type = :type";
            $params[':type'] = $type;
        }
        if ($search !== null && trim($search) !== '') {
            $where .= " AND ({$itemAlias}.model LIKE :search OR {$tableAlias}.technician_name LIKE :search OR {$tableAlias}.notes LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $where .= " AND DATE({$tableAlias}.created_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $where .= " AND DATE({$tableAlias}.created_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        return [$where, $params];
    }

    /** Count of DISTINCT order/transfer groups matching the same filters as
     *  readGrouped() - for pagination on the consolidated Product Movement
     *  list. See readGrouped() for what a "group" is. */
    public function countGroups(?int $itemId = null, ?string $type = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        [$where, $params] = $this->buildFilterClause($itemId, $type, $search, $dateFrom, $dateTo);

        $query = "SELECT COUNT(*) AS total FROM (
                      SELECT COALESCE(t.reference_number, CONCAT('txn-', t.transaction_id)) AS grouping_key
                      FROM {$this->table} t
                      LEFT JOIN inventory_items i ON t.item_id = i.item_id
                      WHERE {$where}
                      GROUP BY grouping_key
                  ) g";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /** Consolidated Product Movement listing: a Delivery/Transfer's several
     *  product lines (one per item, sharing a reference_number - see
     *  DeliveryController/TransferController) collapse into ONE row here -
     *  its earliest-added line item, plus line_count (how many products
     *  were in that order/transfer) and total_quantity (the sum of every
     *  line's quantity, not just the representative line's own - shown in
     *  the row-detail modal so a consolidated row doesn't look like it only
     *  moved a fraction of what the order actually contained). The batch
     *  modal (TransactionController::batch()) is where every line actually
     *  gets listed. Every other transaction type has no reference_number,
     *  so it's its own group of one - completely unaffected (line_count = 1,
     *  total_quantity = its own quantity).
     *
     *  Filters apply INSIDE the grouping subquery, not after, so a filter
     *  that only matches one line of a multi-product order (e.g. item_id)
     *  still finds that order and correctly picks the matching line as
     *  the representative - not silently dropping the whole group because
     *  the group's usual first line doesn't happen to match.
     *
     *  Same $itemId/$type/$search/$dateFrom/$dateTo/$sort/$limit/$offset
     *  contract as readAll(). */
    public function readGrouped(?int $itemId = null, ?string $type = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, ?string $sort = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildFilterClause($itemId, $type, $search, $dateFrom, $dateTo, 't2', 'i2');
        $orderBy = self::SORT_OPTIONS[$sort] ?? self::SORT_OPTIONS['date_desc'];

        $query = "SELECT t.*, i.model, l.location_name, tl.location_name AS to_location_name, g.line_count, g.total_quantity
                  FROM {$this->table} t
                  INNER JOIN (
                      SELECT COALESCE(t2.reference_number, CONCAT('txn-', t2.transaction_id)) AS grouping_key,
                             MIN(t2.transaction_id) AS representative_id,
                             COUNT(*) AS line_count,
                             SUM(t2.quantity) AS total_quantity
                      FROM {$this->table} t2
                      LEFT JOIN inventory_items i2 ON t2.item_id = i2.item_id
                      WHERE {$where}
                      GROUP BY grouping_key
                  ) g ON t.transaction_id = g.representative_id
                  LEFT JOIN inventory_items i ON t.item_id = i.item_id
                  LEFT JOIN locations l ON t.location_id = l.location_id
                  LEFT JOIN locations tl ON t.to_location_id = tl.location_id
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

    /** READ - most recent N transactions, for the Dashboard */
    public function readRecent(int $limit = 5): array
    {
        $query = "SELECT t.*, i.model, l.location_name, tl.location_name AS to_location_name
                  FROM {$this->table} t
                  LEFT JOIN inventory_items i ON t.item_id = i.item_id
                  LEFT JOIN locations l ON t.location_id = l.location_id
                  LEFT JOIN locations tl ON t.to_location_id = tl.location_id
                  ORDER BY t.transaction_id DESC
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
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
