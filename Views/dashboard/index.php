<?php
/**
 * Views/dashboard/index.php
 * Shell for the role-specific dashboards. Owns everything common to all
 * three - page chrome, the greeting, the database-error banner - then
 * hands the body to the partial for the signed-in role:
 *
 *   _technician.php  what I requested and what I'm still holding
 *   _warehouse.php   stock health and the approval queue
 *   _admin.php       everything Warehouse sees, plus org-level sections
 *
 * Expects (staff only, see DashboardController): $stats,
 * $recentTransactions, $productsByCategory, $transactionsByType,
 * $dailyVolume, $predictedStockouts. $dbError may be set for any role.
 */
require_once __DIR__ . '/../../Models/Transaction.php';

$pageTitle = 'Dashboard';
$activeSection = 'dashboard';
$viewer = current_user();

// First name only - "Good afternoon, Roberto" reads better than the full
// name, and the rail already shows who is signed in.
$firstName = trim(explode(' ', trim((string) ($viewer['full_name'] ?? '')))[0]);
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

require __DIR__ . '/../partials/header.php';
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">
                    <?= $firstName !== '' ? htmlspecialchars($greeting . ', ' . $firstName) : 'Dashboard' ?>
                </h1>
            </div>
            <?php if (has_role('technician')): ?>
                <?php // A Technician's only create action. Without it, once
                      // they have a request pending the empty-state CTA
                      // disappears and the dashboard becomes read-only. ?>
                <div class="header-actions">
                    <a href="index.php?module=requests&action=index&tab=pending" class="btn btn-primary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        New Request
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($dbError): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($dbError) ?></div>
        <?php endif; ?>

<?php
if (has_role('technician')) {
    require __DIR__ . '/_technician.php';
} elseif (has_role('admin')) {
    require __DIR__ . '/_admin.php';
} else {
    require __DIR__ . '/_warehouse.php';
}
?>
<?php require __DIR__ . '/../partials/footer.php'; ?>
