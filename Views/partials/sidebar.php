<?php
/**
 * Views/partials/sidebar.php
 * Expects: $activeSection (string) - 'dashboard' | 'requests' | 'inventory' | 'reports' | 'users' | 'settings'
 *          $activeSubNav (string) - 'products' | 'transactions' | 'delivery' |
 *          'transfer' | 'categories' | 'brands' | 'itemtypes' | 'locations' when in inventory
 */
require_once __DIR__ . '/../../Models/User.php';

$activeSection = $activeSection ?? '';
$activeSubNav = $activeSubNav ?? '';
$inventoryOpen = $activeSection === 'inventory';
$viewer = current_user();
$canSeeInventory = $viewer && has_role('admin', 'warehouse_staff');
$canSeeUsers = $viewer && has_role('admin');
?>
<?php // No inline position here on purpose: .rail is sticky on desktop and
      // fixed as a drawer on mobile, and both already anchor .rail-close. ?>
<aside class="rail" id="railNav">
    <?php // Only rendered as a control below 900px, where the rail is a drawer. ?>
    <button type="button" class="rail-close" id="railClose" aria-label="Close navigation menu">&times;</button>

    <div class="brand">
        <span class="brand-mark">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        </span>
        CoolStock
    </div>

    <a href="index.php?module=dashboard" class="nav-item <?= $activeSection === 'dashboard' ? 'active' : '' ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
        Dashboard
    </a>

    <?php if ($viewer): ?>
    <a href="index.php?module=requests&action=index" class="nav-item <?= $activeSection === 'requests' ? 'active' : '' ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Item Requests
    </a>
    <?php endif; ?>

    <?php if ($canSeeInventory): ?>
    <button type="button" class="nav-item nav-group-toggle <?= $inventoryOpen ? 'active' : '' ?>" onclick="toggleInventoryMenu()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        <span style="flex:1;text-align:left;">Inventory</span>
        <svg id="inventoryChevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transform:rotate(<?= $inventoryOpen ? '180' : '0' ?>deg);"><polyline points="6 9 12 15 18 9"/></svg>
    </button>

    <div id="inventorySubmenu" class="nav-submenu" style="display:<?= $inventoryOpen ? 'block' : 'none' ?>;">
        <a href="index.php?module=products&action=index" class="nav-subitem <?= $activeSubNav === 'products' ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            Products
        </a>
        <a href="index.php?module=transactions&action=index" class="nav-subitem <?= $activeSubNav === 'transactions' ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
            Product Movement
        </a>
        <a href="index.php?module=delivery&action=index" class="nav-subitem <?= $activeSubNav === 'delivery' ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            Delivery
        </a>
        <a href="index.php?module=transfer&action=index" class="nav-subitem <?= $activeSubNav === 'transfer' ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
            Transfer
        </a>
        <a href="index.php?module=categories&action=index" class="nav-subitem <?= $activeSubNav === 'categories' ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3.24L3 3v6.59a2 2 0 0 0 .59 1.41l9.58 9.59a2 2 0 0 0 2.83 0l4.59-4.59a2 2 0 0 0 0-2.83z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
            Categories
        </a>
        <a href="index.php?module=brands&action=index" class="nav-subitem <?= $activeSubNav === 'brands' ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            Brands
        </a>
        <a href="index.php?module=itemtypes&action=index" class="nav-subitem <?= $activeSubNav === 'itemtypes' ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Item Types
        </a>
        <a href="index.php?module=locations&action=index" class="nav-subitem <?= $activeSubNav === 'locations' ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Locations
        </a>
    </div>
    <?php endif; ?>

    <?php if ($canSeeInventory): ?>
    <a href="index.php?module=reports&action=index" class="nav-item <?= $activeSection === 'reports' ? 'active' : '' ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Reports
    </a>
    <?php endif; ?>

    <?php if ($canSeeUsers): ?>
    <a href="index.php?module=users&action=index" class="nav-item <?= $activeSection === 'users' ? 'active' : '' ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Users
    </a>
    <?php endif; ?>

    <div class="sidebar-footer">
        <?php if ($viewer): ?>
        <div class="nav-item" style="cursor:default;pointer-events:none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span style="overflow:hidden;text-overflow:ellipsis;">
                <?= htmlspecialchars($viewer['full_name']) ?>
                <span style="display:block;font-size:11px;opacity:.75;"><?= htmlspecialchars(User::roleLabel($viewer['role'])) ?></span>
            </span>
        </div>
        <a href="index.php?module=auth&action=logout" class="nav-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
        </a>
        <?php endif; ?>
    </div>
</aside>
<script>
// No open/close animation yet - plain instant toggle (revisit once real data/usage patterns are in)
function toggleInventoryMenu() {
    const submenu = document.getElementById('inventorySubmenu');
    const chevron = document.getElementById('inventoryChevron');
    const isOpen = submenu.style.display !== 'none';
    submenu.style.display = isOpen ? 'none' : 'block';
    chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
}
</script>
