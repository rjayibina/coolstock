<?php
/**
 * Views/dashboard/index.php
 * Expects: $stats, $lowStockItems, $recentTransactions,
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
    'stock_in' => '#16A34A',
    'return' => '#16A34A',
    'stock_out' => '#4C5FD5',
    'item_request' => '#9333EA',
    'borrow' => '#D97706',
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
                <div class="stat-label">Low Stock Alerts</div>
                <div class="stat-value <?= $stats['low_stock'] > 0 ? 'warn' : '' ?>"><?= $stats['low_stock'] ?></div>
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

        <div class="section-title">Low Stock Products</div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Serial No.</th>
                        <th>On Hand</th>
                        <th>Minimum</th>
                        <th>Category</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lowStockItems)): ?>
                        <tr class="empty-row"><td colspan="3">Nothing is low on stock right now.</td></tr>
                    <?php else: ?>
                        <?php foreach ($lowStockItems as $it): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($it['item_name']) ?></strong></td>
                                <td class="cell-id"><?= htmlspecialchars($it['serial_number'] ?? '—') ?></td>
                                <td class="cell-id"><?= (int) $it['quantity_on_hand'] ?></td>
                                <td class="cell-id"><?= (int) $it['minimum_stock_level'] ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($it['category_name'] ?? 'Uncategorized') ?></td>
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
                                <td><strong><?= htmlspecialchars($t['item_name'] ?? 'Unknown product') ?></strong></td>
                                <td><span class="badge badge-<?= htmlspecialchars($t['transaction_type']) ?>"><?= Transaction::typeLabel($t['transaction_type']) ?></span></td>
                                <td class="cell-muted">
                                    <?php if ($t['source'] === 'auto'): ?>
                                        <span style="font-style:italic;">System</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($t['technician_name'] ?? '—') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-id"><?= (int) $t['quantity'] ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($t['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
