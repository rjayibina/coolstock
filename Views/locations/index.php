<?php
/**
 * Views/locations/index.php
 * Expects: $locations (array of rows from the locations table),
 *          $pagination (array: page, perPage, totalCount, totalPages)
 */
$status = $_GET['status'] ?? null;
$bulkCount = (int) ($_GET['count'] ?? 0);
$bulkSkipped = (int) ($_GET['skipped'] ?? 0);
$currentSort = $_GET['sort'] ?? 'newest';
$pageTitle = 'Locations';
$activeSection = 'inventory';
$activeSubNav = 'locations';
$count = $pagination['totalCount'];
$canManageLocations = has_role('admin');
require __DIR__ . '/../partials/header.php';

// Builds a pagination link that keeps the current sort.
//
// Reads $_GET directly rather than via `global` on $currentSort above -
// this file is require()'d from inside LocationController::index(), so
// its "top-level" code runs in THAT METHOD's local scope, not PHP's
// real global scope, and `global $x` only ever binds to $GLOBALS['x'].
// That silently emitted an empty sort on every pagination link. $_GET
// is a true superglobal, reachable from any scope, so it doesn't have
// this problem.
function locationPageUrl(int $page): string
{
    return "index.php?module=locations&action=index"
        . "&sort=" . urlencode($_GET['sort'] ?? 'newest')
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
                <?php if ($canManageLocations): ?>
                <button type="button" class="btn btn-primary" onclick="openAddLocationModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Location
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($status === 'created'): ?>
            <div class="alert alert-success">Location added successfully.</div>
        <?php elseif ($status === 'updated'): ?>
            <div class="alert alert-success">Location updated successfully.</div>
        <?php elseif ($status === 'deleted'): ?>
            <div class="alert alert-success">Location deleted successfully.</div>
        <?php elseif ($status === 'has_items'): ?>
            <div class="alert alert-warning">This location can't be deleted because it still has products assigned to it.</div>
        <?php elseif ($status === 'bulk_deleted'): ?>
            <div class="alert alert-success"><?= $bulkCount ?> location<?= $bulkCount === 1 ? '' : 's' ?> deleted.</div>
        <?php elseif ($status === 'bulk_partial'): ?>
            <div class="alert alert-warning"><?= $bulkCount ?> deleted, <?= $bulkSkipped ?> skipped because <?= $bulkSkipped === 1 ? 'it still has' : 'they still have' ?> products assigned.</div>
        <?php elseif ($status === 'name_required'): ?>
            <div class="alert alert-warning">Location name is required.</div>
        <?php elseif ($status === 'name_too_long'): ?>
            <div class="alert alert-warning">Location name must be at most 100 characters.</div>
        <?php elseif ($status === 'error'): ?>
            <div class="alert alert-warning">Something went wrong. Please try again.</div>
        <?php elseif ($status === 'forbidden'): ?>
            <div class="alert alert-warning">Only an Administrator can add, edit, or delete locations.</div>
        <?php endif; ?>

        <div class="sort-bar">
            <label for="sortSelect">Sort by</label>
            <select id="sortSelect" onchange="location.href = 'index.php?module=locations&action=index&sort=' + this.value">
                <option value="newest" <?= $currentSort === 'newest' ? 'selected' : '' ?>>Recently added</option>
                <option value="oldest" <?= $currentSort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                <option value="name_asc" <?= $currentSort === 'name_asc' ? 'selected' : '' ?>>Name: A–Z</option>
                <option value="name_desc" <?= $currentSort === 'name_desc' ? 'selected' : '' ?>>Name: Z–A</option>
            </select>
        </div>

        <form method="POST" id="bulkLocationForm">
            <?php if ($canManageLocations): ?>
            <div id="bulkBar" class="bulk-bar">
                <span><strong id="bulkCount">0</strong> selected</span>
                <button type="button" class="btn btn-danger btn-sm" onclick="openBulkDeleteLocationModal()">Delete Selected</button>
            </div>
            <?php endif; ?>

            <div class="table-card">
                <table id="locationTable">
                    <thead>
                        <tr>
                            <?php if ($canManageLocations): ?>
                            <th style="width:36px;"><input type="checkbox" id="selectAllLocations" class="row-check" onclick="toggleAllLocations(this)"></th>
                            <?php endif; ?>
                            <th style="width:60px;">ID</th>
                            <th>Name</th>
                            <?php if ($canManageLocations): ?>
                            <th style="width:150px;">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($locations)): ?>
                            <tr class="empty-row">
                                <td colspan="<?= $canManageLocations ? 4 : 2 ?>">No locations yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($locations as $l): ?>
                                <tr>
                                    <?php if ($canManageLocations): ?>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $l['location_id'] ?>" class="row-check location-check" onchange="updateBulkBarLocations()"></td>
                                    <?php endif; ?>
                                    <td class="cell-id"><?= (int) $l['location_id'] ?></td>
                                    <td><strong><?= htmlspecialchars($l['location_name']) ?></strong></td>
                                    <?php if ($canManageLocations): ?>
                                    <td class="actions">
                                        <button type="button" class="btn btn-edit btn-sm" onclick="openEditLocationModal(<?= $l['location_id'] ?>)">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteLocationModal(<?= $l['location_id'] ?>)">Delete</button>
                                    </td>
                                    <?php endif; ?>
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
                <span>Showing <?= $startRow ?>–<?= $endRow ?> of <?= $pagination['totalCount'] ?> locations</span>
                <div class="pagination-controls">
                    <a href="<?= locationPageUrl(max(1, $pagination['page'] - 1)) ?>" class="page-btn <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">&lsaquo; Prev</a>
                    <?php foreach (paginate_page_numbers($pagination['page'], $pagination['totalPages']) as $p): ?>
                        <?php if ($p === null): ?>
                            <span class="page-ellipsis">&hellip;</span>
                        <?php else: ?>
                            <a href="<?= locationPageUrl($p) ?>" class="page-btn <?= $p === $pagination['page'] ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <a href="<?= locationPageUrl(min($pagination['totalPages'], $pagination['page'] + 1)) ?>" class="page-btn <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">Next &rsaquo;</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canManageLocations): ?>
        <div id="addLocationModal" class="modal-overlay" onclick="if(event.target===this) closeModal('addLocationModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Add Location</h3>
                    <button type="button" class="modal-close" onclick="closeModal('addLocationModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php?module=locations&action=create">
                        <label for="al_location_name">Name <span class="required-asterisk">*</span></label>
                        <input type="text" id="al_location_name" name="location_name" placeholder="e.g. Main Store, Warehouse" maxlength="100" required>

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

                        <label for="el_location_name">Name <span class="required-asterisk">*</span></label>
                        <input type="text" id="el_location_name" name="location_name" maxlength="100" required>

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

        <?php // Bulk delete used a bare confirm() before - same destructive
              // action, same styled modal as the single-row delete above. ?>
        <div id="bulkDeleteLocationModal" class="modal-overlay" onclick="if(event.target===this) closeModal('bulkDeleteLocationModal')">
            <div class="modal-dialog modal-dialog-sm">
                <div class="modal-header">
                    <h3>Delete Locations</h3>
                    <button type="button" class="modal-close" onclick="closeModal('bulkDeleteLocationModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Delete <strong id="bdl_count">0</strong> selected <span id="bdl_noun">locations</span>? Any location still holding products will be skipped. This cannot be undone.</p>
                    <div class="form-actions">
                        <button type="submit" form="bulkLocationForm" formaction="index.php?module=locations&action=bulkDelete" class="btn btn-danger-solid">Delete</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('bulkDeleteLocationModal')">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <script>
        const locationsData = <?= json_encode(array_column($locations, null, 'location_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function closeModal(id) {
            document.getElementById(id)?.classList.remove('open');
        }

        function openAddLocationModal() {
            document.getElementById('addLocationModal').classList.add('open');
        }

        function openBulkDeleteLocationModal() {
            const count = document.querySelectorAll('.location-check:checked').length;
            if (count === 0) { return; }
            document.getElementById('bdl_count').textContent = count;
            document.getElementById('bdl_noun').textContent = count === 1 ? 'location' : 'locations';
            document.getElementById('bulkDeleteLocationModal').classList.add('open');
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

        function toggleAllLocations(source) {
            document.querySelectorAll('.location-check').forEach(cb => cb.checked = source.checked);
            updateBulkBarLocations();
        }

        function updateBulkBarLocations() {
            const checked = document.querySelectorAll('.location-check:checked').length;
            const bar = document.getElementById('bulkBar');
            document.getElementById('bulkCount').textContent = checked;
            bar.classList.toggle('visible', checked > 0);

            const all = document.querySelectorAll('.location-check').length;
            const selectAll = document.getElementById('selectAllLocations');
            if (selectAll) {
                selectAll.checked = checked > 0 && checked === all;
                selectAll.indeterminate = checked > 0 && checked < all;
            }
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
