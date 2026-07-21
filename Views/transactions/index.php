<?php
/**
 * Views/transactions/index.php
 * Expects: $transactions (array), $items (array, for the filter dropdown),
 *          $pagination (array: page, perPage, totalCount, totalPages), $error (string|null)
 */
require_once __DIR__ . '/../../Models/Transaction.php';
$status = $_GET['status'] ?? null;
$currentItem = $_GET['item_id'] ?? '';
$currentType = $_GET['type'] ?? '';
$pageTitle = 'Transactions';
$activeSection = 'inventory';
$activeSubNav = 'transactions';
$count = count($transactions);

// Builds a pagination link that keeps the current filters
function transactionPageUrl(int $page): string
{
    global $currentItem, $currentType;
    return "index.php?module=transactions&action=index"
        . "&item_id=" . urlencode($currentItem)
        . "&type=" . urlencode($currentType)
        . "&page=" . $page;
}

require __DIR__ . '/../partials/header.php';
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Transactions</h1>
                <span class="page-title-count"><?= $pagination['totalCount'] ?> <?= $pagination['totalCount'] === 1 ? 'record' : 'records' ?></span>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="transactionSearch" placeholder="Search transactions..." onkeyup="filterTransactions()">
                </div>
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
        <?php elseif ($status === 'requested'): ?>
            <div class="alert alert-success">Item request logged as pending — stock won't be deducted until it's approved.</div>
        <?php elseif ($status === 'approved'): ?>
            <div class="alert alert-success">Request approved and stock deducted.</div>
        <?php elseif ($status === 'approve_insufficient'): ?>
            <div class="alert alert-warning">Can't approve — only <?= (int) ($_GET['available'] ?? 0) ?> in stock, which isn't enough to cover this request.</div>
        <?php elseif ($status === 'approve_invalid'): ?>
            <div class="alert alert-warning">That request has already been handled or doesn't exist.</div>
        <?php elseif ($status === 'deleted'): ?>
            <div class="alert alert-success">Transaction deleted and stock reversed.</div>
        <?php elseif ($status === 'auto_locked'): ?>
            <div class="alert alert-warning">That transaction was generated automatically by the system and can't be deleted from here.</div>
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
                        <th>Notes</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr class="empty-row">
                            <td colspan="8">No transactions match these filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['item_name'] ?? 'Unknown product') ?></strong></td>
                                <td><span class="badge badge-<?= htmlspecialchars($t['transaction_type']) ?>"><?= Transaction::typeLabel($t['transaction_type']) ?></span></td>
                                <td class="cell-id"><?= (int) $t['quantity'] ?></td>
                                <td class="cell-muted">
                                    <?php if ($t['source'] === 'auto'): ?>
                                        <span style="font-style:italic;">System</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($t['technician_name'] ?? '—') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-muted" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($t['notes'] ?? '') ?>"><?= htmlspecialchars($t['notes'] ?: '—') ?></td>
                                <td>
                                    <?php if ($t['status'] === 'pending'): ?>
                                        <span class="badge" style="background:var(--warning-bg);color:var(--warning);">Pending</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--success-bg);color:var(--success);">Completed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-muted"><?= htmlspecialchars($t['created_at']) ?></td>
                                <td class="actions">
                                    <?php if ($t['status'] === 'pending' && $t['transaction_type'] === 'item_request'): ?>
                                        <a href="index.php?module=transactions&action=approve&id=<?= $t['transaction_id'] ?>"
                                           class="btn btn-sm" style="background:var(--success-bg);color:var(--success);"
                                           onclick="return confirm('Approve this request? Stock will be deducted now.');">Approve</a>
                                    <?php else: ?>
                                        <span class="cell-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination['totalCount'] > 0): ?>
            <?php
            $startRow = ($pagination['page'] - 1) * $pagination['perPage'] + 1;
            $endRow = min($pagination['page'] * $pagination['perPage'], $pagination['totalCount']);
            ?>
            <div class="pagination-bar">
                <span>Showing <?= $startRow ?>–<?= $endRow ?> of <?= $pagination['totalCount'] ?> transactions</span>
                <div class="pagination-controls">
                    <a href="<?= transactionPageUrl(max(1, $pagination['page'] - 1)) ?>" class="page-btn <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">&lsaquo; Prev</a>
                    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                        <a href="<?= transactionPageUrl($p) ?>" class="page-btn <?= $p === $pagination['page'] ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="<?= transactionPageUrl(min($pagination['totalPages'], $pagination['page'] + 1)) ?>" class="page-btn <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">Next &rsaquo;</a>
                </div>
            </div>
        <?php endif; ?>

        <script>
        function filterTransactions() {
            const q = document.getElementById('transactionSearch').value.toLowerCase();
            document.querySelectorAll('#transactionTable tbody tr').forEach(row => {
                if (row.classList.contains('empty-row')) return;
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
