<?php
require_once __DIR__ . '/../Helpers/auth.php';
require_once __DIR__ . '/../Models/Report.php';
require_once __DIR__ . '/../Models/InventoryItem.php';
require_once __DIR__ . '/../Models/ItemStock.php';
require_once __DIR__ . '/../Models/Transaction.php';

/**
 * ReportController.php
 * Reporting and Monitoring Module. index() (GET) computes and displays a
 * report live with no database write - a report is just a view over data
 * other modules already own (InventoryItem/ItemStock/Transaction), so
 * viewing one has no side effect. generate() (POST) is the only action
 * that writes: it logs one `reports` audit row (who ran what, over what
 * range, when) via Report::create(), then redirects back to index() with
 * the same type/date params so the report displays immediately.
 */
class ReportController
{
    private Report $report;
    private InventoryItem $item;
    private ItemStock $itemStock;
    private Transaction $transaction;

    public function __construct()
    {
        $this->report = new Report();
        $this->item = new InventoryItem();
        $this->itemStock = new ItemStock();
        $this->transaction = new Transaction();
    }

    public function index(): void
    {
        $type = $_GET['type'] ?? '';
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');
        if (!array_key_exists($type, Report::TYPES)) {
            $type = '';
        }

        $reportData = null;
        if ($type !== '') {
            $reportData = $this->computeReport($type, $dateFrom, $dateTo);
        }

        $recentReports = $this->report->readRecent(10);

        require __DIR__ . '/../Views/reports/index.php';
    }

    /** Logs the audit row, then hands off to index() with the same
     *  query params so the just-generated report is what's on screen. */
    public function generate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?module=reports&action=index");
            exit;
        }

        $type = $_POST['type'] ?? '';
        $dateFrom = trim($_POST['date_from'] ?? '');
        $dateTo = trim($_POST['date_to'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!array_key_exists($type, Report::TYPES)) {
            header("Location: index.php?module=reports&action=index&status=error&message=" . urlencode("Please choose a valid report type."));
            exit;
        }

        $viewer = current_user();

        $this->report->report_id = null;
        $this->report->report_type = $type;
        $this->report->date_from = $dateFrom !== '' ? $dateFrom : null;
        $this->report->date_to = $dateTo !== '' ? $dateTo : null;
        $this->report->generated_by = $viewer['full_name'] ?? null;
        $this->report->notes = $notes !== '' ? $notes : null;
        $this->report->create();

        $query = "type=" . urlencode($type) . "&status=generated";
        if ($dateFrom !== '') {
            $query .= "&date_from=" . urlencode($dateFrom);
        }
        if ($dateTo !== '') {
            $query .= "&date_to=" . urlencode($dateTo);
        }

        header("Location: index.php?module=reports&action=index&{$query}");
        exit;
    }

    /** Computes the actual figures for one report type - reuses the
     *  models that already own this data rather than duplicating their
     *  queries here. */
    private function computeReport(string $type, string $dateFrom, string $dateTo): array
    {
        switch ($type) {
            case 'stock_summary':
                $items = $this->item->readAll();
                $itemIds = array_map(fn($i) => (int) $i['item_id'], $items);
                $breakdown = $this->itemStock->breakdownForItems($itemIds);
                return [
                    'items' => $items,
                    'breakdown' => $breakdown,
                ];

            case 'usage_report':
                return [
                    'usage' => $this->transaction->usageByItem(
                        $dateFrom !== '' ? $dateFrom : null,
                        $dateTo !== '' ? $dateTo : null
                    ),
                ];

            case 'low_stock':
                return [
                    'alerts' => $this->itemStock->predictedStockouts(),
                ];

            case 'transaction_log':
                $rows = $this->transaction->readGrouped(
                    null,
                    null,
                    null,
                    $dateFrom !== '' ? $dateFrom : null,
                    $dateTo !== '' ? $dateTo : null,
                    'date_desc'
                );
                return ['transactions' => $rows];

            default:
                return [];
        }
    }
}
