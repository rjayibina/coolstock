<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * ItemStock.php (Model)
 * One row per (item_id, location_id) pair holding that item's quantity at
 * that location. An item with no stock recorded anywhere simply has no
 * rows here - a row is created on demand by the first Stock In for that
 * item+location pair (see adjust()).
 */
class ItemStock
{
    private PDO $conn;
    private string $table = "item_stock";

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /** Total quantity across all locations, per item - keyed by item_id.
     *  Items with no stock rows at all are simply absent from the result. */
    public function totalsForItems(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $stmt = $this->conn->prepare(
            "SELECT item_id, SUM(quantity) AS total FROM {$this->table} WHERE item_id IN ($placeholders) GROUP BY item_id"
        );
        $stmt->execute(array_map('intval', $itemIds));

        $totals = [];
        foreach ($stmt->fetchAll() as $row) {
            $totals[(int) $row['item_id']] = (int) $row['total'];
        }
        return $totals;
    }

    /** Per-location breakdown for a set of items, joined with location name -
     *  keyed by item_id => [['location_id', 'location_name', 'quantity'], ...],
     *  ordered by location name. Zero-quantity rows are left out (nothing
     *  meaningful to show). */
    public function breakdownForItems(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $query = "SELECT s.item_id, s.location_id, l.location_name, s.quantity
                  FROM {$this->table} s
                  JOIN locations l ON l.location_id = s.location_id
                  WHERE s.item_id IN ($placeholders) AND s.quantity > 0
                  ORDER BY l.location_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(array_map('intval', $itemIds));

        $breakdown = [];
        foreach ($stmt->fetchAll() as $row) {
            $breakdown[(int) $row['item_id']][] = [
                'location_id' => (int) $row['location_id'],
                'location_name' => $row['location_name'],
                'quantity' => (int) $row['quantity'],
            ];
        }
        return $breakdown;
    }

    /** Current quantity of one item at one location (0 if no row yet) - used
     *  to check a Stock Out won't take a location below zero. */
    public function getQuantity(int $itemId, int $locationId): int
    {
        $query = "SELECT quantity FROM {$this->table} WHERE item_id = :item_id AND location_id = :location_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
        $stmt->bindParam(':location_id', $locationId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (int) $row['quantity'] : 0;
    }

    /** Adds (Stock In) or subtracts (Stock Out, pass a negative delta)
     *  quantity for an item at a location. Creates the (item, location) row
     *  on first use. */
    public function adjust(int $itemId, int $locationId, int $delta): bool
    {
        $query = "INSERT INTO {$this->table} (item_id, location_id, quantity)
                  VALUES (:item_id, :location_id, :delta)
                  ON DUPLICATE KEY UPDATE quantity = quantity + :delta2";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
        $stmt->bindParam(':location_id', $locationId, PDO::PARAM_INT);
        $stmt->bindParam(':delta', $delta, PDO::PARAM_INT);
        $stmt->bindParam(':delta2', $delta, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Predicted/actual stockout alerts per (item, location) pair - Mean
     *  Time Between Stockouts (MTBS) computed from *stock-out history*,
     *  never average daily sales. Implements the methodology written up
     *  in PREDICTIVE_STOCKOUT_ALERT.md; read that file for the full
     *  reasoning behind every step below.
     *
     *  Returns one row per pair with something to report, most urgent
     *  first:
     *   - status = 'actual': quantity is 0 right now - already knowable
     *     without any history.
     *   - status = 'predicted': quantity > 0, but with at least 2 past
     *     stockout events (n >= 2) the projected next one falls within
     *     $alertWindowDays of today.
     *  Pairs with fewer than 2 stockout events and current stock > 0 are
     *  left out entirely - there's nothing predictive to say about them
     *  yet (see the doc's "confidence tiers" section).
     *
     *  $frequencyWindowDays sets the trailing window for the secondary
     *  "stockouts per 30 days" figure attached to every row. */
    public function predictedStockouts(int $alertWindowDays = 7, int $frequencyWindowDays = 90): array
    {
        // One row per (item, location) pair that has ever had stock
        // recorded, with today's quantity - also where "actual stockout"
        // (quantity = 0 right now) comes from.
        $pairsStmt = $this->conn->query(
            "SELECT s.item_id, s.location_id, s.quantity, i.model, l.location_name
             FROM {$this->table} s
             JOIN inventory_items i ON i.item_id = s.item_id
             JOIN locations l ON l.location_id = s.location_id"
        );
        $pairs = $pairsStmt->fetchAll();
        if (empty($pairs)) {
            return [];
        }

        // Every Stock In/Out/Delivery/Transfer row, oldest first - Item
        // Request/Borrow/Return never move stock (see Transaction.php) so
        // they're excluded from the replay entirely.
        $txnStmt = $this->conn->query(
            "SELECT item_id, location_id, to_location_id, transaction_type, quantity, transaction_date
             FROM transactions
             WHERE transaction_type IN ('stock_in', 'stock_out', 'delivery', 'transfer')
             ORDER BY transaction_date ASC, transaction_id ASC"
        );
        $transactions = $txnStmt->fetchAll();

        // Bucket each transaction's effect(s) into per-(item,location)
        // signed delta lists, staying in the same chronological order as
        // the query above - mirrors exactly what adjust() does
        // incrementally at Delivery/Transfer/Stock In/Out time. A Transfer
        // affects two pairs at once: negative at the FROM location,
        // positive at the TO location.
        $deltasByPair = [];
        foreach ($transactions as $t) {
            $itemId = (int) $t['item_id'];
            $qty = (int) $t['quantity'];
            $date = $t['transaction_date'];
            $type = $t['transaction_type'];

            if ($type === 'stock_in' || $type === 'delivery') {
                $deltasByPair[$itemId . ':' . $t['location_id']][] = ['date' => $date, 'delta' => $qty];
            } elseif ($type === 'stock_out') {
                $deltasByPair[$itemId . ':' . $t['location_id']][] = ['date' => $date, 'delta' => -$qty];
            } elseif ($type === 'transfer') {
                $deltasByPair[$itemId . ':' . $t['location_id']][] = ['date' => $date, 'delta' => -$qty];
                if (!empty($t['to_location_id'])) {
                    $deltasByPair[$itemId . ':' . $t['to_location_id']][] = ['date' => $date, 'delta' => $qty];
                }
            }
        }

        $today = new DateTimeImmutable(date('Y-m-d'));
        $frequencyCutoff = $today->modify("-{$frequencyWindowDays} days");
        $results = [];

        foreach ($pairs as $pair) {
            $key = $pair['item_id'] . ':' . $pair['location_id'];
            $deltas = $deltasByPair[$key] ?? [];

            // Replay the running balance to find every date it hit zero
            // coming down from a positive balance (a "stockout event").
            $balance = 0;
            $stockoutDates = [];
            foreach ($deltas as $d) {
                $prevBalance = $balance;
                $balance += $d['delta'];
                if ($prevBalance > 0 && $balance <= 0) {
                    $stockoutDates[] = $d['date'];
                }
            }

            $n = count($stockoutDates);
            $recentCount = count(array_filter(
                $stockoutDates,
                fn($d) => new DateTimeImmutable($d) >= $frequencyCutoff
            ));
            $stockoutFrequency = round($recentCount / ($frequencyWindowDays / 30), 1);
            $quantity = (int) $pair['quantity'];

            if ($quantity <= 0) {
                $results[] = [
                    'item_id' => (int) $pair['item_id'],
                    'location_id' => (int) $pair['location_id'],
                    'model' => $pair['model'],
                    'location_name' => $pair['location_name'],
                    'status' => 'actual',
                    'confidence' => null,
                    'days_until' => 0,
                    'predicted_date' => null,
                    'predicted_range' => null,
                    'stockout_count' => $n,
                    'stockout_frequency' => $stockoutFrequency,
                ];
                continue;
            }

            if ($n < 2) {
                continue; // no MTBS yet - nothing predictive to say
            }

            $firstDate = new DateTimeImmutable($stockoutDates[0]);
            $lastDate = new DateTimeImmutable($stockoutDates[$n - 1]);
            $mtbs = (int) $firstDate->diff($lastDate)->days / ($n - 1);

            $gaps = [];
            for ($i = 1; $i < $n; $i++) {
                $prev = new DateTimeImmutable($stockoutDates[$i - 1]);
                $curr = new DateTimeImmutable($stockoutDates[$i]);
                $gaps[] = (int) $prev->diff($curr)->days;
            }
            $mean = array_sum($gaps) / count($gaps);
            $variance = array_sum(array_map(fn($g) => ($g - $mean) ** 2, $gaps)) / count($gaps);
            $stdDev = sqrt($variance);

            $predictedDate = $lastDate->modify('+' . (int) round($mtbs) . ' days');
            $daysUntil = (int) $today->diff($predictedDate)->days * ($predictedDate < $today ? -1 : 1);

            if ($daysUntil > $alertWindowDays) {
                continue; // too far out to alert on yet
            }

            $results[] = [
                'item_id' => (int) $pair['item_id'],
                'location_id' => (int) $pair['location_id'],
                'model' => $pair['model'],
                'location_name' => $pair['location_name'],
                'status' => 'predicted',
                'confidence' => $n >= 5 ? 'High' : ($n === 4 ? 'Medium' : 'Low'),
                'days_until' => $daysUntil,
                'predicted_date' => $predictedDate->format('Y-m-d'),
                'predicted_range' => [
                    $predictedDate->modify('-' . (int) round($stdDev) . ' days')->format('Y-m-d'),
                    $predictedDate->modify('+' . (int) round($stdDev) . ' days')->format('Y-m-d'),
                ],
                'stockout_count' => $n,
                'stockout_frequency' => $stockoutFrequency,
            ];
        }

        usort($results, function ($a, $b) {
            if ($a['status'] !== $b['status']) {
                return $a['status'] === 'actual' ? -1 : 1;
            }
            return $a['days_until'] <=> $b['days_until'];
        });

        return $results;
    }
}
