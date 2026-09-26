<?php
/**
 * index.php
 * Front controller: every request comes through here first.
 * ?module=dashboard|categories|products|transactions|brands|itemtypes|locations|delivery|transfer|users|auth|requests|reports
 * ?action=index|create|edit|delete
 */

// Every date()/strtotime() call in the app (transaction dates, the
// dashboard greeting, delivery/transfer/request timestamps) is relative
// to whatever timezone PHP defaults to on the host, which isn't
// guaranteed to match the business's. Pin it here, once, so a server
// left on its default TZ can't stamp a record a day off near midnight.
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/Helpers/auth.php';
require_once __DIR__ . '/Controllers/DashboardController.php';
require_once __DIR__ . '/Controllers/CategoryController.php';
require_once __DIR__ . '/Controllers/InventoryItemController.php';
require_once __DIR__ . '/Controllers/TransactionController.php';
require_once __DIR__ . '/Controllers/BrandController.php';
require_once __DIR__ . '/Controllers/ItemTypeController.php';
require_once __DIR__ . '/Controllers/LocationController.php';
require_once __DIR__ . '/Controllers/DeliveryController.php';
require_once __DIR__ . '/Controllers/TransferController.php';
require_once __DIR__ . '/Controllers/AuthController.php';
require_once __DIR__ . '/Controllers/UserController.php';
require_once __DIR__ . '/Controllers/ItemRequestController.php';
require_once __DIR__ . '/Controllers/ReportController.php';
require_once __DIR__ . '/Helpers/format.php';

$module = $_GET['module'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

// Modules reachable without being signed in - just the login screen.
$publicModules = ['auth'];

if (!in_array($module, $publicModules, true) && !is_logged_in()) {
    header("Location: index.php?module=auth&action=index&error=forbidden");
    exit;
}

// Already signed in - the login screen has nothing left to offer, except
// logging out, which index.php sends through AuthController either way.
if (is_logged_in() && $module === 'auth' && $action !== 'logout') {
    header("Location: index.php?module=dashboard");
    exit;
}

// Which roles can reach which module. Anything not listed here (e.g.
// 'dashboard', 'auth') is open to every signed-in role.
$modulePermissions = [
    'products' => ['admin', 'warehouse_staff'],
    'transactions' => ['admin', 'warehouse_staff'],
    'delivery' => ['admin', 'warehouse_staff'],
    'transfer' => ['admin', 'warehouse_staff'],
    'categories' => ['admin', 'warehouse_staff'],
    'brands' => ['admin', 'warehouse_staff'],
    'itemtypes' => ['admin', 'warehouse_staff'],
    'locations' => ['admin', 'warehouse_staff'],
    'users' => ['admin'],
    'reports' => ['admin', 'warehouse_staff'],
];

if (is_logged_in() && isset($modulePermissions[$module]) && !has_role(...$modulePermissions[$module])) {
    header("Location: index.php?module=dashboard&status=forbidden");
    exit;
}

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
    case 'brands':
        $controller = new BrandController();
        break;
    case 'itemtypes':
        $controller = new ItemTypeController();
        break;
    case 'locations':
        $controller = new LocationController();
        break;
    case 'delivery':
        $controller = new DeliveryController();
        break;
    case 'transfer':
        $controller = new TransferController();
        break;
    case 'auth':
        $controller = new AuthController();
        break;
    case 'users':
        $controller = new UserController();
        break;
    case 'requests':
        $controller = new ItemRequestController();
        break;
    case 'reports':
        $controller = new ReportController();
        break;
    case 'dashboard':
    default:
        $controller = new DashboardController();
        $action = 'index'; // dashboard only has one view
        break;
}

// Public, safely-callable actions. Anything else (or a method that doesn't
// exist on the resolved controller) falls back to index().
$allowedActions = ['index', 'create', 'edit', 'delete', 'import', 'export', 'bulkDelete', 'bulkUpdateCategory', 'bulkStockOut', 'batch', 'login', 'logout', 'reactivate', 'approve', 'decline', 'returnItem', 'generate', 'modal'];

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
