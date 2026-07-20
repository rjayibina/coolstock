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

                <label for="category_id">Category <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <?php if (empty($categories)): ?>
                    <p style="font-size:12.5px;color:var(--text-muted);margin:-6px 0 12px 0;">No categories yet — you can <a href="index.php?module=categories&action=create">create one</a>, or leave this product uncategorized for now.</p>
                <?php endif; ?>
                <select id="category_id" name="category_id"
                        style="width:100%;padding:10px 13px;border:1px solid var(--border);border-radius:8px;font-size:14px;margin-bottom:18px;font-family:inherit;color:var(--text-dark);">
                    <option value="">No category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= (($old['category_id'] ?? '') == $cat['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
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

                <label for="quantity_on_hand">Quantity on Hand</label>
                <input type="number" id="quantity_on_hand" name="quantity_on_hand" min="0" step="1"
                       value="<?= htmlspecialchars($old['quantity_on_hand'] ?? '0') ?>" required>

                <label for="minimum_stock_level">Minimum Stock Level</label>
                <input type="number" id="minimum_stock_level" name="minimum_stock_level" min="0" step="1"
                       value="<?= htmlspecialchars($old['minimum_stock_level'] ?? '0') ?>" required>

                <label for="serial_number">Serial Number <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <input type="text" id="serial_number" name="serial_number"
                       placeholder="e.g. SN-2026-00123"
                       value="<?= htmlspecialchars($old['serial_number'] ?? '') ?>">

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Product</button>
                    <a href="index.php?module=products&action=index" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
