<?php
/**
 * Views/products/edit.php
 * Expects: $data (array - current item row), $error (string|null), $categories (array), $brands (array), $itemTypes (array)
 */
$pageTitle = 'Edit Product';
$activeSection = 'inventory';
$activeSubNav = 'products';
require __DIR__ . '/../partials/header.php';
?>
        <a href="index.php?module=products&action=index" class="back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back to Products
        </a>

        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Edit Product</h1>
                <span class="page-title-count">Item ID: <?= (int) $data['item_id'] ?></span>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" id="editProductForm" action="index.php?module=products&action=edit&id=<?= htmlspecialchars($data['item_id']) ?>" enctype="multipart/form-data">
                <input type="hidden" name="item_id" value="<?= htmlspecialchars($data['item_id']) ?>">

                <label for="product_image">Product Image <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <?php if (!empty($data['image_path'])): ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                        <img src="<?= htmlspecialchars($data['image_path']) ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                        <label style="display:flex;align-items:center;gap:6px;font-weight:500;font-size:13px;color:var(--text-muted);margin:0;">
                            <input type="checkbox" name="remove_image" value="1" style="width:auto;">
                            Remove this image
                        </label>
                    </div>
                <?php endif; ?>
                <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="margin-bottom:6px;">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:18px;">Leave empty to keep the current image, or choose a new file to replace it.</div>

                <?php
                $selectedCategoryId = $data['category_id'] ?? '';
                $consumablesCategoryId = null;
                foreach ($categories as $cat) {
                    if (strcasecmp(trim($cat['category_name']), 'Consumables & Spare Parts') === 0) {
                        $consumablesCategoryId = (int) $cat['category_id'];
                        break;
                    }
                }
                ?>
                <label for="category_id">Category <span class="required-asterisk">*</span></label>
                <select id="category_id" name="category_id" onchange="updateCategoryDependentFields()" required>
                    <option value="" disabled <?= $selectedCategoryId === '' ? 'selected' : '' ?>>Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= (string) $selectedCategoryId === (string) $cat['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="model">Model <span class="required-asterisk">*</span></label>
                <input type="text" id="model" name="model" maxlength="100"
                       value="<?= htmlspecialchars($data['model']) ?>" required>

                <label for="brand_id">Brand <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <select id="brand_id" name="brand_id">
                    <option value="">No brand</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['brand_id'] ?>" data-brand-name="<?= htmlspecialchars($b['brand_name']) ?>" <?= (string) ($data['brand_id'] ?? '') === (string) $b['brand_id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['brand_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="brand_id_locked" name="brand_id" value="" disabled>

                <?php
                // Item Type lock per category is data-driven via
                // Category::item_type_id (see database/migration_add_
                // item_type_to_categories.sql) rather than hardcoded by
                // category name - see the matching maps in
                // Views/products/index.php's inline Add/Edit modals.
                $categoryItemTypeLocks = [];
                foreach ($categories as $cat) {
                    if (!empty($cat['item_type_id'])) {
                        $categoryItemTypeLocks[(int) $cat['category_id']] = (int) $cat['item_type_id'];
                    }
                }
                $itemTypeNames = [];
                foreach ($itemTypes as $t) {
                    $itemTypeNames[(int) $t['item_type_id']] = $t['type_name'];
                }
                ?>
                <label for="item_type_id">Item Type <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <select id="item_type_id" name="item_type_id" onchange="updateSpecsVisibility()">
                    <option value="">No item type</option>
                    <?php foreach ($itemTypes as $t): ?>
                        <option value="<?= $t['item_type_id'] ?>" data-type-name="<?= htmlspecialchars($t['type_name']) ?>" <?= (string) ($data['item_type_id'] ?? '') === (string) $t['item_type_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="item_type_id_locked" name="item_type_id" value="" disabled>
                <p id="item_type_locked_note" class="cell-muted" style="display:none;margin-top:4px;"></p>

                <div id="specs_section">
                    <h3 style="margin:24px 0 4px;font-size:15px;color:var(--text-muted);">Technical Specifications <span style="font-weight:400;">(required for Asset item types)</span></h3>

                    <label for="energy_rating">Energy Rating <span class="required-asterisk">*</span></label>
                    <input type="text" id="energy_rating" name="energy_rating" placeholder="e.g. 5 Star" maxlength="20"
                           value="<?= htmlspecialchars($data['energy_rating'] ?? '') ?>">

                    <label for="monthly_consumption">Monthly Consumption (kWh) <span class="required-asterisk">*</span></label>
                    <input type="number" id="monthly_consumption" name="monthly_consumption" min="0" step="0.01"
                           placeholder="e.g. 120.50"
                           value="<?= htmlspecialchars($data['monthly_consumption'] ?? '') ?>">

                    <label for="cooling_capacity">Cooling Capacity <span class="required-asterisk">*</span></label>
                    <input type="text" id="cooling_capacity" name="cooling_capacity" placeholder="e.g. 1.5 HP (12,000 BTU/hr)" maxlength="50"
                           value="<?= htmlspecialchars($data['cooling_capacity'] ?? '') ?>">

                    <label for="refrigerant">Refrigerant <span class="required-asterisk">*</span></label>
                    <input type="text" id="refrigerant" name="refrigerant" placeholder="e.g. R32" maxlength="50"
                           value="<?= htmlspecialchars($data['refrigerant'] ?? '') ?>">

                    <label for="installation_type">Installation Type <span class="required-asterisk">*</span></label>
                    <input type="text" id="installation_type" name="installation_type" placeholder="e.g. Wall Mounted" maxlength="50"
                           value="<?= htmlspecialchars($data['installation_type'] ?? '') ?>">

                    <label for="power_input">Power Input <span class="required-asterisk">*</span></label>
                    <input type="text" id="power_input" name="power_input" placeholder="e.g. 220-240V ~50Hz" maxlength="50"
                           value="<?= htmlspecialchars($data['power_input'] ?? '') ?>">

                    <label for="year">Year <span class="required-asterisk">*</span></label>
                    <input type="number" id="year" name="year" min="1990" max="2100" step="1" placeholder="e.g. 2024"
                           value="<?= htmlspecialchars($data['year'] ?? '') ?>">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Product</button>
                    <a href="index.php?module=products&action=index" class="btn btn-secondary">Cancel</a>
                </div>
            </form>

            <script>
            const CONSUMABLES_CATEGORY_ID = <?= json_encode($consumablesCategoryId) ?>;
            const CATEGORY_ITEM_TYPE_LOCKS = <?= json_encode($categoryItemTypeLocks) ?>;
            const ITEM_TYPE_NAMES = <?= json_encode($itemTypeNames) ?>;

            function lockSelect(select, hidden, value) {
                select.disabled = true;
                hidden.disabled = false;
                hidden.value = value;
            }

            function unlockSelect(select, hidden) {
                select.disabled = false;
                hidden.disabled = true;
                hidden.value = '';
            }

            // Shows the Technical Specifications section (and marks its fields
            // required) only when the selected Item Type is "Asset" - hidden
            // and optional for Consumable or no item type selected.
            function updateSpecsVisibility() {
                const typeSelect = document.getElementById('item_type_id');
                const selectedOption = typeSelect.options[typeSelect.selectedIndex];
                const isAsset = selectedOption && selectedOption.dataset.typeName === 'Asset';

                document.getElementById('specs_section').style.display = isAsset ? '' : 'none';

                ['energy_rating', 'monthly_consumption', 'cooling_capacity', 'refrigerant', 'installation_type', 'power_input', 'year'].forEach(name => {
                    document.getElementById(name).required = isAsset;
                });
            }

            // Consumables & Spare Parts: Brand auto-locked to N/A (no
            // Location field on this form - stock/location is managed
            // separately via item_stock) - kept keyed off that specific
            // category by name, a distinct rule from the Item Type lock
            // below.
            //
            // Item Type lock is data-driven per category (Category::
            // item_type_id, set from the Categories page's "Locks Item
            // Type" field - see CATEGORY_ITEM_TYPE_LOCKS above) instead of
            // a hardcoded Asset-vs-Consumable split. A category with no
            // lock configured leaves Brand/Item Type manually selectable,
            // matching this form's existing optional semantics ("No
            // brand" / "No item type").
            function updateCategoryDependentFields() {
                const categorySelect = document.getElementById('category_id');
                const categoryId = categorySelect.value ? parseInt(categorySelect.value, 10) : null;

                const brandSelect = document.getElementById('brand_id');
                const brandHidden = document.getElementById('brand_id_locked');
                const typeSelect = document.getElementById('item_type_id');
                const typeHidden = document.getElementById('item_type_id_locked');
                const typeLockedNote = document.getElementById('item_type_locked_note');

                if (categoryId !== null && categoryId === CONSUMABLES_CATEGORY_ID) {
                    let naOption = null;
                    for (const opt of brandSelect.options) {
                        if (opt.dataset.brandName === 'N/A') { naOption = opt; break; }
                    }
                    if (naOption) {
                        brandSelect.value = naOption.value;
                        lockSelect(brandSelect, brandHidden, naOption.value);
                    }
                } else {
                    unlockSelect(brandSelect, brandHidden);
                }

                const lockedTypeId = categoryId !== null ? CATEGORY_ITEM_TYPE_LOCKS[categoryId] : undefined;
                if (lockedTypeId !== undefined) {
                    typeSelect.value = String(lockedTypeId);
                    lockSelect(typeSelect, typeHidden, String(lockedTypeId));
                    typeLockedNote.textContent = 'Item Type is set to ' + (ITEM_TYPE_NAMES[lockedTypeId] || 'a fixed type') + ' for this category.';
                    typeLockedNote.style.display = '';
                } else {
                    unlockSelect(typeSelect, typeHidden);
                    typeLockedNote.style.display = 'none';
                }

                updateSpecsVisibility();
            }

            updateSpecsVisibility();
            updateCategoryDependentFields();
            </script>
        </div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
