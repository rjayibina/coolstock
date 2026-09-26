<?php
/**
 * Views/delivery/index.php
 * Expects: $error (string|null), $items (array of inventory_items),
 *          $locations (array of locations), $categories (array),
 *          $itemTypes (array) - the latter two used by the "Add Product
 *          Manually" section's Category/Item Type dropdowns (required -
 *          see DeliveryController::validate()).
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
                <label for="supplier_name">Supplier <span class="required-asterisk">*</span></label>
                <input type="text" id="supplier_name" name="supplier_name" placeholder="e.g. Carrier Philippines" maxlength="150"
                       value="<?= htmlspecialchars($old['supplier_name'] ?? '') ?>" required>

                <label for="technician_name">Received By <span class="required-asterisk">*</span></label>
                <input type="text" id="technician_name" name="technician_name" placeholder="e.g. Juan Dela Cruz" maxlength="100"
                       value="<?= htmlspecialchars($old['technician_name'] ?? '') ?>" required>

                <label for="transaction_date">Delivery Date <span class="required-asterisk">*</span></label>
                <input type="date" id="transaction_date" name="transaction_date" readonly
                       value="<?= htmlspecialchars($old['transaction_date'] ?? date('Y-m-d')) ?>"
                       max="<?= date('Y-m-d') ?>" required>

                <label for="location_id">Received At <span class="required-asterisk">*</span></label>
                <select id="location_id" name="location_id" required>
                    <option value="" disabled <?= empty($old['location_id']) ? 'selected' : '' ?>>Select a location</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['location_id'] ?>" <?= (($old['location_id'] ?? '') == $loc['location_id']) ? 'selected' : '' ?>><?= htmlspecialchars($loc['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="notes">Notes <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <textarea id="notes" name="notes" placeholder="Optional notes about this delivery"><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
            </div>

            <div class="page-header" style="margin-top:22px;">
                <div class="page-title-group">
                    <h2 class="page-title" style="font-size:16px;">Products Received</h2>
                    <span class="page-title-count">Search the catalog and add each product delivered, with its quantity</span>
                </div>
            </div>

            <?php if (!empty($items)): ?>
            <div class="search-box" style="position:relative;max-width:420px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="deliveryProductSearch" placeholder="Search products to add..." autocomplete="off"
                       oninput="renderDeliverySearchResults()" onfocus="renderDeliverySearchResults()">
                <div id="deliverySearchResults" class="search-results-dropdown" style="display:none;"></div>
            </div>
            <?php endif; ?>

            <div class="table-card" id="deliveryLineItemsCard" style="padding:14px;margin-top:12px;display:none;">
                <div id="deliveryLineItems"></div>
            </div>

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
        // Full catalog, loaded once - Delivery never paginates products
        // server-side (DeliveryController::index() always reads every
        // item), so search-as-you-type is just filtering this array
        // client-side, no round trip needed.
        const deliveryCatalog = <?= json_encode(array_map(fn($it) => [
            'item_id' => (int) $it['item_id'],
            'model' => $it['model'],
            'category_name' => $it['category_name'] ?? 'Uncategorized',
            'brand_name' => $it['brand_name'] ?? null,
        ], $items), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const deliveryAddedItemIds = new Set();

        function renderDeliverySearchResults() {
            const input = document.getElementById('deliveryProductSearch');
            const dropdown = document.getElementById('deliverySearchResults');
            const q = input.value.trim().toLowerCase();

            if (q === '') {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
                return;
            }

            const matches = deliveryCatalog
                .filter(p => !deliveryAddedItemIds.has(p.item_id) && p.model.toLowerCase().includes(q))
                .slice(0, 8);

            if (matches.length === 0) {
                dropdown.innerHTML = '<div class="search-result-empty">No matching products</div>';
                dropdown.style.display = '';
                return;
            }

            dropdown.innerHTML = matches.map(p =>
                '<div class="search-result-item" onclick="addDeliveryLineItem(' + p.item_id + ')">'
                    + '<strong>' + htmlEscapeDelivery(p.model) + '</strong>'
                    + '<span class="cell-muted">' + htmlEscapeDelivery(p.category_name) + (p.brand_name ? ' · ' + htmlEscapeDelivery(p.brand_name) : '') + '</span>'
                    + '</div>'
            ).join('');
            dropdown.style.display = '';
        }

        function addDeliveryLineItem(itemId, quantity) {
            const product = deliveryCatalog.find(p => p.item_id === itemId);
            if (!product) return;

            if (deliveryAddedItemIds.has(itemId)) {
                document.getElementById('dli_qty_' + itemId)?.focus();
                return;
            }
            deliveryAddedItemIds.add(itemId);

            const card = document.getElementById('deliveryLineItemsCard');
            card.style.display = '';

            const row = document.createElement('div');
            row.className = 'line-item-row';
            row.id = 'dli_row_' + itemId;
            row.style.cssText = 'display:flex;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);';
            row.innerHTML = `
                <div style="flex:1;">
                    <strong>${htmlEscapeDelivery(product.model)}</strong>
                    <div class="cell-muted" style="font-size:12.5px;">${htmlEscapeDelivery(product.category_name)}${product.brand_name ? ' &middot; ' + htmlEscapeDelivery(product.brand_name) : ''}</div>
                </div>
                <input type="number" name="quantities[${itemId}]" id="dli_qty_${itemId}" min="1" step="1" placeholder="Quantity"
                       value="${quantity || ''}" style="width:120px;margin-bottom:0;" required>
                <button type="button" class="btn btn-secondary btn-sm" onclick="removeDeliveryLineItem(${itemId})">Remove</button>
            `;
            document.getElementById('deliveryLineItems').appendChild(row);

            document.getElementById('deliveryProductSearch').value = '';
            document.getElementById('deliverySearchResults').style.display = 'none';
            document.getElementById('dli_qty_' + itemId).focus();
        }

        function removeDeliveryLineItem(itemId) {
            document.getElementById('dli_row_' + itemId)?.remove();
            deliveryAddedItemIds.delete(itemId);
            if (deliveryAddedItemIds.size === 0) {
                document.getElementById('deliveryLineItemsCard').style.display = 'none';
            }
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#deliveryProductSearch') && !e.target.closest('#deliverySearchResults')) {
                document.getElementById('deliverySearchResults').style.display = 'none';
            }
        });

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
                    <label style="font-size:12px;">Model <span class="required-asterisk">*</span></label>
                    <input type="text" name="manual_products[${i}][model]" placeholder="e.g. 2.0 HP Window Type AC" style="margin-bottom:0;" value="${htmlEscapeDelivery(values.model || '')}">
                </div>
                <div style="flex:1;min-width:130px;">
                    <label style="font-size:12px;">Category <span class="required-asterisk">*</span></label>
                    <select name="manual_products[${i}][category_id]" style="margin-bottom:0;" required>
                        <option value="" disabled ${values.category_id ? '' : 'selected'}>Select a category</option>
                        ${manualCategoryOptions(values.category_id)}
                    </select>
                </div>
                <div style="flex:1;min-width:130px;">
                    <label style="font-size:12px;">Item Type <span class="required-asterisk">*</span></label>
                    <select name="manual_products[${i}][item_type_id]" style="margin-bottom:0;" required>
                        <option value="" disabled ${values.item_type_id ? '' : 'selected'}>Select an item type</option>
                        ${manualItemTypeOptions(values.item_type_id)}
                    </select>
                </div>
                <div style="width:110px;">
                    <label style="font-size:12px;">Quantity <span class="required-asterisk">*</span></label>
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
        const oldQuantities = <?= json_encode($oldQuantities, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        // Re-populate line items on a failed-validation redisplay.
        Object.keys(oldQuantities).forEach(itemId => {
            const qty = oldQuantities[itemId];
            if (qty !== '' && qty !== null && Number(qty) > 0) {
                addDeliveryLineItem(Number(itemId), qty);
            }
        });
        oldManualProducts.forEach(mp => addManualProductRow(mp));
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
