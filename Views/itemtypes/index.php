<?php
/**
 * Views/itemtypes/index.php
 * Expects: $itemTypes (array of rows from the item_types table, each with product_count),
 *          $pagination (array: page, perPage, totalCount, totalPages)
 */
$status = $_GET['status'] ?? null;
$currentHasProducts = $_GET['has_products'] ?? '';
$currentSort = $_GET['sort'] ?? 'newest';
$pageTitle = 'Item Types';
$activeSection = 'inventory';
$activeSubNav = 'itemtypes';
$count = $pagination['totalCount'];
require __DIR__ . '/../partials/header.php';

// Builds a pagination link that keeps the current filters
function itemTypePageUrl(int $page): string
{
    global $currentHasProducts, $currentSort;
    return "index.php?module=itemtypes&action=index"
        . "&has_products=" . urlencode($currentHasProducts)
        . "&sort=" . urlencode($currentSort)
        . "&page=" . $page;
}
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Item Types</h1>
                <span class="page-title-count"><?= $count ?> <?= $count === 1 ? 'type' : 'types' ?></span>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="itemTypeSearch" placeholder="Search item types..." onkeyup="filterItemTypes()">
                </div>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('filterPanel').classList.toggle('open')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filter
                </button>
                <button type="button" class="btn btn-primary" onclick="openAddItemTypeModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Item Type
                </button>
            </div>
        </div>

        <div id="filterPanel" class="filter-panel <?= $currentHasProducts !== '' ? 'open' : '' ?>">
            <form method="GET" action="index.php" class="filter-form">
                <input type="hidden" name="module" value="itemtypes">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($currentSort) ?>">
                <div>
                    <label>Products</label>
                    <select name="has_products" onchange="this.form.submit()">
                        <option value="">All Item Types</option>
                        <option value="has" <?= $currentHasProducts === 'has' ? 'selected' : '' ?>>Has Products</option>
                        <option value="empty" <?= $currentHasProducts === 'empty' ? 'selected' : '' ?>>No Products</option>
                    </select>
                </div>
                <?php if ($currentHasProducts !== ''): ?>
                    <a href="index.php?module=itemtypes&action=index" class="btn btn-secondary btn-sm" style="align-self:flex-end;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($status === 'created'): ?>
            <div class="alert alert-success">Item type added successfully.</div>
        <?php elseif ($status === 'updated'): ?>
            <div class="alert alert-success">Item type updated successfully.</div>
        <?php elseif ($status === 'deleted'): ?>
            <div class="alert alert-success">Item type deleted successfully.</div>
        <?php elseif ($status === 'has_items'): ?>
            <div class="alert alert-warning">This item type can't be deleted because it still has products assigned to it.</div>
        <?php elseif ($status === 'name_required'): ?>
            <div class="alert alert-warning">Item type name is required.</div>
        <?php elseif ($status === 'error'): ?>
            <div class="alert alert-warning">Something went wrong. Please try again.</div>
        <?php endif; ?>

        <div class="sort-bar">
            <label for="sortSelect">Sort by</label>
            <select id="sortSelect" onchange="location.href = 'index.php?module=itemtypes&action=index&has_products=<?= urlencode($currentHasProducts) ?>&sort=' + this.value">
                <option value="newest" <?= $currentSort === 'newest' ? 'selected' : '' ?>>Recently added</option>
                <option value="oldest" <?= $currentSort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                <option value="name_asc" <?= $currentSort === 'name_asc' ? 'selected' : '' ?>>Name: A–Z</option>
                <option value="name_desc" <?= $currentSort === 'name_desc' ? 'selected' : '' ?>>Name: Z–A</option>
                <option value="products_desc" <?= $currentSort === 'products_desc' ? 'selected' : '' ?>>Most products</option>
                <option value="products_asc" <?= $currentSort === 'products_asc' ? 'selected' : '' ?>>Fewest products</option>
            </select>
        </div>

        <div class="table-card">
            <table id="itemTypeTable">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Name</th>
                        <th style="width:150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($itemTypes)): ?>
                        <tr class="empty-row">
                            <td colspan="3">No item types match these filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($itemTypes as $t): ?>
                            <tr>
                                <td class="cell-id"><?= (int) $t['item_type_id'] ?></td>
                                <td><strong><?= htmlspecialchars($t['type_name']) ?></strong></td>
                                <td class="actions">
                                    <button type="button" class="btn btn-edit btn-sm" onclick="openEditItemTypeModal(<?= $t['item_type_id'] ?>)">Edit</button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteItemTypeModal(<?= $t['item_type_id'] ?>)">Delete</button>
                                </td>
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
                <span>Showing <?= $startRow ?>–<?= $endRow ?> of <?= $pagination['totalCount'] ?> item types</span>
                <div class="pagination-controls">
                    <a href="<?= itemTypePageUrl(max(1, $pagination['page'] - 1)) ?>" class="page-btn <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">&lsaquo; Prev</a>
                    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                        <a href="<?= itemTypePageUrl($p) ?>" class="page-btn <?= $p === $pagination['page'] ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="<?= itemTypePageUrl(min($pagination['totalPages'], $pagination['page'] + 1)) ?>" class="page-btn <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">Next &rsaquo;</a>
                </div>
            </div>
        <?php endif; ?>

        <div id="addItemTypeModal" class="modal-overlay" onclick="if(event.target===this) closeModal('addItemTypeModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Add Item Type</h3>
                    <button type="button" class="modal-close" onclick="closeModal('addItemTypeModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php?module=itemtypes&action=create">
                        <label for="ait_type_name">Name</label>
                        <input type="text" id="ait_type_name" name="type_name" placeholder="e.g. Asset, Consumable" required>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Item Type</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addItemTypeModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editItemTypeModal" class="modal-overlay" onclick="if(event.target===this) closeModal('editItemTypeModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Edit Item Type</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editItemTypeModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editItemTypeForm" action="index.php?module=itemtypes&action=edit">
                        <input type="hidden" name="item_type_id" id="eit_item_type_id" value="">

                        <label for="eit_type_name">Name</label>
                        <input type="text" id="eit_type_name" name="type_name" required>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Item Type</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('editItemTypeModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="deleteItemTypeModal" class="modal-overlay" onclick="if(event.target===this) closeModal('deleteItemTypeModal')">
            <div class="modal-dialog modal-dialog-sm">
                <div class="modal-header">
                    <h3>Delete Item Type</h3>
                    <button type="button" class="modal-close" onclick="closeModal('deleteItemTypeModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Delete <strong id="dit_name"></strong>? This cannot be undone.</p>
                    <div class="form-actions">
                        <a id="dit_confirm_link" href="#" class="btn btn-danger-solid">Delete</a>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('deleteItemTypeModal')">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const itemTypesData = <?= json_encode(array_column($itemTypes, null, 'item_type_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function closeModal(id) {
            document.getElementById(id)?.classList.remove('open');
        }

        function openAddItemTypeModal() {
            document.getElementById('addItemTypeModal').classList.add('open');
        }

        function openEditItemTypeModal(id) {
            const t = itemTypesData[id];
            if (!t) return;

            document.getElementById('eit_item_type_id').value = id;
            document.getElementById('editItemTypeForm').action = 'index.php?module=itemtypes&action=edit&id=' + id;
            document.getElementById('eit_type_name').value = t.type_name || '';

            document.getElementById('editItemTypeModal').classList.add('open');
        }

        function openDeleteItemTypeModal(id) {
            const t = itemTypesData[id];
            if (!t) return;

            document.getElementById('dit_name').textContent = t.type_name;
            document.getElementById('dit_confirm_link').href = 'index.php?module=itemtypes&action=delete&id=' + id;
            document.getElementById('deleteItemTypeModal').classList.add('open');
        }

        function filterItemTypes() {
            const q = document.getElementById('itemTypeSearch').value.toLowerCase();
            document.querySelectorAll('#itemTypeTable tbody tr').forEach(row => {
                if (row.classList.contains('empty-row')) return;
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.getElementById('addItemTypeModal')?.classList.remove('open');
                document.getElementById('editItemTypeModal')?.classList.remove('open');
                document.getElementById('deleteItemTypeModal')?.classList.remove('open');
            }
        });
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
