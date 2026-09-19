<?php
/**
 * Views/dashboard/_admin.php
 * Administrator dashboard body.
 *
 * Admin is a superset of Warehouse Staff: an Administrator still runs the
 * warehouse day to day, so rather than maintaining a near-duplicate of
 * that view this includes it wholesale and then adds the oversight
 * sections only an Administrator can act on.
 *
 * Order is deliberate - act (approvals, reorders), analyse (trends, from
 * _warehouse.php), then oversee (below). The org figures here are the
 * ones deliberately dropped from the warehouse tiles in Phase 3: nobody
 * on the floor acts on "5 categories", but the person managing the
 * system does.
 *
 * Included from Views/dashboard/index.php; see _warehouse.php for the
 * inherited variables, plus $totalUsers and $usersByRole.
 */
require __DIR__ . '/_warehouse.php';
?>

        <div class="section-title">Organisation</div>
        <div class="stat-grid">
            <a href="index.php?module=users&action=index" class="stat-card stat-card-link">
                <div class="stat-label">User Accounts</div>
                <div class="stat-value"><?= (int) $totalUsers ?></div>
            </a>
            <a href="index.php?module=categories&action=index" class="stat-card stat-card-link">
                <div class="stat-label">Categories</div>
                <div class="stat-value"><?= (int) $stats['total_categories'] ?></div>
            </a>
            <a href="index.php?module=transactions&action=index" class="stat-card stat-card-link">
                <div class="stat-label">Transactions Logged</div>
                <div class="stat-value"><?= (int) $stats['total_transactions'] ?></div>
            </a>
        </div>

        <div class="section-head">
            <div class="section-title">Team by Role</div>
            <a href="index.php?module=users&action=index" class="text-link">Manage users</a>
        </div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Active</th>
                        <th>Deactivated</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usersByRole)): ?>
                        <tr class="empty-row"><td colspan="4">No user accounts found.</td></tr>
                    <?php else: ?>
                        <?php // $roleKey, not $role: an included partial shares the
                              // enclosing scope, so a loop variable with a common
                              // name silently overwrites whatever the caller had
                              // in it once the loop ends. ?>
                        <?php foreach ($usersByRole as $roleKey => $roleCounts): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars(User::roleLabel($roleKey)) ?></strong></td>
                                <td class="cell-id"><?= (int) $roleCounts['active'] ?></td>
                                <td>
                                    <?php if ((int) $roleCounts['inactive'] > 0): ?>
                                        <span class="badge" style="background:var(--danger-bg);color:var(--danger);"><?= (int) $roleCounts['inactive'] ?></span>
                                    <?php else: ?>
                                        <span class="cell-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-id"><?= (int) $roleCounts['total'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
