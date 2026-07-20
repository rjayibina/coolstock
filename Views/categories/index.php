<?php
/**
 * Views/categories/index.php
 * Expects: $categories (array of rows from item_categories, each with product_count)
 */
$status = $_GET['status'] ?? null;
$bulkCount = (int) ($_GET['count'] ?? 0);
$bulkSkipped = (int) ($_GET['skipped'] ?? 0);
$currentHasProducts = $_GET['has_products'] ?? '';
$pageTitle = 'Categories';
$activeSection = 'inventory';
$activeSubNav = 'categories';
$count = count($categories);
require __DIR__ . '/../partials/header.php';
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Categories</h1>
                <span class="page-title-count"><?= $count ?> <?= $count === 1 ? 'category' : 'categories' ?></span>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="categorySearch" placeholder="Search categories..." onkeyup="filterCategories()">
                </div>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('filterPanel').classList.toggle('open')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filter
                </button>
                <a href="index.php?module=categories&action=create" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Category
                </a>
            </div>
        </div>

        <div id="filterPanel" class="filter-panel <?= $currentHasProducts !== '' ? 'open' : '' ?>">
            <form method="GET" action="index.php" class="filter-form">
                <input type="hidden" name="module" value="categories">
                <div>
                    <label>Products</label>
                    <select name="has_products" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <option value="has" <?= $currentHasProducts === 'has' ? 'selected' : '' ?>>Has Products</option>
                        <option value="empty" <?= $currentHasProducts === 'empty' ? 'selected' : '' ?>>No Products</option>
                    </select>
                </div>
                <?php if ($currentHasProducts !== ''): ?>
                    <a href="index.php?module=categories&action=index" class="btn btn-secondary btn-sm" style="align-self:flex-end;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($status === 'created'): ?>
            <div class="alert alert-success">Category created successfully.</div>
        <?php elseif ($status === 'updated'): ?>
            <div class="alert alert-success">Category updated successfully.</div>
        <?php elseif ($status === 'deleted'): ?>
            <div class="alert alert-success">Category deleted successfully.</div>
        <?php elseif ($status === 'has_items'): ?>
            <div class="alert alert-warning">This category can't be deleted because it still has products assigned to it.</div>
        <?php elseif ($status === 'bulk_deleted'): ?>
            <div class="alert alert-success"><?= $bulkCount ?> categor<?= $bulkCount === 1 ? 'y' : 'ies' ?> deleted.</div>
        <?php elseif ($status === 'bulk_partial'): ?>
            <div class="alert alert-warning"><?= $bulkCount ?> deleted, <?= $bulkSkipped ?> skipped because <?= $bulkSkipped === 1 ? 'it still has' : 'they still have' ?> products assigned.</div>
        <?php endif; ?>

        <form method="POST" id="bulkCategoryForm">
            <div id="bulkBar" class="bulk-bar">
                <span><strong id="bulkCount">0</strong> selected</span>
                <button type="submit" formaction="index.php?module=categories&action=bulkDelete" class="btn btn-danger btn-sm"
                        onclick="return confirm('Delete the selected categories? Any category still holding products will be skipped.');">Delete Selected</button>
            </div>

            <div class="table-card">
                <table id="categoryTable">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="selectAllCategories" class="row-check" onclick="toggleAllCategories(this)"></th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Created</th>
                            <th style="width:190px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr class="empty-row">
                                <td colspan="6">No categories yet. Use "Add Category" to create your first one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $cat['category_id'] ?>" class="row-check category-check" onchange="updateBulkBarCategories()"></td>
                                    <td><strong><?= htmlspecialchars($cat['category_name']) ?></strong></td>
                                    <td class="cell-muted"><?= htmlspecialchars($cat['category_description'] ?: '—') ?></td>
                                    <td class="cell-id"><?= (int) $cat['product_count'] ?></td>
                                    <td class="cell-muted"><?= htmlspecialchars($cat['created_at']) ?></td>
                                    <td class="actions">
                                        <a href="index.php?module=categories&action=view&id=<?= $cat['category_id'] ?>" class="btn btn-edit btn-sm">View</a>
                                        <a href="index.php?module=categories&action=edit&id=<?= $cat['category_id'] ?>" class="btn btn-edit btn-sm">Edit</a>
                                        <a href="index.php?module=categories&action=delete&id=<?= $cat['category_id'] ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Delete this category? This cannot be undone.');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <script>
        function filterCategories() {
            const q = document.getElementById('categorySearch').value.toLowerCase();
            document.querySelectorAll('#categoryTable tbody tr').forEach(row => {
                if (row.classList.contains('empty-row')) return;
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        function toggleAllCategories(source) {
            document.querySelectorAll('.category-check').forEach(cb => cb.checked = source.checked);
            updateBulkBarCategories();
        }

        function updateBulkBarCategories() {
            const checked = document.querySelectorAll('.category-check:checked').length;
            const bar = document.getElementById('bulkBar');
            document.getElementById('bulkCount').textContent = checked;
            bar.classList.toggle('visible', checked > 0);

            const all = document.querySelectorAll('.category-check').length;
            const selectAll = document.getElementById('selectAllCategories');
            if (selectAll) selectAll.checked = checked > 0 && checked === all;
        }
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
