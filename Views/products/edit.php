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
            <form method="POST" id="editProductForm" action="index.php?module=products&action=edit&id=<?= htmlspecialchars($data['item_id']) ?>">
                <input type="hidden" name="item_id" value="<?= htmlspecialchars($data['item_id']) ?>">

                <?php $selectedCategoryId = $data['category_id'] ?? ''; ?>
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="" disabled <?= $selectedCategoryId === '' ? 'selected' : '' ?>>Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= (string) $selectedCategoryId === (string) $cat['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="model">Model</label>
                <input type="text" id="model" name="model"
                       value="<?= htmlspecialchars($data['model']) ?>" required>

                <label for="brand_id">Brand <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <select id="brand_id" name="brand_id">
                    <option value="">No brand</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['brand_id'] ?>" <?= (string) ($data['brand_id'] ?? '') === (string) $b['brand_id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['brand_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="item_type_id">Item Type <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <select id="item_type_id" name="item_type_id" onchange="updateSpecsVisibility()">
                    <option value="">No item type</option>
                    <?php foreach ($itemTypes as $t): ?>
                        <option value="<?= $t['item_type_id'] ?>" data-type-name="<?= htmlspecialchars($t['type_name']) ?>" <?= (string) ($data['item_type_id'] ?? '') === (string) $t['item_type_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <div id="specs_section">
                    <h3 style="margin:24px 0 4px;font-size:15px;color:var(--text-muted);">Technical Specifications <span style="font-weight:400;">(required for Asset item types)</span></h3>

                    <label for="energy_rating">Energy Rating</label>
                    <input type="text" id="energy_rating" name="energy_rating" placeholder="e.g. 5 Star"
                           value="<?= htmlspecialchars($data['energy_rating'] ?? '') ?>">

                    <label for="monthly_consumption">Monthly Consumption (kWh)</label>
                    <input type="number" id="monthly_consumption" name="monthly_consumption" min="0" step="0.01"
                           placeholder="e.g. 120.50"
                           value="<?= htmlspecialchars($data['monthly_consumption'] ?? '') ?>">

                    <label for="cooling_capacity">Cooling Capacity</label>
                    <input type="text" id="cooling_capacity" name="cooling_capacity" placeholder="e.g. 1.5 HP (12,000 BTU/hr)"
                           value="<?= htmlspecialchars($data['cooling_capacity'] ?? '') ?>">

                    <label for="refrigerant">Refrigerant</label>
                    <input type="text" id="refrigerant" name="refrigerant" placeholder="e.g. R32"
                           value="<?= htmlspecialchars($data['refrigerant'] ?? '') ?>">

                    <label for="installation_type">Installation Type</label>
                    <input type="text" id="installation_type" name="installation_type" placeholder="e.g. Wall Mounted"
                           value="<?= htmlspecialchars($data['installation_type'] ?? '') ?>">

                    <label for="power_input">Power Input</label>
                    <input type="text" id="power_input" name="power_input" placeholder="e.g. 220-240V ~50Hz"
                           value="<?= htmlspecialchars($data['power_input'] ?? '') ?>">

                    <label for="year">Year</label>
                    <input type="number" id="year" name="year" min="1990" max="2100" step="1" placeholder="e.g. 2024"
                           value="<?= htmlspecialchars($data['year'] ?? '') ?>">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Product</button>
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
