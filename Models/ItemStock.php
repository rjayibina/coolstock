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
}
