<?php
/**
 * Views/products/index.php
 * Expects: $items (array), $categories (array), $pagination (array: page, perPage, totalCount, totalPages)
 */
$status = $_GET['status'] ?? null;
$bulkCount = (int) ($_GET['count'] ?? 0);
$currentCategory = $_GET['category_id'] ?? '';
$currentLocation = $_GET['location_id'] ?? '';
$currentSort = $_GET['sort'] ?? 'newest';
$pageTitle = 'Products';
$activeSection = 'inventory';
$activeSubNav = 'products';
$count = count($items);

// Builds a pagination link that keeps the current filters
function productPageUrl(int $page): string
{
    global $currentCategory, $currentLocation, $currentSort;
    return "index.php?module=products&action=index"
        . "&category_id=" . urlencode($currentCategory)
        . "&location_id=" . urlencode($currentLocation)
        . "&sort=" . urlencode($currentSort)
        . "&page=" . $page;
}

require __DIR__ . '/../partials/header.php';
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Products</h1>
                <span class="page-title-count"><?= $pagination['totalCount'] ?> <?= $pagination['totalCount'] === 1 ? 'product' : 'products' ?></span>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="productSearch" placeholder="Search products..." onkeyup="filterProducts()">
                </div>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('filterPanel').classList.toggle('open')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filter
                </button>

                <div class="split-btn">
                    <button type="button" class="btn btn-primary split-btn-main" onclick="openAddProductModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Product
                    </button>
                    <button type="button" class="btn btn-primary split-btn-toggle" onclick="document.getElementById('addProductMenu').classList.toggle('open')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div id="addProductMenu" class="dropdown-menu">
                        <a href="index.php?module=products&action=import">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Import
                        </a>
                        <a href="index.php?module=products&action=export&category_id=<?= urlencode($currentCategory) ?>&location_id=<?= urlencode($currentLocation) ?>&sort=<?= urlencode($currentSort) ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Export
                        </a>
                    </div>
                </div>

                <button type="button" class="help-btn" onclick="document.getElementById('helpModal').classList.add('open')" title="How Products works">?</button>
            </div>
        </div>

        <div id="filterPanel" class="filter-panel <?= ($currentCategory !== '' || $currentLocation !== '') ? 'open' : '' ?>">
            <form method="GET" action="index.php" class="filter-form">
                <input type="hidden" name="module" value="products">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($currentSort) ?>">
                <div>
                    <label>Category</label>
                    <select name="category_id" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <option value="none" <?= $currentCategory === 'none' ? 'selected' : '' ?>>Uncategorized</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= ($currentCategory == $cat['category_id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Location</label>
                    <select name="location_id" onchange="this.form.submit()">
                        <option value="">All Locations</option>
                        <option value="none" <?= $currentLocation === 'none' ? 'selected' : '' ?>>Unassigned</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['location_id'] ?>" <?= ($currentLocation == $loc['location_id']) ? 'selected' : '' ?>><?= htmlspecialchars($loc['location_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($currentCategory !== '' || $currentLocation !== ''): ?>
                    <a href="index.php?module=products&action=index" class="btn btn-secondary btn-sm" style="align-self:flex-end;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($status === 'created'): ?>
            <div class="alert alert-success">Product created successfully.</div>
        <?php elseif ($status === 'updated'): ?>
            <div class="alert alert-success">Product updated successfully.</div>
        <?php elseif ($status === 'deleted'): ?>
            <div class="alert alert-success">Product deleted successfully.</div>
        <?php elseif ($status === 'bulk_updated'): ?>
            <div class="alert alert-success"><?= $bulkCount ?> product<?= $bulkCount === 1 ? '' : 's' ?> moved to the new category.</div>
        <?php endif; ?>

        <div class="sort-bar">
            <label for="sortSelect">Sort by</label>
            <select id="sortSelect" onchange='location.href = <?= json_encode(productPageUrl(1)) ?>.replace(/sort=[^&]*/, "sort=" + this.value)'>
                <option value="newest" <?= $currentSort === 'newest' ? 'selected' : '' ?>>Recently added</option>
                <option value="oldest" <?= $currentSort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                <option value="name_asc" <?= $currentSort === 'name_asc' ? 'selected' : '' ?>>Model: A–Z</option>
                <option value="name_desc" <?= $currentSort === 'name_desc' ? 'selected' : '' ?>>Model: Z–A</option>
                <option value="category_asc" <?= $currentSort === 'category_asc' ? 'selected' : '' ?>>Category: A–Z</option>
            </select>
        </div>

        <form method="POST" id="bulkForm">
            <div id="bulkBar" class="bulk-bar">
                <span><strong id="bulkCount">0</strong> selected</span>
                <select name="bulk_category_id">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" formaction="index.php?module=products&action=bulkUpdateCategory" class="btn btn-secondary btn-sm">Change Category</button>
            </div>

            <div class="table-card">
                <table id="productTable">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="selectAll" class="row-check" onclick="toggleAllProducts(this)"></th>
                            <th style="width:60px;">ID</th>
                            <th>Model</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr class="empty-row">
                                <td colspan="8">No products match these filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $it): ?>
                                <tr class="product-row" onclick="handleProductRowClick(event, <?= $it['item_id'] ?>)">
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $it['item_id'] ?>" class="row-check product-check" onchange="updateBulkBar()"></td>
                                    <td class="cell-id"><?= (int) $it['item_id'] ?></td>
                                    <td><strong><?= htmlspecialchars($it['model']) ?></strong></td>
                                    <td class="cell-muted"><?= htmlspecialchars($it['category_name'] ?? 'Uncategorized') ?></td>
                                    <td class="cell-muted"><?= htmlspecialchars($it['brand_name'] ?? '—') ?></td>
                                    <td class="cell-muted"><?= htmlspecialchars($it['type_name'] ?? '—') ?></td>
                                    <td class="cell-muted"><?= htmlspecialchars($it['location_name'] ?? '—') ?></td>
                                    <td class="actions">
                                        <button type="button" class="btn btn-edit btn-sm" onclick="openEditProductModal(<?= $it['item_id'] ?>)">Edit</button>
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
                <span>Showing <?= $startRow ?>–<?= $endRow ?> of <?= $pagination['totalCount'] ?> products</span>
                <div class="pagination-controls">
                    <a href="<?= productPageUrl(max(1, $pagination['page'] - 1)) ?>" class="page-btn <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">&lsaquo; Prev</a>
                    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                        <a href="<?= productPageUrl($p) ?>" class="page-btn <?= $p === $pagination['page'] ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="<?= productPageUrl(min($pagination['totalPages'], $pagination['page'] + 1)) ?>" class="page-btn <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">Next &rsaquo;</a>
                </div>
            </div>
        <?php endif; ?>

        <div id="viewProductModal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('open')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3 id="vpm-name">Product</h3>
                    <button type="button" class="modal-close" onclick="document.getElementById('viewProductModal').classList.remove('open')">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="margin-bottom:16px;">
                        <div style="font-size:13px;color:var(--text-muted);">Item ID: <strong id="vpm-itemid" style="color:var(--text-dark);"></strong> &middot; Category: <strong id="vpm-category" style="color:var(--text-dark);"></strong></div>
                    </div>

                    <table style="width:100%;font-size:13.5px;border-collapse:collapse;">
                        <tbody>
                            <tr><td style="padding:6px 0;color:var(--text-muted);width:140px;">Brand</td><td id="vpm-brand" style="padding:6px 0;font-weight:600;"></td></tr>
                            <tr><td style="padding:6px 0;color:var(--text-muted);">Item Type</td><td id="vpm-itemtype" style="padding:6px 0;font-weight:600;"></td></tr>
                            <tr><td style="padding:6px 0;color:var(--text-muted);">Location</td><td id="vpm-location" style="padding:6px 0;font-weight:600;"></td></tr>
                        </tbody>
                        <tbody id="vpm-specs-body">
                            <tr><td style="padding:6px 0;color:var(--text-muted);">Energy Rating</td><td id="vpm-energyrating" style="padding:6px 0;font-weight:600;"></td></tr>
                            <tr><td style="padding:6px 0;color:var(--text-muted);">Monthly Consumption</td><td id="vpm-consumption" style="padding:6px 0;font-weight:600;"></td></tr>
                            <tr><td style="padding:6px 0;color:var(--text-muted);">Cooling Capacity</td><td id="vpm-cooling" style="padding:6px 0;font-weight:600;"></td></tr>
                            <tr><td style="padding:6px 0;color:var(--text-muted);">Refrigerant</td><td id="vpm-refrigerant" style="padding:6px 0;font-weight:600;"></td></tr>
                            <tr><td style="padding:6px 0;color:var(--text-muted);">Installation Type</td><td id="vpm-installtype" style="padding:6px 0;font-weight:600;"></td></tr>
                            <tr><td style="padding:6px 0;color:var(--text-muted);">Power Input</td><td id="vpm-powerinput" style="padding:6px 0;font-weight:600;"></td></tr>
                            <tr><td style="padding:6px 0;color:var(--text-muted);">Year</td><td id="vpm-year" style="padding:6px 0;font-weight:600;"></td></tr>
                        </tbody>
                    </table>

                    <div class="form-actions" style="margin-top:18px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('viewProductModal').classList.remove('open')">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="addProductModal" class="modal-overlay" onclick="if(event.target===this) closeModal('addProductModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Add Product</h3>
                    <button type="button" class="modal-close" onclick="closeModal('addProductModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addProductForm" action="index.php?module=products&action=create">
                        <label for="ap_category_id">Category</label>
                        <select id="ap_category_id" name="category_id" required>
                            <option value="" disabled selected>Select a category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="ap_model">Model</label>
                        <input type="text" id="ap_model" name="model" placeholder="e.g. FTKC50UVM" required>

                        <label for="ap_brand_id">Brand <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <select id="ap_brand_id" name="brand_id">
                            <option value="">No brand</option>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?= $b['brand_id'] ?>"><?= htmlspecialchars($b['brand_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="ap_item_type_id">Item Type <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <select id="ap_item_type_id" name="item_type_id" onchange="updateSpecsVisibility('ap')">
                            <option value="">No item type</option>
                            <?php foreach ($itemTypes as $t): ?>
                                <option value="<?= $t['item_type_id'] ?>" data-type-name="<?= htmlspecialchars($t['type_name']) ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="ap_location_id">Location <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <select id="ap_location_id" name="location_id">
                            <option value="">No location</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['location_id'] ?>"><?= htmlspecialchars($loc['location_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div id="ap_specs_section">
                            <h3 style="margin:24px 0 4px;font-size:15px;color:var(--text-muted);">Technical Specifications <span style="font-weight:400;">(required for Asset item types)</span></h3>

                            <label for="ap_energy_rating">Energy Rating</label>
                            <input type="text" id="ap_energy_rating" name="energy_rating" placeholder="e.g. 5 Star">

                            <label for="ap_monthly_consumption">Monthly Consumption (kWh)</label>
                            <input type="number" id="ap_monthly_consumption" name="monthly_consumption" min="0" step="0.01" placeholder="e.g. 120.50">

                            <label for="ap_cooling_capacity">Cooling Capacity</label>
                            <input type="text" id="ap_cooling_capacity" name="cooling_capacity" placeholder="e.g. 1.5 HP (12,000 BTU/hr)">

                            <label for="ap_refrigerant">Refrigerant</label>
                            <input type="text" id="ap_refrigerant" name="refrigerant" placeholder="e.g. R32">

                            <label for="ap_installation_type">Installation Type</label>
                            <input type="text" id="ap_installation_type" name="installation_type" placeholder="e.g. Wall Mounted">

                            <label for="ap_power_input">Power Input</label>
                            <input type="text" id="ap_power_input" name="power_input" placeholder="e.g. 220-240V ~50Hz">

                            <label for="ap_year">Year</label>
                            <input type="number" id="ap_year" name="year" min="1990" max="2100" step="1" placeholder="e.g. 2024">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Product</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addProductModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editProductModal" class="modal-overlay" onclick="if(event.target===this) closeModal('editProductModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Edit Product</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editProductModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editProductForm" action="index.php?module=products&action=edit">
                        <input type="hidden" name="item_id" id="ep_item_id" value="">

                        <label for="ep_category_id">Category</label>
                        <select id="ep_category_id" name="category_id" required>
                            <option value="" disabled>Select a category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="ep_model">Model</label>
                        <input type="text" id="ep_model" name="model" required>

                        <label for="ep_brand_id">Brand <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <select id="ep_brand_id" name="brand_id">
                            <option value="">No brand</option>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?= $b['brand_id'] ?>"><?= htmlspecialchars($b['brand_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="ep_item_type_id">Item Type <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <select id="ep_item_type_id" name="item_type_id" onchange="updateSpecsVisibility('ep')">
                            <option value="">No item type</option>
                            <?php foreach ($itemTypes as $t): ?>
                                <option value="<?= $t['item_type_id'] ?>" data-type-name="<?= htmlspecialchars($t['type_name']) ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="ep_location_id">Location <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <select id="ep_location_id" name="location_id">
                            <option value="">No location</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['location_id'] ?>"><?= htmlspecialchars($loc['location_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div id="ep_specs_section">
                            <h3 style="margin:24px 0 4px;font-size:15px;color:var(--text-muted);">Technical Specifications <span style="font-weight:400;">(required for Asset item types)</span></h3>

                            <label for="ep_energy_rating">Energy Rating</label>
                            <input type="text" id="ep_energy_rating" name="energy_rating" placeholder="e.g. 5 Star">

                            <label for="ep_monthly_consumption">Monthly Consumption (kWh)</label>
                            <input type="number" id="ep_monthly_consumption" name="monthly_consumption" min="0" step="0.01" placeholder="e.g. 120.50">

                            <label for="ep_cooling_capacity">Cooling Capacity</label>
                            <input type="text" id="ep_cooling_capacity" name="cooling_capacity" placeholder="e.g. 1.5 HP (12,000 BTU/hr)">

                            <label for="ep_refrigerant">Refrigerant</label>
                            <input type="text" id="ep_refrigerant" name="refrigerant" placeholder="e.g. R32">

                            <label for="ep_installation_type">Installation Type</label>
                            <input type="text" id="ep_installation_type" name="installation_type" placeholder="e.g. Wall Mounted">

                            <label for="ep_power_input">Power Input</label>
                            <input type="text" id="ep_power_input" name="power_input" placeholder="e.g. 220-240V ~50Hz">

                            <label for="ep_year">Year</label>
                            <input type="number" id="ep_year" name="year" min="1990" max="2100" step="1" placeholder="e.g. 2024">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Product</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('editProductModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="helpModal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('open')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>How Products works</h3>
                    <button type="button" class="modal-close" onclick="document.getElementById('helpModal').classList.remove('open')">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Products is where every item you stock lives — with categories and AC technical specs all in one place.</p>

                    <h4>What you can do here</h4>
                    <ul>
                        <li>Add products manually, or import many at once via CSV or XLSX.</li>
                        <li>Every product is identified by its <strong>Model</strong> — there's no separate product name.</li>
                        <li>Record Brand, Item Type, Location, and AC technical specs on each product.</li>
                        <li>When Item Type is set to <strong>Asset</strong>, the Technical Specifications section appears and every field in it becomes required. It's hidden (and optional) for <strong>Consumable</strong> or when no item type is set.</li>
                        <li>Select multiple products with the checkboxes to bulk-move them to another category.</li>
                    </ul>

                    <h4>Getting started</h4>
                    <ol>
                        <li>Click <strong>Add Product</strong> for a single new product, or use the arrow beside it to <strong>Import</strong> a spreadsheet of many products at once.</li>
                        <li>Fill in the model and category — brand, item type, and location are all optional and can be added later.</li>
                        <li>Use the search bar and <strong>Filter</strong> panel to quickly find products by model, category, or location.</li>
                    </ol>
                </div>
            </div>
        </div>

        <script>
        const productsData = <?= json_encode(array_column($items, null, 'item_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function handleProductRowClick(event, id) {
            // Ignore clicks on checkboxes, links, or buttons inside the row -
            // only clicking blank row space should open the modal
            if (event.target.closest('input, a, button')) return;
            showProductModal(id);
        }

        function showProductModal(id) {
            const p = productsData[id];
            if (!p) return;

            document.getElementById('vpm-name').textContent = p.model;
            document.getElementById('vpm-itemid').textContent = p.item_id;
            document.getElementById('vpm-category').textContent = p.category_name || 'Uncategorized';
            document.getElementById('vpm-brand').textContent = p.brand_name || '—';
            document.getElementById('vpm-itemtype').textContent = p.type_name || '—';
            document.getElementById('vpm-location').textContent = p.location_name || '—';
            document.getElementById('vpm-energyrating').textContent = p.energy_rating || '—';
            document.getElementById('vpm-consumption').textContent = p.monthly_consumption ? (p.monthly_consumption + ' kWh/mo') : '—';
            document.getElementById('vpm-cooling').textContent = p.cooling_capacity || '—';
            document.getElementById('vpm-refrigerant').textContent = p.refrigerant || '—';
            document.getElementById('vpm-installtype').textContent = p.installation_type || '—';
            document.getElementById('vpm-powerinput').textContent = p.power_input || '—';
            document.getElementById('vpm-year').textContent = p.year || '—';

            document.getElementById('vpm-specs-body').style.display = (p.type_name === 'Asset') ? '' : 'none';

            document.getElementById('viewProductModal').classList.add('open');
        }

        function filterProducts() {
            const q = document.getElementById('productSearch').value.toLowerCase();
            document.querySelectorAll('#productTable tbody tr').forEach(row => {
                if (row.classList.contains('empty-row')) return;
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        function closeModal(id) {
            document.getElementById(id)?.classList.remove('open');
        }

        // Shows the Technical Specifications section (and marks its fields
        // required) only when the selected Item Type is "Asset" - hidden and
        // optional for Consumable or no item type selected. $prefix is 'ap'
        // (Add modal) or 'ep' (Edit modal).
        function updateSpecsVisibility(prefix) {
            const typeSelect = document.getElementById(prefix + '_item_type_id');
            const selectedOption = typeSelect.options[typeSelect.selectedIndex];
            const isAsset = selectedOption && selectedOption.dataset.typeName === 'Asset';

            const section = document.getElementById(prefix + '_specs_section');
            section.style.display = isAsset ? '' : 'none';

            const specFields = ['energy_rating', 'cooling_capacity', 'refrigerant', 'installation_type', 'power_input'];
            specFields.forEach(name => {
                document.getElementById(prefix + '_' + name).required = isAsset;
            });
            document.getElementById(prefix + '_monthly_consumption').required = isAsset;
            document.getElementById(prefix + '_year').required = isAsset;
        }

        function openAddProductModal() {
            document.getElementById('addProductForm')?.reset();
            updateSpecsVisibility('ap');
            document.getElementById('addProductModal').classList.add('open');
        }

        function openEditProductModal(id) {
            const p = productsData[id];
            if (!p) return;

            document.getElementById('ep_item_id').value = id;
            document.getElementById('editProductForm').action = 'index.php?module=products&action=edit&id=' + id;
            document.getElementById('ep_model').value = p.model || '';
            document.getElementById('ep_brand_id').value = p.brand_id || '';
            document.getElementById('ep_item_type_id').value = p.item_type_id || '';
            document.getElementById('ep_location_id').value = p.location_id || '';
            document.getElementById('ep_energy_rating').value = p.energy_rating || '';
            document.getElementById('ep_monthly_consumption').value = p.monthly_consumption || '';
            document.getElementById('ep_cooling_capacity').value = p.cooling_capacity || '';
            document.getElementById('ep_refrigerant').value = p.refrigerant || '';
            document.getElementById('ep_installation_type').value = p.installation_type || '';
            document.getElementById('ep_power_input').value = p.power_input || '';
            document.getElementById('ep_year').value = p.year || '';
            document.getElementById('ep_category_id').value = p.category_id || '';

            updateSpecsVisibility('ep');
            document.getElementById('editProductModal').classList.add('open');
        }

        function toggleAllProducts(source) {
            document.querySelectorAll('.product-check').forEach(cb => cb.checked = source.checked);
            updateBulkBar();
        }

        function updateBulkBar() {
            const checked = document.querySelectorAll('.product-check:checked').length;
            const bar = document.getElementById('bulkBar');
            document.getElementById('bulkCount').textContent = checked;
            bar.classList.toggle('visible', checked > 0);

            const all = document.querySelectorAll('.product-check').length;
            document.getElementById('selectAll').checked = checked > 0 && checked === all;
        }

        // Close dropdown/modal on outside click or Escape
        document.addEventListener('click', function (e) {
            const menu = document.getElementById('addProductMenu');
            if (menu && menu.classList.contains('open') && !e.target.closest('.split-btn')) {
                menu.classList.remove('open');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.getElementById('addProductMenu')?.classList.remove('open');
                document.getElementById('helpModal')?.classList.remove('open');
                document.getElementById('viewProductModal')?.classList.remove('open');
                document.getElementById('addProductModal')?.classList.remove('open');
                document.getElementById('editProductModal')?.classList.remove('open');
            }
        });
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
