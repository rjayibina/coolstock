<?php
/**
 * Views/dashboard/_technician.php
 * Technician dashboard body - everything here is scoped to the signed-in
 * technician's own requests.
 *
 * Before the role split a Technician landed on the warehouse dashboard:
 * org-wide product and transaction totals, a products-by-category chart,
 * the predicted-stockout table and a feed of every other technician's
 * movements - none of it theirs to act on, and none of their own open
 * requests anywhere in sight.
 *
 * The three figures and two preview tables below answer the only
 * questions this role actually has: what am I waiting on, and what am I
 * still holding? Each one links into the matching Item Requests tab,
 * which owns the full paginated lists.
 *
 * Included from Views/dashboard/index.php, which owns the page chrome and
 * supplies: $myStats, $myBorrows, $myRequests.
 */
$requestsUrl = 'index.php?module=requests&action=index';
?>
        <div class="stat-grid">
            <a href="<?= $requestsUrl ?>&tab=pending" class="stat-card stat-card-link">
                <div class="stat-label">Awaiting Approval</div>
                <div class="stat-value"><?= (int) $myStats['pending'] ?></div>
            </a>
            <a href="<?= $requestsUrl ?>&tab=active" class="stat-card stat-card-link">
                <div class="stat-label">Items I Have Out</div>
                <div class="stat-value"><?= (int) $myStats['active'] ?></div>
            </a>
            <a href="<?= $requestsUrl ?>&tab=active" class="stat-card stat-card-link">
                <div class="stat-label">Units Still Out</div>
                <div class="stat-value"><?= (int) $myStats['units_out'] ?></div>
            </a>
        </div>

        <div class="section-head">
            <div class="section-title">Items I Have Out</div>
            <?php if (!empty($myBorrows)): ?>
                <a href="<?= $requestsUrl ?>&tab=active" class="text-link">View all</a>
            <?php endif; ?>
        </div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Released From</th>
                        <th>Still Out</th>
                        <th>Released On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myBorrows)): ?>
                        <tr class="empty-row"><td colspan="4">
                            Nothing is checked out to you right now. Approved requests appear here once Warehouse Staff releases the stock.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($myBorrows as $b): ?>
                            <?php
                            $borrowed = (int) $b['quantity'];
                            $returned = (int) $b['returned_quantity'];
                            $outstanding = $borrowed - $returned;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($b['model'] ?? 'Unknown product') ?></strong></td>
                                <td class="cell-muted"><?= htmlspecialchars($b['location_name'] ?? '—') ?></td>
                                <td>
                                    <strong><?= $outstanding ?></strong> <span class="cell-muted">of <?= $borrowed ?></span>
                                    <?php if ($returned > 0): ?>
                                        <div class="cell-muted" style="font-size:12px;"><?= $returned ?> already returned</div>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-muted"><?= htmlspecialchars(format_datetime($b['transaction_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-head">
            <div class="section-title">Awaiting Approval</div>
            <?php if (!empty($myRequests)): ?>
                <a href="<?= $requestsUrl ?>&tab=pending" class="text-link">View all</a>
            <?php endif; ?>
        </div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Requested On</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myRequests)): ?>
                        <tr class="empty-row"><td colspan="4">
                            You have no requests waiting for approval.
                            <div style="margin-top:12px;">
                                <a href="<?= $requestsUrl ?>&tab=pending" class="btn btn-primary btn-sm">Make a request</a>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($myRequests as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['model'] ?? 'Unknown product') ?></strong></td>
                                <td class="cell-id"><?= (int) $r['quantity'] ?></td>
                                <td class="cell-muted"><?= htmlspecialchars(format_datetime($r['transaction_date'])) ?></td>
                                <td class="cell-muted"><?= htmlspecialchars(trim((string) ($r['notes'] ?? '')) !== '' ? $r['notes'] : '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
