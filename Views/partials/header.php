<?php
/**
 * Views/partials/header.php
 * Expects: $pageTitle (string), $activeSection ('dashboard'|'inventory'|'settings'),
 *          $activeSubNav ('products'|'transactions'|'categories'|'brands'|'itemtypes'|'locations') when in inventory
 */
$pageTitle = $pageTitle ?? 'CoolStock';
$activeSection = $activeSection ?? 'dashboard';
$activeSubNav = $activeSubNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - CoolStock</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <style>
        <?php
        // Inlined via filesystem path (not a browser HTTP request) so the
        // styling always loads regardless of how/where this app is hosted.
        $cssPath = __DIR__ . '/../../assets/css/style.css';
        if (file_exists($cssPath)) {
            echo file_get_contents($cssPath);
        }
        ?>
    </style>
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <?php // Dimmer behind the mobile nav drawer. Inert above 900px (CSS). ?>
    <div class="rail-scrim" id="railScrim"></div>

    <main class="main">
        <?php // Mobile-only app bar - the rail is off-canvas at this size. ?>
        <div class="topbar">
            <button type="button" class="rail-toggle" id="railToggle"
                    aria-label="Open navigation menu" aria-expanded="false" aria-controls="railNav">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <?php // Brand, not $pageTitle - the page's own <h1> sits directly
                  // below this bar and repeating the name there reads as a bug. ?>
            <span class="topbar-brand">
                <span class="brand-mark">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                </span>
                CoolStock
            </span>
        </div>

