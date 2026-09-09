<?php
/**
 * account/orders/view.php
 * Apex Diurnal Solar - Order Detail & Visual Status Tracker
 */

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../product_data.php';
require_once __DIR__ . '/../../cart/cart_functions.php';

if (!isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit;
}

$db = getConnection();
$userId = getCurrentUserId();
$userEmail = getCurrentUserEmail() ?? 'U';
$orderId = (int)($_GET['id'] ?? 0);

// Validate and fetch order with strict ownership
$order = null;
$items = [];
$history = [];

if ($orderId > 0) {
    $orderStmt = $db->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :uid LIMIT 1");
    $orderStmt->execute([':id' => $orderId, ':uid' => $userId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        // Fetch items
        $itemsStmt = $db->prepare("
            SELECT oi.*, p.image 
            FROM order_items oi 
            LEFT JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = :oid 
            ORDER BY oi.id ASC
        ");
        $itemsStmt->execute([':oid' => $orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch history
        $histStmt = $db->prepare("
            SELECT * FROM order_status_history 
            WHERE order_id = :oid 
            ORDER BY changed_at ASC, id ASC
        ");
        $histStmt->execute([':oid' => $orderId]);
        $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($history)) {
            $history[] = [
                'status'     => $order['status'],
                'changed_at' => $order['created_at'],
                'notes'      => 'Order placed'
            ];
        }
    }
}

// Cart hydration & notifications
$cart_notices = [];
if (!empty($_SESSION['cart_notifications']) && is_array($_SESSION['cart_notifications'])) {
    $cart_notices = array_merge($cart_notices, $_SESSION['cart_notifications']);
    unset($_SESSION['cart_notifications']);
}

// Commercial specifications lookup
$isCommercial = ($order && $order['property_type'] === 'Commercial');
$commercialSpecs = [];
if ($isCommercial) {
    $quoteNum = null;
    if (preg_match('/RFQ-\d{8}-\d{4}/', $order['order_number'], $matches)) {
        $quoteNum = $matches[0];
    } elseif (preg_match('/RFQ-\d{8}-\d{4}/', $order['notes'] ?? '', $matches)) {
        $quoteNum = $matches[0];
    }

    if ($quoteNum) {
        $qStmt = $db->prepare("SELECT * FROM quote_requests WHERE quote_number = :qnum LIMIT 1");
        $qStmt->execute([':qnum' => $quoteNum]);
        $commercialSpecs = $qStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // Auto-heal / synchronize status if the quote has already been confirmed by admin
    if (!empty($commercialSpecs)) {
        $isQuoteConfirmed = ($commercialSpecs['status'] === 'confirmed' || !empty($commercialSpecs['installation_head']));
        if ($isQuoteConfirmed && in_array($order['status'], ['pending', 'client_confirmed'], true)) {
            $order['status'] = 'confirmed';
            if (!empty($commercialSpecs['installation_head'])) {
                $order['installation_head'] = $commercialSpecs['installation_head'];
            }
            if (!empty($commercialSpecs['installation_date'])) {
                $order['installation_date'] = $commercialSpecs['installation_date'];
            }
            $syncStmt = $db->prepare("
                UPDATE orders 
                SET status = 'confirmed',
                    installation_head = COALESCE(:head, installation_head),
                    installation_date = COALESCE(:idate, installation_date),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $syncStmt->execute([
                ':head' => !empty($commercialSpecs['installation_head']) ? $commercialSpecs['installation_head'] : null,
                ':idate' => !empty($commercialSpecs['installation_date']) ? $commercialSpecs['installation_date'] : null,
                ':id' => $order['id']
            ]);
        }
    }
}

// Calculate progress width percentage for line connector
// Stages: pending (0%) -> client_confirmed (12.5%) -> confirmed (25%) -> processing (50%) -> shipped (75%) -> delivered (75%)
$standardStages = ['pending', 'client_confirmed', 'confirmed', 'processing', 'shipped', 'delivered'];
$currentStatus = $order ? $order['status'] : 'pending';
$stageIndex = array_search($currentStatus, $standardStages);

if ($currentStatus === 'client_confirmed') {
    $progressWidthPercent = 12.5;
} elseif ($currentStatus === 'confirmed') {
    $progressWidthPercent = 25;
} elseif ($currentStatus === 'processing') {
    $progressWidthPercent = 50;
} elseif ($currentStatus === 'shipped') {
    $progressWidthPercent = 75;
} elseif ($currentStatus === 'delivered') {
    $progressWidthPercent = 75;
} else {
    $progressWidthPercent = 0;
}

if (!function_exists('getOrderDisplayStatus')) {
    function getOrderDisplayStatus($order) {
        if (!$order) return 'Unknown';
        $st = $order['status'];
        $isComm = ($order['property_type'] === 'Commercial');
        if ($st === 'confirmed') return 'Already Confirmed';
        if ($st === 'client_confirmed') return 'Confirmed by Client';
        if ($st === 'pending') return $isComm ? 'Pending Client Confirmation' : 'Pending';
        return ucfirst($st);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $order ? "Order {$order['order_number']}" : 'Order Not Found'; ?> | Apex Diurnal Solar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../styles.css?v=5">
    <link rel="stylesheet" href="../account.css?v=<?php echo filemtime(__DIR__ . '/../account.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/order-history.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/order-history.css'); ?>">
    <link rel="stylesheet" href="../../cart/cart.css">
</head>
<body>
    <!-- Site Header -->
    <header class="site-header" id="site-header">
        <div class="header-container">
            <a href="../../index.php#home" class="brand-logo" aria-label="Apex Diurnal Home">
                <img src="../../assets/images/logo-clean.png" alt="Apex Diurnal Logo" class="logo-mark">
                <div class="brand-text">
                    <span class="brand-title">APEX</span>
                    <span class="brand-subtitle">DIURNAL</span>
                </div>
            </a>

            <nav class="main-nav" aria-label="Main Navigation">
                <ul class="nav-list">
                    <li><a href="../../index.php#home" class="nav-link">Home</a></li>
                    <li><a href="../../index.php#products" class="nav-link">Products</a></li>
                    <li><a href="../../index.php#services" class="nav-link">Services</a></li>
                    <li><a href="../../index.php#about" class="nav-link">About Us</a></li>
                    <li><a href="../../index.php#contact" class="nav-link">Contact</a></li>
                </ul>
            </nav>

            <div class="header-actions">
                <div class="search-container" id="search-container">
                    <button type="button" class="icon-btn" aria-label="Search" id="search-toggle" aria-expanded="false"
                        aria-controls="search-bar">
                        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                    <div class="search-bar" id="search-bar" role="search">
                        <input type="search" class="search-input" id="search-input" placeholder="Search"
                            aria-label="Search products and navigation" autocomplete="off">
                        <button type="button" class="search-close" id="search-close" aria-label="Close search">×</button>
                    </div>
                    <div class="search-results" id="search-results" aria-live="polite"></div>
                </div>

                <button type="button" class="icon-btn cart-btn" id="cart-trigger" aria-label="Shopping Cart">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2-1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <span class="cart-badge" id="cart-count" data-count="<?php echo cart_item_count(); ?>" <?php if (cart_item_count() === 0) echo 'style="display:none"'; ?>><?php echo cart_item_count(); ?></span>
                </button>

                <div class="user-dropdown" id="user-dropdown">
                    <button type="button" class="user-avatar-btn" aria-label="User Account" id="user-account-btn" title="<?php echo htmlspecialchars($userEmail); ?>">
                        <span class="user-avatar"><?php echo htmlspecialchars(strtoupper(substr($userEmail, 0, 1))); ?></span>
                    </button>
                    <div class="user-dropdown-menu" id="user-dropdown-menu">
                        <a href="../settings.php" class="user-dropdown-item">Settings</a>
                        <a href="index.php" class="user-dropdown-item">My Orders</a>
                        <a href="../../auth/logout.php" class="user-dropdown-item">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="orders-page">
        <div class="orders-container">
            <?php if (!$order): ?>
                <!-- Not Found / Access Denied -->
                <div class="tracker-card" style="text-align: center; padding: 60px 20px;">
                    <div class="empty-state-icon" style="color:#ef4444; background:#fee2e2;">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <h2 class="empty-state-title">Order Not Found</h2>
                    <p class="empty-state-desc">The requested order does not exist or you do not have permission to view it.</p>
                    <a href="index.php" class="btn-primary-cta">&larr; Back to Order History</a>
                </div>
            <?php else: ?>
                <!-- Breadcrumb -->
                <div style="margin-bottom: 20px;">
                    <a href="index.php" style="color:var(--color-navy); text-decoration:none; font-weight:600; font-size:0.9rem; display:inline-flex; align-items:center; gap:6px;">
                        &larr; Back to All Orders
                    </a>
                </div>

                <!-- ---------------------------------------------------------- -->
                <!-- VISUAL STATUS TRACKER CARD                                 -->
                <!-- ---------------------------------------------------------- -->
                <div class="tracker-card" id="status-tracker-container" 
                     data-order-id="<?php echo (int)$order['id']; ?>" 
                     data-current-status="<?php echo htmlspecialchars($order['status']); ?>">
                    
                    <div class="tracker-card-header">
                        <div>
                            <span style="font-size:0.85rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.5px;"><?php echo $isCommercial ? 'Commercial Installation Project' : 'Order Tracking'; ?></span>
                            <h2 style="font-size:1.6rem; font-weight:800; color:var(--color-navy); margin:4px 0 0;">
                                <?php echo htmlspecialchars($order['order_number']); ?>
                            </h2>
                        </div>
                        <div class="tracker-badge-group">
                            <span class="status-pill status-<?php echo htmlspecialchars($order['status']); ?>" id="order-header-status-pill">
                                <?php echo getOrderDisplayStatus($order); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Flash messages from customer actions -->
                    <?php 
                    $showMsg = !empty($_GET['msg']);
                    // If order is already confirmed by both sides, suppress any stale flash messages saying "Awaiting"
                    if ($showMsg && $order['status'] === 'confirmed' && stripos($_GET['msg'], 'awaiting') !== false) {
                        $showMsg = false;
                    }
                    ?>
                    <?php if ($showMsg): ?>
                        <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:0.9rem 1.25rem; margin-top:1.25rem; margin-bottom:0.75rem; color:#065f46; font-size:0.92rem; font-weight:600;">
                            &#10003; <?php echo htmlspecialchars($_GET['msg']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($_GET['err'])): ?>
                        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:0.9rem 1.25rem; margin-top:1.25rem; margin-bottom:0.75rem; color:#991b1b; font-size:0.92rem; font-weight:600;">
                            &#9888; <?php echo htmlspecialchars($_GET['err']); ?>
                        </div>
                    <?php endif; ?>
                    <!-- Commercial Workflow Banners & Action Buttons -->
                    <?php if ($order['status'] === 'confirmed'): ?>
                        <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:1.25rem 1.5rem; margin-top:1.25rem; <?php echo $isCommercial ? 'margin-bottom:0;' : 'margin-bottom:1.25rem;'; ?>">
                            <div style="font-weight:800; color:#065f46; font-size:1.1rem;">
                                &#10003; Commercial Grid Installation Confirmed
                            </div>
                            <div style="font-size:0.88rem; color:#047857; margin-top:0.35rem; line-height:1.6;">
                                Your commercial solar grid installation schedule and assigned project head have been approved and confirmed by our engineering administration.
                            </div>
                            <?php 
                            $head = $commercialSpecs['installation_head'] ?? $order['installation_head'] ?? '';
                            $date = $commercialSpecs['installation_date'] ?? $order['installation_date'] ?? '';
                            if (!empty($head) || !empty($date)): ?>
                                <div style="margin-top:0.75rem; padding-top:0.75rem; border-top:1px dashed #a7f3d0; display:flex; gap:1.5rem; flex-wrap:wrap; font-size:0.88rem;">
                                    <?php if (!empty($head)): ?>
                                        <div><strong style="color:#065f46;">Lead Engineer:</strong> <span style="color:#047857; font-weight:700;"><?php echo htmlspecialchars($head); ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($date)): ?>
                                        <div><strong style="color:#065f46;">Scheduled Date:</strong> <span style="color:#047857; font-weight:700;"><?php echo date('F j, Y', strtotime($date)); ?></span></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($isCommercial && $order['status'] === 'client_confirmed'): ?>
                        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:1.25rem 1.5rem; margin-top:1.25rem; margin-bottom:0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                            <div>
                                <div style="font-weight:700; color:#1e40af; font-size:1.05rem;">
                                    &#10003; Confirmed by You (Awaiting Admin Approval &amp; Head Engineer Assignment)
                                </div>
                                <div style="font-size:0.88rem; color:#2563eb; margin-top:0.35rem; line-height:1.5;">
                                    Thank you! You have confirmed this commercial installation project. Our engineering team is currently assigning the lead engineer and finalizing the deployment schedule.
                                </div>
                            </div>
                            <div>
                                <form action="action.php" method="POST" style="margin:0;">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn-cancel-action" onclick="return confirm('Are you sure you want to cancel this confirmed request?');" style="background:#fff; color:#dc2626; border:1px solid #fca5a5; padding:8px 16px; border-radius:6px; font-weight:600; font-size:0.85rem; cursor:pointer;">
                                        &#10005; Cancel Request
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($isCommercial && $order['status'] === 'pending'): ?>
                        <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:1.25rem 1.5rem; margin-top:1.25rem; margin-bottom:0;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                                <div>
                                    <div style="font-weight:700; color:#92400e; font-size:1.05rem;">
                                        Commercial Grid Installation Proposal Ready
                                    </div>
                                    <div style="font-size:0.88rem; color:#b45309; margin-top:0.35rem; line-height:1.5;">
                                        Please review the turnkey equipment details and pricing below. Confirm your installation request to notify our engineering team, or cancel the request.
                                    </div>
                                </div>
                                <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                                    <form action="action.php" method="POST" style="margin:0;">
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                        <input type="hidden" name="action" value="confirm">
                                        <button type="submit" class="btn-confirm-action" style="background:#059669; color:#fff; border:none; padding:10px 22px; border-radius:6px; font-weight:700; font-size:0.92rem; cursor:pointer; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 4px rgba(5,150,105,0.25);">
                                            &#10003; Confirm Installation
                                        </button>
                                    </form>
                                    <form action="action.php" method="POST" style="margin:0;">
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="btn-cancel-action" onclick="return confirm('Are you sure you want to cancel this installation request?');" style="background:#fff; color:#dc2626; border:1px solid #fca5a5; padding:10px 18px; border-radius:6px; font-weight:700; font-size:0.92rem; cursor:pointer;">
                                            &#10005; Cancel Request
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Terminal Banner (if Cancelled or Refunded) -->
                    <div class="terminal-status-banner <?php echo in_array($order['status'], ['cancelled', 'refunded']) ? $order['status'] : ''; ?>" 
                         id="terminal-status-banner" 
                         style="<?php echo in_array($order['status'], ['cancelled', 'refunded']) ? 'display:flex;' : 'display:none;'; ?> <?php echo $isCommercial ? 'margin-bottom:0;' : ''; ?>">
                        <div class="banner-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <div>
                            <div style="font-weight:800; font-size:1rem;" class="banner-text">
                                <?php echo $order['status'] === 'cancelled' ? 'This order has been cancelled.' : 'This order has been refunded.'; ?>
                            </div>
                            <div style="font-size:0.85rem; opacity:0.85; margin-top:2px;">
                                Please contact our engineering support team if you have any questions regarding this order update.
                            </div>
                        </div>
                    </div>

                    <!-- Standard Visual Progression Timeline (Omitted for Commercial Grids) -->
                    <?php if (!$isCommercial): ?>
                    <div class="status-tracker-timeline" id="standard-status-timeline" 
                         style="<?php echo in_array($order['status'], ['cancelled', 'refunded']) ? 'display:none;' : 'display:flex;'; ?>">
                        
                        <div class="timeline-progress-bar" id="timeline-progress-bar" style="width: <?php echo $progressWidthPercent; ?>%;"></div>

                        <!-- 1. Pending (Order Placed) -->
                        <?php 
                            $idxPending = array_search('pending', $standardStages);
                            $classPending = ($stageIndex !== false && $stageIndex > $idxPending) ? 'completed' : (($stageIndex === $idxPending) ? 'active' : 'upcoming');
                        ?>
                        <div class="timeline-node <?php echo $classPending; ?>" id="node-pending">
                            <div class="node-icon-circle">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                            </div>
                            <div class="node-title">Order Placed</div>
                            <div class="node-time"><?php echo date('M d, H:i', strtotime($order['created_at'])); ?></div>
                        </div>

                        <!-- 2. Confirmed / Processing -->
                        <?php 
                            $isConfirmed = in_array($order['status'], ['confirmed', 'processing', 'shipped', 'delivered'], true);
                            if ($isConfirmed) {
                                $classNode2 = 'completed';
                                $node2Title = 'Processing';
                                $node2Time  = 'In Progress';
                            } else {
                                $classNode2 = 'upcoming';
                                $node2Title = 'Processing';
                                $node2Time  = 'Pending';
                            }
                        ?>
                        <div class="timeline-node <?php echo $classNode2; ?>" id="node-processing">
                            <div class="node-icon-circle">
                                <?php if ($isConfirmed): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                <?php else: ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <circle cx="12" cy="12" r="3"></circle>
                                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="node-title"><?php echo $node2Title; ?></div>
                            <div class="node-time"><?php echo $node2Time; ?></div>
                        </div>

                        <!-- 3. Shipped (In Transit) -->
                        <?php 
                            $idxShipped = array_search('shipped', $standardStages);
                            $classShipped = ($stageIndex !== false && $stageIndex > $idxShipped) ? 'completed' : (($stageIndex === $idxShipped) ? 'active' : 'upcoming');
                        ?>
                        <div class="timeline-node <?php echo $classShipped; ?>" id="node-shipped">
                            <div class="node-icon-circle">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <rect x="1" y="3" width="15" height="13"></rect>
                                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                </svg>
                            </div>
                            <div class="node-title">Dispatched</div>
                            <div class="node-time">In Transit</div>
                        </div>

                        <!-- 4. Delivered (Completed) -->
                        <?php 
                            $idxDelivered = array_search('delivered', $standardStages);
                            $classDelivered = ($stageIndex !== false && $stageIndex >= $idxDelivered) ? 'completed' : 'upcoming';
                        ?>
                        <div class="timeline-node <?php echo $classDelivered; ?>" id="node-delivered">
                            <div class="node-icon-circle">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                            </div>
                            <div class="node-title">Delivered</div>
                            <div class="node-time">Site Turnkey</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ---------------------------------------------------------- -->
                <!-- ORDER CONTENT GRID (Items & Customer Summary)               -->
                <!-- ---------------------------------------------------------- -->
                <div class="order-detail-grid">
                    <!-- Left: Purchased Equipment Items -->
                    <div class="detail-card">
                        <div class="detail-card-title">
                            Solar Hardware &amp; Installation Items
                        </div>

                        <div class="order-items-container">
                            <?php foreach ($items as $it): 
                                $imgSrc = $it['image'] ?? 'assets/images/residential arrays.png';
                                if (!empty($imgSrc) && strpos($imgSrc, 'http') !== 0 && strpos($imgSrc, '/') !== 0 && strpos($imgSrc, '../../') !== 0) {
                                    $imgSrc = '../../' . $imgSrc;
                                }
                            ?>
                                <div class="order-item-row">
                                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($it['product_name']); ?>" class="order-item-img">
                                    <div class="order-item-info">
                                        <div class="order-item-name"><?php echo htmlspecialchars($it['product_name']); ?></div>
                                        <div class="order-item-qty">Qty: <?php echo (int)$it['quantity']; ?> &times; &#8369;<?php echo number_format($it['unit_price'], 2); ?></div>
                                    </div>
                                    <div class="order-item-total">
                                        &#8369;<?php echo number_format($it['total_price'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="order-totals-list">
                            <div class="totals-row">
                                <span>Subtotal</span>
                                <span>&#8369;<?php echo number_format($order['subtotal'], 2); ?></span>
                            </div>
                            <div class="totals-row">
                                <span>Value Added Tax (12% VAT)</span>
                                <span>&#8369;<?php echo number_format($order['tax_amount'], 2); ?></span>
                            </div>
                            <div class="totals-row grand-total">
                                <span>Total Price</span>
                                <span>&#8369;<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>

                        <?php if ($isCommercial): ?>
                            <!-- Commercial Solar Engineering Specifications -->
                            <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--color-border);">
                                <div style="font-size: 0.95rem; font-weight: 700; color: var(--color-navy); margin-bottom: 0.85rem; display: flex; align-items: center; gap: 6px;">
                                    Commercial Solar Engineering Specifications
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;">
                                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                                        <div style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px;">System Capacity</div>
                                        <div style="font-size: 1.15rem; font-weight: 800; color: var(--color-navy); margin-top: 3px;">
                                            <?php echo !empty($commercialSpecs['estimated_system_size']) ? number_format((float)$commercialSpecs['estimated_system_size'], 2) . ' kWp' : 'Turnkey Array'; ?>
                                        </div>
                                    </div>
                                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                                        <div style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px;">Facility Area</div>
                                        <div style="font-size: 1.15rem; font-weight: 800; color: var(--color-navy); margin-top: 3px;">
                                            <?php echo !empty($commercialSpecs['facility_size']) ? number_format((float)$commercialSpecs['facility_size'], 2) . ' sqm' : 'N/A'; ?>
                                        </div>
                                    </div>
                                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                                        <div style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px;">Monthly Bill</div>
                                        <div style="font-size: 1.15rem; font-weight: 800; color: var(--color-navy); margin-top: 3px;">
                                            <?php echo !empty($commercialSpecs['current_monthly_bill']) ? '&#8369;' . number_format((float)$commercialSpecs['current_monthly_bill'], 2) : 'N/A'; ?>
                                        </div>
                                    </div>
                                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                                        <div style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px;">Est. Annual Savings</div>
                                        <div style="font-size: 1.15rem; font-weight: 800; color: #059669; margin-top: 3px;">
                                            <?php echo !empty($commercialSpecs['estimated_annual_savings']) ? '&#8369;' . number_format((float)$commercialSpecs['estimated_annual_savings'], 2) : 'N/A'; ?>
                                        </div>
                                    </div>
                                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                                        <div style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px;">Estimated Payback</div>
                                        <div style="font-size: 1.15rem; font-weight: 800; color: var(--color-navy); margin-top: 3px;">
                                            <?php echo !empty($commercialSpecs['estimated_payback_period']) ? number_format((float)$commercialSpecs['estimated_payback_period'], 1) . ' years' : 'N/A'; ?>
                                        </div>
                                    </div>
                                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                                        <div style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px;">Target Timeline</div>
                                        <div style="font-size: 1.15rem; font-weight: 800; color: var(--color-navy); margin-top: 3px;">
                                            <?php echo !empty($commercialSpecs['target_timeline']) ? htmlspecialchars($commercialSpecs['target_timeline']) : 'Immediate'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Customer, Shipping & Payment Summary -->
                    <div>
                        <div class="detail-card">
                            <div class="detail-card-title">
                                Shipping &amp; Deployment
                            </div>
                            <div style="font-size:0.9rem; line-height:1.6; color:#334155;">
                                <div style="font-weight:700; color:var(--color-navy);"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                <div><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></div>
                                <div style="margin-top:8px; color:var(--color-text-muted);">
                                    Phone: <?php echo htmlspecialchars($order['customer_phone']); ?><br>
                                    Email: <?php echo htmlspecialchars($order['customer_email']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="detail-card">
                            <div class="detail-card-title">
                                Payment Details
                            </div>
                            <div style="font-size:0.9rem; line-height:1.6; color:#334155;">
                                <div><strong style="color:var(--color-navy);">Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></div>
                                <div><strong style="color:var(--color-navy);">Property Type:</strong> <?php echo htmlspecialchars($order['property_type']); ?></div>
                                <div><strong style="color:var(--color-navy);">Date Placed:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
                                <?php if (!empty($order['notes'])): ?>
                                    <div style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--color-border); font-size:0.84rem; color:var(--color-text-muted);">
                                        <strong>Notes:</strong><br><?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <a href="../../index.php#contact" class="btn-filter-reset" style="width:100%; height:44px;">
                                Need Help? Contact Support
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Cart Drawer -->
    <aside class="cart-drawer" id="cart-drawer" aria-label="Shopping Cart" aria-hidden="true">
        <div class="cart-drawer-header">
            <h3>Your Cart <span class="cart-drawer-count" id="cart-drawer-count"><?php echo cart_item_count(); ?> item(s)</span></h3>
            <button type="button" class="cart-close" id="cart-close" aria-label="Close cart">&times;</button>
        </div>
        <div class="cart-drawer-body" id="cart-items"></div>
        <div class="cart-drawer-footer">
            <div class="cart-total-row">
                <span>Total</span>
                <strong id="cart-total">&#8369;<?php echo number_format(cart_total($products), 2); ?></strong>
            </div>
            <?php $has_cart_items = (cart_item_count() > 0); ?>
            <div class="cart-footer-actions">
                <button type="button" class="btn btn-outline btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>" id="cart-clear" aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>">Clear Cart</button>
                <button type="button" class="btn btn-yellow btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>" id="cart-checkout" aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>">Checkout</button>
            </div>
        </div>
    </aside>
    <div id="cart-toast" class="cart-toast" role="status" aria-live="polite"></div>

    <script>
        const CART_INITIAL = <?php echo json_encode([
            'itemCount' => cart_item_count(),
            'grandTotal' => number_format(cart_total($products), 2),
            'items' => array_values(array_map(function ($item, $id) {
                $img = $item['image'] ?? 'assets/images/logo-clean.png';
                if (!empty($img) && strpos($img, 'http') !== 0 && strpos($img, '/') !== 0 && strpos($img, '../../') !== 0) {
                    $img = '../../' . $img;
                }
                return [
                    'id' => $id,
                    'title' => $item['title'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'line_total' => number_format($item['line_total'], 2),
                    'image' => $img,
                    'alt' => $item['alt']
                ];
            }, get_cart_items($products), array_keys(get_cart_items($products)))),
            'notices' => $cart_notices
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <?php if ($order && $order['status'] === 'confirmed' && !empty($_GET['msg']) && stripos($_GET['msg'], 'awaiting') !== false): ?>
    <script>
        if (window.history.replaceState) {
            const cleanUrl = window.location.pathname + window.location.search.replace(/([?&])msg=[^&]*(&|$)/, '$1').replace(/[?&]$/, '');
            window.history.replaceState({}, document.title, cleanUrl);
        }
    </script>
    <?php endif; ?>
    <script src="../../cart/cart.js?v=<?php echo filemtime(__DIR__ . '/../../cart/cart.js'); ?>" defer></script>
    <script src="../account.js" defer></script>
    <script src="../../assets/js/order-history.js" defer></script>
    <script src="../../js/main.js" defer></script>
</body>
</html>
