<?php
/**
 * Views/dashboard/_warehouse.php
 * Warehouse Staff dashboard body. Also included wholesale by
 * _admin.php, which layers its own org-level sections on top - Admin is
 * a superset of this view, not a different one.
 *
 * Included from Views/dashboard/index.php, which owns the page chrome
 * and supplies: $stats, $ops, $approvalQueue, $productsByCategory,
 * $transactionsByType, $predictedStockouts, $recentTransactions,
 * $typeColors.
 */
$requestsUrl = 'index.php?module=requests&action=index';
$stockAlerts = count($predictedStockouts);
$outNow = 0;
foreach ($predictedStockouts as $__row) {
    if (($__row['status'] ?? '') === 'actual') { $outNow++; }
}
?>
        <?php if ((int) $ops['pending_approvals'] > 0): ?>
            <div class="callout">
                <span class="callout-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </span>
                <div class="callout-body">
                    <div class="callout-title">
                        <?= (int) $ops['pending_approvals'] ?> request<?= (int) $ops['pending_approvals'] === 1 ? '' : 's' ?> waiting on you
                    </div>
                    <div class="callout-text">Technicians can't collect their stock until these are approved or declined.</div>
                </div>
                <a href="<?= $requestsUrl ?>&tab=pending" class="btn btn-primary">Review requests</a>
            </div>
        <?php endif; ?>

        <?php // Action first, catalogue size last - these are ordered by what
              // somebody has to do something about today. ?>
        <div class="stat-grid">
            <a href="<?= $requestsUrl ?>&tab=pending" class="stat-card stat-card-link">
                <div class="stat-label">Awaiting Approval</div>
                <div class="stat-value"><?= (int) $ops['pending_approvals'] ?></div>
            </a>
            <a href="index.php?module=reports&action=index&type=low_stock" class="stat-card stat-card-link">
                <div class="stat-label">Stock Alerts<?= $outNow > 0 ? ' (' . $outNow . ' out now)' : '' ?></div>
                <div class="stat-value"><?= $stockAlerts ?></div>
            </a>
            <a href="<?= $requestsUrl ?>&tab=active" class="stat-card stat-card-link">
                <div class="stat-label">Units Still Out</div>
                <div class="stat-value"><?= (int) $ops['units_out'] ?></div>
            </a>
            <a href="index.php?module=products&action=index" class="stat-card stat-card-link">
                <div class="stat-label">Total Products</div>
                <div class="stat-value"><?= (int) $stats['total_products'] ?></div>
            </a>
        </div>

        <div class="section-head">
            <div class="section-title">Awaiting Approval</div>
            <?php if (!empty($approvalQueue)): ?>
                <a href="<?= $requestsUrl ?>&tab=pending" class="text-link">Open approval queue</a>
            <?php endif; ?>
        </div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Requested By</th>
                        <th>Quantity</th>
                        <th>Requested On</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($approvalQueue)): ?>
                        <tr class="empty-row"><td colspan="5">Nothing is waiting for approval right now.</td></tr>
                    <?php else: ?>
                        <?php foreach ($approvalQueue as $q): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($q['model'] ?? 'Unknown product') ?></strong></td>
                                <td class="cell-muted"><?= htmlspecialchars($q['requested_by_name'] ?? $q['technician_name'] ?? '—') ?></td>
                                <td class="cell-id"><?= (int) $q['quantity'] ?></td>
                                <td class="cell-muted"><?= htmlspecialchars(format_datetime($q['transaction_date'])) ?></td>
                                <td class="cell-muted"><?= htmlspecialchars(trim((string) ($q['notes'] ?? '')) !== '' ? $q['notes'] : '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-head">
            <div class="section-title">Predicted Stockouts</div>
            <?php if (!empty($predictedStockouts)): ?>
                <a href="index.php?module=reports&action=index&type=low_stock" class="text-link">Open full report</a>
            <?php endif; ?>
        </div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Current Stock</th>
                        <th>Avg. Daily Stock-Outs</th>
                        <th>Predicted Stockout</th>
                        <th>Reorder Point</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($predictedStockouts)): ?>
                        <tr class="empty-row"><td colspan="6">No stockout risk detected right now.</td></tr>
                    <?php else: ?>
                        <?php foreach ($predictedStockouts as $row): ?>
                            <?php
                            // predicted_days comes back from round() as a float, so a
                            // strict === 1 comparison never matched and every row read
                            // "days", even at exactly one day.
                            $days = (float) $row['predicted_days'];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['model']) ?></strong></td>
                                <td class="cell-id"><?= (int) $row['current_stock'] ?></td>
                                <td class="cell-muted"><?= htmlspecialchars((string) $row['avg_daily_stock_outs']) ?>/day</td>
                                <td class="cell-muted"><?= $row['status'] === 'actual' ? '—' : htmlspecialchars($row['predicted_days'] . ' day' . ($days == 1 ? '' : 's')) ?></td>
                                <td class="cell-muted"><?= $row['reorder_point'] !== null ? (int) $row['reorder_point'] : '—' ?></td>
                                <td>
                                    <?php if ($row['status'] === 'actual'): ?>
                                        <span class="badge" style="background:var(--danger-bg);color:var(--danger);">Out now</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--warning-bg);color:var(--warning);">Reorder now</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-head">
            <div class="section-title">Recent Stock Movement</div>
            <?php if (!empty($recentTransactions)): ?>
                <a href="index.php?module=transactions&action=index" class="text-link">View all movement</a>
            <?php endif; ?>
        </div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Technician</th>
                        <th>Quantity</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTransactions)): ?>
                        <?php // colspan matches the five headers above - was 4. ?>
                        <tr class="empty-row"><td colspan="5">No transactions logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentTransactions as $t): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['model'] ?? 'Unknown product') ?></strong></td>
                                <td><span class="badge badge-<?= htmlspecialchars($t['transaction_type']) ?>"><?= Transaction::typeLabel($t['transaction_type']) ?></span></td>
                                <td class="cell-muted">
                                    <?php if ($t['source'] === 'auto'): ?>
                                        <span style="font-style:italic;">System</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($t['technician_name'] ?? '—') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-id"><?= (int) $t['quantity'] ?></td>
                                <td class="cell-muted"><?= htmlspecialchars(format_datetime($t['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php // Analysis last. Everything above is something to act on -
              // approvals, reorders, what just moved - so the charts sit
              // below the fold rather than splitting the action items. ?>
        <div class="section-head">
            <div class="section-title">Trends</div>
            <a href="index.php?module=reports&action=index" class="text-link">Open reports</a>
        </div>
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-title">Products by Category</div>
                <?php if (empty($productsByCategory)): ?>
                    <div class="empty-state">No categories yet.</div>
                <?php else: ?>
                    <div class="chart-canvas-wrap">
                        <canvas id="categoryChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chart-card">
                <div class="chart-title">Transactions by Type</div>
                <?php if (empty($transactionsByType)): ?>
                    <div class="empty-state">No transactions yet.</div>
                <?php else: ?>
                    <div class="chart-canvas-wrap">
                        <canvas id="transactionTypeChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php // Charts are staff-only, so their library loads only here. ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
        (function () {
            const categoryLabels = <?= json_encode(array_column($productsByCategory, 'category_name')) ?>;
            const categoryTotals = <?= json_encode(array_map('intval', array_column($productsByCategory, 'total'))) ?>;
            const typeLabels = <?= json_encode(array_map(fn($t) => Transaction::typeLabel($t), array_keys($transactionsByType))) ?>;
            const typeTotals = <?= json_encode(array_values(array_map('intval', $transactionsByType))) ?>;
            const typeColorMap = <?= json_encode($typeColors) ?>;
            const typeKeys = <?= json_encode(array_keys($transactionsByType)) ?>;

            const gridColor = 'rgba(20, 21, 43, 0.06)';
            const tickColor = '#6B6D85';
            const commonScales = {
                x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 12 } } },
                y: { beginAtZero: true, ticks: { precision: 0, color: tickColor, font: { size: 12 } }, grid: { color: gridColor } },
            };
            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: commonScales,
            };

            const categoryCanvas = document.getElementById('categoryChart');
            if (categoryCanvas) {
                new Chart(categoryCanvas, {
                    type: 'bar',
                    data: {
                        labels: categoryLabels,
                        datasets: [{
                            data: categoryTotals,
                            backgroundColor: '#4C5FD5',
                            borderRadius: 5,
                            maxBarThickness: 42,
                        }],
                    },
                    options: commonOptions,
                });
            }

            const typeCanvas = document.getElementById('transactionTypeChart');
            if (typeCanvas) {
                new Chart(typeCanvas, {
                    type: 'bar',
                    data: {
                        labels: typeLabels,
                        datasets: [{
                            data: typeTotals,
                            backgroundColor: typeKeys.map(k => typeColorMap[k] || '#4C5FD5'),
                            borderRadius: 5,
                            maxBarThickness: 42,
                        }],
                    },
                    options: commonOptions,
                });
            }
        })();
        </script>
