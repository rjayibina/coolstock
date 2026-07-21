<?php
/**
 * Views/transactions/create.php
 * Expects: $error (string|null), $items (array of inventory_items)
 */
$pageTitle = 'Log Transaction';
$activeSection = 'inventory';
$activeSubNav = 'transactions';
require __DIR__ . '/../partials/header.php';
$old = $_POST ?: array_filter(['item_id' => $prefillItemId, 'transaction_type' => $prefillType]);
?>
        <a href="index.php?module=transactions&action=index" class="back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back to Transactions
        </a>

        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Log Transaction</h1>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <?php if (empty($items)): ?>
                <div class="alert alert-warning">No products exist yet — <a href="index.php?module=products&action=create">add one first</a> before logging a transaction.</div>
            <?php else: ?>
            <form method="POST" action="index.php?module=transactions&action=create">
                <label for="transaction_type">Transaction Type</label>
                <select id="transaction_type" name="transaction_type" required
                        style="width:100%;padding:10px 13px;border:1px solid var(--border);border-radius:8px;font-size:14px;margin-bottom:18px;font-family:inherit;color:var(--text-dark);">
                    <option value="">Select a type...</option>
                    <option value="stock_in" <?= (($old['transaction_type'] ?? '') === 'stock_in') ? 'selected' : '' ?>>Stock In — new or returned stock added</option>
                    <option value="stock_out" <?= (($old['transaction_type'] ?? '') === 'stock_out') ? 'selected' : '' ?>>Stock Out — items released for service use</option>
                    <option value="item_request" <?= (($old['transaction_type'] ?? '') === 'item_request') ? 'selected' : '' ?>>Item Request — pending until approved, stock not deducted yet</option>
                    <option value="borrow" <?= (($old['transaction_type'] ?? '') === 'borrow') ? 'selected' : '' ?>>Borrow — technician borrows a tool temporarily</option>
                    <option value="return" <?= (($old['transaction_type'] ?? '') === 'return') ? 'selected' : '' ?>>Return — borrowed or requested item returned</option>
                </select>

                <label for="item_id">Product</label>
                <select id="item_id" name="item_id" required
                        style="width:100%;padding:10px 13px;border:1px solid var(--border);border-radius:8px;font-size:14px;margin-bottom:18px;font-family:inherit;color:var(--text-dark);">
                    <option value="">Select a product...</option>
                    <?php foreach ($items as $it): ?>
                        <option value="<?= $it['item_id'] ?>" <?= (($old['item_id'] ?? '') == $it['item_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($it['item_name']) ?> (<?= (int) $it['quantity_on_hand'] ?> <?= htmlspecialchars($it['unit_of_measure'] ?? '') ?> on hand)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="quantity">Quantity</label>
                <input type="number" id="quantity" name="quantity" min="1" step="1"
                       value="<?= htmlspecialchars($old['quantity'] ?? '1') ?>" required>

                <label for="technician_name">Technician Name <span style="font-weight:400;color:var(--text-muted);">(required for requests, borrows, and returns)</span></label>
                <input type="text" id="technician_name" name="technician_name"
                       placeholder="e.g. Juan Dela Cruz"
                       value="<?= htmlspecialchars($old['technician_name'] ?? '') ?>">

                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" placeholder="Optional notes about this transaction"><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Log Transaction</button>
                    <a href="index.php?module=transactions&action=index" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
