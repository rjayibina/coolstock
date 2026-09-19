<?php
require_once __DIR__ . '/../Helpers/auth.php';
require_once __DIR__ . '/../Models/Transaction.php';
require_once __DIR__ . '/../Models/InventoryItem.php';
require_once __DIR__ . '/../Models/ItemStock.php';
require_once __DIR__ . '/../Models/Location.php';

/**
 * ItemRequestController.php
 * Item Request and Return Monitoring Module. A Technician submits a
 * request (item + quantity, no location - that's decided at approval
 * time); Warehouse Staff/Admin approve it (creating a Borrow row and
 * deducting stock at the location they pick), decline it, or log a
 * return against an active Borrow (creating a Return row and restocking
 * whatever wasn't damaged).
 *
 * Reuses the transactions table and its existing item_request/borrow/
 * return types (Models/Transaction.php) rather than a separate table -
 * see related_transaction_id there for how a Borrow links back to its
 * Item Request, and a Return back to its Borrow.
 */
class ItemRequestController
{
    private const PER_PAGE = 10;

    private Transaction $transaction;
    private InventoryItem $item;
    private ItemStock $itemStock;
    private Location $location;

    public function __construct()
    {
        $this->transaction = new Transaction();
        $this->item = new InventoryItem();
        $this->itemStock = new ItemStock();
        $this->location = new Location();
    }

    /** List page - Pending Requests / Active Borrows / History tabs,
     *  scoped to the signed-in Technician's own rows, or every row for
     *  Warehouse Staff/Admin.
     *
     *  "Own rows" means rows from requests THEY made: the Borrow and
     *  Return rows further down the chain carry the releasing Warehouse
     *  Staff's name, not the requester's, so the scoping walks back up
     *  related_transaction_id (see Transaction::requesterNameExpression()).
     *  Without that, a Technician's Active Borrows and History would come
     *  back empty even with items in their hands. */
    public function index(): void
    {
        $viewer = current_user();
        $isTechnician = has_role('technician');
        $technicianFilter = $isTechnician ? ($viewer['full_name'] ?? '') : null;

        $tab = $_GET['tab'] ?? 'pending';
        if (!in_array($tab, ['pending', 'active', 'history'], true)) {
            $tab = 'pending';
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));

        if ($tab === 'history') {
            // Mixes two different row types (declined requests,
            // fully-returned borrows) - fetched separately and merged
            // here rather than through one query.
            $declined = $this->transaction->readRequestList('item_request', ['declined'], $technicianFilter, 'date_desc');
            $returned = $this->transaction->readRequestList('borrow', ['completed'], $technicianFilter, 'date_desc');
            $requests = array_merge($declined, $returned);
            usort($requests, fn($a, $b) => $b['transaction_id'] <=> $a['transaction_id']);

            $totalCount = count($requests);
            $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
            $page = min($page, $totalPages);
            $requests = array_slice($requests, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        } else {
            [$type, $statuses] = $tab === 'pending'
                ? ['item_request', ['pending']]
                : ['borrow', ['active']];

            $totalCount = $this->transaction->countRequestList($type, $statuses, $technicianFilter);
            $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * self::PER_PAGE;
            $requests = $this->transaction->readRequestList($type, $statuses, $technicianFilter, 'date_desc', self::PER_PAGE, $offset);
        }

        $pagination = [
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
        ];

        // At-a-glance figures for the summary strip - same scoping as the
        // rows below, so a Technician's tiles count only their own.
        $summary = [
            'pending' => $this->transaction->countRequestList('item_request', ['pending'], $technicianFilter),
            'active' => $this->transaction->countRequestList('borrow', ['active'], $technicianFilter),
            'outstandingUnits' => $this->transaction->outstandingBorrowedUnits($technicianFilter),
        ];

        $items = $this->item->readAll();
        $locations = $this->location->readAll();

        require __DIR__ . '/../Views/requests/index.php';
    }

    /** A Technician (or Admin) submits a new request - one or more
     *  items, no location (Warehouse Staff picks that at approval time). */
    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?module=requests&action=index");
            exit;
        }

        $viewer = current_user();
        $requestedBy = $viewer['full_name'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        $date = date('Y-m-d');
        $quantities = $_POST['quantities'] ?? [];

        $lines = [];
        foreach ($quantities as $itemId => $qty) {
            $qty = (int) $qty;
            if ($qty > 0) {
                $lines[(int) $itemId] = $qty;
            }
        }

        if (empty($lines)) {
            header("Location: index.php?module=requests&action=index&status=error&message=" . urlencode("Enter a quantity for at least one item."));
            exit;
        }

        // A Technician can't request a product that has zero stock across
        // every location - there'd be nothing for Warehouse Staff to
        // release at approval time. The catalog search already leaves
        // these out (Views/requests/index.php), but that's client-side
        // only, so re-check here against the live totals before writing
        // anything.
        $totals = $this->itemStock->totalsForItems(array_keys($lines));
        $outOfStock = [];
        foreach (array_keys($lines) as $itemId) {
            if (($totals[$itemId] ?? 0) <= 0) {
                $outOfStock[] = $itemId;
            }
        }
        if (!empty($outOfStock)) {
            $names = array_map(
                fn($id) => $this->item->readOne($id)['model'] ?? "item #{$id}",
                $outOfStock
            );
            header("Location: index.php?module=requests&action=index&status=error&message=" . urlencode(
                "Out of stock, can't be requested: " . implode(', ', $names)
            ));
            exit;
        }

        // Only tag a shared reference number when there's actually more
        // than one line to group together - same convention as a single-
        // line Stock Out never getting one either.
        $referenceNumber = count($lines) > 1 ? $this->transaction->nextReferenceNumber('RQ') : null;

        foreach ($lines as $itemId => $qty) {
            $this->transaction->transaction_id = null;
            $this->transaction->item_id = $itemId;
            $this->transaction->location_id = null;
            $this->transaction->to_location_id = null;
            $this->transaction->transaction_type = 'item_request';
            $this->transaction->reference_number = $referenceNumber;
            $this->transaction->manually_added = false;
            $this->transaction->quantity = $qty;
            $this->transaction->serial_number = null;
            $this->transaction->transaction_date = $date;
            $this->transaction->technician_name = $requestedBy;
            $this->transaction->supplier_name = null;
            $this->transaction->notes = $notes;
            $this->transaction->source = 'manual';
            $this->transaction->status = 'pending';
            $this->transaction->related_transaction_id = null;
            $this->transaction->damaged_quantity = null;
            $this->transaction->create();
        }

        header("Location: index.php?module=requests&action=index&status=requested&count=" . count($lines));
        exit;
    }

    /** Warehouse Staff/Admin approves a pending Item Request: picks a
     *  fulfilling location, checks stock is actually available there,
     *  creates the Borrow row, and deducts stock at that location. */
    public function approve(): void
    {
        $this->requireStaff();

        $id = (int) ($_POST['request_id'] ?? $_GET['id'] ?? 0);
        $locationId = (int) ($_POST['location_id'] ?? 0);

        $request = $id > 0 ? $this->transaction->readById($id) : null;

        if (!$request || $request['transaction_type'] !== 'item_request' || $request['status'] !== 'pending') {
            header("Location: index.php?module=requests&action=index&status=error&message=" . urlencode("That request is no longer pending."));
            exit;
        }
        if ($locationId <= 0) {
            header("Location: index.php?module=requests&action=index&status=error&message=" . urlencode("Please select a location to release from."));
            exit;
        }

        $available = $this->itemStock->getQuantity((int) $request['item_id'], $locationId);
        if ($available < (int) $request['quantity']) {
            header("Location: index.php?module=requests&action=index&status=error&message=" . urlencode("Only {$available} unit(s) available at that location - not enough to approve this request."));
            exit;
        }

        $viewer = current_user();

        $this->transaction->transaction_id = null;
        $this->transaction->item_id = (int) $request['item_id'];
        $this->transaction->location_id = $locationId;
        $this->transaction->to_location_id = null;
        $this->transaction->transaction_type = 'borrow';
        $this->transaction->reference_number = $request['reference_number'];
        $this->transaction->manually_added = false;
        $this->transaction->quantity = (int) $request['quantity'];
        $this->transaction->serial_number = null;
        $this->transaction->transaction_date = date('Y-m-d');
        $this->transaction->technician_name = $viewer['full_name'] ?? '';
        $this->transaction->supplier_name = null;
        $this->transaction->notes = $request['notes'];
        $this->transaction->source = 'manual';
        $this->transaction->status = 'active';
        $this->transaction->related_transaction_id = $id;
        $this->transaction->damaged_quantity = null;
        $this->transaction->create();

        $this->itemStock->adjust((int) $request['item_id'], $locationId, -(int) $request['quantity']);
        $this->transaction->updateStatus($id, 'completed');

        header("Location: index.php?module=requests&action=index&status=approved");
        exit;
    }

    /** Warehouse Staff/Admin declines a pending Item Request - no stock
     *  effect, terminal. */
    public function decline(): void
    {
        $this->requireStaff();

        $id = (int) ($_POST['request_id'] ?? $_GET['id'] ?? 0);
        $request = $id > 0 ? $this->transaction->readById($id) : null;

        if ($request && $request['transaction_type'] === 'item_request' && $request['status'] === 'pending') {
            $this->transaction->updateStatus($id, 'declined');
            header("Location: index.php?module=requests&action=index&status=declined");
            exit;
        }

        header("Location: index.php?module=requests&action=index&status=error&message=" . urlencode("That request is no longer pending."));
        exit;
    }

    /** Warehouse Staff/Admin logs a return (full or partial) against an
     *  active Borrow - restocks whatever wasn't damaged, and marks the
     *  Borrow completed once its full quantity has come back. */
    public function returnItem(): void
    {
        $this->requireStaff();

        $borrowId = (int) ($_POST['borrow_id'] ?? 0);
        $returnedQty = (int) ($_POST['returned_quantity'] ?? 0);
        $damagedQty = (int) ($_POST['damaged_quantity'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        $borrow = $borrowId > 0 ? $this->transaction->readById($borrowId) : null;

        if (!$borrow || $borrow['transaction_type'] !== 'borrow' || $borrow['status'] !== 'active') {
            header("Location: index.php?module=requests&action=index&tab=active&status=error&message=" . urlencode("That borrow is no longer active."));
            exit;
        }

        $alreadyReturned = $this->transaction->returnedQuantityFor($borrowId);
        $outstanding = (int) $borrow['quantity'] - $alreadyReturned;

        if ($returnedQty <= 0 || $returnedQty > $outstanding) {
            header("Location: index.php?module=requests&action=index&tab=active&status=error&message=" . urlencode("Returned quantity must be between 1 and {$outstanding} (the outstanding balance)."));
            exit;
        }
        if ($damagedQty < 0 || $damagedQty > $returnedQty) {
            header("Location: index.php?module=requests&action=index&tab=active&status=error&message=" . urlencode("Damaged quantity can't exceed the returned quantity."));
            exit;
        }

        $viewer = current_user();

        $this->transaction->transaction_id = null;
        $this->transaction->item_id = (int) $borrow['item_id'];
        $this->transaction->location_id = (int) $borrow['location_id'];
        $this->transaction->to_location_id = null;
        $this->transaction->transaction_type = 'return';
        $this->transaction->reference_number = null;
        $this->transaction->manually_added = false;
        $this->transaction->quantity = $returnedQty;
        $this->transaction->serial_number = null;
        $this->transaction->transaction_date = date('Y-m-d');
        $this->transaction->technician_name = $viewer['full_name'] ?? '';
        $this->transaction->supplier_name = null;
        $this->transaction->notes = $notes;
        $this->transaction->source = 'manual';
        $this->transaction->status = 'completed';
        $this->transaction->related_transaction_id = $borrowId;
        $this->transaction->damaged_quantity = $damagedQty;
        $this->transaction->create();

        $restock = $returnedQty - $damagedQty;
        if ($restock > 0) {
            $this->itemStock->adjust((int) $borrow['item_id'], (int) $borrow['location_id'], $restock);
        }

        if ($alreadyReturned + $returnedQty >= (int) $borrow['quantity']) {
            $this->transaction->updateStatus($borrowId, 'completed');
        }

        header("Location: index.php?module=requests&action=index&tab=active&status=returned");
        exit;
    }

    /** approve()/decline()/returnItem() are Warehouse Staff/Admin-only.
     *  The 'requests' module itself stays open to every role in
     *  index.php's permission map (a Technician needs it too, to submit
     *  requests), so this is enforced here instead. */
    private function requireStaff(): void
    {
        if (!has_role('admin', 'warehouse_staff')) {
            header("Location: index.php?module=requests&action=index&status=forbidden");
            exit;
        }
    }
}
