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
                <input type="text" id="technician_name" name="technician_name" placeholder="e.g. Juan Dela Cruz" maxlength="100"
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
                    <span class="page-title-count">Search the catalog and add each product to move, with its quantity</span>
                </div>
            </div>

            <div class="search-box" style="position:relative;max-width:420px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="transferProductSearch" placeholder="Search products to add..." autocomplete="off"
                       oninput="renderTransferSearchResults()" onfocus="renderTransferSearchResults()">
                <div id="transferSearchResults" class="search-results-dropdown" style="display:none;"></div>
            </div>

            <div class="table-card" id="transferLineItemsCard" style="padding:14px;margin-top:12px;display:none;">
                <div id="transferLineItems"></div>
            </div>

            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" class="btn btn-primary">Log Transfer</button>
                <a href="index.php?module=transactions&action=index" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        <?php endif; ?>

        <script>
        const stockBreakdown = <?= json_encode($stockBreakdown ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const locationNames = <?= json_encode(array_column($locations, 'location_name', 'location_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        // Full catalog, loaded once - same reasoning as Delivery's search box.
        const transferCatalog = <?= json_encode(array_map(fn($it) => [
            'item_id' => (int) $it['item_id'],
            'model' => $it['model'],
            'category_name' => $it['category_name'] ?? 'Uncategorized',
        ], $items), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const transferAddedItemIds = new Set();

        function renderTransferSearchResults() {
            const input = document.getElementById('transferProductSearch');
            const dropdown = document.getElementById('transferSearchResults');
            const q = input.value.trim().toLowerCase();

            if (q === '') {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
                return;
            }

            const matches = transferCatalog
                .filter(p => !transferAddedItemIds.has(p.item_id) && p.model.toLowerCase().includes(q))
                .slice(0, 8);

            if (matches.length === 0) {
                dropdown.innerHTML = '<div class="search-result-empty">No matching products</div>';
                dropdown.style.display = '';
                return;
            }

            dropdown.innerHTML = matches.map(p =>
                '<div class="search-result-item" onclick="addTransferLineItem(' + p.item_id + ')">'
                    + '<strong>' + htmlEscapeTransfer(p.model) + '</strong>'
                    + '<span class="cell-muted">' + htmlEscapeTransfer(p.category_name) + '</span>'
                    + '</div>'
            ).join('');
            dropdown.style.display = '';
        }

        function addTransferLineItem(itemId, quantity) {
            const product = transferCatalog.find(p => p.item_id === itemId);
            if (!product) return;

            if (transferAddedItemIds.has(itemId)) {
                document.getElementById('qty-' + itemId)?.focus();
                return;
            }
            transferAddedItemIds.add(itemId);

            const card = document.getElementById('transferLineItemsCard');
            card.style.display = '';

            const row = document.createElement('div');
            row.className = 'line-item-row';
            row.id = 'tli_row_' + itemId;
            row.style.cssText = 'display:flex;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);';
            row.innerHTML = `
                <div style="flex:1;">
                    <strong>${htmlEscapeTransfer(product.model)}</strong>
                    <div class="cell-muted" style="font-size:12.5px;">${htmlEscapeTransfer(product.category_name)} &middot; <span id="avail-${itemId}">Available: \u2014</span></div>
                </div>
                <input type="number" name="quantities[${itemId}]" id="qty-${itemId}" min="1" step="1" placeholder="Quantity"
                       value="${quantity || ''}" style="width:120px;margin-bottom:0;" required>
                <button type="button" class="btn btn-secondary btn-sm" onclick="removeTransferLineItem(${itemId})">Remove</button>
            `;
            document.getElementById('transferLineItems').appendChild(row);

            document.getElementById('transferProductSearch').value = '';
            document.getElementById('transferSearchResults').style.display = 'none';
            updateAvailability();
            document.getElementById('qty-' + itemId).focus();
        }

        function removeTransferLineItem(itemId) {
            document.getElementById('tli_row_' + itemId)?.remove();
            transferAddedItemIds.delete(itemId);
            if (transferAddedItemIds.size === 0) {
                document.getElementById('transferLineItemsCard').style.display = 'none';
            }
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#transferProductSearch') && !e.target.closest('#transferSearchResults')) {
                document.getElementById('transferSearchResults').style.display = 'none';
            }
        });

        function htmlEscapeTransfer(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        // Updates every already-added line item's "Available at X" figure
        // and the quantity input's max - runs whenever From Location
        // changes, and once right after a new line item is added.
        function updateAvailability() {
            const fromLocationId = document.getElementById('from_location_id').value;
            const fromLocationName = locationNames[fromLocationId] || '';

            transferAddedItemIds.forEach(itemId => {
                const cell = document.getElementById('avail-' + itemId);
                const qtyInput = document.getElementById('qty-' + itemId);
                if (!cell) return;
                const rows = stockBreakdown[itemId] || [];
                const match = rows.find(r => String(r.location_id) === String(fromLocationId));
                const available = match ? match.quantity : 0;
                cell.textContent = fromLocationId ? ('Available at ' + fromLocationName + ': ' + available) : 'Available: \u2014';
                if (qtyInput) qtyInput.max = fromLocationId ? available : '';
            });
        }

        const oldQuantities = <?= json_encode($oldQuantities, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        Object.keys(oldQuantities).forEach(itemId => {
            const qty = oldQuantities[itemId];
            if (qty !== '' && qty !== null && Number(qty) > 0) {
                addTransferLineItem(Number(itemId), qty);
            }
        });
        updateAvailability();
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
