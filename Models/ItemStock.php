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

    /** Predictive Stock Alert - average daily stock-out rate compared
     *  against supplier lead time, per the standard capstone formula
     *  (see PREDICTIVE_STOCKOUT_ALERT.md): "average daily sales" swapped
     *  for "average daily stock-outs" throughout, since this system logs
     *  stock-outs directly rather than sales.
     *
     *  Per product (summed across every location - the formula doesn't
     *  distinguish locations):
     *   1. Get Current Stock - today's total quantity, every location.
     *   2. Get Stock Out History - total units stocked out in the
     *      trailing $lookbackDays.
     *   3. Average Daily Stock Outs = that total / $lookbackDays.
     *   4. Predicted Days Until Stockout = Current Stock / Average Daily
     *      Stock Outs.
     *   5. Reorder Point = (Average Daily Stock Outs x $leadTimeDays) +
     *      $safetyStock - the "more realistic" version the doc
     *      recommends over a bare day-count comparison.
     *   6. Alert when Current Stock <= Reorder Point (equivalent to
     *      "Predicted Days Until Stockout <= Lead Time", just expressed
     *      as a single stock-level threshold instead of two figures).
     *
     *  $leadTimeDays/$safetyStock default to the doc's own worked example
     *  (7 days, 3 units) - this system doesn't have a per-supplier lead
     *  time or per-product safety stock setting yet, so these are applied
     *  uniformly. That's a reasonable capstone-scope simplification, but
     *  a real deployment would want both configurable per product/supplier.
     *
     *  Products with zero stock-out history in the window are skipped
     *  entirely (status = 'predicted') - there's no rate to compute a
     *  prediction from - UNLESS current stock is already 0, which is
     *  always alertable regardless of history (status = 'actual'). */
    public function predictedStockouts(int $leadTimeDays = 7, int $safetyStock = 3, int $lookbackDays = 30): array
    {
        // 1. Get Current Stock - per product, summed across every location.
        $stockStmt = $this->conn->query(
            "SELECT s.item_id, i.model, SUM(s.quantity) AS current_stock
             FROM {$this->table} s
             JOIN inventory_items i ON i.item_id = s.item_id
             GROUP BY s.item_id, i.model"
        );
        $stockRows = $stockStmt->fetchAll();
        if (empty($stockRows)) {
            return [];
        }

        // 2. Get Stock Out History - total units stocked out per product
        // in the trailing $lookbackDays.
        $cutoff = date('Y-m-d', strtotime("-{$lookbackDays} days"));
        $historyStmt = $this->conn->prepare(
            "SELECT item_id, SUM(quantity) AS total_out
             FROM transactions
             WHERE transaction_type = 'stock_out' AND transaction_date >= :cutoff
             GROUP BY item_id"
        );
        $historyStmt->bindValue(':cutoff', $cutoff);
        $historyStmt->execute();
        $historyByItem = [];
        foreach ($historyStmt->fetchAll() as $row) {
            $historyByItem[(int) $row['item_id']] = (int) $row['total_out'];
        }

        $results = [];
        foreach ($stockRows as $row) {
            $itemId = (int) $row['item_id'];
            $currentStock = (int) $row['current_stock'];
            $totalStockedOut = $historyByItem[$itemId] ?? 0;

            // 3. Calculate Average Daily Stock Outs
            $avgDailyStockOuts = $totalStockedOut / $lookbackDays;

            if ($currentStock <= 0) {
                $results[] = [
                    'item_id' => $itemId,
                    'model' => $row['model'],
                    'current_stock' => $currentStock,
                    'avg_daily_stock_outs' => round($avgDailyStockOuts, 2),
                    'predicted_days' => 0,
                    'reorder_point' => null,
                    'status' => 'actual',
                ];
                continue;
            }

            if ($avgDailyStockOuts <= 0) {
                continue; // no stock-out history yet - nothing to predict
            }

            // 4. Predict the stockout date
            $predictedDays = $currentStock / $avgDailyStockOuts;

            // 5. Reorder Point = (Average Daily Stock Outs x Lead Time) + Safety Stock
            $reorderPoint = ($avgDailyStockOuts * $leadTimeDays) + $safetyStock;

            // 6. Is Predicted Stockout <= Lead Time? (same test, expressed
            // as Current Stock <= Reorder Point)
            if ($currentStock <= $reorderPoint) {
                $results[] = [
                    'item_id' => $itemId,
                    'model' => $row['model'],
                    'current_stock' => $currentStock,
                    'avg_daily_stock_outs' => round($avgDailyStockOuts, 2),
                    'predicted_days' => (int) round($predictedDays),
                    'reorder_point' => (int) ceil($reorderPoint),
                    'status' => 'predicted',
                ];
            }
        }

        usort($results, function ($a, $b) {
            if ($a['status'] !== $b['status']) {
                return $a['status'] === 'actual' ? -1 : 1;
            }
            return $a['predicted_days'] <=> $b['predicted_days'];
        });

        return $results;
    }
}
