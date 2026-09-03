<?php
/**
 * Views/transfer/index.php
 * Expects: $error (string|null), $items (array of inventory_items),
 *          $locations (array of locations), $stockBreakdown (array,
 *          item_id => [['location_id','location_name','quantity'], ...] -
 *          from ItemStock::breakdownForItems(), used to show/enforce how
 *          much is available at whichever From location is selected)
 */
$pageTitle = 'Transfer';
$activeSection = 'inventory';
$activeSubNav = 'transfer';
require __DIR__ . '/../partials/header.php';
$old = $_POST ?: [];
$oldQuantities = $old['quantities'] ?? [];
?>
        <a href="index.php?module=transactions&action=index" class="back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back to Product Movement
        </a>

        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Transfer Stock</h1>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($items)): ?>
            <div class="alert alert-warning">No products exist yet — <a href="index.php?module=products&action=create">add one first</a> before transferring stock.</div>
        <?php elseif (count($locations) < 2): ?>
            <div class="alert alert-warning">You need at least two locations to transfer stock — <a href="index.php?module=locations&action=index">manage locations</a>.</div>
        <?php else: ?>
        <form method="POST" action="index.php?module=transfer&action=index" id="transferForm">
            <div class="form-card">
                <label for="from_location_id">From Location</label>
                <select id="from_location_id" name="from_location_id" required onchange="updateAvailability()">
                    <option value="" disabled <?= empty($old['from_location_id']) ? 'selected' : '' ?>>Select a location</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['location_id'] ?>" <?= (($old['from_location_id'] ?? '') == $loc['location_id']) ? 'selected' : '' ?>><?= htmlspecialchars($loc['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="to_location_id">To Location</label>
                <select id="to_location_id" name="to_location_id" required>
                    <option value="" disabled <?= empty($old['to_location_id']) ? 'selected' : '' ?>>Select a location</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['location_id'] ?>" <?= (($old['to_location_id'] ?? '') == $loc['location_id']) ? 'selected' : '' ?>><?= htmlspecialchars($loc['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="technician_name">Moved By</label>
                <input type="text" id="technician_name" name="technician_name" placeholder="e.g. Juan Dela Cruz"
                       value="<?= htmlspecialchars($old['technician_name'] ?? '') ?>" required>

                <label for="transaction_date">Transfer Date</label>
                <input type="date" id="transaction_date" name="transaction_date"
                       value="<?= htmlspecialchars($old['transaction_date'] ?? date('Y-m-d')) ?>" required>

                <label for="notes">Notes <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <textarea id="notes" name="notes" placeholder="Optional notes about this transfer"><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
            </div>

            <div class="page-header" style="margin-top:22px;">
                <div class="page-title-group">
                    <h2 class="page-title" style="font-size:16px;">Products to Transfer</h2>
                    <span class="page-title-count" id="transferProductCount">Enter a quantity for each product to move — leave the rest blank</span>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="transferProductSearch" placeholder="Search products..." onkeyup="filterTransferProducts()">
                    </div>
                </div>
            </div>

            <div class="table-card">
                <table id="transferProductTable">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Category</th>
                            <th style="width:150px;" id="availFromHeader">Available at From</th>
                            <th style="width:140px;">Quantity to Move</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr class="catalog-row">
                                <td><strong><?= htmlspecialchars($it['model']) ?></strong></td>
                                <td class="cell-muted"><?= htmlspecialchars($it['category_name'] ?? 'Uncategorized') ?></td>
                                <td class="cell-muted" id="avail-<?= $it['item_id'] ?>">—</td>
                                <td>
                                    <input type="number" name="quantities[<?= $it['item_id'] ?>]" min="0" step="1" placeholder="0"
                                           id="qty-<?= $it['item_id'] ?>"
                                           value="<?= htmlspecialchars($oldQuantities[$it['item_id']] ?? '') ?>" style="width:100px;margin-bottom:0;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-bar" id="transferPaginationBar">
                <span id="transferPaginationSummary"></span>
                <div class="pagination-controls" id="transferPaginationControls"></div>
            </div>

            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" class="btn btn-primary">Log Transfer</button>
                <a href="index.php?module=transactions&action=index" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        <?php endif; ?>

        <script>
        const stockBreakdown = <?= json_encode($stockBreakdown ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const allItemIds = <?= json_encode(array_column($items, 'item_id')) ?>;
        const locationNames = <?= json_encode(array_column($locations, 'location_name', 'location_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        const TRANSFER_PER_PAGE = 10;
        let transferCurrentPage = 1;

        function paginateTransferProducts() {
            const table = document.getElementById('transferProductTable');
            if (!table) return;
            const rows = Array.from(table.querySelectorAll('tbody tr.catalog-row'));
            const totalPages = Math.max(1, Math.ceil(rows.length / TRANSFER_PER_PAGE));
            transferCurrentPage = Math.min(transferCurrentPage, totalPages);

            rows.forEach((row, i) => {
                const page = Math.floor(i / TRANSFER_PER_PAGE) + 1;
                row.style.display = (page === transferCurrentPage) ? '' : 'none';
            });

            const start = rows.length === 0 ? 0 : (transferCurrentPage - 1) * TRANSFER_PER_PAGE + 1;
            const end = Math.min(transferCurrentPage * TRANSFER_PER_PAGE, rows.length);
            document.getElementById('transferProductCount').textContent =
                'Showing ' + start + '–' + end + ' of ' + rows.length + ' products — enter a quantity, leave the rest blank';
            document.getElementById('transferPaginationSummary').textContent =
                rows.length + ' product' + (rows.length === 1 ? '' : 's') + ' total';

            renderTransferPaginationControls(totalPages);
        }

        function renderTransferPaginationControls(totalPages) {
            const controls = document.getElementById('transferPaginationControls');
            if (!controls) return;
            if (totalPages <= 1) {
                controls.innerHTML = '';
                return;
            }
            let html = '<a href="#" class="page-btn ' + (transferCurrentPage <= 1 ? 'disabled' : '') + '" onclick="event.preventDefault(); goToTransferPage(' + (transferCurrentPage - 1) + ');">&lsaquo; Prev</a>';
            for (let p = 1; p <= totalPages; p++) {
                html += '<a href="#" class="page-btn ' + (p === transferCurrentPage ? 'active' : '') + '" onclick="event.preventDefault(); goToTransferPage(' + p + ');">' + p + '</a>';
            }
            html += '<a href="#" class="page-btn ' + (transferCurrentPage >= totalPages ? 'disabled' : '') + '" onclick="event.preventDefault(); goToTransferPage(' + (transferCurrentPage + 1) + ');">Next &rsaquo;</a>';
            controls.innerHTML = html;
        }

        function goToTransferPage(p) {
            transferCurrentPage = p;
            paginateTransferProducts();
        }

        // While searching, pagination is suspended - every matching row is
        // shown at once regardless of page, same convention Products uses.
        function filterTransferProducts() {
            const q = document.getElementById('transferProductSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#transferProductTable tbody tr.catalog-row');
            const paginationBar = document.getElementById('transferPaginationBar');

            if (q === '') {
                paginationBar.style.display = '';
                paginateTransferProducts();
                return;
            }

            paginationBar.style.display = 'none';
            rows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        function updateAvailability() {
            const fromLocationId = document.getElementById('from_location_id').value;

            const header = document.getElementById('availFromHeader');
            header.textContent = fromLocationId ? 'Available at ' + (locationNames[fromLocationId] || 'From') : 'Available at From';

            allItemIds.forEach(itemId => {
                const cell = document.getElementById('avail-' + itemId);
                const qtyInput = document.getElementById('qty-' + itemId);
                if (!cell) return;
                const rows = stockBreakdown[itemId] || [];
                const match = rows.find(r => String(r.location_id) === String(fromLocationId));
                const available = match ? match.quantity : 0;
                cell.textContent = fromLocationId ? available : '—';
                if (qtyInput) qtyInput.max = fromLocationId ? available : '';
            });
        }

        updateAvailability();
        paginateTransferProducts();
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
