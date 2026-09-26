<?php
/**
 * Views/products/create.php
 * Expects: $error (string|null), $categories (array), $brands (array),
 *          $itemTypes (array), $locations (array)
 */
$pageTitle = 'Add Product';
$activeSection = 'inventory';
$activeSubNav = 'products';
require __DIR__ . '/../partials/header.php';
$old = $_POST ?? [];
?>
        <a href="index.php?module=products&action=index" class="back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back to Products
        </a>

        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Add Product</h1>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" id="addProductForm" action="index.php?module=products&action=create" enctype="multipart/form-data">
                <label for="product_image">Product Image <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="margin-bottom:18px;">

                <label for="model">Model <span class="required-asterisk">*</span></label>
                <input type="text" id="model" name="model" placeholder="e.g. FTKC50UVM" maxlength="100"
                       value="<?= htmlspecialchars($old['model'] ?? '') ?>" required>

                <?php
                $selectedCategoryId = $old['category_id'] ?? '';
                // "Consumables & Spare Parts" is matched by name, not a
                // hardcoded id, so this keeps working if the categories
                // table is ever re-seeded with different ids.
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

                <label for="brand_id">Brand <span class="required-asterisk">*</span></label>
                <select id="brand_id" name="brand_id" required>
                    <option value="" disabled <?= empty($old['brand_id']) ? 'selected' : '' ?>>Select a brand</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['brand_id'] ?>" data-brand-name="<?= htmlspecialchars($b['brand_name']) ?>" <?= (string) ($old['brand_id'] ?? '') === (string) $b['brand_id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['brand_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="brand_id_locked" name="brand_id" value="" disabled>

                <?php
                $selectedItemTypeId = $old['item_type_id'] ?? '';
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
                <label for="item_type_id">Item Type <span class="required-asterisk">*</span></label>
                <select id="item_type_id" name="item_type_id" onchange="updateSpecsVisibility()" required>
                    <option value="" disabled <?= $selectedItemTypeId === '' ? 'selected' : '' ?>>Select an item type</option>
                    <?php foreach ($itemTypes as $t): ?>
                        <option value="<?= $t['item_type_id'] ?>" data-type-name="<?= htmlspecialchars($t['type_name']) ?>" <?= (string) $selectedItemTypeId === (string) $t['item_type_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="item_type_id_locked" name="item_type_id" value="" disabled>
                <p id="item_type_locked_note" class="cell-muted" style="display:none;margin-top:4px;"></p>

                <?php $selectedLocationId = $old['location_id'] ?? ''; ?>
                <label for="location_id">Location <span class="required-asterisk">*</span></label>
                <select id="location_id" name="location_id" required>
                    <option value="" disabled <?= $selectedLocationId === '' ? 'selected' : '' ?>>Select a location</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['location_id'] ?>" data-location-name="<?= htmlspecialchars(strtolower($loc['location_name'])) ?>" <?= (string) $selectedLocationId === (string) $loc['location_id'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="location_id_locked" name="location_id" value="" disabled>

                <label for="quantity">Quantity <span class="required-asterisk">*</span> <span style="font-weight:400;color:var(--text-muted);">at that location</span></label>
                <input type="number" id="quantity" name="quantity" min="0" step="1" placeholder="0"
                       value="<?= htmlspecialchars($old['quantity'] ?? '0') ?>" required>

                <div id="specs_section">
                    <h3 style="margin:24px 0 4px;font-size:15px;color:var(--text-muted);">Technical Specifications <span style="font-weight:400;">(required for Asset item types)</span></h3>

                    <label for="energy_rating">Energy Rating <span class="required-asterisk">*</span></label>
                    <input type="text" id="energy_rating" name="energy_rating" placeholder="e.g. 5 Star" maxlength="20"
                           value="<?= htmlspecialchars($old['energy_rating'] ?? '') ?>">

                    <label for="monthly_consumption">Monthly Consumption (kWh) <span class="required-asterisk">*</span></label>
                    <input type="number" id="monthly_consumption" name="monthly_consumption" min="0" step="0.01"
                           placeholder="e.g. 120.50"
                           value="<?= htmlspecialchars($old['monthly_consumption'] ?? '') ?>">

                    <label for="cooling_capacity">Cooling Capacity <span class="required-asterisk">*</span></label>
                    <input type="text" id="cooling_capacity" name="cooling_capacity" placeholder="e.g. 1.5 HP (12,000 BTU/hr)" maxlength="50"
                           value="<?= htmlspecialchars($old['cooling_capacity'] ?? '') ?>">

                    <label for="refrigerant">Refrigerant <span class="required-asterisk">*</span></label>
                    <input type="text" id="refrigerant" name="refrigerant" placeholder="e.g. R32" maxlength="50"
                           value="<?= htmlspecialchars($old['refrigerant'] ?? '') ?>">

                    <label for="installation_type">Installation Type <span class="required-asterisk">*</span></label>
                    <input type="text" id="installation_type" name="installation_type" placeholder="e.g. Wall Mounted" maxlength="50"
                           value="<?= htmlspecialchars($old['installation_type'] ?? '') ?>">

                    <label for="power_input">Power Input <span class="required-asterisk">*</span></label>
                    <input type="text" id="power_input" name="power_input" placeholder="e.g. 220-240V ~50Hz" maxlength="50"
                           value="<?= htmlspecialchars($old['power_input'] ?? '') ?>">

                    <label for="year">Year <span class="required-asterisk">*</span></label>
                    <input type="number" id="year" name="year" min="1990" max="2100" step="1" placeholder="e.g. 2024"
                           value="<?= htmlspecialchars($old['year'] ?? '') ?>">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Product</button>
                    <a href="index.php?module=products&action=index" class="btn btn-secondary">Cancel</a>
                </div>
            </form>

            <script>
            const CONSUMABLES_CATEGORY_ID = <?= json_encode($consumablesCategoryId) ?>;
            const CATEGORY_ITEM_TYPE_LOCKS = <?= json_encode($categoryItemTypeLocks) ?>;
            const ITEM_TYPE_NAMES = <?= json_encode($itemTypeNames) ?>;

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

            // Locks a <select> by disabling it (so it's greyed out and
            // stops accepting input) while a same-named hidden input
            // carries its value instead - a disabled control is excluded
            // from form submission entirely, so this is how the value
            // still reaches the server even though the visible select no
            // longer will.
            function lockSelect(select, hidden, value) {
                select.value = value;
                select.disabled = true;
                hidden.value = value;
                hidden.disabled = false;
            }

            function unlockSelect(select, hidden) {
                select.disabled = false;
                hidden.disabled = true;
                hidden.value = '';
            }

            // Consumables & Spare Parts: Brand auto-locked to N/A and
            // Location auto-locked to Warehouse (a spare part isn't
            // branded the way an HVAC unit is, and isn't stocked anywhere
            // but the Warehouse) - kept keyed off that specific category
            // by name, a distinct rule from the Item Type lock below.
            //
            // Item Type lock is data-driven per category (Category::
            // item_type_id, set from the Categories page's "Locks Item
            // Type" field - see CATEGORY_ITEM_TYPE_LOCKS above) instead of
            // a hardcoded Asset-vs-Consumable split.
            function updateCategoryDependentFields() {
                const categorySelect = document.getElementById('category_id');
                const categoryId = categorySelect.value !== '' ? parseInt(categorySelect.value, 10) : null;
                const isConsumablesCategory = CONSUMABLES_CATEGORY_ID !== null
                    && String(categorySelect.value) === String(CONSUMABLES_CATEGORY_ID);

                const brandSelect = document.getElementById('brand_id');
                const brandHidden = document.getElementById('brand_id_locked');
                const itemTypeSelect = document.getElementById('item_type_id');
                const itemTypeHidden = document.getElementById('item_type_id_locked');
                const locationSelect = document.getElementById('location_id');
                const locationHidden = document.getElementById('location_id_locked');
                const itemTypeNote = document.getElementById('item_type_locked_note');

                if (isConsumablesCategory) {
                    const naOption = Array.from(brandSelect.options).find(o => o.dataset.brandName === 'N/A');
                    if (naOption) {
                        lockSelect(brandSelect, brandHidden, naOption.value);
                    }
                    const warehouseOption = Array.from(locationSelect.options).find(o => o.dataset.locationName === 'warehouse');
                    if (warehouseOption) {
                        lockSelect(locationSelect, locationHidden, warehouseOption.value);
                    }
                } else {
                    unlockSelect(brandSelect, brandHidden);
                    unlockSelect(locationSelect, locationHidden);
                }

                const lockedTypeId = categoryId !== null ? CATEGORY_ITEM_TYPE_LOCKS[categoryId] : undefined;
                if (lockedTypeId !== undefined) {
                    lockSelect(itemTypeSelect, itemTypeHidden, lockedTypeId);
                    itemTypeNote.style.display = '';
                    itemTypeNote.textContent = 'Locked to ' + (ITEM_TYPE_NAMES[lockedTypeId] || 'a fixed type') + ' for this category.';
                } else {
                    unlockSelect(itemTypeSelect, itemTypeHidden);
                    itemTypeNote.style.display = 'none';
                }

                updateSpecsVisibility();
            }

            updateSpecsVisibility();
            updateCategoryDependentFields();
            </script>
        </div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
