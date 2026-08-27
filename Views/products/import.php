<?php
/**
 * Views/products/import.php
 * Expects: $error (string|null), $results (array|null: ['imported' => int, 'skipped' => string[]])
 */
$pageTitle = 'Import Products';
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
                <h1 class="page-title">Import Products</h1>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($results): ?>
            <div class="alert alert-success"><?= $results['imported'] ?> product<?= $results['imported'] === 1 ? '' : 's' ?> imported successfully.</div>
            <?php if (!empty($results['skipped'])): ?>
                <div class="alert alert-warning">
                    <?= count($results['skipped']) ?> row<?= count($results['skipped']) === 1 ? '' : 's' ?> skipped:
                    <ul style="margin:8px 0 0 18px;">
                        <?php foreach ($results['skipped'] as $msg): ?>
                            <li><?= htmlspecialchars($msg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <a href="index.php?module=products&action=index" class="btn btn-primary">Back to Products</a>
        <?php else: ?>
            <div class="form-card">
                <form method="POST" action="index.php?module=products&action=import" enctype="multipart/form-data">
                    <label for="import_file">CSV or XLSX File</label>
                    <input type="file" id="import_file" name="import_file" accept=".csv,.xlsx" required style="margin-bottom:18px;">

                    <div class="alert alert-warning" style="margin-bottom:18px;">
                        The old .xls format isn't supported — please save as .xlsx or .csv.
                    </div>

                    <div style="font-size:13px;color:var(--text-muted);margin-bottom:18px;line-height:1.6;">
                        The first row must be a header with these column names (any order):<br>
                        <code>category_name, brand_name, type_name, location_name, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year</code><br>
                        <code>category_name</code> and <code>model</code> are required — <code>model</code> is each product's only identifying name, and <code>category_name</code> must match an existing category exactly. <code>brand_name</code>, <code>type_name</code>, and <code>location_name</code> are optional — leave them blank if not applicable, or they must match an existing brand / item type / location exactly (add new ones from the Brands / Item Types / Locations pages first).
                        <code>energy_rating</code>, <code>monthly_consumption</code>, <code>cooling_capacity</code>, <code>refrigerant</code>, <code>installation_type</code>, <code>power_input</code>, and <code>year</code> are free-form spec fields — required when <code>type_name</code> is <code>Asset</code>, otherwise leave them blank.
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Import</button>
                        <a href="index.php?module=products&action=index" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
<?php require __DIR__ . '/../partials/footer.php'; ?>
