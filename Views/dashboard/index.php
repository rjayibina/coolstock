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
