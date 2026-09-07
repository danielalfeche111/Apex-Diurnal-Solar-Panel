<?php
/**
 * admin/includes/header.php - Apex Diurnal Admin Header Component
 */

if (!isset($admin_base)) {
    $admin_base = (strpos($_SERVER['PHP_SELF'], '/admin/orders/') !== false ||
                   strpos($_SERVER['PHP_SELF'], '/admin/inventory/') !== false ||
                   strpos($_SERVER['PHP_SELF'], '/admin/schedule/') !== false ||
                   strpos($_SERVER['PHP_SELF'], '/admin/quotes/') !== false) ? '../' : './';
}

$page_title = $page_title ?? 'Admin Dashboard';
$page_subtitle = $page_subtitle ?? 'Apex Diurnal Solar Panels Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title); ?> | Apex Diurnal Admin</title>
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="<?php echo $admin_base; ?>../assets/images/favicon.png">
  
  <!-- Admin Stylesheet -->
  <?php $css_v = file_exists(__DIR__ . '/../assets/css/admin.css') ? filemtime(__DIR__ . '/../assets/css/admin.css') : time(); ?>
  <link rel="stylesheet" href="<?php echo $admin_base; ?>assets/css/admin.css?v=<?php echo $css_v; ?>">
  
  <?php if (!empty($extra_head)): echo $extra_head; endif; ?>
</head>
<body>
<div class="admin-wrapper">
  <!-- Include Sidebar -->
  <?php include __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content Container -->
  <div class="admin-main">
    <!-- Top Navbar -->
    <header class="admin-header">
      <div class="header-left">
        <button class="mobile-nav-toggle" id="mobile-nav-toggle" aria-label="Toggle navigation menu">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
        <div class="header-title-wrap">
          <h1><?php echo htmlspecialchars($page_title); ?></h1>
          <div class="header-breadcrumbs">
            <span>Apex Admin</span>
            <span>&rsaquo;</span>
            <span><?php echo htmlspecialchars($page_title); ?></span>
          </div>
        </div>
      </div>

      <div class="header-right">
        <a href="<?php echo $admin_base; ?>../index.php" target="_blank" class="btn-view-store">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
            <polyline points="15 3 21 3 21 9"></polyline>
            <line x1="10" y1="14" x2="21" y2="3"></line>
          </svg>
          <span>View Store</span>
        </a>
      </div>
    </header>

    <!-- Page Content -->
    <main class="admin-content">
