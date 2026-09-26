<?php
/**
 * Views/reports/_report_table.php
 * Defines renderReportTable() - split out of Views/reports/index.php so
 * it can be require()'d on its own by ReportController::modal() (the
 * Recently Generated row's AJAX fragment endpoint) without pulling in
 * the rest of the Reports page's chrome (header, filter form, dropdown,
 * generate-report modal, etc.). Views/reports/index.php still requires
 * this same file for its own inline card + full-page modal fallback, so
 * both displays render off one definition and can never drift apart.
 *
 * Expects: $type (string), $reportData (array), $instanceId (string,
 * 'inline' or 'modal'), $dateFrom/$dateTo (strings, only used to build
 * the Export CSV link).
 */

/**
 * Echoes the table for one report type against $reportData. Shared by
 * the inline card and the modal so the two displays can never drift out
 * of sync with each other. $instanceId keeps the two copies (inline card
 * + Recently Generated modal) from colliding when both are on the page
 * at once - initPaginatedTables() (see the script block below) looks for
 * the .paginated-table wrapper and paginates each one independently.
 */
function renderReportTable(string $type, array $reportData, string $instanceId, string $dateFrom = '', string $dateTo = ''): void
{
    ?>
    <div class="paginated-table" id="rt_<?= htmlspecialchars($instanceId) ?>" data-page-size="10">
    <?php if ($instanceId === 'modal'): ?>
    <div class="no-print" style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:12px;">
        <a class="btn btn-secondary btn-sm"
           href="index.php?module=reports&action=export&type=<?= urlencode($type) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
            Export CSV
        </a>
        <button type="button" class="btn btn-secondary btn-sm" onclick="reportPrint('<?= htmlspecialchars($instanceId) ?>')">Print / Save as PDF</button>
    </div>
    <?php endif; ?>
    <h2 class="print-only" style="margin:0 0 12px;"><?= htmlspecialchars(Report::typeLabel($type)) ?></h2>
    <?php if ($type === 'stock_summary'): ?>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Total Stock</th>
                    <th>By Location</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportData['items'])): ?>
                    <tr class="empty-row"><td colspan="4">No products in the catalog.</td></tr>
                <?php else: ?>
                    <?php foreach ($reportData['items'] as $i): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($i['model']) ?></strong></td>
                            <td><?= htmlspecialchars($i['category_name'] ?? '—') ?></td>
                            <td><?= (int) $i['total_quantity'] ?></td>
                            <td>
                                <?php $rows = $reportData['breakdown'][(int) $i['item_id']] ?? []; ?>
                                <?php if (empty($rows)): ?>
                                    —
                                <?php else: ?>
                                    <?php foreach ($rows as $b): ?>
                                        <span style="display:inline-block;background:#EEF0FE;color:#4C5FD5;border-radius:6px;padding:2px 8px;font-size:12px;margin-right:4px;"><?= htmlspecialchars($b['location_name']) ?>: <?= (int) $b['quantity'] ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php elseif ($type === 'usage_report'): ?>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Units Used (Stock Out)</th>
                    <th>Stock-Out Events</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportData['usage'])): ?>
                    <tr class="empty-row"><td colspan="3">No stock-out activity in this range.</td></tr>
                <?php else: ?>
                    <?php foreach ($reportData['usage'] as $u): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['model'] ?? 'Unknown product') ?></strong></td>
                            <td><?= (int) $u['total_used'] ?></td>
                            <td><?= (int) $u['movement_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php elseif ($type === 'low_stock'): ?>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Current Stock</th>
                    <th>Avg. Daily Stock Out</th>
                    <th>Predicted Days Left</th>
                    <th>Reorder Point</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportData['alerts'])): ?>
                    <tr class="empty-row"><td colspan="6">No low-stock alerts right now.</td></tr>
                <?php else: ?>
                    <?php foreach ($reportData['alerts'] as $a): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($a['model']) ?></strong></td>
                            <td><?= (int) $a['current_stock'] ?></td>
                            <td><?= htmlspecialchars((string) $a['avg_daily_stock_outs']) ?></td>
                            <td><?= $a['predicted_days'] !== null ? round((float) $a['predicted_days'], 1) : '—' ?></td>
                            <td><?= $a['reorder_point'] ?? '—' ?></td>
                            <td>
                                <?php if ($a['status'] === 'actual'): ?>
                                    <span class="badge badge-danger">Out Now</span>
                                <?php else: ?>
                                    <span class="badge badge-transfer">Reorder Now</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php elseif ($type === 'transaction_log'): ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Location</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportData['transactions'])): ?>
                    <tr class="empty-row"><td colspan="6">No transactions in this range.</td></tr>
                <?php else: ?>
                    <?php foreach ($reportData['transactions'] as $t): ?>
                        <tr>
                            <td><?= format_datetime($t['created_at']) ?></td>
                            <td><strong><?= htmlspecialchars($t['model'] ?? 'Unknown product') ?></strong></td>
                            <td><span class="badge badge-<?= htmlspecialchars($t['transaction_type']) ?>"><?= Transaction::movementLabel($t['transaction_type']) ?></span></td>
                            <td><?= (int) $t['quantity'] ?><?= $t['line_count'] > 1 ? ' (' . (int) $t['total_quantity'] . ' total across ' . (int) $t['line_count'] . ' items)' : '' ?></td>
                            <td><?= htmlspecialchars($t['location_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($t['technician_name'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <div class="pagination-bar report-table-pager" style="margin-top:12px;"></div>
    </div>
    <?php
}
