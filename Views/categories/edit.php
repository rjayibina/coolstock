<?php
/**
 * Views/categories/edit.php
 * Expects: $data (array - current category row), $error (string|null)
 */
$pageTitle = 'Edit Category';
$activeSection = 'inventory';
$activeSubNav = 'categories';
require __DIR__ . '/../partials/header.php';
?>
        <a href="index.php?module=categories&action=index" class="back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back to Categories
        </a>

        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Edit Category</h1>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="index.php?module=categories&action=edit&id=<?= htmlspecialchars($data['category_id']) ?>">
                <input type="hidden" name="category_id" value="<?= htmlspecialchars($data['category_id']) ?>">

                <label for="category_name">Category Name</label>
                <input type="text" id="category_name" name="category_name"
                       value="<?= htmlspecialchars($data['category_name']) ?>" required>

                <label for="category_description">Description</label>
                <textarea id="category_description" name="category_description"><?= htmlspecialchars($data['category_description'] ?? '') ?></textarea>

                <label style="display:flex;align-items:center;gap:8px;font-weight:500;font-size:13px;color:var(--text-dark);margin-bottom:18px;">
                    <input type="checkbox" name="requires_serial" value="1" <?= !empty($data['requires_serial']) ? 'checked' : '' ?> style="width:auto;margin:0;">
                    Requires a serial number when stock is taken out
                </label>
                <div style="font-size:12px;color:var(--text-muted);margin-top:-14px;margin-bottom:18px;">Leave checked for whole units and unit parts. Uncheck for tools and cleaning/repair materials.</div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Category</button>
                    <a href="index.php?module=categories&action=index" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
