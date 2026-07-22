<?php
/**
 * Views/categories/view.php
 * Expects: $category (array - single row), $products (array of inventory_items in this category)
 */
$pageTitle = $category['category_name'];
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
                <h1 class="page-title"><?= htmlspecialchars($category['category_name']) ?></h1>
                <span class="page-title-count"><?= count($products) ?> <?= count($products) === 1 ? 'product' : 'products' ?></span>
            </div>
            <div class="header-actions">
                <a href="index.php?module=categories&action=edit&id=<?= $category['category_id'] ?>" class="btn btn-secondary">Edit Category</a>
                <a href="index.php?module=products&action=create" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Product
                </a>
            </div>
        </div>

        <?php if (!empty($category['category_description'])): ?>
            <p style="color:var(--text-muted);font-size:14px;margin:-10px 0 20px 0;"><?= htmlspecialchars($category['category_description']) ?></p>
        <?php endif; ?>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Unit</th>
                        <th>Serial No.</th>
                        <th>Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr class="empty-row">
                            <td colspan="4">No products in this category yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <?php
                            $isOut = (int) $p['quantity_on_hand'] === 0;
                            $isLow = !$isOut && $p['quantity_on_hand'] <= $p['minimum_stock_level'];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['item_name']) ?></strong></td>
                                <td class="cell-muted"><?= htmlspecialchars($p['unit_of_measure'] ?? '—') ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($p['serial_number'] ?? '—') ?></td>
                                <td class="cell-id"><?= (int) $p['quantity_on_hand'] ?> <span class="cell-muted">/ min <?= (int) $p['minimum_stock_level'] ?></span></td>
                                <td>
                                    <?php if ($isOut): ?>
                                        <span class="badge" style="background:var(--danger-bg);color:var(--danger);">Out of stock</span>
                                    <?php elseif ($isLow): ?>
                                        <span class="badge" style="background:var(--warning-bg);color:var(--warning);">Low stock</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--success-bg);color:var(--success);">In stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
