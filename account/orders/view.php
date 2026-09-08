<?php
/**
 * account/orders/view.php
 * Apex Diurnal Solar - Order Detail & Visual Status Tracker
 */

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../product_data.php';
require_once __DIR__ . '/../../cart/cart_functions.php';

if (!isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
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

// Calculate progress width percentage for line connector
// 4 nodes: centers at 12.5%, 37.5%, 62.5%, 87.5% (span = 75%).
// Each stage step is 25%.
$standardStages = ['pending', 'processing', 'shipped', 'delivered'];
$currentStatus = $order ? $order['status'] : 'pending';
$stageIndex = array_search($currentStatus, $standardStages);
$progressWidthPercent = ($stageIndex !== false) ? min(75, $stageIndex * 25) : 0;
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
                            <span style="font-size:0.85rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.5px;">Order Tracking</span>
                            <h2 style="font-size:1.6rem; font-weight:800; color:var(--color-navy); margin:4px 0 0;">
                                <?php echo htmlspecialchars($order['order_number']); ?>
                            </h2>
                        </div>
                        <div class="tracker-badge-group">
                            <span class="status-pill status-<?php echo htmlspecialchars($order['status']); ?>" id="order-header-status-pill">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Terminal Banner (if Cancelled or Refunded) -->
                    <div class="terminal-status-banner <?php echo in_array($order['status'], ['cancelled', 'refunded']) ? $order['status'] : ''; ?>" 
                         id="terminal-status-banner" 
                         style="<?php echo in_array($order['status'], ['cancelled', 'refunded']) ? 'display:flex;' : 'display:none;'; ?>">
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

                    <!-- Standard Visual Progression Timeline -->
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

                        <!-- 2. Processing (Engineering & Allocation) -->
                        <?php 
                            $idxProcessing = array_search('processing', $standardStages);
                            $classProcessing = ($stageIndex !== false && $stageIndex > $idxProcessing) ? 'completed' : (($stageIndex === $idxProcessing) ? 'active' : 'upcoming');
                        ?>
                        <div class="timeline-node <?php echo $classProcessing; ?>" id="node-processing">
                            <div class="node-icon-circle">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                            </div>
                            <div class="node-title">Processing</div>
                            <div class="node-time">Warehouse Prep</div>
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
                </div>

                <!-- ---------------------------------------------------------- -->
                <!-- ORDER CONTENT GRID (Items & Customer Summary)               -->
                <!-- ---------------------------------------------------------- -->
                <div class="order-detail-grid">
                    <!-- Left: Purchased Equipment Items -->
                    <div class="detail-card">
                        <div class="detail-card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-2z"></path>
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <path d="M16 10a4 4 0 0 1-8 0"></path>
                            </svg>
                            Solar Hardware & Installation Items
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
                                <span>Total Paid</span>
                                <span>&#8369;<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Customer, Shipping & Payment Summary -->
                    <div>
                        <div class="detail-card">
                            <div class="detail-card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                Shipping & Deployment
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
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                    <line x1="1" y1="10" x2="23" y2="10"></line>
                                </svg>
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
                            <button type="button" onclick="window.print();" class="btn-filter-reset" style="width:100%; height:44px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;">
                                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                    <rect x="6" y="14" width="12" height="8"></rect>
                                </svg>
                                Print Order Receipt
                            </button>
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
    <script src="../../cart/cart.js" defer></script>
    <script src="../account.js" defer></script>
    <script src="../../assets/js/order-history.js" defer></script>
    <script src="../../js/main.js" defer></script>
</body>
</html>
