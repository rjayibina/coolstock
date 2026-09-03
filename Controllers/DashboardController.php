<?php
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/InventoryItem.php';
require_once __DIR__ . '/../Models/Transaction.php';
require_once __DIR__ . '/../Models/ItemStock.php';

/**
 * DashboardController.php
 * Pulls together counts + chart data for the Reporting and Monitoring
 * Module. Wrapped in try/catch per data source so that one missing
 * table (e.g. "transactions" not yet migrated on this install) shows a
 * clear message instead of a blank white-screen error.
 */
class DashboardController
{
    public function index(): void
    {
        $category = new Category();
        $item = new InventoryItem();
        $transaction = new Transaction();
        $itemStock = new ItemStock();

        $dbError = null;
        $stats = ['total_products' => 0, 'total_categories' => 0, 'total_transactions' => 0];
        $recentTransactions = [];
        $productsByCategory = [];
        $transactionsByType = [];
        $predictedStockouts = [];

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
            $transactionsByType = $transaction->countByType();
        } catch (PDOException $e) {
            $dbError = ($dbError ? $dbError . " " : "")
                . "Could not load transaction data — make sure the 'transactions' table has been created (run database/coolstock_full_setup.sql).";
        }

        try {
            $predictedStockouts = $itemStock->predictedStockouts();
        } catch (PDOException $e) {
            $dbError = ($dbError ? $dbError . " " : "") . "Could not load predicted stockout data.";
        }

        require __DIR__ . '/../Views/dashboard/index.php';
    }
}
