<?php
/**
 * Views/delivery/index.php
 * Expects: $error (string|null), $items (array of inventory_items),
 *          $locations (array of locations), $categories (array),
 *          $itemTypes (array) - the latter two only used by the
 *          "Add Product Manually" section's optional dropdowns.
 */
$pageTitle = 'Delivery';
$activeSection = 'inventory';
$activeSubNav = 'delivery';
require __DIR__ . '/../partials/header.php';
$old = $_POST ?: [];
$oldQuantities = $old['quantities'] ?? [];
$oldManualProducts = $old['manual_products'] ?? [];
?>
        <a href="index.php?module=transactions&action=index" class="back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back to Product Movement
        </a>

        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Log Delivery</h1>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($items)): ?>
            <div class="alert alert-warning">No products exist yet — <a href="index.php?module=products&action=create">add one first</a>, or add one manually below.</div>
        <?php endif; ?>

        <?php if (empty($locations)): ?>
            <div class="alert alert-warning">No locations exist yet — <a href="index.php?module=locations&action=index">add one first</a> before logging a delivery.</div>
        <?php else: ?>
        <form method="POST" action="index.php?module=delivery&action=index" id="deliveryForm">
            <div class="form-card">
                <label for="supplier_name">Supplier</label>
                <input type="text" id="supplier_name" name="supplier_name" placeholder="e.g. Carrier Philippines"
                       value="<?= htmlspecialchars($old['supplier_name'] ?? '') ?>" required>

                <label for="technician_name">Received By</label>
                <input type="text" id="technician_name" name="technician_name" placeholder="e.g. Juan Dela Cruz"
                       value="<?= htmlspecialchars($old['technician_name'] ?? '') ?>" required>

                <label for="transaction_date">Delivery Date</label>
                <input type="date" id="transaction_date" name="transaction_date"
                       value="<?= htmlspecialchars($old['transaction_date'] ?? date('Y-m-d')) ?>" required>

                <label for="location_id">Received At</label>
                <select id="location_id" name="location_id" required>
                    <option value="" disabled <?= empty($old['location_id']) ? 'selected' : '' ?>>Select a location</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['location_id'] ?>" <?= (($old['location_id'] ?? '') == $loc['location_id']) ? 'selected' : '' ?>><?= htmlspecialchars($loc['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="notes">Notes <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <textarea id="notes" name="notes" placeholder="Optional notes about this delivery"><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
            </div>

            <?php if (!empty($items)): ?>
            <div class="page-header" style="margin-top:22px;">
                <div class="page-title-group">
                    <h2 class="page-title" style="font-size:16px;">Products Received</h2>
                    <span class="page-title-count" id="deliveryProductCount">Enter a quantity for each product delivered — leave the rest blank</span>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="deliveryProductSearch" placeholder="Search products..." onkeyup="filterDeliveryProducts()">
                    </div>
                </div>
            </div>

            <div class="table-card">
                <table id="deliveryProductTable">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th style="width:140px;">Quantity Received</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr class="catalog-row">
                                <td><strong><?= htmlspecialchars($it['model']) ?></strong></td>
                                <td class="cell-muted"><?= htmlspecialchars($it['category_name'] ?? 'Uncategorized') ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($it['brand_name'] ?? '—') ?></td>
                                <td>
                                    <input type="number" name="quantities[<?= $it['item_id'] ?>]" min="0" step="1" placeholder="0"
                                           value="<?= htmlspecialchars($oldQuantities[$it['item_id']] ?? '') ?>" style="width:100px;margin-bottom:0;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-bar" id="deliveryPaginationBar">
                <span id="deliveryPaginationSummary"></span>
                <div class="pagination-controls" id="deliveryPaginationControls"></div>
            </div>
            <?php endif; ?>

            <div class="page-header" style="margin-top:22px;">
                <div class="page-title-group">
                    <h2 class="page-title" style="font-size:16px;">Add Product Manually</h2>
                    <span class="page-title-count">For a product that isn't in the catalog yet — it'll be added as a new product and delivered in one step</span>
                </div>
                <div class="header-actions">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addManualProductRow()">+ Add Product Manually</button>
                </div>
            </div>

            <div class="table-card" id="manualProductRows" style="padding:14px;display:none;"></div>

            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" class="btn btn-primary">Log Delivery</button>
                <a href="index.php?module=transactions&action=index" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        <?php endif; ?>

        <script>
        const DELIVERY_PER_PAGE = 10;
        let deliveryCurrentPage = 1;

        function paginateDeliveryProducts() {
            const table = document.getElementById('deliveryProductTable');
            if (!table) return;
            const rows = Array.from(table.querySelectorAll('tbody tr.catalog-row'));
            const totalPages = Math.max(1, Math.ceil(rows.length / DELIVERY_PER_PAGE));
            deliveryCurrentPage = Math.min(deliveryCurrentPage, totalPages);

            rows.forEach((row, i) => {
                const page = Math.floor(i / DELIVERY_PER_PAGE) + 1;
                row.style.display = (page === deliveryCurrentPage) ? '' : 'none';
            });

            const start = rows.length === 0 ? 0 : (deliveryCurrentPage - 1) * DELIVERY_PER_PAGE + 1;
            const end = Math.min(deliveryCurrentPage * DELIVERY_PER_PAGE, rows.length);
            document.getElementById('deliveryProductCount').textContent =
                'Showing ' + start + '–' + end + ' of ' + rows.length + ' products — enter a quantity, leave the rest blank';
            document.getElementById('deliveryPaginationSummary').textContent =
                rows.length + ' product' + (rows.length === 1 ? '' : 's') + ' total';

            renderDeliveryPaginationControls(totalPages);
        }

        function renderDeliveryPaginationControls(totalPages) {
            const controls = document.getElementById('deliveryPaginationControls');
            if (!controls) return;
            if (totalPages <= 1) {
                controls.innerHTML = '';
                return;
            }
            let html = '<a href="#" class="page-btn ' + (deliveryCurrentPage <= 1 ? 'disabled' : '') + '" onclick="event.preventDefault(); goToDeliveryPage(' + (deliveryCurrentPage - 1) + ');">&lsaquo; Prev</a>';
            for (let p = 1; p <= totalPages; p++) {
                html += '<a href="#" class="page-btn ' + (p === deliveryCurrentPage ? 'active' : '') + '" onclick="event.preventDefault(); goToDeliveryPage(' + p + ');">' + p + '</a>';
            }
            html += '<a href="#" class="page-btn ' + (deliveryCurrentPage >= totalPages ? 'disabled' : '') + '" onclick="event.preventDefault(); goToDeliveryPage(' + (deliveryCurrentPage + 1) + ');">Next &rsaquo;</a>';
            controls.innerHTML = html;
        }

        function goToDeliveryPage(p) {
            deliveryCurrentPage = p;
            paginateDeliveryProducts();
        }

        // While searching, pagination is suspended - every matching row is
        // shown at once regardless of page, same convention Products uses.
        function filterDeliveryProducts() {
            const q = document.getElementById('deliveryProductSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#deliveryProductTable tbody tr.catalog-row');
            const paginationBar = document.getElementById('deliveryPaginationBar');

            if (q === '') {
                paginationBar.style.display = '';
                paginateDeliveryProducts();
                return;
            }

            paginationBar.style.display = 'none';
            rows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        // "Add Product Manually" - each row is a brand-new product that
        // doesn't exist in the catalog yet. manualProductIndex only ever
        // increments, so removed rows never get their index reused.
        let manualProductIndex = 0;

        function addManualProductRow(values) {
            values = values || {};
            const i = manualProductIndex++;
            const container = document.getElementById('manualProductRows');
            container.style.display = '';

            const row = document.createElement('div');
            row.className = 'manual-product-row';
            row.style.cssText = 'display:flex;gap:10px;align-items:flex-end;padding:10px 0;border-bottom:1px solid var(--border);flex-wrap:wrap;';
            row.innerHTML = `
                <div style="flex:2;min-width:160px;">
                    <label style="font-size:12px;">Model</label>
                    <input type="text" name="manual_products[${i}][model]" placeholder="e.g. 2.0 HP Window Type AC" style="margin-bottom:0;" value="${htmlEscapeDelivery(values.model || '')}">
                </div>
                <div style="flex:1;min-width:130px;">
                    <label style="font-size:12px;">Category <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                    <select name="manual_products[${i}][category_id]" style="margin-bottom:0;">
                        <option value="">None</option>
                        ${manualCategoryOptions(values.category_id)}
                    </select>
                </div>
                <div style="flex:1;min-width:130px;">
                    <label style="font-size:12px;">Item Type <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                    <select name="manual_products[${i}][item_type_id]" style="margin-bottom:0;">
                        <option value="">None</option>
                        ${manualItemTypeOptions(values.item_type_id)}
                    </select>
                </div>
                <div style="width:110px;">
                    <label style="font-size:12px;">Quantity</label>
                    <input type="number" name="manual_products[${i}][quantity]" min="1" step="1" placeholder="0" style="margin-bottom:0;" value="${htmlEscapeDelivery(values.quantity || '')}">
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="this.closest('.manual-product-row').remove()">Remove</button>
            `;
            container.appendChild(row);
        }

        function manualCategoryOptions(selectedId) {
            return manualCategories.map(c =>
                '<option value="' + c.category_id + '"' + (String(selectedId) === String(c.category_id) ? ' selected' : '') + '>' + htmlEscapeDelivery(c.category_name) + '</option>'
            ).join('');
        }

        function manualItemTypeOptions(selectedId) {
            return manualItemTypes.map(t =>
                '<option value="' + t.item_type_id + '"' + (String(selectedId) === String(t.item_type_id) ? ' selected' : '') + '>' + htmlEscapeDelivery(t.type_name) + '</option>'
            ).join('');
        }

        function htmlEscapeDelivery(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        const manualCategories = <?= json_encode($categories ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const manualItemTypes = <?= json_encode($itemTypes ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const oldManualProducts = <?= json_encode(array_values($oldManualProducts), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        paginateDeliveryProducts();
        oldManualProducts.forEach(mp => addManualProductRow(mp));
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
