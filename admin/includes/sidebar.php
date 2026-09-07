<?php
/**
 * admin/includes/sidebar.php - Apex Diurnal Admin Sidebar
 * Color: Deep Navy Blue (#1b335f) with Warm Yellow (#fee000) accents
 */

if (!isset($admin_base)) {
    $admin_base = (strpos($_SERVER['PHP_SELF'], '/admin/orders/') !== false ||
                   strpos($_SERVER['PHP_SELF'], '/admin/inventory/') !== false ||
                   strpos($_SERVER['PHP_SELF'], '/admin/schedule/') !== false ||
                   strpos($_SERVER['PHP_SELF'], '/admin/quotes/') !== false) ? '../' : './';
}

$current_nav = $active_nav ?? 'dashboard';
$adminUser = getAdminUser();
$counts = getAdminBadgeCounts();
$site_root = ($admin_base === '../') ? '../../' : '../';
$logo_file = __DIR__ . '/../../assets/images/logo.png';
$logo_v = file_exists($logo_file) ? filemtime($logo_file) : time();
$logo_path = $site_root . 'assets/images/logo.png?v=' . $logo_v;
?>
<!-- Sidebar Overlay for mobile -->
<div id="sidebar-overlay" class="modal-overlay" style="z-index: 999; backdrop-filter: blur(1px);"></div>

<aside class="admin-sidebar" id="admin-sidebar">
  <div class="sidebar-brand">
    <a href="<?php echo $admin_base; ?>index.php" class="brand-link" title="Apex Diurnal Admin Dashboard">
      <img src="<?php echo $logo_path; ?>" alt="Apex Diurnal Logo" class="sidebar-logo" style="border:none;outline:none;background:transparent;">
      <div class="brand-text">
        <span class="brand-title">APEX DIURNAL</span>
        <span class="brand-subtitle">ADMIN MANAGEMENT</span>
      </div>
    </a>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">Overview</div>
    <a href="<?php echo $admin_base; ?>index.php" class="nav-item <?php echo ($current_nav === 'dashboard') ? 'active' : ''; ?>">
      <div class="nav-item-content">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7"></rect>
          <rect x="14" y="3" width="7" height="7"></rect>
          <rect x="14" y="14" width="7" height="7"></rect>
          <rect x="3" y="14" width="7" height="7"></rect>
        </svg>
        <span>Dashboard</span>
      </div>
    </a>

    <div class="nav-section-title">Operations</div>
    <a href="<?php echo $admin_base; ?>orders/index.php" class="nav-item <?php echo ($current_nav === 'orders') ? 'active' : ''; ?>">
      <div class="nav-item-content">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="9" cy="21" r="1"></circle>
          <circle cx="20" cy="21" r="1"></circle>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
        </svg>
        <span>Orders</span>
      </div>
      <?php if (!empty($counts['pending_orders'])): ?>
        <span class="nav-badge"><?php echo $counts['pending_orders']; ?></span>
      <?php endif; ?>
    </a>

    <a href="<?php echo $admin_base; ?>inventory/index.php" class="nav-item <?php echo ($current_nav === 'inventory') ? 'active' : ''; ?>">
      <div class="nav-item-content">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
          <line x1="12" y1="22.08" x2="12" y2="12"></line>
        </svg>
        <span>Inventory</span>
      </div>
      <?php if (!empty($counts['low_stock'])): ?>
        <span class="nav-badge" style="background:#ef4444;"><?php echo $counts['low_stock']; ?></span>
      <?php endif; ?>
    </a>

    <a href="<?php echo $admin_base; ?>schedule/index.php" class="nav-item <?php echo ($current_nav === 'schedule') ? 'active' : ''; ?>">
      <div class="nav-item-content">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <span>Service Schedule</span>
      </div>
      <?php if (!empty($counts['pending_bookings'])): ?>
        <span class="nav-badge"><?php echo $counts['pending_bookings']; ?></span>
      <?php endif; ?>
    </a>

    <a href="<?php echo $admin_base; ?>quotes/index.php" class="nav-item <?php echo ($current_nav === 'quotes') ? 'active' : ''; ?>">
      <div class="nav-item-content">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
        <span>Quote Requests</span>
      </div>
      <?php if (!empty($counts['new_quotes'])): ?>
        <span class="nav-badge"><?php echo $counts['new_quotes']; ?></span>
      <?php endif; ?>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="admin-profile">
      <div class="admin-avatar">
        <?php echo strtoupper(substr($adminUser['username'] ?? 'A', 0, 1)); ?>
      </div>
      <div class="admin-info">
        <span class="admin-name"><?php echo htmlspecialchars($adminUser['username'] ?? 'Admin'); ?></span>
        <span class="admin-role"><?php echo htmlspecialchars($adminUser['role'] ?? 'Staff'); ?></span>
      </div>
      <a href="<?php echo $admin_base; ?>logout.php" title="Sign Out" style="color:#cbd5e1; padding:4px; display:flex; align-items:center;" onmouseover="this.style.color='#fee000'" onmouseout="this.style.color='#cbd5e1'">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
      </a>
    </div>
  </div>
</aside>
