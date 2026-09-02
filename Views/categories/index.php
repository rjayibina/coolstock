<?php
/**
 * Views/categories/index.php
 * Expects: $categories (array of rows from item_categories, each with product_count),
 *          $pagination (array: page, perPage, totalCount, totalPages)
 */
$status = $_GET['status'] ?? null;
$bulkCount = (int) ($_GET['count'] ?? 0);
$bulkSkipped = (int) ($_GET['skipped'] ?? 0);
$currentSort = $_GET['sort'] ?? 'newest';
$pageTitle = 'Categories';
$activeSection = 'inventory';
$activeSubNav = 'categories';
$count = $pagination['totalCount'];
require __DIR__ . '/../partials/header.php';

// Builds a pagination link that keeps the current sort
function categoryPageUrl(int $page): string
{
    global $currentSort;
    return "index.php?module=categories&action=index"
        . "&sort=" . urlencode($currentSort)
        . "&page=" . $page;
}
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
                <button type="button" class="btn btn-primary" onclick="openAddCategoryModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Category
                </button>
            </div>
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

        <div class="sort-bar">
            <label for="sortSelect">Sort by</label>
            <select id="sortSelect" onchange="location.href = 'index.php?module=categories&action=index&sort=' + this.value">
                <option value="newest" <?= $currentSort === 'newest' ? 'selected' : '' ?>>Recently added</option>
                <option value="oldest" <?= $currentSort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                <option value="name_asc" <?= $currentSort === 'name_asc' ? 'selected' : '' ?>>Name: A–Z</option>
                <option value="name_desc" <?= $currentSort === 'name_desc' ? 'selected' : '' ?>>Name: Z–A</option>
                <option value="products_desc" <?= $currentSort === 'products_desc' ? 'selected' : '' ?>>Most products</option>
                <option value="products_asc" <?= $currentSort === 'products_asc' ? 'selected' : '' ?>>Fewest products</option>
            </select>
        </div>

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
                            <th style="width:60px;">ID</th>
                            <th>Category</th>
                            <th style="width:190px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr class="empty-row">
                                <td colspan="4">No categories match these filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $cat['category_id'] ?>" class="row-check category-check" onchange="updateBulkBarCategories()"></td>
                                    <td class="cell-id"><?= (int) $cat['category_id'] ?></td>
                                    <td><strong><?= htmlspecialchars($cat['category_name']) ?></strong></td>
                                    <td class="actions">
                                        <button type="button" class="btn btn-edit btn-sm" onclick="openEditCategoryModal(<?= $cat['category_id'] ?>)">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteCategoryModal(<?= $cat['category_id'] ?>)">Delete</button>
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
                <span>Showing <?= $startRow ?>–<?= $endRow ?> of <?= $pagination['totalCount'] ?> categories</span>
                <div class="pagination-controls">
                    <a href="<?= categoryPageUrl(max(1, $pagination['page'] - 1)) ?>" class="page-btn <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">&lsaquo; Prev</a>
                    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                        <a href="<?= categoryPageUrl($p) ?>" class="page-btn <?= $p === $pagination['page'] ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="<?= categoryPageUrl(min($pagination['totalPages'], $pagination['page'] + 1)) ?>" class="page-btn <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">Next &rsaquo;</a>
                </div>
            </div>
        <?php endif; ?>

        <div id="addCategoryModal" class="modal-overlay" onclick="if(event.target===this) closeModal('addCategoryModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Add Category</h3>
                    <button type="button" class="modal-close" onclick="closeModal('addCategoryModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php?module=categories&action=create">
                        <label for="ac_category_name">Category Name</label>
                        <input type="text" id="ac_category_name" name="category_name"
                               placeholder="e.g. Refrigeration Parts" required>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Category</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editCategoryModal" class="modal-overlay" onclick="if(event.target===this) closeModal('editCategoryModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Edit Category</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editCategoryModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editCategoryForm" action="index.php?module=categories&action=edit">
                        <input type="hidden" name="category_id" id="ec_category_id" value="">

                        <label for="ec_category_name">Category Name</label>
                        <input type="text" id="ec_category_name" name="category_name" required>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Category</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('editCategoryModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="deleteCategoryModal" class="modal-overlay" onclick="if(event.target===this) closeModal('deleteCategoryModal')">
            <div class="modal-dialog modal-dialog-sm">
                <div class="modal-header">
                    <h3>Delete Category</h3>
                    <button type="button" class="modal-close" onclick="closeModal('deleteCategoryModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Delete <strong id="dc_name"></strong>? This cannot be undone.</p>
                    <div class="form-actions">
                        <a id="dc_confirm_link" href="#" class="btn btn-danger-solid">Delete</a>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('deleteCategoryModal')">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const categoriesData = <?= json_encode(array_column($categories, null, 'category_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function closeModal(id) {
            document.getElementById(id)?.classList.remove('open');
        }

        function openAddCategoryModal() {
            document.getElementById('addCategoryModal').classList.add('open');
        }

        function openEditCategoryModal(id) {
            const c = categoriesData[id];
            if (!c) return;

            document.getElementById('ec_category_id').value = id;
            document.getElementById('editCategoryForm').action = 'index.php?module=categories&action=edit&id=' + id;
            document.getElementById('ec_category_name').value = c.category_name || '';

            document.getElementById('editCategoryModal').classList.add('open');
        }

        function openDeleteCategoryModal(id) {
            const c = categoriesData[id];
            if (!c) return;

            document.getElementById('dc_name').textContent = c.category_name;
            document.getElementById('dc_confirm_link').href = 'index.php?module=categories&action=delete&id=' + id;
            document.getElementById('deleteCategoryModal').classList.add('open');
        }

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
            if (selectAll) {
                selectAll.checked = checked > 0 && checked === all;
                selectAll.indeterminate = checked > 0 && checked < all;
            }
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.getElementById('addCategoryModal')?.classList.remove('open');
                document.getElementById('editCategoryModal')?.classList.remove('open');
                document.getElementById('deleteCategoryModal')?.classList.remove('open');
            }
        });
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
