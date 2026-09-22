<?php
/**
 * Views/reports/index.php
 * Expects: $type (string, '' if none picked), $dateFrom, $dateTo (strings),
 *          $reportData (array|null - shape depends on $type, see
 *          ReportController::computeReport()), $recentReports (array).
 *
 * Two ways to land here with a $type set, two different displays:
 *  - Picking a type from the dropdown above shows the report directly in
 *    the card below the filters (same as before the modal work).
 *  - Clicking a row in "Recently Generated" shows that same report as a
 *    modal popup instead - reportTypeUrl($type, true) is what marks a
 *    link as coming from that table via the ?view=modal query param.
 */
$status = $_GET['status'] ?? null;
$message = $_GET['message'] ?? '';
$pageTitle = 'Reports';
$activeSection = 'reports';
require __DIR__ . '/../partials/header.php';

function reportTypeUrl(string $type, bool $asModal = false): string
{
    $url = "index.php?module=reports&action=index&type=" . urlencode($type);
    return $asModal ? $url . '&view=modal' : $url;
}

// Only a Recently Generated row link sets view=modal - the report-type
// dropdown's own onchange submit never does, so that path is unaffected.
$viaRecent = $type !== '' && (($_GET['view'] ?? '') === 'modal');

/**
 * Echoes the table for one report type against $reportData. Shared by
 * the inline card and the modal so the two displays can never drift out
 * of sync with each other. $instanceId keeps the two copies (inline card
 * + Recently Generated modal) from colliding when both are on the page
 * at once - initPaginatedTables() (see the script block below) looks for
 * the .paginated-table wrapper and paginates each one independently.
 */
function renderReportTable(string $type, array $reportData, string $instanceId): void
{
    ?>
    <div class="paginated-table" id="rt_<?= htmlspecialchars($instanceId) ?>" data-page-size="10">
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

        <div class="table-card reports-table-card" style="padding:20px 24px;margin-bottom:20px;">
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
        <?php else: ?>
            <div class="table-card reports-results">
                <?php renderReportTable($type, $reportData, 'inline'); ?>
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
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentReports)): ?>
                        <tr class="empty-row"><td colspan="4">No reports generated yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentReports as $r): ?>
                            <tr>
                                <td><a href="<?= reportTypeUrl($r['report_type'], true) ?>"><?= htmlspecialchars(Report::typeLabel($r['report_type'])) ?></a></td>
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
                                <td><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($viaRecent): ?>
        <?php
        // Only built when the page was reached by clicking a Recently
        // Generated row (?view=modal). Rendered closed - the deferred
        // setTimeout below opens it after footer.php's shared modal
        // a11y observer has attached, so Escape/focus-trap still work
        // even though this open happens on page load rather than a
        // click inside this page.
        ?>
        <div class="modal-overlay" id="reportModal" onclick="if(event.target===this) closeReportModal()">
            <div class="modal-dialog modal-dialog-lg">
                <div class="modal-header">
                    <h3><?= htmlspecialchars(Report::typeLabel($type)) ?></h3>
                    <button type="button" class="modal-close" onclick="closeReportModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <?php renderReportTable($type, $reportData, 'modal'); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
        function openReportModal() {
            document.getElementById('reportModal')?.classList.add('open');
        }
        function closeReportModal() {
            document.getElementById('reportModal')?.classList.remove('open');
        }
        <?php if ($viaRecent): ?>
        // This page load came from clicking a Recently Generated row, so
        // the report modal opens on its own - deferred so the open still
        // registers as an observed transition (see comment above the
        // modal markup).
        setTimeout(openReportModal, 0);
        <?php endif; ?>
        function openGenerateModal() {
            document.getElementById('generateModal')?.classList.add('open');
        }
        function closeGenerateModal() {
            document.getElementById('generateModal')?.classList.remove('open');
        }

        // Paginates every .paginated-table on the page 10 rows at a time
        // (see renderReportTable() above) - purely client-side since the
        // full result set is already in the rendered HTML. Rows beyond
        // the current page are hidden with display:none rather than
        // removed, so nothing here touches the empty-row placeholder or
        // any other markup.
        function initPaginatedTables() {
            document.querySelectorAll('.paginated-table').forEach(function (wrap) {
                const pageSize = parseInt(wrap.dataset.pageSize, 10) || 10;
                const tbody = wrap.querySelector('tbody');
                const pagerEl = wrap.querySelector('.report-table-pager');
                if (!tbody || !pagerEl) return;

                const rows = Array.from(tbody.querySelectorAll('tr')).filter(function (tr) {
                    return !tr.classList.contains('empty-row');
                });
                if (rows.length <= pageSize) {
                    return;
                }

                let page = 1;
                const totalPages = Math.ceil(rows.length / pageSize);

                function render() {
                    rows.forEach(function (tr, i) {
                        tr.style.display = (i >= (page - 1) * pageSize && i < page * pageSize) ? '' : 'none';
                    });
                    const start = (page - 1) * pageSize + 1;
                    const end = Math.min(page * pageSize, rows.length);
                    let html = '<span>Showing ' + start + '–' + end + ' of ' + rows.length + '</span><div class="pagination-controls">';
                    html += '<button type="button" class="page-btn' + (page <= 1 ? ' disabled' : '') + '" data-page="' + (page - 1) + '">&lsaquo; Prev</button>';
                    for (let p = 1; p <= totalPages; p++) {
                        html += '<button type="button" class="page-btn' + (p === page ? ' active' : '') + '" data-page="' + p + '">' + p + '</button>';
                    }
                    html += '<button type="button" class="page-btn' + (page >= totalPages ? ' disabled' : '') + '" data-page="' + (page + 1) + '">Next &rsaquo;</button></div>';
                    pagerEl.innerHTML = html;
                    pagerEl.querySelectorAll('[data-page]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            const p = parseInt(btn.dataset.page, 10);
                            if (p >= 1 && p <= totalPages) {
                                page = p;
                                render();
                            }
                        });
                    });
                }
                render();
            });
        }
        initPaginatedTables();
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
