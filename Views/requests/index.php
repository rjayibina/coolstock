<?php
/**
 * Views/requests/index.php
 * Expects: $requests (array - shape depends on $tab: item_request rows
 *          for 'pending', borrow rows for 'active', a merged mix for
 *          'history'), $pagination, $items (catalog, for the Add
 *          Request modal), $locations (for the Approve modal).
 */
$status = $_GET['status'] ?? null;
$message = $_GET['message'] ?? '';
$requestedCount = (int) ($_GET['count'] ?? 0);
$tab = $_GET['tab'] ?? 'pending';
if (!in_array($tab, ['pending', 'active', 'history'], true)) {
    $tab = 'pending';
}
$viewer = current_user();
$isStaff = has_role('admin', 'warehouse_staff');
$isTechnician = has_role('technician');
$pageTitle = 'Item Requests';
$activeSection = 'requests';
$count = $pagination['totalCount'];
require __DIR__ . '/../partials/header.php';

function requestTabUrl(string $tab): string
{
    return "index.php?module=requests&action=index&tab=" . urlencode($tab);
}

/** A Technician only ever sees rows from their own requests, so the
 *  labels say so rather than implying they're looking at everyone's. */
function requestTabLabel(string $tab, bool $isTechnician): string
{
    return match ($tab) {
        'pending' => $isTechnician ? 'My Pending Requests' : 'Pending Requests',
        'active' => $isTechnician ? 'Items I Have Out' : 'Active Borrows',
        default => $isTechnician ? 'My History' : 'History',
    };
}
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Item Requests</h1>
                <span class="page-title-count"><?= $count ?> <?= $count === 1 ? 'record' : 'records' ?></span>
            </div>
            <div class="header-actions">
                <button type="button" class="btn btn-primary" onclick="openAddRequestModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New Request
                </button>
            </div>
        </div>

        <?php if ($status === 'requested'): ?>
            <div class="alert alert-success"><?= $requestedCount ?> item<?= $requestedCount === 1 ? '' : 's' ?> requested. Waiting on Warehouse Staff approval.</div>
        <?php elseif ($status === 'approved'): ?>
            <div class="alert alert-success">Request approved and released.</div>
        <?php elseif ($status === 'declined'): ?>
            <div class="alert alert-success">Request declined.</div>
        <?php elseif ($status === 'returned'): ?>
            <div class="alert alert-success">Return logged.</div>
        <?php elseif ($status === 'forbidden'): ?>
            <div class="alert alert-warning">Only Warehouse Staff and Administrators can do that.</div>
        <?php elseif ($status === 'error'): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($message !== '' ? $message : 'Something went wrong. Please try again.') ?></div>
        <?php endif; ?>

        <div class="stat-grid" style="margin-bottom:18px;">
            <div class="stat-card">
                <div class="stat-label"><?= $isTechnician ? 'My Pending Requests' : 'Awaiting Approval' ?></div>
                <div class="stat-value"><?= (int) $summary['pending'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><?= $isTechnician ? 'Items I Have Out' : 'Active Borrows' ?></div>
                <div class="stat-value"><?= (int) $summary['active'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Units Still Out</div>
                <div class="stat-value"><?= (int) $summary['outstandingUnits'] ?></div>
            </div>
        </div>

        <div class="header-actions" style="margin-bottom:16px;">
            <a href="<?= requestTabUrl('pending') ?>" class="btn <?= $tab === 'pending' ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= requestTabLabel('pending', $isTechnician) ?></a>
            <a href="<?= requestTabUrl('active') ?>" class="btn <?= $tab === 'active' ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= requestTabLabel('active', $isTechnician) ?></a>
            <a href="<?= requestTabUrl('history') ?>" class="btn <?= $tab === 'history' ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= requestTabLabel('history', $isTechnician) ?></a>
        </div>

        <div class="table-card">
            <table id="requestsTable">
                <?php if ($tab === 'pending'): ?>
                <thead>
                    <tr>
                        <th>Product</th>
                        <?php if (!$isTechnician): ?><th>Requested By</th><?php endif; ?>
                        <th>Quantity</th>
                        <th>Date</th>
                        <th>Notes</th>
                        <?php if ($isStaff): ?><th style="width:170px;">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $pendingCols = 4 + ($isTechnician ? 0 : 1) + ($isStaff ? 1 : 0); ?>
                    <?php if (empty($requests)): ?>
                        <tr class="empty-row"><td colspan="<?= $pendingCols ?>">
                            <?= $isTechnician
                                ? 'You have no requests waiting for approval.'
                                : 'No pending requests.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['model'] ?? 'Unknown product') ?></strong></td>
                                <?php if (!$isTechnician): ?>
                                <td class="cell-muted"><?= htmlspecialchars($r['requested_by_name'] ?? $r['technician_name'] ?? '—') ?></td>
                                <?php endif; ?>
                                <td class="cell-id"><?= (int) $r['quantity'] ?></td>
                                <td class="cell-muted"><?= htmlspecialchars(format_datetime($r['transaction_date'])) ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
                                <?php if ($isStaff): ?>
                                <td class="actions">
                                    <button type="button" class="btn btn-success btn-sm" onclick="openApproveModal(<?= (int) $r['transaction_id'] ?>)">Approve</button>
                                    <button type="button" class="btn btn-danger btn-sm"
                                            onclick="openDeclineModal(<?= (int) $r['transaction_id'] ?>, <?= htmlspecialchars(json_encode($r['model'] ?? 'this item'), ENT_QUOTES) ?>)">Decline</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

                <?php elseif ($tab === 'active'): ?>
                <thead>
                    <tr>
                        <th>Product</th>
                        <?php if (!$isTechnician): ?><th>Requested By</th><?php endif; ?>
                        <th>Released By</th>
                        <th>Location</th>
                        <th>Still Out</th>
                        <th>Released On</th>
                        <?php if ($isStaff): ?><th style="width:150px;">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $activeCols = 5 + ($isTechnician ? 0 : 1) + ($isStaff ? 1 : 0);
                    ?>
                    <?php if (empty($requests)): ?>
                        <tr class="empty-row"><td colspan="<?= $activeCols ?>">
                            <?= $isTechnician
                                ? 'Nothing checked out to you right now. Approved requests show up here once Warehouse Staff releases the stock.'
                                : 'No active borrows.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <?php
                            $borrowed = (int) $r['quantity'];
                            $returned = (int) $r['returned_quantity'];
                            $outstanding = $borrowed - $returned;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['model'] ?? 'Unknown product') ?></strong></td>
                                <?php if (!$isTechnician): ?>
                                <td class="cell-muted"><?= htmlspecialchars($r['requested_by_name'] ?? $r['technician_name'] ?? '—') ?></td>
                                <?php endif; ?>
                                <td class="cell-muted"><?= htmlspecialchars($r['technician_name'] ?? '—') ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($r['location_name'] ?? '—') ?></td>
                                <td>
                                    <strong><?= $outstanding ?></strong> <span class="cell-muted">of <?= $borrowed ?></span>
                                    <?php if ($returned > 0): ?>
                                        <div class="cell-muted" style="font-size:12px;"><?= $returned ?> already returned</div>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-muted"><?= htmlspecialchars(format_datetime($r['transaction_date'])) ?></td>
                                <?php if ($isStaff): ?>
                                <td class="actions">
                                    <button type="button" class="btn btn-edit btn-sm"
                                            onclick="openReturnModal(<?= (int) $r['transaction_id'] ?>, <?= $outstanding ?>, <?= htmlspecialchars(json_encode($r['model'] ?? 'this item'), ENT_QUOTES) ?>)">Log Return</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

                <?php else: ?>
                <thead>
                    <tr>
                        <th>Product</th>
                        <?php if (!$isTechnician): ?><th>Requested By</th><?php endif; ?>
                        <th>Outcome</th>
                        <th>Quantity</th>
                        <th>Closed On</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $historyCols = 5 + ($isTechnician ? 0 : 1); ?>
                    <?php if (empty($requests)): ?>
                        <tr class="empty-row"><td colspan="<?= $historyCols ?>">
                            <?= $isTechnician
                                ? 'Nothing closed out yet. Declined requests and items you have returned land here.'
                                : 'No history yet.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <?php
                            $wasDeclined = $r['transaction_type'] === 'item_request';
                            $damaged = (int) ($r['returned_damaged_quantity'] ?? 0);
                            // A returned Borrow closes on its last return date;
                            // a declined request on its own request date.
                            $closedOn = $wasDeclined
                                ? $r['transaction_date']
                                : ($r['last_return_date'] ?? $r['transaction_date']);
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['model'] ?? 'Unknown product') ?></strong></td>
                                <?php if (!$isTechnician): ?>
                                <td class="cell-muted"><?= htmlspecialchars($r['requested_by_name'] ?? $r['technician_name'] ?? '—') ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($wasDeclined): ?>
                                        <span class="badge" style="background:var(--danger-bg);color:var(--danger);">Declined</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--success-bg);color:var(--success);">Returned</span>
                                        <?php if ($damaged > 0): ?>
                                            <div class="cell-muted" style="font-size:12px;margin-top:3px;"><?= $damaged ?> damaged, not restocked</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($wasDeclined): ?>
                                        <span class="cell-id"><?= (int) $r['quantity'] ?></span>
                                        <div class="cell-muted" style="font-size:12px;">requested</div>
                                    <?php else: ?>
                                        <span class="cell-id"><?= (int) $r['returned_quantity'] ?> of <?= (int) $r['quantity'] ?></span>
                                        <div class="cell-muted" style="font-size:12px;">returned</div>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-muted"><?= htmlspecialchars(format_datetime($closedOn)) ?></td>
                                <td class="cell-muted">
                                    <?php
                                    // On a returned Borrow the useful note is what
                                    // was said at return time (condition of the
                                    // goods), not the original request's note.
                                    $note = $wasDeclined
                                        ? ($r['notes'] ?? '')
                                        : ($r['last_return_notes'] ?? $r['notes'] ?? '');
                                    ?>
                                    <?= htmlspecialchars(trim((string) $note) !== '' ? $note : '—') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php endif; ?>
            </table>
        </div>

        <?php if ($pagination['totalCount'] > 0): ?>
            <?php
            $startRow = ($pagination['page'] - 1) * $pagination['perPage'] + 1;
            $endRow = min($pagination['page'] * $pagination['perPage'], $pagination['totalCount']);
            ?>
            <div class="pagination-bar">
                <span>Showing <?= $startRow ?>–<?= $endRow ?> of <?= $pagination['totalCount'] ?></span>
                <div class="pagination-controls">
                    <a href="<?= requestTabUrl($tab) ?>&page=<?= max(1, $pagination['page'] - 1) ?>" class="page-btn <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">&lsaquo; Prev</a>
                    <?php foreach (paginate_page_numbers($pagination['page'], $pagination['totalPages']) as $p): ?>
                        <?php if ($p === null): ?>
                            <span class="page-ellipsis">&hellip;</span>
                        <?php else: ?>
                            <a href="<?= requestTabUrl($tab) ?>&page=<?= $p ?>" class="page-btn <?= $p === $pagination['page'] ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <a href="<?= requestTabUrl($tab) ?>&page=<?= min($pagination['totalPages'], $pagination['page'] + 1) ?>" class="page-btn <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">Next &rsaquo;</a>
                </div>
            </div>
        <?php endif; ?>

        <div id="addRequestModal" class="modal-overlay" onclick="if(event.target===this) closeRequestModal('addRequestModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>New Item Request</h3>
                    <button type="button" class="modal-close" onclick="closeRequestModal('addRequestModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php?module=requests&action=create">
                        <?php $requestableItems = array_values(array_filter($items ?? [], fn($it) => (int) ($it['total_quantity'] ?? 0) > 0)); ?>
                        <?php if (!empty($requestableItems)): ?>
                        <div class="search-box" style="position:relative;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="requestProductSearch" placeholder="Search products to request..." autocomplete="off"
                                   oninput="renderRequestSearchResults()" onfocus="renderRequestSearchResults()">
                            <div id="requestSearchResults" class="search-results-dropdown" style="display:none;"></div>
                        </div>
                        <p class="cell-muted" style="font-size:12px;margin:6px 0 0;">Out-of-stock products aren't listed - nothing to release against them yet.</p>

                        <?php // Browsable list (10 per page) so every role can see what's
                              // available without having to type anything - the search box
                              // above still works exactly as before and takes over this
                              // area while there's a query in it. ?>
                        <div id="requestBrowseWrap" style="margin-top:10px;">
                            <div id="requestProductList" class="request-browse-list"></div>
                            <div id="requestProductPagination" class="pagination-bar" style="margin-top:8px;"></div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">No products currently have stock available to request.</div>
                        <?php endif; ?>

                        <div id="requestLineItemsCard" style="margin-top:12px;display:none;">
                            <div id="requestLineItems"></div>
                        </div>

                        <label for="rq_notes" style="margin-top:14px;">Notes <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <textarea id="rq_notes" name="notes" placeholder="What's this for?"></textarea>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Submit Request</button>
                            <button type="button" class="btn btn-secondary" onclick="closeRequestModal('addRequestModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($isStaff): ?>
        <div id="approveModal" class="modal-overlay" onclick="if(event.target===this) closeRequestModal('approveModal')">
            <div class="modal-dialog modal-dialog-sm">
                <div class="modal-header">
                    <h3>Approve Request</h3>
                    <button type="button" class="modal-close" onclick="closeRequestModal('approveModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="approveForm" action="index.php?module=requests&action=approve">
                        <input type="hidden" name="request_id" id="ap_request_id" value="">

                        <label for="ap_location_id">Release From</label>
                        <select id="ap_location_id" name="location_id" required>
                            <option value="" disabled selected>Select a location</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['location_id'] ?>"><?= htmlspecialchars($loc['location_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Approve &amp; Release</button>
                            <button type="button" class="btn btn-secondary" onclick="closeRequestModal('approveModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="returnModal" class="modal-overlay" onclick="if(event.target===this) closeRequestModal('returnModal')">
            <div class="modal-dialog modal-dialog-sm">
                <div class="modal-header">
                    <h3>Log Return</h3>
                    <button type="button" class="modal-close" onclick="closeRequestModal('returnModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="returnForm" action="index.php?module=requests&action=returnItem">
                        <input type="hidden" name="borrow_id" id="rt_borrow_id" value="">

                        <p id="rt_summary" style="margin-top:0;color:var(--text-muted);font-size:13px;"></p>

                        <label for="rt_returned_quantity">Returned Quantity</label>
                        <input type="number" id="rt_returned_quantity" name="returned_quantity" min="1" step="1" required
                               oninput="syncReturnDamagedMax()">

                        <label for="rt_damaged_quantity">Damaged Quantity <span style="font-weight:400;color:var(--text-muted);">(not restocked)</span></label>
                        <input type="number" id="rt_damaged_quantity" name="damaged_quantity" min="0" step="1" value="0" required>

                        <label for="rt_notes">Notes <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <textarea id="rt_notes" name="notes" placeholder="Condition of the returned item(s)"></textarea>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Log Return</button>
                            <button type="button" class="btn btn-secondary" onclick="closeRequestModal('returnModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php // Declining used a bare confirm() before - same styled modal
              // pattern as every delete confirmation elsewhere in the app. ?>
        <div id="declineModal" class="modal-overlay" onclick="if(event.target===this) closeRequestModal('declineModal')">
            <div class="modal-dialog modal-dialog-sm">
                <div class="modal-header">
                    <h3>Decline Request</h3>
                    <button type="button" class="modal-close" onclick="closeRequestModal('declineModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Decline the request for <strong id="dcl_name"></strong>? This cannot be undone.</p>
                    <div class="form-actions">
                        <a id="dcl_confirm_link" href="#" class="btn btn-danger-solid">Decline</a>
                        <button type="button" class="btn btn-secondary" onclick="closeRequestModal('declineModal')">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <script>
        // Out-of-stock products are left out entirely - a Technician can't
        // request what isn't there to release (see ItemRequestController::
        // create() for the matching server-side check).
        const requestCatalog = <?= json_encode(array_map(fn($it) => [
            'item_id' => (int) $it['item_id'],
            'model' => $it['model'],
            'category_name' => $it['category_name'] ?? 'Uncategorized',
        ], $requestableItems), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const requestAddedItemIds = new Set();
        const REQUEST_BROWSE_PAGE_SIZE = 10;
        let requestBrowsePage = 1;

        function closeRequestModal(id) {
            document.getElementById(id)?.classList.remove('open');
        }

        function openAddRequestModal() {
            requestBrowsePage = 1;
            renderRequestBrowseList();
            document.getElementById('addRequestModal').classList.add('open');
        }

        // Every role sees the same 10-per-page browsable catalog here -
        // it's just requestCatalog (already built server-side from every
        // in-stock product, see $requestableItems above) minus whatever
        // is already added as a line item, sliced client-side. The search
        // box's own type-ahead dropdown is untouched; this list is what
        // shows while that box is empty.
        function availableRequestProducts() {
            return requestCatalog.filter(p => !requestAddedItemIds.has(p.item_id));
        }

        function renderRequestBrowseList() {
            const listEl = document.getElementById('requestProductList');
            const pagerEl = document.getElementById('requestProductPagination');
            if (!listEl || !pagerEl) {
                return;
            }

            const products = availableRequestProducts();
            const totalPages = Math.max(1, Math.ceil(products.length / REQUEST_BROWSE_PAGE_SIZE));
            if (requestBrowsePage > totalPages) requestBrowsePage = totalPages;
            if (requestBrowsePage < 1) requestBrowsePage = 1;

            if (products.length === 0) {
                listEl.innerHTML = '<div class="search-result-empty">All available products have been added.</div>';
                pagerEl.innerHTML = '';
                return;
            }

            const start = (requestBrowsePage - 1) * REQUEST_BROWSE_PAGE_SIZE;
            const pageItems = products.slice(start, start + REQUEST_BROWSE_PAGE_SIZE);

            listEl.innerHTML = pageItems.map(p =>
                '<div class="search-result-item" onclick="addRequestLineItem(' + p.item_id + ')">'
                    + '<strong>' + htmlEscapeRequests(p.model) + '</strong>'
                    + '<span class="cell-muted">' + htmlEscapeRequests(p.category_name) + '</span>'
                    + '</div>'
            ).join('');

            const startRow = start + 1;
            const endRow = Math.min(start + REQUEST_BROWSE_PAGE_SIZE, products.length);
            let pagerHtml = '<span>Showing ' + startRow + '–' + endRow + ' of ' + products.length + '</span>';
            pagerHtml += '<div class="pagination-controls">';
            pagerHtml += '<button type="button" class="page-btn' + (requestBrowsePage <= 1 ? ' disabled' : '') + '" onclick="changeRequestBrowsePage(' + (requestBrowsePage - 1) + ')">&lsaquo; Prev</button>';
            for (let p = 1; p <= totalPages; p++) {
                pagerHtml += '<button type="button" class="page-btn' + (p === requestBrowsePage ? ' active' : '') + '" onclick="changeRequestBrowsePage(' + p + ')">' + p + '</button>';
            }
            pagerHtml += '<button type="button" class="page-btn' + (requestBrowsePage >= totalPages ? ' disabled' : '') + '" onclick="changeRequestBrowsePage(' + (requestBrowsePage + 1) + ')">Next &rsaquo;</button>';
            pagerHtml += '</div>';
            pagerEl.innerHTML = pagerHtml;
        }

        function changeRequestBrowsePage(page) {
            requestBrowsePage = page;
            renderRequestBrowseList();
        }

        <?php if ($isStaff): ?>
        function openApproveModal(requestId) {
            document.getElementById('ap_request_id').value = requestId;
            document.getElementById('approveModal').classList.add('open');
        }

        function openDeclineModal(requestId, model) {
            document.getElementById('dcl_name').textContent = model;
            document.getElementById('dcl_confirm_link').href = 'index.php?module=requests&action=decline&id=' + requestId;
            document.getElementById('declineModal').classList.add('open');
        }

        function openReturnModal(borrowId, outstanding, model) {
            document.getElementById('rt_borrow_id').value = borrowId;

            const qty = document.getElementById('rt_returned_quantity');
            qty.max = outstanding;
            qty.value = outstanding;

            const damaged = document.getElementById('rt_damaged_quantity');
            damaged.value = 0;
            damaged.max = outstanding;

            document.getElementById('rt_summary').textContent =
                'Logging a return of ' + (model || 'this item') + ' - '
                + outstanding + ' unit' + (outstanding === 1 ? '' : 's') + ' still out. '
                + 'Return less than that to record a partial return.';

            document.getElementById('returnModal').classList.add('open');
        }
        <?php endif; ?>

        // Damaged can never exceed what's being returned - the server
        // re-checks this too (ItemRequestController::returnItem()), this
        // just stops the pointless round trip.
        function syncReturnDamagedMax() {
            const qty = parseInt(document.getElementById('rt_returned_quantity').value, 10);
            const damaged = document.getElementById('rt_damaged_quantity');
            if (!Number.isNaN(qty)) {
                damaged.max = qty;
                if (parseInt(damaged.value, 10) > qty) {
                    damaged.value = qty;
                }
            }
        }

        function renderRequestSearchResults() {
            const input = document.getElementById('requestProductSearch');
            const dropdown = document.getElementById('requestSearchResults');
            const browseWrap = document.getElementById('requestBrowseWrap');
            const q = input.value.trim().toLowerCase();

            if (q === '') {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
                // Nothing typed - go back to the browsable, paginated list.
                if (browseWrap) browseWrap.style.display = '';
                return;
            }
            // A query is active - the type-ahead dropdown takes over from
            // the paginated list until the search box is cleared again.
            if (browseWrap) browseWrap.style.display = 'none';

            const matches = requestCatalog
                .filter(p => !requestAddedItemIds.has(p.item_id) && p.model.toLowerCase().includes(q))
                .slice(0, 8);

            if (matches.length === 0) {
                dropdown.innerHTML = '<div class="search-result-empty">No matching products</div>';
                dropdown.style.display = '';
                return;
            }

            dropdown.innerHTML = matches.map(p =>
                '<div class="search-result-item" onclick="addRequestLineItem(' + p.item_id + ')">'
                    + '<strong>' + htmlEscapeRequests(p.model) + '</strong>'
                    + '<span class="cell-muted">' + htmlEscapeRequests(p.category_name) + '</span>'
                    + '</div>'
            ).join('');
            dropdown.style.display = '';
        }

        function addRequestLineItem(itemId) {
            const product = requestCatalog.find(p => p.item_id === itemId);
            if (!product || requestAddedItemIds.has(itemId)) {
                return;
            }
            requestAddedItemIds.add(itemId);

            document.getElementById('requestLineItemsCard').style.display = '';

            const row = document.createElement('div');
            row.className = 'line-item-row';
            row.id = 'rli_row_' + itemId;
            row.style.cssText = 'display:flex;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);';
            row.innerHTML = `
                <div style="flex:1;">
                    <strong>${htmlEscapeRequests(product.model)}</strong>
                    <div class="cell-muted" style="font-size:12.5px;">${htmlEscapeRequests(product.category_name)}</div>
                </div>
                <input type="number" name="quantities[${itemId}]" min="1" step="1" placeholder="Quantity" value="1" style="width:110px;margin-bottom:0;" required>
                <button type="button" class="btn btn-secondary btn-sm" onclick="removeRequestLineItem(${itemId})">Remove</button>
            `;
            document.getElementById('requestLineItems').appendChild(row);

            document.getElementById('requestProductSearch').value = '';
            document.getElementById('requestSearchResults').style.display = 'none';
            const browseWrap = document.getElementById('requestBrowseWrap');
            if (browseWrap) browseWrap.style.display = '';
            renderRequestBrowseList();
        }

        function removeRequestLineItem(itemId) {
            document.getElementById('rli_row_' + itemId)?.remove();
            requestAddedItemIds.delete(itemId);
            if (requestAddedItemIds.size === 0) {
                document.getElementById('requestLineItemsCard').style.display = 'none';
            }
            renderRequestBrowseList();
        }

        function htmlEscapeRequests(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#requestProductSearch') && !e.target.closest('#requestSearchResults')) {
                const dropdown = document.getElementById('requestSearchResults');
                if (dropdown) dropdown.style.display = 'none';
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                ['addRequestModal', 'approveModal', 'returnModal', 'declineModal'].forEach(closeRequestModal);
            }
        });
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
