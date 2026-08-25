<?php
/**
 * Views/products/create.php
 * Expects: $error (string|null), $categories (array)
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
            <form method="POST" action="index.php?module=products&action=create" enctype="multipart/form-data">
                <label for="product_image">Product Image</label>
                <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="margin-bottom:18px;">

                <?php $selectedCategoryId = $old['category_id'] ?? ''; ?>
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="" disabled <?= $selectedCategoryId === '' ? 'selected' : '' ?>>Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= (string) $selectedCategoryId === (string) $cat['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="item_name">Product Name</label>
                <input type="text" id="item_name" name="item_name"
                       placeholder="e.g. R410A Refrigerant Tank"
                       value="<?= htmlspecialchars($old['item_name'] ?? '') ?>" required>

                <label for="description">Description</label>
                <textarea id="description" name="description"
                          placeholder="Optional notes about this product"><?= htmlspecialchars($old['description'] ?? '') ?></textarea>

                <label for="unit_of_measure">Unit of Measure</label>
                <input type="text" id="unit_of_measure" name="unit_of_measure"
                       placeholder="e.g. pcs, kg, box"
                       value="<?= htmlspecialchars($old['unit_of_measure'] ?? '') ?>">

                <?php
                $selectedBrandId = (string) ($old['brand_id'] ?? '');
                $selectedBrandCode = '';
                foreach ($brands as $b) {
                    if ((string) $b['brand_id'] === $selectedBrandId) {
                        $selectedBrandCode = $b['brand_code'] ?? '';
                        break;
                    }
                }
                ?>
                <label for="brand_id">Brand <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <select id="brand_id" name="brand_id" onchange="var o=this.options[this.selectedIndex];document.getElementById('brand_code_display').textContent = o.dataset.code ? ('Code: ' + o.dataset.code) : '';">
                    <option value="" data-code="">No brand</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['brand_id'] ?>" data-code="<?= htmlspecialchars($b['brand_code'] ?? '') ?>" <?= (string) ($old['brand_id'] ?? '') === (string) $b['brand_id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['brand_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="brand_code_display" style="font-size:13px;color:var(--text-muted);margin-top:-10px;margin-bottom:14px;"><?= $selectedBrandCode !== '' ? 'Code: ' . htmlspecialchars($selectedBrandCode) : '' ?></div>

                <label for="item_type_id">Item Type <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <select id="item_type_id" name="item_type_id">
                    <option value="">No item type</option>
                    <?php foreach ($itemTypes as $t): ?>
                        <option value="<?= $t['item_type_id'] ?>" <?= (string) ($old['item_type_id'] ?? '') === (string) $t['item_type_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="location_id">Location <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <select id="location_id" name="location_id">
                    <option value="">No location</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['location_id'] ?>" <?= (string) ($old['location_id'] ?? '') === (string) $loc['location_id'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <h3 style="margin:24px 0 4px;font-size:15px;color:var(--text-muted);">Technical Specifications <span style="font-weight:400;">(all optional)</span></h3>

                <label for="model">Model</label>
                <input type="text" id="model" name="model" placeholder="e.g. FTKC50UVM"
                       value="<?= htmlspecialchars($old['model'] ?? '') ?>">

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

                <label for="quantity_on_hand">Quantity on Hand</label>
                <input type="number" id="quantity_on_hand" name="quantity_on_hand" min="0" step="1"
                       value="<?= htmlspecialchars($old['quantity_on_hand'] ?? '0') ?>" required>

                <label for="minimum_stock_level">Minimum Stock Level</label>
                <input type="number" id="minimum_stock_level" name="minimum_stock_level" min="0" step="1"
                       value="<?= htmlspecialchars($old['minimum_stock_level'] ?? '0') ?>" required>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Product</button>
                    <a href="index.php?module=products&action=index" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
