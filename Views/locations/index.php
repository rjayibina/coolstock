<?php
/**
 * Views/locations/index.php
 * Expects: $locations (array of rows from the locations table, each with product_count),
 *          $pagination (array: page, perPage, totalCount, totalPages)
 */
$status = $_GET['status'] ?? null;
$currentHasProducts = $_GET['has_products'] ?? '';
$currentSort = $_GET['sort'] ?? 'newest';
$pageTitle = 'Locations';
$activeSection = 'inventory';
$activeSubNav = 'locations';
$count = $pagination['totalCount'];
require __DIR__ . '/../partials/header.php';

// Builds a pagination link that keeps the current filters
function locationPageUrl(int $page): string
{
    global $currentHasProducts, $currentSort;
    return "index.php?module=locations&action=index"
        . "&has_products=" . urlencode($currentHasProducts)
        . "&sort=" . urlencode($currentSort)
        . "&page=" . $page;
}
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Locations</h1>
                <span class="page-title-count"><?= $count ?> <?= $count === 1 ? 'location' : 'locations' ?></span>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="locationSearch" placeholder="Search locations..." onkeyup="filterLocations()">
                </div>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('filterPanel').classList.toggle('open')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filter
                </button>
                <button type="button" class="btn btn-primary" onclick="openAddLocationModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Location
                </button>
            </div>
        </div>

        <div id="filterPanel" class="filter-panel <?= $currentHasProducts !== '' ? 'open' : '' ?>">
            <form method="GET" action="index.php" class="filter-form">
                <input type="hidden" name="module" value="locations">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($currentSort) ?>">
                <div>
                    <label>Products</label>
                    <select name="has_products" onchange="this.form.submit()">
                        <option value="">All Locations</option>
                        <option value="has" <?= $currentHasProducts === 'has' ? 'selected' : '' ?>>Has Products</option>
                        <option value="empty" <?= $currentHasProducts === 'empty' ? 'selected' : '' ?>>No Products</option>
                    </select>
                </div>
                <?php if ($currentHasProducts !== ''): ?>
                    <a href="index.php?module=locations&action=index" class="btn btn-secondary btn-sm" style="align-self:flex-end;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($status === 'created'): ?>
            <div class="alert alert-success">Location added successfully.</div>
        <?php elseif ($status === 'updated'): ?>
            <div class="alert alert-success">Location updated successfully.</div>
        <?php elseif ($status === 'deleted'): ?>
            <div class="alert alert-success">Location deleted successfully.</div>
        <?php elseif ($status === 'has_items'): ?>
            <div class="alert alert-warning">This location can't be deleted because it still has products assigned to it.</div>
        <?php elseif ($status === 'name_required'): ?>
            <div class="alert alert-warning">Location name is required.</div>
        <?php elseif ($status === 'error'): ?>
            <div class="alert alert-warning">Something went wrong. Please try again.</div>
        <?php endif; ?>

        <div class="sort-bar">
            <label for="sortSelect">Sort by</label>
            <select id="sortSelect" onchange="location.href = 'index.php?module=locations&action=index&has_products=<?= urlencode($currentHasProducts) ?>&sort=' + this.value">
                <option value="newest" <?= $currentSort === 'newest' ? 'selected' : '' ?>>Recently added</option>
                <option value="oldest" <?= $currentSort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                <option value="name_asc" <?= $currentSort === 'name_asc' ? 'selected' : '' ?>>Name: A–Z</option>
                <option value="name_desc" <?= $currentSort === 'name_desc' ? 'selected' : '' ?>>Name: Z–A</option>
                <option value="products_desc" <?= $currentSort === 'products_desc' ? 'selected' : '' ?>>Most products</option>
                <option value="products_asc" <?= $currentSort === 'products_asc' ? 'selected' : '' ?>>Fewest products</option>
            </select>
        </div>

        <div class="table-card">
            <table id="locationTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Products</th>
                        <th>Added</th>
                        <th style="width:150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($locations)): ?>
                        <tr class="empty-row">
                            <td colspan="4">No locations match these filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($locations as $l): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($l['location_name']) ?></strong></td>
                                <td class="cell-muted"><?= (int) $l['product_count'] ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($l['created_at']) ?></td>
                                <td class="actions">
                                    <button type="button" class="btn btn-edit btn-sm" onclick="openEditLocationModal(<?= $l['location_id'] ?>)">Edit</button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteLocationModal(<?= $l['location_id'] ?>)">Delete</button>
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
                <span>Showing <?= $startRow ?>–<?= $endRow ?> of <?= $pagination['totalCount'] ?> locations</span>
                <div class="pagination-controls">
                    <a href="<?= locationPageUrl(max(1, $pagination['page'] - 1)) ?>" class="page-btn <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">&lsaquo; Prev</a>
                    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                        <a href="<?= locationPageUrl($p) ?>" class="page-btn <?= $p === $pagination['page'] ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="<?= locationPageUrl(min($pagination['totalPages'], $pagination['page'] + 1)) ?>" class="page-btn <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">Next &rsaquo;</a>
                </div>
            </div>
        <?php endif; ?>

        <div id="addLocationModal" class="modal-overlay" onclick="if(event.target===this) closeModal('addLocationModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Add Location</h3>
                    <button type="button" class="modal-close" onclick="closeModal('addLocationModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php?module=locations&action=create">
                        <label for="al_location_name">Name</label>
                        <input type="text" id="al_location_name" name="location_name" placeholder="e.g. Main Store, Warehouse" required>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Location</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addLocationModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editLocationModal" class="modal-overlay" onclick="if(event.target===this) closeModal('editLocationModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Edit Location</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editLocationModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editLocationForm" action="index.php?module=locations&action=edit">
                        <input type="hidden" name="location_id" id="el_location_id" value="">

                        <label for="el_location_name">Name</label>
                        <input type="text" id="el_location_name" name="location_name" required>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Location</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('editLocationModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="deleteLocationModal" class="modal-overlay" onclick="if(event.target===this) closeModal('deleteLocationModal')">
            <div class="modal-dialog modal-dialog-sm">
                <div class="modal-header">
                    <h3>Delete Location</h3>
                    <button type="button" class="modal-close" onclick="closeModal('deleteLocationModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Delete <strong id="dl_name"></strong>? This cannot be undone.</p>
                    <div class="form-actions">
                        <a id="dl_confirm_link" href="#" class="btn btn-danger-solid">Delete</a>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('deleteLocationModal')">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const locationsData = <?= json_encode(array_column($locations, null, 'location_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function closeModal(id) {
            document.getElementById(id)?.classList.remove('open');
        }

        function openAddLocationModal() {
            document.getElementById('addLocationModal').classList.add('open');
        }

        function openEditLocationModal(id) {
            const l = locationsData[id];
            if (!l) return;

            document.getElementById('el_location_id').value = id;
            document.getElementById('editLocationForm').action = 'index.php?module=locations&action=edit&id=' + id;
            document.getElementById('el_location_name').value = l.location_name || '';

            document.getElementById('editLocationModal').classList.add('open');
        }

        function openDeleteLocationModal(id) {
            const l = locationsData[id];
            if (!l) return;

            document.getElementById('dl_name').textContent = l.location_name;
            document.getElementById('dl_confirm_link').href = 'index.php?module=locations&action=delete&id=' + id;
            document.getElementById('deleteLocationModal').classList.add('open');
        }

        function filterLocations() {
            const q = document.getElementById('locationSearch').value.toLowerCase();
            document.querySelectorAll('#locationTable tbody tr').forEach(row => {
                if (row.classList.contains('empty-row')) return;
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.getElementById('addLocationModal')?.classList.remove('open');
                document.getElementById('editLocationModal')?.classList.remove('open');
                document.getElementById('deleteLocationModal')?.classList.remove('open');
            }
        });
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
