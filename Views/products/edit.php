<?php
/**
 * Views/products/edit.php
 * Expects: $data (array - current item row), $error (string|null), $categories (array)
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
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="index.php?module=products&action=edit&id=<?= htmlspecialchars($data['item_id']) ?>" enctype="multipart/form-data">
                <input type="hidden" name="item_id" value="<?= htmlspecialchars($data['item_id']) ?>">

                <label for="product_image">Product Image</label>
                <?php if (!empty($data['image_path'])): ?>
                    <div style="margin-bottom:10px;">
                        <img src="<?= htmlspecialchars($data['image_path']) ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                    </div>
                <?php endif; ?>
                <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="margin-bottom:18px;">
                <div style="font-size:12px;color:var(--text-muted);margin-top:-14px;margin-bottom:18px;">Leave empty to keep the current image.</div>

                <?php
                $selectedCategoryId = $data['category_id'] ?? '';
                $selectedCategoryName = $data['category_name'] ?? '';
                require __DIR__ . '/../partials/category-combobox.php';
                ?>

                <label for="item_name">Product Name</label>
                <input type="text" id="item_name" name="item_name"
                       value="<?= htmlspecialchars($data['item_name']) ?>" required>

                <label for="description">Description</label>
                <textarea id="description" name="description"><?= htmlspecialchars($data['description'] ?? '') ?></textarea>

                <label for="unit_of_measure">Unit of Measure</label>
                <input type="text" id="unit_of_measure" name="unit_of_measure"
                       value="<?= htmlspecialchars($data['unit_of_measure'] ?? '') ?>">

                <label for="quantity_on_hand">Quantity on Hand</label>
                <input type="number" id="quantity_on_hand" name="quantity_on_hand" min="0" step="1"
                       value="<?= htmlspecialchars($data['quantity_on_hand']) ?>" required>

                <label for="minimum_stock_level">Minimum Stock Level</label>
                <input type="number" id="minimum_stock_level" name="minimum_stock_level" min="0" step="1"
                       value="<?= htmlspecialchars($data['minimum_stock_level']) ?>" required>

                <label for="serial_number">Serial Number <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <input type="text" id="serial_number" name="serial_number"
                       value="<?= htmlspecialchars($data['serial_number'] ?? '') ?>">

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Product</button>
                    <a href="index.php?module=products&action=index" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
