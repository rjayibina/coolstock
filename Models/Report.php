<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Report.php (Model)
 * Reporting and Monitoring Module. This model owns only the audit trail
 * (the `reports` table - who generated what report, over what date
 * range, and when) via create()/readRecent()/count(). It does NOT compute
 * report data itself - ReportController pulls the actual figures from the
 * models that already own them (InventoryItem, ItemStock, Transaction) so
 * this module never duplicates their query logic.
 */
class Report
{
    private PDO $conn;
    private string $table = "reports";

    /** report_type => display label, and the canonical list/order shown
     *  on the report-type picker. */
    public const TYPES = [
        'stock_summary' => 'Stock Summary',
        'usage_report' => 'Usage Report',
        'low_stock' => 'Low Stock / Predictive Alerts',
        'transaction_log' => 'Transaction Log',
    ];

    public ?int $report_id = null;
    public ?string $report_type = null;
    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $generated_by = null;
    public ?string $notes = null;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    /** Logs one audit row for a report the user just generated. Doesn't
     *  store the computed figures themselves (those can change as stock/
     *  transactions move) - only that the report was run, by whom, over
     *  what range, so ReportController can re-run the same computation
     *  live whenever this history is revisited. */
    public function create(): bool
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table} (report_type, date_from, date_to, generated_by, notes)
             VALUES (:report_type, :date_from, :date_to, :generated_by, :notes)"
        );
        $stmt->bindValue(':report_type', $this->report_type);
        $stmt->bindValue(':date_from', $this->date_from);
        $stmt->bindValue(':date_to', $this->date_to);
        $stmt->bindValue(':generated_by', $this->generated_by);
        $stmt->bindValue(':notes', $this->notes);
        return $stmt->execute();
    }

    /** Most recently generated reports, for the "Recently Generated" list
     *  on the Reports page. */
    public function readRecent(int $limit = 10): array
    {
        $query = "SELECT * FROM {$this->table} ORDER BY report_id DESC LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function count(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }
}
