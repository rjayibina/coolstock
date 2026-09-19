<?php
/**
 * Views/reports/index.php
 * Expects: $type (string, '' if none picked), $dateFrom, $dateTo (strings),
 *          $reportData (array|null - shape depends on $type, see
 *          ReportController::computeReport()), $recentReports (array).
 */
$status = $_GET['status'] ?? null;
$message = $_GET['message'] ?? '';
$pageTitle = 'Reports';
$activeSection = 'reports';
require __DIR__ . '/../partials/header.php';

function reportTypeUrl(string $type): string
{
    return "index.php?module=reports&action=index&type=" . urlencode($type);
}
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Reports</h1>
                <span class="page-title-count"><?= count($recentReports) ?> recently generated</span>
            </div>
        </div>

        <?php if ($status === 'generated'): ?>
            <div class="alert alert-success">Report generated and logged.</div>
        <?php elseif ($status === 'error'): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($message !== '' ? $message : 'Something went wrong. Please try again.') ?></div>
        <?php endif; ?>

        <div class="table-card" style="padding:20px 24px;margin-bottom:20px;">
            <form method="GET" action="index.php" class="inline-form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="index">
                <div class="inline-form-grow">
                    <label for="reportType">Report Type</label>
                    <select name="type" id="reportType" onchange="this.form.submit()">
                        <option value="">Choose a report...</option>
                        <?php foreach (Report::TYPES as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (in_array($type, ['usage_report', 'transaction_log'], true)): ?>
                <div>
                    <label for="dateFrom">From</label>
                    <input type="date" name="date_from" id="dateFrom" value="<?= htmlspecialchars($dateFrom) ?>"
                           style="padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:inherit;">
                </div>
                <div>
                    <label for="dateTo">To</label>
                    <input type="date" name="date_to" id="dateTo" value="<?= htmlspecialchars($dateTo) ?>"
                           style="padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:inherit;">
                </div>
                <div>
                    <button type="submit" class="btn btn-secondary">Apply</button>
                </div>
                <?php endif; ?>
                <?php if ($type !== ''): ?>
                <div class="inline-form-push">
                    <button type="button" class="btn btn-primary" onclick="openGenerateModal()">Log This Report</button>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($type === ''): ?>
            <div class="table-card" style="padding:40px 24px;text-align:center;color:#6B7280;">
                Pick a report type above to view it.
            </div>
        <?php elseif ($type === 'stock_summary'): ?>
            <div class="table-card">
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
            </div>
        <?php elseif ($type === 'usage_report'): ?>
            <div class="table-card">
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
            </div>
        <?php elseif ($type === 'low_stock'): ?>
            <div class="table-card">
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
                                            <span class="badge badge-stock_out">Out Now</span>
                                        <?php else: ?>
                                            <span class="badge badge-transfer">Reorder Now</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($type === 'transaction_log'): ?>
            <div class="table-card">
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
            </div>
        <?php endif; ?>

        <div class="table-card" style="margin-top:24px;">
            <div style="padding:16px 20px 0;font-weight:600;font-size:14px;">Recently Generated</div>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Range</th>
                        <th>Generated By</th>
                        <th>Generated At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentReports)): ?>
                        <tr class="empty-row"><td colspan="4">No reports generated yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentReports as $r): ?>
                            <tr>
                                <td><a href="<?= reportTypeUrl($r['report_type']) ?>"><?= htmlspecialchars(Report::typeLabel($r['report_type'])) ?></a></td>
                                <td>
                                    <?php
                                    // Report types that aren't date-scoped store no range -
                                    // say so rather than printing "— to —".
                                    if (empty($r['date_from']) && empty($r['date_to'])) {
                                        echo 'All dates';
                                    } else {
                                        echo htmlspecialchars(
                                            format_datetime($r['date_from']) . ' to ' . format_datetime($r['date_to'])
                                        );
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($r['generated_by'] ?? '—') ?></td>
                                <td><?= format_datetime($r['generated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($type !== ''): ?>
        <div class="modal-overlay" id="generateModal" onclick="if(event.target===this) closeGenerateModal()">
            <div class="modal-dialog modal-dialog-sm">
                <div class="modal-header">
                    <h3>Log This Report</h3>
                    <button type="button" class="modal-close" onclick="closeGenerateModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php?module=reports&action=generate">
                        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                        <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                        <input type="hidden" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                        <p style="margin-top:0;color:var(--text-muted);font-size:13px;">
                            This logs an audit entry (report type, date range, who ran it) to the reports history below. It doesn't change any stock or transaction data.
                        </p>
                        <label for="reportNotes">Notes <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <textarea name="notes" id="reportNotes" rows="3"></textarea>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Log Report</button>
                            <button type="button" class="btn btn-secondary" onclick="closeGenerateModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <script>
        function openGenerateModal() {
            document.getElementById('generateModal')?.classList.add('open');
        }
        function closeGenerateModal() {
            document.getElementById('generateModal')?.classList.remove('open');
        }
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
