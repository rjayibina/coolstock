<?php
/**
 * Views/transactions/index.php
 * Expects: $transactions (array), $items (array, for the filter dropdown),
 *          $pagination (array: page, perPage, totalCount, totalPages), $error (string|null)
 */
require_once __DIR__ . '/../../Models/Transaction.php';
$status = $_GET['status'] ?? null;
$bulkCount = (int) ($_GET['count'] ?? 0);
$bulkSkipped = (int) ($_GET['skipped'] ?? 0);
$currentItem = $_GET['item_id'] ?? '';
$currentType = $_GET['type'] ?? '';
$currentDateFrom = $_GET['date_from'] ?? '';
$currentDateTo = $_GET['date_to'] ?? '';
$currentSort = $_GET['sort'] ?? 'date_desc';
$pageTitle = 'Transactions';
$activeSection = 'inventory';
$activeSubNav = 'transactions';

// Builds a pagination/sort link that keeps the current filters
function transactionPageUrl(int $page): string
{
    global $currentItem, $currentType, $currentDateFrom, $currentDateTo, $currentSort;
    return "index.php?module=transactions&action=index"
        . "&item_id=" . urlencode($currentItem)
        . "&type=" . urlencode($currentType)
        . "&date_from=" . urlencode($currentDateFrom)
        . "&date_to=" . urlencode($currentDateTo)
        . "&sort=" . urlencode($currentSort)
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

        <div id="filterPanel" class="filter-panel <?= ($currentItem !== '' || $currentType !== '' || $currentDateFrom !== '' || $currentDateTo !== '') ? 'open' : '' ?>">
            <form method="GET" action="index.php" class="filter-form">
                <input type="hidden" name="module" value="transactions">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($currentSort) ?>">
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
                <div>
                    <label>From</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($currentDateFrom) ?>" onchange="this.form.submit()"
                           style="padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:inherit;">
                </div>
                <div>
                    <label>To</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($currentDateTo) ?>" onchange="this.form.submit()"
                           style="padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:inherit;">
                </div>
                <?php if ($currentItem !== '' || $currentType !== '' || $currentDateFrom !== '' || $currentDateTo !== ''): ?>
                    <a href="index.php?module=transactions&action=index&sort=<?= urlencode($currentSort) ?>" class="btn btn-secondary btn-sm" style="align-self:flex-end;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($status === 'created'): ?>
            <div class="alert alert-success">Transaction logged and stock updated.</div>
        <?php elseif ($status === 'requested'): ?>
            <div class="alert alert-success">Item request logged as pending — stock won't be deducted until it's approved.</div>
        <?php elseif ($status === 'approved'): ?>
            <div class="alert alert-success">Request approved and stock deducted.</div>
        <?php elseif ($status === 'declined'): ?>
            <div class="alert alert-success">Request declined. No stock was affected.</div>
        <?php elseif ($status === 'approve_insufficient'): ?>
            <div class="alert alert-warning">Can't approve — only <?= (int) ($_GET['available'] ?? 0) ?> in stock, which isn't enough to cover this request.</div>
        <?php elseif ($status === 'approve_invalid'): ?>
            <div class="alert alert-warning">That request has already been handled or doesn't exist.</div>
        <?php elseif ($status === 'deleted'): ?>
            <div class="alert alert-success">Transaction deleted and stock reversed.</div>
        <?php elseif ($status === 'bulk_deleted'): ?>
            <div class="alert alert-success"><?= $bulkCount ?> transaction<?= $bulkCount === 1 ? '' : 's' ?> deleted.</div>
        <?php elseif ($status === 'bulk_partial'): ?>
            <div class="alert alert-warning"><?= $bulkCount ?> deleted, <?= $bulkSkipped ?> skipped (system-generated entries can't be deleted here).</div>
        <?php elseif ($status === 'bulk_approved'): ?>
            <div class="alert alert-success"><?= $bulkCount ?> request<?= $bulkCount === 1 ? '' : 's' ?> approved.</div>
        <?php elseif ($status === 'bulk_approve_partial'): ?>
            <div class="alert alert-warning"><?= $bulkCount ?> approved, <?= $bulkSkipped ?> skipped (not a pending request, or not enough stock).</div>
        <?php elseif ($status === 'auto_locked'): ?>
            <div class="alert alert-warning">That transaction was generated automatically by the system and can't be deleted from here.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="sort-bar">
            <label for="sortSelect">Sort by</label>
            <select id="sortSelect" onchange='location.href = <?= json_encode(transactionPageUrl(1)) ?>.replace(/sort=[^&]*/, "sort=" + this.value)'>
                <option value="date_desc" <?= $currentSort === 'date_desc' ? 'selected' : '' ?>>Newest first</option>
                <option value="date_asc" <?= $currentSort === 'date_asc' ? 'selected' : '' ?>>Oldest first</option>
                <option value="quantity_desc" <?= $currentSort === 'quantity_desc' ? 'selected' : '' ?>>Quantity: high to low</option>
                <option value="quantity_asc" <?= $currentSort === 'quantity_asc' ? 'selected' : '' ?>>Quantity: low to high</option>
                <option value="product_asc" <?= $currentSort === 'product_asc' ? 'selected' : '' ?>>Product: A–Z</option>
                <option value="product_desc" <?= $currentSort === 'product_desc' ? 'selected' : '' ?>>Product: Z–A</option>
            </select>
        </div>

        <form method="POST" id="bulkForm">
            <div id="bulkBar" class="bulk-bar">
                <span><strong id="bulkCountLabel">0</strong> selected</span>
                <button type="submit" formaction="index.php?module=transactions&action=bulkApprove" class="btn btn-sm" style="background:var(--success-bg);color:var(--success);"
                        onclick="return confirm('Approve all selected pending requests? Anything else selected will be skipped.');">Approve Selected</button>
                <button type="submit" formaction="index.php?module=transactions&action=bulkDelete" class="btn btn-danger btn-sm"
                        onclick="return confirm('Delete the selected transactions? System-generated entries will be skipped.');">Delete Selected</button>
            </div>

            <div class="table-card">
                <table id="transactionTable">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="selectAllTransactions" class="row-check" onclick="toggleAllTransactions(this)"></th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Technician</th>
                            <th>Notes</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="width:150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr class="empty-row">
                                <td colspan="9">No transactions match these filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr class="transaction-row" onclick="handleTransactionRowClick(event, <?= $t['transaction_id'] ?>)">
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $t['transaction_id'] ?>" class="row-check transaction-check" onchange="updateBulkBarTransactions()"></td>
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
                                    <td class="cell-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($t['notes'] ?: '—') ?></td>
                                    <td>
                                        <?php if ($t['status'] === 'pending'): ?>
                                            <span class="badge" style="background:var(--warning-bg);color:var(--warning);">Pending</span>
                                        <?php elseif ($t['status'] === 'declined'): ?>
                                            <span class="badge" style="background:var(--danger-bg);color:var(--danger);">Declined</span>
                                        <?php else: ?>
                                            <span class="badge" style="background:var(--success-bg);color:var(--success);">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cell-muted"><?= htmlspecialchars($t['created_at']) ?></td>
                                    <td class="actions">
                                        <?php if ($t['status'] === 'pending' && $t['transaction_type'] === 'item_request'): ?>
                                            <a href="index.php?module=transactions&action=approve&id=<?= $t['transaction_id'] ?>"
                                               class="btn btn-sm" style="background:var(--success-bg);color:var(--success);"
                                               onclick="event.stopPropagation(); return confirm('Approve this request? Stock will be deducted now.');">Approve</a>
                                            <a href="index.php?module=transactions&action=decline&id=<?= $t['transaction_id'] ?>"
                                               class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger);"
                                               onclick="event.stopPropagation(); return confirm('Decline this request? No stock will be affected.');">Decline</a>
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
        </form>

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

        <div id="viewTransactionModal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('open')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3 id="vtm-product">Transaction</h3>
                    <button type="button" class="modal-close" onclick="document.getElementById('viewTransactionModal').classList.remove('open')">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="margin-bottom:14px;">
                        <span id="vtm-type" class="badge"></span>
                        <span id="vtm-status" class="badge" style="margin-left:6px;"></span>
                    </div>
                    <table style="width:100%;font-size:13.5px;border-collapse:collapse;">
                        <tr><td style="padding:6px 0;color:var(--text-muted);width:140px;">Quantity</td><td id="vtm-quantity" style="padding:6px 0;font-weight:600;"></td></tr>
                        <tr><td style="padding:6px 0;color:var(--text-muted);">Technician</td><td id="vtm-technician" style="padding:6px 0;font-weight:600;"></td></tr>
                        <tr><td style="padding:6px 0;color:var(--text-muted);">Date</td><td id="vtm-date" style="padding:6px 0;font-weight:600;"></td></tr>
                    </table>
                    <div style="margin-top:14px;">
                        <div style="color:var(--text-muted);font-size:12.5px;font-weight:600;margin-bottom:4px;">Notes</div>
                        <p id="vtm-notes" style="margin:0;white-space:pre-wrap;"></p>
                    </div>
                    <div class="form-actions" style="margin-top:18px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('viewTransactionModal').classList.remove('open')">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const transactionsData = <?= json_encode(array_column($transactions, null, 'transaction_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function filterTransactions() {
            const q = document.getElementById('transactionSearch').value.toLowerCase();
            document.querySelectorAll('#transactionTable tbody tr').forEach(row => {
                if (row.classList.contains('empty-row')) return;
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        function toggleAllTransactions(source) {
            document.querySelectorAll('.transaction-check').forEach(cb => cb.checked = source.checked);
            updateBulkBarTransactions();
        }

        function updateBulkBarTransactions() {
            const checked = document.querySelectorAll('.transaction-check:checked').length;
            const bar = document.getElementById('bulkBar');
            document.getElementById('bulkCountLabel').textContent = checked;
            bar.classList.toggle('visible', checked > 0);

            const all = document.querySelectorAll('.transaction-check').length;
            document.getElementById('selectAllTransactions').checked = checked > 0 && checked === all;
        }

        function handleTransactionRowClick(event, id) {
            if (event.target.closest('input, a, button')) return;
            const t = transactionsData[id];
            if (!t) return;

            document.getElementById('vtm-product').textContent = t.item_name || 'Unknown product';
            document.getElementById('vtm-quantity').textContent = t.quantity;
            document.getElementById('vtm-technician').textContent = t.source === 'auto' ? 'System' : (t.technician_name || '—');
            document.getElementById('vtm-date').textContent = t.created_at;
            document.getElementById('vtm-notes').textContent = t.notes || 'No notes for this transaction.';

            const typeEl = document.getElementById('vtm-type');
            typeEl.textContent = t.transaction_type.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
            typeEl.className = 'badge badge-' + t.transaction_type;

            const statusEl = document.getElementById('vtm-status');
            if (t.status === 'pending') {
                statusEl.textContent = 'Pending';
                statusEl.style.background = 'var(--warning-bg)';
                statusEl.style.color = 'var(--warning)';
            } else if (t.status === 'declined') {
                statusEl.textContent = 'Declined';
                statusEl.style.background = 'var(--danger-bg)';
                statusEl.style.color = 'var(--danger)';
            } else {
                statusEl.textContent = 'Completed';
                statusEl.style.background = 'var(--success-bg)';
                statusEl.style.color = 'var(--success)';
            }

            document.getElementById('viewTransactionModal').classList.add('open');
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.getElementById('viewTransactionModal')?.classList.remove('open');
            }
        });
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
