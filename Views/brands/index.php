<?php
/**
 * Views/brands/index.php
 * Expects: $brands (array of rows from the brands table)
 */
$status = $_GET['status'] ?? null;
$pageTitle = 'Brands';
$activeSection = 'inventory';
$activeSubNav = 'brands';
$count = count($brands);
require __DIR__ . '/../partials/header.php';
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Brands</h1>
                <span class="page-title-count"><?= $count ?> <?= $count === 1 ? 'brand' : 'brands' ?></span>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="brandSearch" placeholder="Search brands..." onkeyup="filterBrands()">
                </div>
                <button type="button" class="btn btn-primary" onclick="openAddBrandModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Brand
                </button>
            </div>
        </div>

        <?php if ($status === 'created'): ?>
            <div class="alert alert-success">Brand added successfully.</div>
        <?php elseif ($status === 'updated'): ?>
            <div class="alert alert-success">Brand updated successfully.</div>
        <?php elseif ($status === 'deleted'): ?>
            <div class="alert alert-success">Brand deleted successfully.</div>
        <?php elseif ($status === 'has_items'): ?>
            <div class="alert alert-warning">This brand can't be deleted because it still has products assigned to it.</div>
        <?php elseif ($status === 'name_required'): ?>
            <div class="alert alert-warning">Brand name is required.</div>
        <?php elseif ($status === 'error'): ?>
            <div class="alert alert-warning">Something went wrong. Please try again.</div>
        <?php endif; ?>

        <div class="table-card">
            <table id="brandTable">
                <thead>
                    <tr>
                        <th>Brand</th>
                        <th>Code</th>
                        <th>Added</th>
                        <th style="width:150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($brands)): ?>
                        <tr class="empty-row">
                            <td colspan="4">No brands yet. Use "Add Brand" to create your first one.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($brands as $b): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($b['brand_name']) ?></strong></td>
                                <td class="cell-muted"><?= htmlspecialchars($b['brand_code'] ?: '—') ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($b['created_at']) ?></td>
                                <td class="actions">
                                    <button type="button" class="btn btn-edit btn-sm" onclick="openEditBrandModal(<?= $b['brand_id'] ?>)">Edit</button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteBrandModal(<?= $b['brand_id'] ?>)">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="addBrandModal" class="modal-overlay" onclick="if(event.target===this) closeModal('addBrandModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Add Brand</h3>
                    <button type="button" class="modal-close" onclick="closeModal('addBrandModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php?module=brands&action=create">
                        <label for="ab_brand_name">Brand Name</label>
                        <input type="text" id="ab_brand_name" name="brand_name" placeholder="e.g. Carrier" required>

                        <label for="ab_brand_code">Brand Code <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <input type="text" id="ab_brand_code" name="brand_code" placeholder="e.g. 088">

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Brand</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addBrandModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editBrandModal" class="modal-overlay" onclick="if(event.target===this) closeModal('editBrandModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Edit Brand</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editBrandModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editBrandForm" action="index.php?module=brands&action=edit">
                        <input type="hidden" name="brand_id" id="eb_brand_id" value="">

                        <label for="eb_brand_name">Brand Name</label>
                        <input type="text" id="eb_brand_name" name="brand_name" required>

                        <label for="eb_brand_code">Brand Code <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <input type="text" id="eb_brand_code" name="brand_code">

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Brand</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('editBrandModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="deleteBrandModal" class="modal-overlay" onclick="if(event.target===this) closeModal('deleteBrandModal')">
            <div class="modal-dialog modal-dialog-sm">
                <div class="modal-header">
                    <h3>Delete Brand</h3>
                    <button type="button" class="modal-close" onclick="closeModal('deleteBrandModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Delete <strong id="db_name"></strong>? This cannot be undone.</p>
                    <div class="form-actions">
                        <a id="db_confirm_link" href="#" class="btn btn-danger-solid">Delete</a>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('deleteBrandModal')">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const brandsData = <?= json_encode(array_column($brands, null, 'brand_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function closeModal(id) {
            document.getElementById(id)?.classList.remove('open');
        }

        function openAddBrandModal() {
            document.getElementById('addBrandModal').classList.add('open');
        }

        function openEditBrandModal(id) {
            const b = brandsData[id];
            if (!b) return;

            document.getElementById('eb_brand_id').value = id;
            document.getElementById('editBrandForm').action = 'index.php?module=brands&action=edit&id=' + id;
            document.getElementById('eb_brand_name').value = b.brand_name || '';
            document.getElementById('eb_brand_code').value = b.brand_code || '';

            document.getElementById('editBrandModal').classList.add('open');
        }

        function openDeleteBrandModal(id) {
            const b = brandsData[id];
            if (!b) return;

            document.getElementById('db_name').textContent = b.brand_name;
            document.getElementById('db_confirm_link').href = 'index.php?module=brands&action=delete&id=' + id;
            document.getElementById('deleteBrandModal').classList.add('open');
        }

        function filterBrands() {
            const q = document.getElementById('brandSearch').value.toLowerCase();
            document.querySelectorAll('#brandTable tbody tr').forEach(row => {
                if (row.classList.contains('empty-row')) return;
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.getElementById('addBrandModal')?.classList.remove('open');
                document.getElementById('editBrandModal')?.classList.remove('open');
                document.getElementById('deleteBrandModal')?.classList.remove('open');
            }
        });
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
