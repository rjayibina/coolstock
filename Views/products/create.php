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
            <form method="POST" id="addProductForm" action="index.php?module=products&action=create">
                <label for="model">Model</label>
                <input type="text" id="model" name="model" placeholder="e.g. FTKC50UVM"
                       value="<?= htmlspecialchars($old['model'] ?? '') ?>" required>

                <?php $selectedCategoryId = $old['category_id'] ?? ''; ?>
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="" disabled <?= $selectedCategoryId === '' ? 'selected' : '' ?>>Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= (string) $selectedCategoryId === (string) $cat['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="brand_id">Brand</label>
                <select id="brand_id" name="brand_id" required>
                    <option value="" disabled <?= empty($old['brand_id']) ? 'selected' : '' ?>>Select a brand</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['brand_id'] ?>" <?= (string) ($old['brand_id'] ?? '') === (string) $b['brand_id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['brand_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php $selectedItemTypeId = $old['item_type_id'] ?? ''; ?>
                <label for="item_type_id">Item Type</label>
                <select id="item_type_id" name="item_type_id" onchange="updateSpecsVisibility()" required>
                    <option value="" disabled <?= $selectedItemTypeId === '' ? 'selected' : '' ?>>Select an item type</option>
                    <?php foreach ($itemTypes as $t): ?>
                        <option value="<?= $t['item_type_id'] ?>" data-type-name="<?= htmlspecialchars($t['type_name']) ?>" <?= (string) $selectedItemTypeId === (string) $t['item_type_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php $selectedLocationId = $old['location_id'] ?? ''; ?>
                <label for="location_id">Location</label>
                <select id="location_id" name="location_id" required>
                    <option value="" disabled <?= $selectedLocationId === '' ? 'selected' : '' ?>>Select a location</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['location_id'] ?>" <?= (string) $selectedLocationId === (string) $loc['location_id'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="quantity">Quantity <span style="font-weight:400;color:var(--text-muted);">at that location</span></label>
                <input type="number" id="quantity" name="quantity" min="0" step="1" placeholder="0"
                       value="<?= htmlspecialchars($old['quantity'] ?? '0') ?>" required>

                <div id="specs_section">
                    <h3 style="margin:24px 0 4px;font-size:15px;color:var(--text-muted);">Technical Specifications <span style="font-weight:400;">(required for Asset item types)</span></h3>

                    <label for="energy_rating">Energy Rating</label>
                    <input type="text" id="energy_rating" name="energy_rating" placeholder="e.g. 5 Star"
                           value="<?= htmlspecialchars($old['energy_rating'] ?? '') ?>">

                    <label for="monthly_consumption">Monthly Consumption (kWh)</label>
                    <input type="number" id="monthly_consumption" name="monthly_consumption" min="0" step="0.01"
                           placeholder="e.g. 120.50"
                           value="<?= htmlspecialchars($old['monthly_consumption'] ?? '') ?>">

                    <label for="cooling_capacity">Cooling Capacity</label>
                    <input type="text" id="cooling_capacity" name="cooling_capacity" placeholder="e.g. 1.5 HP (12,000 BTU/hr)"
                           value="<?= htmlspecialchars($old['cooling_capacity'] ?? '') ?>">

                    <label for="refrigerant">Refrigerant</label>
                    <input type="text" id="refrigerant" name="refrigerant" placeholder="e.g. R32"
                           value="<?= htmlspecialchars($old['refrigerant'] ?? '') ?>">

                    <label for="installation_type">Installation Type</label>
                    <input type="text" id="installation_type" name="installation_type" placeholder="e.g. Wall Mounted"
                           value="<?= htmlspecialchars($old['installation_type'] ?? '') ?>">

                    <label for="power_input">Power Input</label>
                    <input type="text" id="power_input" name="power_input" placeholder="e.g. 220-240V ~50Hz"
                           value="<?= htmlspecialchars($old['power_input'] ?? '') ?>">

                    <label for="year">Year</label>
                    <input type="number" id="year" name="year" min="1990" max="2100" step="1" placeholder="e.g. 2024"
                           value="<?= htmlspecialchars($old['year'] ?? '') ?>">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Product</button>
                    <a href="index.php?module=products&action=index" class="btn btn-secondary">Cancel</a>
                </div>
            </form>

            <script>
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

            updateSpecsVisibility();
            </script>
        </div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
