<?php
/**
 * Views/partials/header.php
 * Expects: $pageTitle (string), $activeSection ('dashboard'|'inventory'|'settings'),
 *          $activeSubNav ('products'|'transactions'|'categories') when in inventory
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
    <main class="main">
