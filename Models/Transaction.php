<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Transaction.php (Model)
 * Every row is an activity-log entry: Item Request, Borrow, Return, Stock
 * In / Stock Out, Delivery, or Transfer. Item Request never touches
 * stock (it's just a request, with no location decided yet) - every
 * other type does, and creating one is paired with ItemStock::adjust()
 * call(s) by the owning controller to keep those locations' quantities
 * in sync (this model itself has no stock side effects; see
 * TransactionController::create(), DeliveryController::index(),
 * TransferController::index(), ItemRequestController).
 *
 * Transactions are treated as an immutable ledger: there is no "update",
 * only create - except for the status column, which the Item Request and
 * Return Monitoring Module flips in place on an existing row (approve/
 * decline an Item Request; complete a Borrow once fully returned) via
 * updateStatus() rather than ever rewriting a row's other fields.
 */
class Transaction
{
    private PDO $conn;
    private string $table = "transactions";

    public const TYPES = ['item_request', 'borrow', 'return', 'stock_in', 'stock_out', 'delivery', 'transfer'];

    /** The Product Movement "Status" filter dropdown's option list - a
     *  deliberately smaller set than TYPES. 'stock_in' here also matches
     *  'delivery' rows (see buildFilterClause()) since they're presented
     *  as one "Stock In" concept. Borrow/Return still aren't offered as
     *  filters here - that workflow has its own page (see
     *  ItemRequestController, module=requests) with its own status-based
     *  filtering instead of Product Movement's - but Item Request itself
     *  is, so Warehouse Staff can see submitted requests alongside every
     *  other movement type on this page too. */
    public const MOVEMENT_FILTERS = [
        'item_request' => 'Item Request',
        'stock_in' => 'Stock In',
        'stock_out' => 'Stock Out',
        'transfer' => 'Transfer',
    ];

    public ?int $transaction_id = null;
    public ?int $item_id = null;
    // Which location a Stock In/Out/Delivery/Borrow/Return happened at,
    // or the FROM location for a Transfer. Null for Item Request - which
    // location fulfills it is decided by Warehouse Staff at approval
    // time, not by the technician requesting it (see ItemRequestController).
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
    // Free-text "Received By" (Stock In/Delivery) / "Released By" (Stock
    // Out) / "Moved By" (Transfer) / "Requested By" (Item Request) label
    // shown in the UI. Kept as a point-in-time snapshot even now that
    // user_id (below) exists, so the row still reads correctly if the
    // account is later renamed or deactivated.
    public ?string $technician_name = null;
    // FK to users.user_id - the account actually signed in when this row
    // was logged. May legitimately differ from technician_name (e.g. a
    // warehouse staff member logging a delivery received by someone else
    // physically present). Null for rows written before this column
    // existed (see migration_add_user_id_to_transactions_reports.sql) or
    // if the request has no signed-in user for some reason.
    public ?int $user_id = null;
    // Free-text supplier name, Delivery only. Null for every other type.
    public ?string $supplier_name = null;
    public ?string $notes = null;
    // 'manual' = logged from the Transactions page or a product's Stock In/Out
    // form. 'auto' is historical only - no code path sets it anymore.
    public string $source = 'manual';
    // 'pending' = an Item Request awaiting a Warehouse Staff decision.
    // 'active' = an approved request's Borrow row while still checked
    // out. 'completed' = a Stock In/Out/Delivery/Transfer row, an
    // approved Item Request, or a Borrow that's been fully returned.
    // 'declined' = a refused request, kept for the audit trail.
    public string $status = 'completed';
    // Self-referencing: on a 'borrow' row, the Item Request it fulfills;
    // on a 'return' row, the Borrow it returns against. Null otherwise.
    public ?int $related_transaction_id = null;
    // 'return' rows only: how many of the returned units came back
    // damaged and were therefore NOT restocked. Null otherwise.
    public ?int $damaged_quantity = null;

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
        $query = "SELECT t.*, i.model, i.item_type_id, l.location_name, tl.location_name AS to_location_name
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
                    (item_id, location_id, to_location_id, transaction_type, reference_number, manually_added, quantity, serial_number, transaction_date, technician_name, user_id, supplier_name, notes, source, status, related_transaction_id, damaged_quantity)
                  VALUES
                    (:item_id, :location_id, :to_location_id, :transaction_type, :reference_number, :manually_added, :quantity, :serial_number, :transaction_date, :technician_name, :user_id, :supplier_name, :notes, :source, :status, :related_transaction_id, :damaged_quantity)";

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
        if ($this->user_id === null) {
            $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':user_id', $this->user_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':supplier_name', $this->supplier_name);
        $stmt->bindParam(':notes', $this->notes);
        $stmt->bindParam(':source', $this->source);
        $stmt->bindParam(':status', $this->status);
        if ($this->related_transaction_id === null) {
            $stmt->bindValue(':related_transaction_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':related_transaction_id', $this->related_transaction_id, PDO::PARAM_INT);
        }
        if ($this->damaged_quantity === null) {
            $stmt->bindValue(':damaged_quantity', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':damaged_quantity', $this->damaged_quantity, PDO::PARAM_INT);
        }

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
    private function buildFilterClause(?int $itemId, ?string $type, ?string $search, ?string $dateFrom = null, ?string $dateTo = null, ?int $locationId = null, string $tableAlias = 't', string $itemAlias = 'i'): array
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
        if ($locationId) {
            // Matches either side of a Transfer (From or To), not just the
            // primary location_id - so filtering by "Warehouse" surfaces a
            // Transfer that moved stock into it, not just out of it.
            $where .= " AND ({$tableAlias}.location_id = :location_id OR {$tableAlias}.to_location_id = :location_id)";
            $params[':location_id'] = $locationId;
        }

        return [$where, $params];
    }

    /** Count of DISTINCT order/transfer groups matching the same filters as
     *  readGrouped() - for pagination on the consolidated Product Movement
     *  list. See readGrouped() for what a "group" is. */
    public function countGroups(?int $itemId = null, ?string $type = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, ?int $locationId = null): int
    {
        [$where, $params] = $this->buildFilterClause($itemId, $type, $search, $dateFrom, $dateTo, $locationId);

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
     *  Same $itemId/$type/$search/$dateFrom/$dateTo/$locationId/$sort/
     *  $limit/$offset contract as readAll(). */
    public function readGrouped(?int $itemId = null, ?string $type = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, ?string $sort = null, ?int $limit = null, ?int $offset = null, ?int $locationId = null): array
    {
        [$where, $params] = $this->buildFilterClause($itemId, $type, $search, $dateFrom, $dateTo, $locationId, 't2', 'i2');
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

    /** Count grouped by transaction_type. Not currently used by the
     *  Dashboard (see dailyVolume() for its trend chart), kept as a
     *  general-purpose breakdown for reporting. */
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

    /** Dashboard line-chart data source: transaction volume per day over
     *  the trailing window (today inclusive), zero-filled so a quiet day
     *  still plots a point instead of leaving a gap in the line. Grouped
     *  on DATE(created_at) - when a row was logged - to match "Recent
     *  Stock Movement" above it on the same dashboard, rather than
     *  transaction_date (when the movement happened), which is nullable
     *  and only ever set on stock_out. */
    public function dailyVolume(int $days = 14): array
    {
        $query = "SELECT DATE(created_at) AS day, COUNT(*) AS total
                   FROM {$this->table}
                   WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                   GROUP BY DATE(created_at)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':days', $days - 1, PDO::PARAM_INT);
        $stmt->execute();
        $byDay = array_column($stmt->fetchAll(), 'total', 'day');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $series[$date] = (int) ($byDay[$date] ?? 0);
        }
        return $series;
    }

    /** Usage Report data source: total units stocked OUT per product
     *  within an optional date range (filtered on transaction_date, the
     *  date the movement actually happened - not created_at, since a
     *  report is asking "how much moved in this period", not "how much
     *  was logged in this period"). "Usage" here means stock_out only,
     *  same as predictedStockouts()'s own history window - Borrow rows
     *  are a separate concept (still checked out, not consumed) and are
     *  intentionally excluded. Ordered by usage descending. */
    public function usageByItem(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $where = "t.transaction_type = 'stock_out'";
        $params = [];
        if ($dateFrom !== null && $dateFrom !== '') {
            $where .= " AND t.transaction_date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $where .= " AND t.transaction_date <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        $query = "SELECT t.item_id, i.model, SUM(t.quantity) AS total_used, COUNT(*) AS movement_count
                  FROM {$this->table} t
                  LEFT JOIN inventory_items i ON t.item_id = i.item_id
                  WHERE {$where}
                  GROUP BY t.item_id, i.model
                  ORDER BY total_used DESC";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** READ - single transaction by ID, joined with product/location
     *  names - used by ItemRequestController to re-validate an Item
     *  Request/Borrow row before acting on it (approve/decline/return). */
    public function readById(int $id): ?array
    {
        $query = "SELECT t.*, i.model, i.item_type_id, l.location_name
                  FROM {$this->table} t
                  LEFT JOIN inventory_items i ON t.item_id = i.item_id
                  LEFT JOIN locations l ON t.location_id = l.location_id
                  WHERE t.transaction_id = :id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** SQL expression yielding the ORIGINAL REQUESTER's name for a row of
     *  the given type.
     *
     *  technician_name means different things down the request chain: on
     *  an 'item_request' it's the Technician who asked, but on the
     *  'borrow' and 'return' rows that follow it's the Warehouse Staff
     *  who released/received the stock (see ItemRequestController::
     *  approve()/returnItem()). So "whose request is this?" has to walk
     *  back up related_transaction_id rather than read the row's own
     *  technician_name - one hop for a Borrow, two for a Return.
     *
     *  Used by BOTH the WHERE clause (scoping a Technician to their own
     *  rows) and the SELECT list (displaying "Requested By"), so the two
     *  can never disagree about who owns a row. The outer reference is
     *  alias-qualified deliberately: an unqualified related_transaction_id
     *  inside the subquery would resolve against the subquery's own copy
     *  of the table, not the outer row. */
    private function requesterNameExpression(string $type, string $alias = 't'): string
    {
        return match ($type) {
            'borrow' => "(SELECT req.technician_name FROM {$this->table} req
                          WHERE req.transaction_id = {$alias}.related_transaction_id)",
            'return' => "(SELECT req.technician_name FROM {$this->table} req
                          WHERE req.transaction_id = (
                              SELECT b.related_transaction_id FROM {$this->table} b
                              WHERE b.transaction_id = {$alias}.related_transaction_id
                          ))",
            default => "{$alias}.technician_name",
        };
    }

    /** Shared WHERE builder for readRequestList()/countRequestList() -
     *  the Item Request and Return Monitoring Module's own listing, kept
     *  separate from buildFilterClause() above since it filters by
     *  status (a concept Product Movement doesn't use) and by requester
     *  rather than by item/date-range/free-text search.
     *
     *  $technicianName scopes to the original REQUESTER, not to whoever's
     *  name happens to be stamped on the row - see
     *  requesterNameExpression(). */
    private function buildRequestFilterClause(string $type, array $statuses, ?string $technicianName, string $alias = 't'): array
    {
        $where = "{$alias}.transaction_type = :type";
        $params = [':type' => $type];

        if (!empty($statuses)) {
            $placeholders = [];
            foreach (array_values($statuses) as $i => $status) {
                $key = ":status{$i}";
                $placeholders[] = $key;
                $params[$key] = $status;
            }
            $where .= " AND {$alias}.status IN (" . implode(', ', $placeholders) . ")";
        }
        if ($technicianName !== null) {
            $where .= " AND " . $this->requesterNameExpression($type, $alias) . " = :technician_name";
            $params[':technician_name'] = $technicianName;
        }

        return [$where, $params];
    }

    /** Count of Item Request/Borrow/Return rows matching the given type/
     *  status/requester filters - for pagination on the Item Requests page. */
    public function countRequestList(string $type, array $statuses = [], ?string $technicianName = null): int
    {
        [$where, $params] = $this->buildRequestFilterClause($type, $statuses, $technicianName);
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM {$this->table} t WHERE {$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /** READ - Item Request, Borrow or Return rows for the Item Requests
     *  page (Pending Requests / Active Borrows / History tabs), one flat
     *  row per line - deliberately not consolidated by reference_number
     *  like readGrouped(), since each line here can be approved/declined/
     *  returned independently of any others submitted alongside it.
     *
     *  The return_* columns are correlated subqueries over the Return
     *  rows linked to a Borrow, so one Borrow row carries its whole
     *  return story - how much came back, how much of that was damaged
     *  and written off, when the last return happened and what was noted
     *  about it - without a second round trip per row. All four stay NULL/
     *  zero for an Item Request row, which has no returns under it.
     *
     *  returned_damaged_quantity is named apart from the table's own
     *  damaged_quantity column on purpose: SELECT t.* already carries
     *  that one (per-Return-row), and two columns of the same name would
     *  silently collide in the fetched array.
     *
     *  last_return_id is separate from last_return_date (same subquery,
     *  keyed off transaction_id instead of transaction_date) so a caller
     *  merging Borrow rows with other request types into one History list
     *  can sort by when a Borrow actually CLOSED OUT (its last Return),
     *  not by the Borrow row's own transaction_id, which was assigned back
     *  when it was first approved and can be far older than today's close-
     *  out - see ItemRequestController::index()'s 'history' tab. */
    public function readRequestList(string $type, array $statuses = [], ?string $technicianName = null, ?string $sort = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildRequestFilterClause($type, $statuses, $technicianName);
        $orderBy = self::SORT_OPTIONS[$sort] ?? self::SORT_OPTIONS['date_desc'];
        $requestedBy = $this->requesterNameExpression($type);

        $query = "SELECT t.*, i.model, i.item_type_id, l.location_name,
                         (SELECT COALESCE(SUM(r.quantity), 0) FROM {$this->table} r
                          WHERE r.related_transaction_id = t.transaction_id AND r.transaction_type = 'return') AS returned_quantity,
                         (SELECT COALESCE(SUM(r.damaged_quantity), 0) FROM {$this->table} r
                          WHERE r.related_transaction_id = t.transaction_id AND r.transaction_type = 'return') AS returned_damaged_quantity,
                         (SELECT MAX(r.transaction_date) FROM {$this->table} r
                          WHERE r.related_transaction_id = t.transaction_id AND r.transaction_type = 'return') AS last_return_date,
                         (SELECT MAX(r.transaction_id) FROM {$this->table} r
                          WHERE r.related_transaction_id = t.transaction_id AND r.transaction_type = 'return') AS last_return_id,
                         (SELECT r.notes FROM {$this->table} r
                          WHERE r.related_transaction_id = t.transaction_id AND r.transaction_type = 'return'
                          ORDER BY r.transaction_id DESC LIMIT 1) AS last_return_notes,
                         {$requestedBy} AS requested_by_name
                  FROM {$this->table} t
                  LEFT JOIN inventory_items i ON t.item_id = i.item_id
                  LEFT JOIN locations l ON t.location_id = l.location_id
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

    /** Count of DISTINCT request groups matching the same filters as
     *  readRequestListGrouped() - for pagination on the consolidated
     *  Pending Requests list. See readRequestListGrouped() for what a
     *  "group" is. */
    public function countRequestListGrouped(string $type, array $statuses = [], ?string $technicianName = null): int
    {
        [$where, $params] = $this->buildRequestFilterClause($type, $statuses, $technicianName);

        $query = "SELECT COUNT(*) AS total FROM (
                      SELECT COALESCE(t.reference_number, CONCAT('txn-', t.transaction_id)) AS grouping_key
                      FROM {$this->table} t
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

    /** Consolidated Pending Requests listing: several item_request lines
     *  submitted together in one "New Request" (sharing a
     *  reference_number - see ItemRequestController::create()) collapse
     *  into ONE row here - its earliest-added line, plus line_count (how
     *  many products were in that submission) and total_quantity (the
     *  sum of every line's quantity). A second, separate submission -
     *  even same requester, same day - gets its own reference_number and
     *  stays its own row, same as readGrouped()'s Product Movement
     *  convention.
     *
     *  Unlike readRequestList() (used for Active Borrows/History, which
     *  stays flat - a return can happen line-by-line), this is safe to
     *  consolidate because Approve/Decline act on the WHOLE group at
     *  once (see ItemRequestController::approve()/decline()), so there's
     *  no remaining per-line action a consolidated row would hide. */
    public function readRequestListGrouped(string $type, array $statuses = [], ?string $technicianName = null, ?string $sort = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildRequestFilterClause($type, $statuses, $technicianName, 't2');
        $orderBy = self::SORT_OPTIONS[$sort] ?? self::SORT_OPTIONS['date_desc'];
        $requestedBy = $this->requesterNameExpression($type);

        $query = "SELECT t.*, i.model, i.item_type_id, l.location_name,
                         {$requestedBy} AS requested_by_name,
                         g.line_count, g.total_quantity
                  FROM {$this->table} t
                  INNER JOIN (
                      SELECT COALESCE(t2.reference_number, CONCAT('txn-', t2.transaction_id)) AS grouping_key,
                             MIN(t2.transaction_id) AS representative_id,
                             COUNT(*) AS line_count,
                             SUM(t2.quantity) AS total_quantity
                      FROM {$this->table} t2
                      WHERE {$where}
                      GROUP BY grouping_key
                  ) g ON t.transaction_id = g.representative_id
                  LEFT JOIN inventory_items i ON t.item_id = i.item_id
                  LEFT JOIN locations l ON t.location_id = l.location_id
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

    /** Units still physically out on active Borrows - the borrowed
     *  quantity minus whatever has already come back against each one, so
     *  a partially-returned Borrow only counts what's still outstanding.
     *  Scoped to one requester when $technicianName is given, using the
     *  same filter builder as the listings so the summary figure and the
     *  rows underneath it can't disagree. */
    public function outstandingBorrowedUnits(?string $technicianName = null): int
    {
        [$where, $params] = $this->buildRequestFilterClause('borrow', ['active'], $technicianName);

        $query = "SELECT COALESCE(SUM(t.quantity - (
                      SELECT COALESCE(SUM(r.quantity), 0) FROM {$this->table} r
                      WHERE r.related_transaction_id = t.transaction_id AND r.transaction_type = 'return'
                  )), 0) AS total
                  FROM {$this->table} t
                  WHERE {$where}";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /** Total already returned against a given Borrow row - used to work
     *  out how much is still outstanding before logging a new (possibly
     *  partial) return against it. */
    public function returnedQuantityFor(int $borrowTransactionId): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COALESCE(SUM(quantity), 0) AS total FROM {$this->table}
             WHERE related_transaction_id = :id AND transaction_type = 'return'"
        );
        $stmt->bindValue(':id', $borrowTransactionId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /** Flips an Item Request/Borrow row's status in place - the one
     *  documented exception to this table being an immutable ledger
     *  (approve/decline an Item Request; complete a Borrow once fully
     *  returned). Every other row type is created once and never touched
     *  again. */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET status = :status WHERE transaction_id = :id");
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Declines a pending Item Request and records the mandatory reason,
     *  replacing whatever note the technician originally submitted - the
     *  decline reason is the one that matters once a request is closed
     *  out (see ItemRequestController::decline()). */
    public function declineWithReason(int $id, string $reason): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET status = 'declined', notes = :notes WHERE transaction_id = :id"
        );
        $stmt->bindValue(':notes', $reason);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Thin PDO transaction wrappers - used by ItemRequestController::
     *  approve() so a whole-batch (or bulk multi-select) approval writes
     *  its Borrow rows and stock deductions atomically: one failed line
     *  rolls every line in that approval back, instead of leaving stock
     *  partially released. */
    public function beginTransaction(): bool
    {
        return $this->conn->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->conn->commit();
    }

    public function rollBack(): bool
    {
        return $this->conn->inTransaction() ? $this->conn->rollBack() : false;
    }
}
