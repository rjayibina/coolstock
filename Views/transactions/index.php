<?php
/**
 * Views/transactions/index.php
 * Expects: $transactions (array), $items (array, for the filter dropdown), $error (string|null)
 */
require_once __DIR__ . '/../../Models/Transaction.php';
$status = $_GET['status'] ?? null;
$currentItem = $_GET['item_id'] ?? '';
$currentType = $_GET['type'] ?? '';
$pageTitle = 'Transactions';
$activeSection = 'inventory';
$activeSubNav = 'transactions';
$count = count($transactions);
require __DIR__ . '/../partials/header.php';
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Transactions</h1>
                <span class="page-title-count"><?= $count ?> <?= $count === 1 ? 'record' : 'records' ?></span>
            </div>
            <div class="header-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('filterPanel').classList.toggle('open')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filter
                </button>
                <a href="index.php?module=transactions&action=create" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Log Transaction
                </a>
            </div>
        </div>

        <div id="filterPanel" class="filter-panel <?= ($currentItem !== '' || $currentType !== '') ? 'open' : '' ?>">
            <form method="GET" action="index.php" class="filter-form">
                <input type="hidden" name="module" value="transactions">
                <div>
                    <label>Product</label>
                    <select name="item_id" onchange="this.form.submit()">
                        <option value="">All Products</option>
                        <?php foreach ($items as $it): ?>
                            <option value="<?= $it['item_id'] ?>" <?= ($currentItem == $it['item_id']) ? 'selected' : '' ?>><?= htmlspecialchars($it['item_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Type</label>
                    <select name="type" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <?php foreach (Transaction::TYPES as $type): ?>
                            <option value="<?= $type ?>" <?= $currentType === $type ? 'selected' : '' ?>><?= Transaction::typeLabel($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($currentItem !== '' || $currentType !== ''): ?>
                    <a href="index.php?module=transactions&action=index" class="btn btn-secondary btn-sm" style="align-self:flex-end;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($status === 'created'): ?>
            <div class="alert alert-success">Transaction logged and stock updated.</div>
        <?php elseif ($status === 'deleted'): ?>
            <div class="alert alert-success">Transaction deleted and stock reversed.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="table-card">
            <table id="transactionTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Technician</th>
                        <th>Date</th>
                        <th style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr class="empty-row">
                            <td colspan="6">No transactions match these filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['item_name'] ?? 'Unknown product') ?></strong></td>
                                <td><span class="badge badge-<?= htmlspecialchars($t['transaction_type']) ?>"><?= Transaction::typeLabel($t['transaction_type']) ?></span></td>
                                <td class="cell-id"><?= (int) $t['quantity'] ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($t['technician_name'] ?? '—') ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($t['created_at']) ?></td>
                                <td class="actions">
                                    <a href="index.php?module=transactions&action=delete&id=<?= $t['transaction_id'] ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this transaction? Its stock effect will be reversed.');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
