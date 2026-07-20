<?php
/**
 * index.php
 * Front controller: every request comes through here first.
 * ?module=dashboard|categories|products|transactions
 * ?action=index|create|edit|delete
 */
require_once __DIR__ . '/Controllers/DashboardController.php';
require_once __DIR__ . '/Controllers/CategoryController.php';
require_once __DIR__ . '/Controllers/InventoryItemController.php';
require_once __DIR__ . '/Controllers/TransactionController.php';

$module = $_GET['module'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

switch ($module) {
    case 'categories':
        $controller = new CategoryController();
        break;
    case 'products':
        $controller = new InventoryItemController();
        break;
    case 'transactions':
        $controller = new TransactionController();
        break;
    case 'dashboard':
    default:
        $controller = new DashboardController();
        $action = 'index'; // dashboard only has one view
        break;
}

// Public, safely-callable actions. Anything else (or a method that doesn't
// exist on the resolved controller) falls back to index().
$allowedActions = ['index', 'create', 'edit', 'delete', 'import', 'export', 'view', 'bulkDelete', 'bulkUpdateCategory', 'quickCreate'];

try {
    if (in_array($action, $allowedActions, true) && method_exists($controller, $action)) {
        $controller->$action();
    } else {
        $controller->index();
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error - CoolStock</title>'
        . '<style>body{font-family:sans-serif;background:#F5F6FB;padding:40px;color:#14152B;}'
        . '.box{background:#fff;border:1px solid #E7E8F0;border-radius:10px;padding:24px 28px;max-width:800px;margin:0 auto;}'
        . 'h1{font-size:18px;margin-top:0;} pre{white-space:pre-wrap;background:#FAFAFD;padding:14px;border-radius:8px;font-size:13px;color:#DC2626;}'
        . 'a{color:#4C5FD5;}</style></head><body><div class="box">'
        . '<h1>Something went wrong loading this page</h1>'
        . '<pre>' . htmlspecialchars($e->getMessage()) . "\n\nin " . htmlspecialchars($e->getFile()) . ' on line ' . $e->getLine() . '</pre>'
        . '<p><a href="index.php?module=dashboard">&larr; Back to Dashboard</a></p>'
        . '</div></body></html>';
}
