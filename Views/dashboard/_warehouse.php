<?php
/**
 * Views/dashboard/_warehouse.php
 * Warehouse Staff dashboard body. Also included wholesale by
 * _admin.php, which layers its own org-level sections on top - Admin is
 * a superset of this view, not a different one.
 *
 * Included from Views/dashboard/index.php, which owns the page chrome
 * and supplies: $stats, $ops, $productsByCategory, $transactionsByType,
 * $dailyVolume, $predictedStockouts, $recentTransactions.
 *
 * The Awaiting Approval table was removed from this dashboard per
 * request - the stat tile above and the "Review requests" callout still
 * point to the approval queue at Item Requests > Pending.
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

        <?php // Analysis first, per the latest request - trends (chart-grid)
              // now sit above Awaiting Approval instead of below the fold. ?>
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
                <div class="chart-title">Transaction Volume (Last 14 Days)</div>
                <?php if (empty($dailyVolume) || array_sum($dailyVolume) === 0): ?>
                    <div class="empty-state">No transactions in the last 14 days.</div>
                <?php else: ?>
                    <div class="chart-canvas-wrap">
                        <canvas id="transactionVolumeChart"></canvas>
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
            const volumeLabels = <?= json_encode(array_map(
                fn($d) => date('M j', strtotime($d)),
                array_keys($dailyVolume)
            )) ?>;
            const volumeTotals = <?= json_encode(array_values(array_map('intval', $dailyVolume))) ?>;

            const gridColor = 'rgba(20, 21, 43, 0.06)';
            const tickColor = '#6B6D85';

            // Cycled across as many category slices as exist - the fixed
            // $typeColors map doesn't apply here since categories are
            // user-defined and open-ended, unlike the seven transaction
            // types it was built for.
            const categoryPalette = ['#4C5FD5', '#16A34A', '#D97706', '#9333EA', '#0369A1', '#BE185D', '#DC2626', '#0D9488', '#7C3AED', '#65A30D'];

            const categoryCanvas = document.getElementById('categoryChart');
            if (categoryCanvas) {
                new Chart(categoryCanvas, {
                    type: 'pie',
                    data: {
                        labels: categoryLabels,
                        datasets: [{
                            data: categoryTotals,
                            backgroundColor: categoryLabels.map((_, i) => categoryPalette[i % categoryPalette.length]),
                            borderColor: '#FFFFFF',
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right', labels: { color: tickColor, font: { size: 12 }, boxWidth: 12 } },
                        },
                    },
                });
            }

            const volumeCanvas = document.getElementById('transactionVolumeChart');
            if (volumeCanvas) {
                new Chart(volumeCanvas, {
                    type: 'line',
                    data: {
                        labels: volumeLabels,
                        datasets: [{
                            data: volumeTotals,
                            borderColor: '#4C5FD5',
                            backgroundColor: 'rgba(76, 95, 213, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 3,
                            pointBackgroundColor: '#4C5FD5',
                            pointBorderColor: '#FFFFFF',
                            pointBorderWidth: 1.5,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 11 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 7 } },
                            y: { beginAtZero: true, ticks: { precision: 0, color: tickColor, font: { size: 12 } }, grid: { color: gridColor } },
                        },
                    },
                });
            }
        })();
        </script>

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
