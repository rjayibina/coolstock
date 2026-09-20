<?php
require_once __DIR__ . '/../Helpers/auth.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/InventoryItem.php';
require_once __DIR__ . '/../Models/Transaction.php';
require_once __DIR__ . '/../Models/ItemStock.php';
require_once __DIR__ . '/../Models/User.php';

/**
 * DashboardController.php
 * The dashboard is the one module open to every role (it is deliberately
 * absent from index.php's $modulePermissions), so what it shows has to be
 * decided here rather than by the router.
 *
 * Each role gets its own view partial and only the data that partial
 * needs - a Technician has no business seeing org-wide stock levels or
 * everyone else's movement history, and the queries behind those are not
 * run for them at all.
 *
 * Every data source stays wrapped in its own try/catch so one missing
 * table (e.g. "transactions" not yet migrated on this install) shows a
 * clear message instead of a blank white-screen error.
 */
class DashboardController
{
    /** How many rows each dashboard preview table shows before deferring
     *  to the full listing page. */
    private const PREVIEW_ROWS = 5;

    public function index(): void
    {
        $viewer = current_user();

        // Admin sees everything Warehouse Staff sees, plus its own
        // sections (see Views/dashboard/_admin.php).
        $isStaff = has_role('admin', 'warehouse_staff');
        $isTechnician = has_role('technician');

        $dbError = null;
        $stats = ['total_products' => 0, 'total_categories' => 0, 'total_transactions' => 0];
        $recentTransactions = [];
        $productsByCategory = [];
        $dailyVolume = [];
        $predictedStockouts = [];

        // Technician-only, all scoped to the signed-in requester.
        $myStats = ['pending' => 0, 'active' => 0, 'units_out' => 0];
        $myBorrows = [];
        $myRequests = [];

        // Staff-only operational figures - the things somebody has to act
        // on today, as opposed to the catalogue totals below.
        $ops = ['pending_approvals' => 0, 'active_borrows' => 0, 'units_out' => 0];
        $approvalQueue = [];

        // Admin-only oversight. Warehouse Staff can't reach the Users
        // module, so they never run these.
        $totalUsers = 0;
        $usersByRole = [];

        if ($isTechnician) {
            $transaction = new Transaction();
            // Rows belonging to this technician are found by the ORIGINAL
            // requester, not by the name stamped on the row - a Borrow
            // carries the releasing staff member's name. See
            // Transaction::requesterNameExpression().
            $me = $viewer['full_name'] ?? '';

            try {
                $myStats['pending'] = $transaction->countRequestList('item_request', ['pending'], $me);
                $myStats['active'] = $transaction->countRequestList('borrow', ['active'], $me);
                $myStats['units_out'] = $transaction->outstandingBorrowedUnits($me);

                // Short previews - the Item Requests page owns the full,
                // paginated lists.
                $myBorrows = $transaction->readRequestList('borrow', ['active'], $me, 'date_desc', self::PREVIEW_ROWS, 0);
                $myRequests = $transaction->readRequestList('item_request', ['pending'], $me, 'date_desc', self::PREVIEW_ROWS, 0);
            } catch (PDOException $e) {
                $dbError = "Could not load your request data — make sure the 'transactions' table has been created (run database/coolstock_full_setup.sql).";
            }
        }

        if ($isStaff) {
            $category = new Category();
            $item = new InventoryItem();
            $transaction = new Transaction();
            $itemStock = new ItemStock();

            try {
                // Unscoped (null requester) - staff see the whole floor's
                // queue, not just their own.
                $ops['pending_approvals'] = $transaction->countRequestList('item_request', ['pending'], null);
                $ops['active_borrows'] = $transaction->countRequestList('borrow', ['active'], null);
                $ops['units_out'] = $transaction->outstandingBorrowedUnits(null);
                $approvalQueue = $transaction->readRequestList('item_request', ['pending'], null, 'date_desc', self::PREVIEW_ROWS, 0);
            } catch (PDOException $e) {
                $dbError = ($dbError ? $dbError . " " : "") . "Could not load the approval queue.";
            }

            try {
                $stats['total_products'] = $item->count();
                $stats['total_categories'] = $category->count();
                $productsByCategory = $category->countProductsByCategory();
            } catch (PDOException $e) {
                $dbError = "Could not load product/category data: " . $e->getMessage();
            }

            try {
                $stats['total_transactions'] = $transaction->count();
                $recentTransactions = $transaction->readRecent(6);
                $dailyVolume = $transaction->dailyVolume(14);
            } catch (PDOException $e) {
                $dbError = ($dbError ? $dbError . " " : "")
                    . "Could not load transaction data — make sure the 'transactions' table has been created (run database/coolstock_full_setup.sql).";
            }

            try {
                $predictedStockouts = $itemStock->predictedStockouts();
            } catch (PDOException $e) {
                $dbError = ($dbError ? $dbError . " " : "") . "Could not load predicted stockout data.";
            }
        }

        if (has_role('admin')) {
            $user = new User();
            try {
                $totalUsers = $user->count();
                $usersByRole = $user->countsByRole();
            } catch (PDOException $e) {
                $dbError = ($dbError ? $dbError . " " : "")
                    . "Could not load user account data — make sure the 'users' table has been created (run database/coolstock_full_setup.sql).";
            }
        }

        require __DIR__ . '/../Views/dashboard/index.php';
    }
}
