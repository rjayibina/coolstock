<?php
/**
 * Views/products/index.php
 * Expects: $items (array), $categories (array), $pagination (array: page, perPage, totalCount, totalPages)
 */
$status = $_GET['status'] ?? null;
$bulkCount = (int) ($_GET['count'] ?? 0);
$bulkSkipped = (int) ($_GET['skipped'] ?? 0);
$currentCategory = $_GET['category_id'] ?? '';
$currentStockStatus = $_GET['stock_status'] ?? '';
$currentSerial = $_GET['has_serial'] ?? '';
$currentSort = $_GET['sort'] ?? 'newest';
$pageTitle = 'Products';
$activeSection = 'inventory';
$activeSubNav = 'products';
$count = count($items);

// Builds a pagination link that keeps the current filters
function productPageUrl(int $page): string
{
    global $currentCategory, $currentStockStatus, $currentSerial, $currentSort;
    return "index.php?module=products&action=index"
        . "&category_id=" . urlencode($currentCategory)
        . "&stock_status=" . urlencode($currentStockStatus)
        . "&has_serial=" . urlencode($currentSerial)
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
                        <a href="index.php?module=products&action=export&category_id=<?= urlencode($currentCategory) ?>&stock_status=<?= urlencode($currentStockStatus) ?>&has_serial=<?= urlencode($currentSerial) ?>&sort=<?= urlencode($currentSort) ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Export
                        </a>
                    </div>
                </div>

                <button type="button" class="help-btn" onclick="document.getElementById('helpModal').classList.add('open')" title="How Products works">?</button>
            </div>
        </div>

        <div id="filterPanel" class="filter-panel <?= ($currentCategory !== '' || $currentStockStatus !== '' || $currentSerial !== '') ? 'open' : '' ?>">
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
                    <label>Stock Status</label>
                    <select name="stock_status" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="in_stock" <?= $currentStockStatus === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                        <option value="low" <?= $currentStockStatus === 'low' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="out_of_stock" <?= $currentStockStatus === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <div>
                    <label>Serial</label>
                    <select name="has_serial" onchange="this.form.submit()">
                        <option value="">All Products</option>
                        <option value="1" <?= $currentSerial === '1' ? 'selected' : '' ?>>Has Serial</option>
                        <option value="0" <?= $currentSerial === '0' ? 'selected' : '' ?>>No Serial</option>
                    </select>
                </div>
                <?php if ($currentCategory !== '' || $currentStockStatus !== '' || $currentSerial !== ''): ?>
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
        <?php elseif ($status === 'stock_updated'): ?>
            <?php $stockType = $_GET['type'] ?? ''; ?>
            <div class="alert alert-success"><?= $stockType === 'stock_out' ? 'Stock removed successfully.' : 'Stock added successfully.' ?></div>
        <?php elseif ($status === 'bulk_deleted'): ?>
            <div class="alert alert-success"><?= $bulkCount ?> product<?= $bulkCount === 1 ? '' : 's' ?> deleted.</div>
        <?php elseif ($status === 'bulk_updated'): ?>
            <div class="alert alert-success"><?= $bulkCount ?> product<?= $bulkCount === 1 ? '' : 's' ?> moved to the new category.</div>
        <?php endif; ?>

        <div class="sort-bar">
            <label for="sortSelect">Sort by</label>
            <select id="sortSelect" onchange='location.href = <?= json_encode(productPageUrl(1)) ?>.replace(/sort=[^&]*/, "sort=" + this.value)'>
                <option value="newest" <?= $currentSort === 'newest' ? 'selected' : '' ?>>Recently added</option>
                <option value="oldest" <?= $currentSort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                <option value="name_asc" <?= $currentSort === 'name_asc' ? 'selected' : '' ?>>Name: A–Z</option>
                <option value="name_desc" <?= $currentSort === 'name_desc' ? 'selected' : '' ?>>Name: Z–A</option>
                <option value="stock_asc" <?= $currentSort === 'stock_asc' ? 'selected' : '' ?>>Stock: low to high</option>
                <option value="stock_desc" <?= $currentSort === 'stock_desc' ? 'selected' : '' ?>>Stock: high to low</option>
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
                <button type="submit" formaction="index.php?module=products&action=bulkDelete" class="btn btn-danger btn-sm"
                        onclick="return confirm('Delete the selected products? This cannot be undone.');">Delete Selected</button>
            </div>

            <div class="table-card">
                <table id="productTable">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="selectAll" class="row-check" onclick="toggleAllProducts(this)"></th>
                            <th></th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Serial No.</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th style="width:190px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr class="empty-row">
                                <td colspan="9">No products match these filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $it): ?>
                                <?php
                                $isOut = (int) $it['quantity_on_hand'] === 0;
                                $isLow = !$isOut && $it['quantity_on_hand'] <= $it['minimum_stock_level'];
                                ?>
                                <tr class="product-row" onclick="handleProductRowClick(event, <?= $it['item_id'] ?>)">
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $it['item_id'] ?>" class="row-check product-check" onchange="updateBulkBar()"></td>
                                    <td>
                                        <?php if (!empty($it['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($it['image_path']) ?>" alt="" class="product-thumb">
                                        <?php else: ?>
                                            <div class="product-thumb product-thumb-placeholder">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($it['item_name']) ?></strong></td>
                                    <td class="cell-muted"><?= htmlspecialchars($it['category_name'] ?? 'Uncategorized') ?></td>
                                    <td class="cell-muted"><?= htmlspecialchars($it['unit_of_measure'] ?? '—') ?></td>
                                    <td class="cell-muted"><?= htmlspecialchars($it['serial_number'] ?? '—') ?></td>
                                    <td class="cell-id"><?= (int) $it['quantity_on_hand'] ?> <span class="cell-muted">/ min <?= (int) $it['minimum_stock_level'] ?></span></td>
                                    <td>
                                        <?php if ($isOut): ?>
                                            <span class="badge" style="background:var(--danger-bg);color:var(--danger);">Out of stock</span>
                                        <?php elseif ($isLow): ?>
                                            <span class="badge" style="background:var(--warning-bg);color:var(--warning);">Low stock</span>
                                        <?php else: ?>
                                            <span class="badge" style="background:var(--success-bg);color:var(--success);">In stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <button type="button" class="btn btn-sm" style="background:var(--success-bg);color:var(--success);" title="Stock in" onclick="openStockModal(<?= $it['item_id'] ?>, 'stock_in')">+</button>
                                        <button type="button" class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger);" title="Stock out" onclick="openStockModal(<?= $it['item_id'] ?>, 'stock_out')">&minus;</button>
                                        <button type="button" class="btn btn-edit btn-sm" onclick="openEditProductModal(<?= $it['item_id'] ?>)">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteProductModal(<?= $it['item_id'] ?>)">Delete</button>
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
                    <div style="display:flex;gap:16px;margin-bottom:16px;align-items:flex-start;">
                        <img id="vpm-image" src="" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid var(--border);flex-shrink:0;">
                        <div id="vpm-image-placeholder" class="product-thumb product-thumb-placeholder" style="width:80px;height:80px;flex-shrink:0;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        </div>
                        <div>
                            <div style="margin-bottom:6px;"><span id="vpm-status" class="badge"></span></div>
                            <div style="font-size:13px;color:var(--text-muted);">Category: <strong id="vpm-category" style="color:var(--text-dark);"></strong></div>
                        </div>
                    </div>

                    <p id="vpm-description" style="color:var(--text-muted);"></p>

                    <table style="width:100%;font-size:13.5px;border-collapse:collapse;">
                        <tr><td style="padding:6px 0;color:var(--text-muted);width:140px;">Unit of Measure</td><td id="vpm-unit" style="padding:6px 0;font-weight:600;"></td></tr>
                        <tr><td style="padding:6px 0;color:var(--text-muted);">Quantity on Hand</td><td id="vpm-stock" style="padding:6px 0;font-weight:600;"></td></tr>
                        <tr><td style="padding:6px 0;color:var(--text-muted);">Minimum Stock Level</td><td id="vpm-min" style="padding:6px 0;font-weight:600;"></td></tr>
                        <tr><td style="padding:6px 0;color:var(--text-muted);">Serial Number</td><td id="vpm-serial" style="padding:6px 0;font-weight:600;"></td></tr>
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
                    <form method="POST" action="index.php?module=products&action=create" enctype="multipart/form-data">
                        <label for="ap_product_image">Product Image</label>
                        <input type="file" id="ap_product_image" name="product_image" accept="image/jpeg,image/png,image/gif,image/webp">

                        <?php
                        $selectedCategoryId = '';
                        $selectedCategoryName = '';
                        require __DIR__ . '/../partials/category-combobox.php';
                        ?>

                        <label for="ap_item_name">Product Name</label>
                        <input type="text" id="ap_item_name" name="item_name"
                               placeholder="e.g. R410A Refrigerant Tank" required>

                        <label for="ap_description">Description</label>
                        <textarea id="ap_description" name="description"
                                  placeholder="Optional notes about this product"></textarea>

                        <label for="ap_unit_of_measure">Unit of Measure</label>
                        <input type="text" id="ap_unit_of_measure" name="unit_of_measure"
                               placeholder="e.g. pcs, kg, box">

                        <label for="ap_quantity_on_hand">Quantity on Hand</label>
                        <input type="number" id="ap_quantity_on_hand" name="quantity_on_hand" min="0" step="1" value="0" required>

                        <label for="ap_minimum_stock_level">Minimum Stock Level</label>
                        <input type="number" id="ap_minimum_stock_level" name="minimum_stock_level" min="0" step="1" value="0" required>

                        <label for="ap_serial_number">Serial Number <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <input type="text" id="ap_serial_number" name="serial_number"
                               placeholder="e.g. SN-2026-00123">

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
                    <form method="POST" id="editProductForm" action="index.php?module=products&action=edit" enctype="multipart/form-data">
                        <input type="hidden" name="item_id" id="ep_item_id" value="">

                        <label>Product Image</label>
                        <div id="ep_current_image_wrap" style="display:none;align-items:center;gap:12px;margin-bottom:10px;">
                            <img id="ep_current_image" src="" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:500;font-size:13px;color:var(--text-muted);margin:0;">
                                <input type="checkbox" name="remove_image" value="1" style="width:auto;">
                                Remove this image
                            </label>
                        </div>
                        <input type="file" id="ep_product_image" name="product_image" accept="image/jpeg,image/png,image/gif,image/webp">
                        <div style="font-size:12px;color:var(--text-muted);margin-top:-14px;margin-bottom:18px;">Leave empty to keep the current image, or choose a new file to replace it.</div>

                        <label for="ep_category_id">Category <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <select id="ep_category_id" name="category_id">
                            <option value="">Uncategorized</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="ep_item_name">Product Name</label>
                        <input type="text" id="ep_item_name" name="item_name" required>

                        <label for="ep_description">Description</label>
                        <textarea id="ep_description" name="description"></textarea>

                        <label for="ep_unit_of_measure">Unit of Measure</label>
                        <input type="text" id="ep_unit_of_measure" name="unit_of_measure">

                        <label for="ep_quantity_on_hand">Quantity on Hand</label>
                        <input type="number" id="ep_quantity_on_hand" name="quantity_on_hand" min="0" step="1" required>

                        <label for="ep_minimum_stock_level">Minimum Stock Level</label>
                        <input type="number" id="ep_minimum_stock_level" name="minimum_stock_level" min="0" step="1" required>

                        <label for="ep_serial_number">Serial Number <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <input type="text" id="ep_serial_number" name="serial_number">

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Product</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('editProductModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="deleteProductModal" class="modal-overlay" onclick="if(event.target===this) closeModal('deleteProductModal')">
            <div class="modal-dialog modal-dialog-sm">
                <div class="modal-header">
                    <h3>Delete Product</h3>
                    <button type="button" class="modal-close" onclick="closeModal('deleteProductModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Delete <strong id="dp_name"></strong>? This cannot be undone.</p>
                    <div class="form-actions">
                        <a id="dp_confirm_link" href="#" class="btn btn-danger-solid">Delete</a>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('deleteProductModal')">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="stockModal" class="modal-overlay" onclick="if(event.target===this) closeModal('stockModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3 id="sm_title">Add Stock</h3>
                    <button type="button" class="modal-close" onclick="closeModal('stockModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="stockForm" action="index.php?module=transactions&action=create">
                        <input type="hidden" name="item_id" id="sm_item_id" value="">
                        <input type="hidden" name="transaction_type" id="sm_transaction_type" value="stock_in">
                        <input type="hidden" name="redirect_to" value="products">

                        <div class="stock-tabs">
                            <button type="button" class="stock-tab stock-tab-in" id="sm_tab_in" onclick="setStockMode('stock_in')">+ Add Stock</button>
                            <button type="button" class="stock-tab stock-tab-out" id="sm_tab_out" onclick="setStockMode('stock_out')">&minus; Remove Stock</button>
                        </div>

                        <label for="sm_quantity">Quantity</label>
                        <input type="number" id="sm_quantity" name="quantity" min="1" step="1" placeholder="Enter quantity" required>

                        <label for="sm_transaction_date">Transaction Date</label>
                        <input type="date" id="sm_transaction_date" name="transaction_date" readonly
                               onkeydown="return false" style="background: var(--bg-subtle, #F5F6FB); color: var(--text-muted); cursor: not-allowed;" required>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:-14px;margin-bottom:18px;">Stock movements are always logged with today's date.</div>

                        <label for="sm_notes">Notes <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <textarea id="sm_notes" name="notes" placeholder="Optional notes about this stock movement"></textarea>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-success" id="sm_submit">+ Add Stock</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('stockModal')">Back</button>
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
                    <p>Products is where every item you stock lives — with quantities, categories, and photos all in one place.</p>

                    <h4>What you can do here</h4>
                    <ul>
                        <li>Add products manually, or import many at once via CSV or XLSX.</li>
                        <li>Track quantity on hand and get a <strong>Low Stock</strong> flag once it hits your minimum stock level.</li>
                        <li>Record a serial number on a product for traceability.</li>
                        <li>Attach a photo to each product.</li>
                        <li>Use the quick <strong>+</strong> / <strong>−</strong> buttons on a row to log a Stock In or Stock Out without leaving this page.</li>
                        <li>Select multiple products with the checkboxes to bulk-delete or bulk-move them to another category.</li>
                    </ul>

                    <h4>Getting started</h4>
                    <ol>
                        <li>Click <strong>Add Product</strong> for a single new product, or use the arrow beside it to <strong>Import</strong> a spreadsheet of many products at once.</li>
                        <li>Fill in the name, category, and starting quantity — description, unit, and image are optional and can be added later.</li>
                        <li>Use the search bar and <strong>Filter</strong> panel to quickly find products by name, category, stock level, or whether they have a serial number recorded.</li>
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

            document.getElementById('vpm-name').textContent = p.item_name;
            document.getElementById('vpm-category').textContent = p.category_name || 'Uncategorized';
            document.getElementById('vpm-description').textContent = p.description || 'No description provided.';
            document.getElementById('vpm-unit').textContent = p.unit_of_measure || '—';
            document.getElementById('vpm-stock').textContent = p.quantity_on_hand;
            document.getElementById('vpm-min').textContent = p.minimum_stock_level;
            document.getElementById('vpm-serial').textContent = p.serial_number || '—';

            const statusEl = document.getElementById('vpm-status');
            const qty = parseInt(p.quantity_on_hand);
            const isOut = qty === 0;
            const isLow = !isOut && qty <= parseInt(p.minimum_stock_level);

            if (isOut) {
                statusEl.textContent = 'Out of stock';
                statusEl.style.background = 'var(--danger-bg)';
                statusEl.style.color = 'var(--danger)';
            } else if (isLow) {
                statusEl.textContent = 'Low stock';
                statusEl.style.background = 'var(--warning-bg)';
                statusEl.style.color = 'var(--warning)';
            } else {
                statusEl.textContent = 'In stock';
                statusEl.style.background = 'var(--success-bg)';
                statusEl.style.color = 'var(--success)';
            }

            const img = document.getElementById('vpm-image');
            const placeholder = document.getElementById('vpm-image-placeholder');
            if (p.image_path) {
                img.src = p.image_path;
                img.style.display = '';
                placeholder.style.display = 'none';
            } else {
                img.style.display = 'none';
                placeholder.style.display = 'flex';
            }

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

        function openAddProductModal() {
            document.getElementById('addProductModal').classList.add('open');
        }

        function openEditProductModal(id) {
            const p = productsData[id];
            if (!p) return;

            document.getElementById('ep_item_id').value = id;
            document.getElementById('editProductForm').action = 'index.php?module=products&action=edit&id=' + id;
            document.getElementById('ep_item_name').value = p.item_name || '';
            document.getElementById('ep_description').value = p.description || '';
            document.getElementById('ep_unit_of_measure').value = p.unit_of_measure || '';
            document.getElementById('ep_quantity_on_hand').value = p.quantity_on_hand;
            document.getElementById('ep_minimum_stock_level').value = p.minimum_stock_level;
            document.getElementById('ep_serial_number').value = p.serial_number || '';
            document.getElementById('ep_category_id').value = p.category_id || '';
            document.getElementById('ep_product_image').value = '';

            const removeCheckbox = document.querySelector('#editProductModal input[name="remove_image"]');
            if (removeCheckbox) removeCheckbox.checked = false;

            const imgWrap = document.getElementById('ep_current_image_wrap');
            const img = document.getElementById('ep_current_image');
            if (p.image_path) {
                img.src = p.image_path;
                imgWrap.style.display = 'flex';
            } else {
                img.src = '';
                imgWrap.style.display = 'none';
            }

            document.getElementById('editProductModal').classList.add('open');
        }

        function openDeleteProductModal(id) {
            const p = productsData[id];
            if (!p) return;

            document.getElementById('dp_name').textContent = p.item_name;
            document.getElementById('dp_confirm_link').href = 'index.php?module=products&action=delete&id=' + id;
            document.getElementById('deleteProductModal').classList.add('open');
        }

        function openStockModal(id, mode) {
            const p = productsData[id];
            if (!p) return;

            document.getElementById('sm_item_id').value = id;
            document.getElementById('sm_quantity').value = '';
            document.getElementById('sm_notes').value = '';
            document.getElementById('sm_transaction_date').value = new Date().toISOString().slice(0, 10);
            setStockMode(mode);
            document.getElementById('stockModal').classList.add('open');
        }

        function setStockMode(mode) {
            const itemId = document.getElementById('sm_item_id').value;
            const p = productsData[itemId];
            const name = p ? p.item_name : '';

            document.getElementById('sm_transaction_type').value = mode;
            document.getElementById('sm_title').textContent = (mode === 'stock_in' ? 'Add Stock' : 'Remove Stock') + (name ? ': ' + name : '');

            document.getElementById('sm_tab_in').classList.toggle('active', mode === 'stock_in');
            document.getElementById('sm_tab_out').classList.toggle('active', mode === 'stock_out');

            const submitBtn = document.getElementById('sm_submit');
            if (mode === 'stock_in') {
                submitBtn.textContent = '+ Add Stock';
                submitBtn.className = 'btn btn-success';
            } else {
                submitBtn.textContent = '\u2212 Remove Stock';
                submitBtn.className = 'btn btn-danger-solid';
            }
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
                document.getElementById('deleteProductModal')?.classList.remove('open');
                document.getElementById('stockModal')?.classList.remove('open');
            }
        });
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
