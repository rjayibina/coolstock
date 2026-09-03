<?php
/**
 * Views/transactions/index.php
 * Expects: $transactions (array), $items (array, for the filter dropdown),
 *          $pagination (array: page, perPage, totalCount, totalPages), $error (string|null)
 */
require_once __DIR__ . '/../../Models/Transaction.php';
$status = $_GET['status'] ?? null;
$bulkCount = (int) ($_GET['count'] ?? 0);
$currentItem = $_GET['item_id'] ?? '';
$currentType = $_GET['type'] ?? '';
$currentDateFrom = $_GET['date_from'] ?? '';
$currentDateTo = $_GET['date_to'] ?? '';
$currentSort = $_GET['sort'] ?? 'date_desc';
$pageTitle = 'Product Movement';
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
                <h1 class="page-title">Product Movement</h1>
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
                            <option value="<?= $it['item_id'] ?>" <?= ($currentItem == $it['item_id']) ? 'selected' : '' ?>><?= htmlspecialchars($it['model']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Remarks</label>
                    <select name="type" onchange="this.form.submit()">
                        <option value="">All Remarks</option>
                        <?php foreach (Transaction::MOVEMENT_FILTERS as $type => $label): ?>
                            <option value="<?= $type ?>" <?= $currentType === $type ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
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

        <?php if ($status === 'delivery_logged'): ?>
            <div class="alert alert-success"><?= $bulkCount ?> product line<?= $bulkCount === 1 ? '' : 's' ?> logged as delivered<?= !empty($_GET['reference']) ? ' — Order # ' . htmlspecialchars($_GET['reference']) : '' ?>.</div>
        <?php elseif ($status === 'transfer_logged'): ?>
            <div class="alert alert-success"><?= $bulkCount ?> product line<?= $bulkCount === 1 ? '' : 's' ?> transferred<?= !empty($_GET['reference']) ? ' — Transfer # ' . htmlspecialchars($_GET['reference']) : '' ?>.</div>
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

        <div class="table-card">
                <table id="transactionTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Remarks</th>
                            <th>Order/Transfer #</th>
                            <th>Serial #</th>
                            <th>Quantity</th>
                            <th>Notes</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr class="empty-row">
                                <td colspan="7">No transactions match these filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr class="transaction-row" onclick="handleTransactionRowClick(event, <?= $t['transaction_id'] ?>)">
                                    <td>
                                        <strong><?= htmlspecialchars($t['model'] ?? 'Unknown product') ?></strong>
                                        <?php if ((int) ($t['line_count'] ?? 1) > 1): ?>
                                            <span class="cell-muted"> +<?= (int) $t['line_count'] - 1 ?> more</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-<?= htmlspecialchars($t['transaction_type']) ?>"><?= Transaction::movementLabel($t['transaction_type']) ?></span></td>
                                    <td class="cell-muted">
                                        <?php if (!empty($t['reference_number'])): ?>
                                            <a href="#" onclick="event.stopPropagation(); openBatchModal('<?= htmlspecialchars($t['reference_number']) ?>'); return false;"><?= htmlspecialchars($t['reference_number']) ?></a>
                                        <?php else: ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                    <td class="cell-muted"><?= htmlspecialchars($t['serial_number'] ?? '—') ?></td>
                                    <td class="cell-id"><?= (int) ($t['total_quantity'] ?? $t['quantity']) ?></td>
                                    <td class="cell-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($t['notes'] ?: '—') ?></td>
                                    <td class="cell-muted"><?= htmlspecialchars(format_datetime($t['created_at'])) ?></td>
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

        <div id="viewTransactionModal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('open')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3 id="vtm-product">Transaction</h3>
                    <button type="button" class="modal-close" onclick="document.getElementById('viewTransactionModal').classList.remove('open')">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="margin-bottom:14px;">
                        <span id="vtm-type" class="badge"></span>
                    </div>
                    <table style="width:100%;font-size:13.5px;border-collapse:collapse;">
                        <tr><td style="padding:6px 0;color:var(--text-muted);width:140px;">Quantity</td><td id="vtm-quantity" style="padding:6px 0;font-weight:600;"></td></tr>
                        <tr><td style="padding:6px 0;color:var(--text-muted);" id="vtm-technician-label">Technician</td><td id="vtm-technician" style="padding:6px 0;font-weight:600;"></td></tr>
                        <tr><td style="padding:6px 0;color:var(--text-muted);">Date</td><td id="vtm-date" style="padding:6px 0;font-weight:600;"></td></tr>
                        <tr id="vtm-location-row" style="display:none;"><td id="vtm-location-label" style="padding:6px 0;color:var(--text-muted);">Location</td><td id="vtm-location" style="padding:6px 0;font-weight:600;"></td></tr>
                        <tr id="vtm-to-location-row" style="display:none;"><td style="padding:6px 0;color:var(--text-muted);">To Location</td><td id="vtm-to-location" style="padding:6px 0;font-weight:600;"></td></tr>
                        <tr id="vtm-supplier-row" style="display:none;"><td style="padding:6px 0;color:var(--text-muted);">Supplier</td><td id="vtm-supplier" style="padding:6px 0;font-weight:600;"></td></tr>
                        <tr id="vtm-serial-row" style="display:none;"><td style="padding:6px 0;color:var(--text-muted);">Serial #</td><td id="vtm-serial" style="padding:6px 0;font-weight:600;"></td></tr>
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

        <div id="batchModal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('open')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <div>
                        <h3 id="bm-title" style="margin:0;">Delivered Products</h3>
                        <div id="bm-subtitle" style="font-size:12px;color:var(--text-muted);font-weight:500;margin-top:2px;"></div>
                    </div>
                    <button type="button" class="modal-close" onclick="document.getElementById('batchModal').classList.remove('open')">&times;</button>
                </div>
                <div class="modal-body">
                    <table style="width:100%;font-size:13.5px;border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left;border-bottom:1px solid var(--border);">
                                <th style="padding:6px 0;">Product</th>
                                <th style="padding:6px 0;">Qty</th>
                                <th style="padding:6px 0;" id="bm-location-header">Location</th>
                            </tr>
                        </thead>
                        <tbody id="bm-rows">
                            <tr><td colspan="3" style="padding:10px 0;color:var(--text-muted);">Loading...</td></tr>
                        </tbody>
                    </table>
                    <div class="form-actions" style="margin-top:18px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('batchModal').classList.remove('open')">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const transactionsData = <?= json_encode(array_column($transactions, null, 'transaction_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        // Fetches every line item sharing $referenceNumber (a Delivery
        // order # or Transfer #, see TransactionController::batch()) and
        // lists them in a modal - so a Delivery/Transfer that spans several
        // products isn't only viewable one row at a time.
        function openBatchModal(referenceNumber) {
            const isTransfer = referenceNumber.startsWith('TR-');
            document.getElementById('bm-title').textContent = isTransfer ? 'Transferred Products' : 'Delivered Products';
            document.getElementById('bm-subtitle').textContent = referenceNumber;
            document.getElementById('bm-location-header').textContent = isTransfer ? 'From \u2192 To' : 'Location';

            const rows = document.getElementById('bm-rows');
            rows.innerHTML = '<tr><td colspan="4" style="padding:10px 0;color:var(--text-muted);">Loading...</td></tr>';
            document.getElementById('batchModal').classList.add('open');

            fetch('index.php?module=transactions&action=batch&reference_number=' + encodeURIComponent(referenceNumber))
                .then(res => res.json())
                .then(lines => {
                    if (!Array.isArray(lines) || lines.length === 0) {
                        rows.innerHTML = '<tr><td colspan="3" style="padding:10px 0;color:var(--text-muted);">No line items found.</td></tr>';
                        return;
                    }
                    rows.innerHTML = lines.map(line => {
                        const location = isTransfer
                            ? htmlEscapeTM(line.location_name || '\u2014') + ' \u2192 ' + htmlEscapeTM(line.to_location_name || '\u2014')
                            : htmlEscapeTM(line.location_name || '\u2014');
                        const newBadge = line.manually_added
                            ? ' <span class="badge" style="background:var(--success-bg);color:var(--success);">New</span>'
                            : '';
                        return '<tr style="border-bottom:1px solid var(--border);">'
                            + '<td style="padding:6px 0;">' + htmlEscapeTM(line.model) + newBadge + '</td>'
                            + '<td style="padding:6px 0;">' + line.quantity + '</td>'
                            + '<td style="padding:6px 0;">' + location + '</td>'
                            + '</tr>';
                    }).join('');
                })
                .catch(() => {
                    rows.innerHTML = '<tr><td colspan="3" style="padding:10px 0;color:var(--danger);">Could not load this order/transfer.</td></tr>';
                });
        }

        function htmlEscapeTM(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        // Mirrors Helpers/format.php's format_datetime() - mm-dd-yyyy,
        // 12-hour time - for the row-detail modal, which renders from the
        // raw transactionsData JSON (still yyyy-mm-dd HH:MM:SS from MySQL)
        // instead of a server-formatted string.
        function formatDateTimeTM(value) {
            if (!value) return '\u2014';
            const d = new Date(String(value).replace(' ', 'T'));
            if (isNaN(d.getTime())) return value;
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const yyyy = d.getFullYear();
            let hours = d.getHours();
            const minutes = String(d.getMinutes()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            return mm + '-' + dd + '-' + yyyy + ' ' + hours + ':' + minutes + ' ' + ampm;
        }

        function filterTransactions() {
            const q = document.getElementById('transactionSearch').value.toLowerCase();
            document.querySelectorAll('#transactionTable tbody tr').forEach(row => {
                if (row.classList.contains('empty-row')) return;
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        function handleTransactionRowClick(event, id) {
            if (event.target.closest('input, a, button')) return;
            const t = transactionsData[id];
            if (!t) return;

            document.getElementById('vtm-product').textContent = t.model || 'Unknown product';
            document.getElementById('vtm-quantity').textContent = t.total_quantity ?? t.quantity;
            document.getElementById('vtm-technician-label').textContent =
                t.transaction_type === 'delivery' ? 'Received By' :
                t.transaction_type === 'transfer' ? 'Transferred By' : 'Technician';
            document.getElementById('vtm-technician').textContent = t.source === 'auto' ? 'System' : (t.technician_name || '—');
            document.getElementById('vtm-date').textContent = formatDateTimeTM(t.created_at);
            document.getElementById('vtm-notes').textContent = t.notes || 'No notes for this transaction.';

            const locationRow = document.getElementById('vtm-location-row');
            if (t.location_id) {
                document.getElementById('vtm-location-label').textContent = t.transaction_type === 'transfer' ? 'From Location' : 'Location';
                document.getElementById('vtm-location').textContent = t.location_name || '—';
                locationRow.style.display = '';
            } else {
                locationRow.style.display = 'none';
            }

            const toLocationRow = document.getElementById('vtm-to-location-row');
            if (t.transaction_type === 'transfer' && t.to_location_id) {
                document.getElementById('vtm-to-location').textContent = t.to_location_name || '—';
                toLocationRow.style.display = '';
            } else {
                toLocationRow.style.display = 'none';
            }

            const supplierRow = document.getElementById('vtm-supplier-row');
            if (t.transaction_type === 'delivery' && t.supplier_name) {
                document.getElementById('vtm-supplier').textContent = t.supplier_name;
                supplierRow.style.display = '';
            } else {
                supplierRow.style.display = 'none';
            }

            const serialRow = document.getElementById('vtm-serial-row');
            if (t.serial_number) {
                document.getElementById('vtm-serial').textContent = t.serial_number;
                serialRow.style.display = '';
            } else {
                serialRow.style.display = 'none';
            }

            const typeEl = document.getElementById('vtm-type');
            typeEl.textContent = t.transaction_type === 'delivery'
                ? 'Stock In'
                : t.transaction_type.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
            typeEl.className = 'badge badge-' + t.transaction_type;

            document.getElementById('viewTransactionModal').classList.add('open');
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.getElementById('viewTransactionModal')?.classList.remove('open');
                document.getElementById('batchModal')?.classList.remove('open');
            }
        });
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
