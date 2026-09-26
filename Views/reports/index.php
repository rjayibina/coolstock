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
 *    modal popup ONLY - reportTypeUrl($type, true) marks a link as coming
 *    from that table via the ?view=modal query param. $type/$dateFrom/
 *    $dateTo are still the request's real values (needed to compute the
 *    report for the modal), but $viaRecent gates every OTHER use of them
 *    below (the dropdown's selected option, the inline card) so clicking
 *    a Recently Generated row can never change what the dropdown/inline
 *    card above are showing - only the modal.
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

// renderReportTable() is shared with ReportController::modal() (the
// Recently Generated row's AJAX endpoint below), so it lives in its own
// file rather than being defined inline here - see _report_table.php.
require __DIR__ . '/_report_table.php';
?>
        <div class="page-header no-print">
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

        <div class="table-card reports-table-card no-print" style="padding:20px 24px;margin-bottom:20px;">
            <form method="GET" action="index.php" class="inline-form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="index">
                <div class="inline-form-grow">
                    <label for="reportType">Report Type</label>
                    <?php // Never reflects a Recently Generated click ($viaRecent) - that
                          // only drives the modal below, so this dropdown (and the inline
                          // card) keep showing whatever was actually chosen from here. ?>
                    <select name="type" id="reportType" onchange="this.form.submit()">
                        <option value="">Choose a report...</option>
                        <?php foreach (Report::TYPES as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= (!$viaRecent && $type === $key) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!$viaRecent && in_array($type, ['usage_report', 'transaction_log'], true)): ?>
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
                <?php if ($type !== '' && !$viaRecent): ?>
                <div class="inline-form-push">
                    <button type="button" class="btn btn-primary" onclick="openGenerateModal()">Log This Report</button>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($type === '' || $viaRecent): ?>
            <div class="table-card" style="padding:40px 24px;text-align:center;color:#6B7280;">
                Pick a report type above to view it.
            </div>
        <?php else: ?>
            <div class="table-card reports-results" id="reportPrintArea">
                <?php renderReportTable($type, $reportData, 'inline', $dateFrom, $dateTo); ?>
            </div>
        <?php endif; ?>

        <div class="table-card no-print" style="margin-top:24px;">
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
                                <td>
                                    <?php
                                    // The href is kept as a real, working link (a full-page
                                    // ?view=modal load) purely as a fallback for when JS fails
                                    // or is disabled - openReportModalAjax() below intercepts
                                    // the click and opens this same report as an AJAX fragment
                                    // instead, so the dropdown/inline card above never get
                                    // reset back to "Choose a report..." by a real navigation.
                                    ?>
                                    <a href="<?= reportTypeUrl($r['report_type'], true) ?>"
                                       onclick="return openReportModalAjax(event, <?= htmlspecialchars(json_encode($r['report_type']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['date_from'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['date_to'] ?? ''), ENT_QUOTES) ?>)"><?= htmlspecialchars(Report::typeLabel($r['report_type'])) ?></a>
                                </td>
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

        <?php
        // Always rendered (not just when $viaRecent) so openReportModalAjax()
        // below always has a container to fill and open, without a page
        // navigation. $viaRecent still fills it server-side too - the
        // no-JS/fetch-failure fallback path (a real ?view=modal load)
        // lands here with content already in place, same as before.
        ?>
        <div class="modal-overlay" id="reportModal" onclick="if(event.target===this) closeReportModal()">
            <div class="modal-dialog modal-dialog-lg">
                <div class="modal-header no-print">
                    <h3 id="reportModalTitle"><?= $viaRecent ? htmlspecialchars(Report::typeLabel($type)) : '' ?></h3>
                    <button type="button" class="modal-close" onclick="closeReportModal()">&times;</button>
                </div>
                <div class="modal-body" id="reportModalBody">
                    <?php if ($viaRecent): ?>
                        <?php renderReportTable($type, $reportData, 'modal', $dateFrom, $dateTo); ?>
                    <?php endif; ?>
                </div>
            </div>
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
        // Print/Save as PDF, called from inside renderReportTable()'s own
        // toolbar (so it's available in both the inline card and the
        // Recently Generated modal). Printing the inline card is a plain
        // window.print() - the existing @media print rules (.no-print,
        // .print-only) already handle it. Printing the MODAL needs one
        // more thing: the modal is normally only visible via its own
        // .open class/CSS, which print stylesheets don't get to rely on
        // the same way, so a body class drives a dedicated print rule
        // (see assets/css/style.css) that hides everything else and lays
        // the modal out as plain content instead.
        function reportPrint(instanceId) {
            if (instanceId === 'modal') {
                document.body.classList.add('printing-report-modal');
                // afterprint fires once the browser's print dialog/preview
                // closes, whether printed or cancelled - more reliable
                // than removing the class right after print() returns,
                // since some browsers don't block on that call.
                window.addEventListener('afterprint', function cleanup() {
                    document.body.classList.remove('printing-report-modal');
                    window.removeEventListener('afterprint', cleanup);
                });
                window.print();
            } else {
                window.print();
            }
        }

        function openReportModal() {
            document.getElementById('reportModal')?.classList.add('open');
        }
        function closeReportModal() {
            document.getElementById('reportModal')?.classList.remove('open');
        }
        <?php if ($viaRecent): ?>
        // This page load came from a fetch()-failure/no-JS fallback (a
        // real ?view=modal navigation - see openReportModalAjax() below),
        // so the report modal opens on its own - deferred so the open
        // still registers as an observed transition (see comment above
        // the modal markup).
        setTimeout(openReportModal, 0);
        <?php endif; ?>

        // Fetches one report type's table as a JSON fragment and swaps it
        // into #reportModal without navigating, so clicking a Recently
        // Generated row never resets the report-type dropdown/inline card
        // above (a real page load re-reads $_GET['type'] from scratch and
        // was clearing them back to "Choose a report..."). Falls back to
        // the row's real href (a full ?view=modal page load) if the fetch
        // fails for any reason - same progressive-enhancement pattern as
        // ajaxPaginate() in partials/footer.php.
        function openReportModalAjax(event, type, dateFrom, dateTo) {
            event.preventDefault();
            const fallbackHref = event.currentTarget.getAttribute('href');
            const url = 'index.php?module=reports&action=modal'
                + '&type=' + encodeURIComponent(type)
                + '&date_from=' + encodeURIComponent(dateFrom || '')
                + '&date_to=' + encodeURIComponent(dateTo || '');

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    if (!res.ok) { throw new Error('Report fetch failed'); }
                    return res.json();
                })
                .then(function (json) {
                    document.getElementById('reportModalTitle').textContent = json.title;
                    const body = document.getElementById('reportModalBody');
                    body.innerHTML = json.html;
                    initPaginatedTable(body.querySelector('.paginated-table'));
                    openReportModal();
                })
                .catch(function () {
                    window.location.href = fallbackHref;
                });

            return false;
        }
        function openGenerateModal() {
            document.getElementById('generateModal')?.classList.add('open');
        }
        function closeGenerateModal() {
            document.getElementById('generateModal')?.classList.remove('open');
        }

        // Paginates one .paginated-table 10 rows at a time (see
        // renderReportTable() above) - purely client-side since the full
        // result set is already in the rendered HTML. Rows beyond the
        // current page are hidden with display:none rather than removed,
        // so nothing here touches the empty-row placeholder or any other
        // markup. Called once per table on page load (initPaginatedTables()
        // below) and again on its own for the modal's table each time
        // openReportModalAjax() swaps in fresh HTML.
        function initPaginatedTable(wrap) {
            if (!wrap) return;
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
        }

        function initPaginatedTables() {
            document.querySelectorAll('.paginated-table').forEach(function (wrap) {
                initPaginatedTable(wrap);
            });
        }
        initPaginatedTables();
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
