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

    /** Returns one report type's table as a JSON fragment ({title, html})
     *  for the Recently Generated row's AJAX open (see
     *  Views/reports/index.php's openReportModalAjax()). Clicking a row
     *  used to do a full page navigation to ?view=modal, which reset the
     *  report-type dropdown and inline card back to "Choose a report..."
     *  every time, since a full reload re-reads $_GET['type'] from
     *  scratch. This computes the exact same $reportData via
     *  computeReport() and renders it with the exact same
     *  renderReportTable() as the inline card, so the modal's numbers can
     *  never drift from what export()/generate() would show. */
    public function modal(): void
    {
        header('Content-Type: application/json');

        $type = $_GET['type'] ?? '';
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');

        if (!array_key_exists($type, Report::TYPES)) {
            http_response_code(400);
            echo json_encode(['error' => 'Please choose a valid report type.']);
            exit;
        }

        $reportData = $this->computeReport($type, $dateFrom, $dateTo);

        require __DIR__ . '/../Views/reports/_report_table.php';
        ob_start();
        renderReportTable($type, $reportData, 'modal', $dateFrom, $dateTo);
        $html = ob_get_clean();

        echo json_encode([
            'title' => Report::typeLabel($type),
            'html' => $html,
        ]);
        exit;
    }

    /** Streams the currently-viewed report as a CSV download (opens
     *  directly in Excel) - same $type/date_from/date_to query params and
     *  the same computeReport() used by index(), so the exported rows can
     *  never drift from what's on screen. No audit row is logged here;
     *  that only happens via generate(), same as before this action
     *  existed. */
    public function export(): void
    {
        $type = $_GET['type'] ?? '';
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');

        if (!array_key_exists($type, Report::TYPES)) {
            header("Location: index.php?module=reports&action=index&status=error&message=" . urlencode("Please choose a valid report type."));
            exit;
        }

        $reportData = $this->computeReport($type, $dateFrom, $dateTo);
        $rows = $this->reportRowsForExport($type, $reportData);

        $filename = preg_replace('/[^a-z0-9_]/', '', $type) . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, $rows['header']);
        foreach ($rows['lines'] as $line) {
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    /** Flattens one report type's computeReport() output into a plain
     *  header row + data rows shape, ready for fputcsv() - kept separate
     *  from renderReportTable() (Views/reports/index.php) since that one
     *  emits HTML, not CSV cells, but both read the exact same
     *  $reportData so the two can't disagree on the underlying figures. */
    private function reportRowsForExport(string $type, array $reportData): array
    {
        switch ($type) {
            case 'stock_summary':
                $lines = [];
                foreach ($reportData['items'] as $i) {
                    $lines[] = [
                        $i['model'],
                        $i['category_name'] ?? '',
                        (int) $i['total_quantity'],
                    ];
                }
                return ['header' => ['Product', 'Category', 'Total Stock'], 'lines' => $lines];

            case 'usage_report':
                $lines = [];
                foreach ($reportData['usage'] as $u) {
                    $lines[] = [
                        $u['model'] ?? 'Unknown product',
                        (int) $u['total_used'],
                        (int) $u['movement_count'],
                    ];
                }
                return ['header' => ['Product', 'Units Used (Stock Out)', 'Stock-Out Events'], 'lines' => $lines];

            case 'low_stock':
                $lines = [];
                foreach ($reportData['alerts'] as $a) {
                    $lines[] = [
                        $a['model'],
                        (int) $a['current_stock'],
                        $a['avg_daily_stock_outs'],
                        $a['predicted_days'] !== null ? round((float) $a['predicted_days'], 1) : '',
                        $a['reorder_point'] ?? '',
                        $a['status'] === 'actual' ? 'Out Now' : 'Reorder Now',
                    ];
                }
                return ['header' => ['Product', 'Current Stock', 'Avg. Daily Stock Out', 'Predicted Days Left', 'Reorder Point', 'Status'], 'lines' => $lines];

            case 'transaction_log':
                $lines = [];
                foreach ($reportData['transactions'] as $t) {
                    $lines[] = [
                        $t['created_at'],
                        $t['model'] ?? 'Unknown product',
                        Transaction::movementLabel($t['transaction_type']),
                        (int) $t['total_quantity'],
                        $t['location_name'] ?? '',
                        $t['technician_name'] ?? '',
                    ];
                }
                return ['header' => ['Date', 'Product', 'Type', 'Quantity', 'Location', 'By'], 'lines' => $lines];

            default:
                return ['header' => [], 'lines' => []];
        }
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
        $this->report->user_id = $viewer['user_id'] ?? null;
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
