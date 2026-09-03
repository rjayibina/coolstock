<?php
/**
 * Views/dashboard/index.php
 * Expects: $stats, $recentTransactions,
 *          $productsByCategory, $transactionsByType, $dbError
 */
require_once __DIR__ . '/../../Models/Transaction.php';
$pageTitle = 'Dashboard';
$activeSection = 'dashboard';
require __DIR__ . '/../partials/header.php';

$maxCategoryCount = max(array_column($productsByCategory, 'total') ?: [0, 1]);
$maxTypeCount = max(array_values($transactionsByType) ?: [0, 1]);
$maxCategoryCount = max($maxCategoryCount, 1);
$maxTypeCount = max($maxTypeCount, 1);

$typeColors = [
    'return' => '#16A34A',
    'stock_in' => '#16A34A',
    'item_request' => '#9333EA',
    'borrow' => '#D97706',
    'stock_out' => '#4C5FD5',
    'delivery' => '#0369A1',
    'transfer' => '#BE185D',
];
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Dashboard</h1>
            </div>
        </div>

        <?php if ($dbError): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($dbError) ?></div>
        <?php endif; ?>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-label">Total Products</div>
                <div class="stat-value"><?= $stats['total_products'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Categories</div>
                <div class="stat-value"><?= $stats['total_categories'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value"><?= $stats['total_transactions'] ?></div>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-title">Products by Category</div>
                <?php if (empty($productsByCategory)): ?>
                    <div class="empty-state">No categories yet.</div>
                <?php else: ?>
                    <div class="bar-chart">
                        <?php foreach ($productsByCategory as $row): ?>
                            <div class="bar-row">
                                <div class="bar-label"><?= htmlspecialchars($row['category_name']) ?></div>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width: <?= max((int)$row['total'] / $maxCategoryCount * 100, $row['total'] > 0 ? 4 : 0) ?>%;"></div>
                                </div>
                                <div class="bar-value"><?= (int) $row['total'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chart-card">
                <div class="chart-title">Transactions by Type</div>
                <div class="bar-chart">
                    <?php foreach ($transactionsByType as $type => $total): ?>
                        <div class="bar-row">
                            <div class="bar-label"><?= Transaction::typeLabel($type) ?></div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: <?= max($total / $maxTypeCount * 100, $total > 0 ? 4 : 0) ?>%; background: <?= $typeColors[$type] ?>;"></div>
                            </div>
                            <div class="bar-value"><?= $total ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="section-title">Predicted Stockouts</div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Confidence</th>
                        <th>Past Stockouts</th>
                        <th>Frequency (per 30d)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($predictedStockouts)): ?>
                        <tr class="empty-row"><td colspan="6">No stockout risk detected right now.</td></tr>
                    <?php else: ?>
                        <?php foreach ($predictedStockouts as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['model']) ?></strong></td>
                                <td class="cell-muted"><?= htmlspecialchars($row['location_name']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'actual'): ?>
                                        <span class="badge" style="background:var(--danger-bg);color:var(--danger);">Out now</span>
                                    <?php elseif ($row['days_until'] <= 0): ?>
                                        <span class="badge" style="background:var(--danger-bg);color:var(--danger);">Overdue by <?= abs($row['days_until']) ?>d</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--warning-bg);color:var(--warning);">In ~<?= $row['days_until'] ?>d (<?= htmlspecialchars($row['predicted_date']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-muted"><?= $row['confidence'] ? htmlspecialchars($row['confidence']) : '—' ?></td>
                                <td class="cell-id"><?= (int) $row['stockout_count'] ?></td>
                                <td class="cell-id"><?= $row['stockout_frequency'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-title">Recent Transactions</div>
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
                        <tr class="empty-row"><td colspan="4">No transactions logged yet.</td></tr>
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
<?php require __DIR__ . '/../partials/footer.php'; ?>
