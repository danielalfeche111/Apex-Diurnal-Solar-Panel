<?php
/**
 * account/orders/index.php
 * Apex Diurnal Solar - Customer Order History
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
$userEmail = getCurrentUserEmail() ?? '';

// Automatically claim any orders placed with the user's email or linked quotes if user_id was unassigned
if (!empty($userEmail)) {
    $claimStmt = $db->prepare("
        UPDATE orders SET user_id = :uid 
        WHERE user_id IS NULL AND (
            LOWER(customer_email) = LOWER(:uemail)
            OR LOWER(customer_email) = REPLACE(LOWER(:uemail2), '@gmail.com', '@gmai.com')
            OR LOWER(customer_email) = REPLACE(LOWER(:uemail3), '@gmai.com', '@gmail.com')
        )
    ");
    $claimStmt->execute([':uid' => $userId, ':uemail' => $userEmail, ':uemail2' => $userEmail, ':uemail3' => $userEmail]);

    try {
        $db->prepare("
            UPDATE orders o
            JOIN quote_requests q ON (o.order_number = CONCAT('APD-INST-', q.quote_number) OR o.notes LIKE CONCAT('%', q.quote_number, '%'))
            SET o.user_id = :uid
            WHERE o.user_id IS NULL AND q.user_id = :uid2
        ")->execute([':uid' => $userId, ':uid2' => $userId]);
    } catch (Exception $e) {
        // ignore if quote_requests column check fails
    }
}

// Initial Server-Side Query for fast first paint & SEO / noscript fallback
$statusFilter = trim($_GET['status'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$sortBy = trim($_GET['sort_by'] ?? 'created_at');
$sortDir = strtolower(trim($_GET['sort_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClauses = ['o.user_id = :user_id'];
$params = [':user_id' => $userId];

$validStatuses = ['pending', 'client_confirmed', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
if (!empty($statusFilter) && in_array($statusFilter, $validStatuses, true)) {
    $whereClauses[] = 'o.status = :status';
    $params[':status'] = $statusFilter;
}
if (!empty($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $whereClauses[] = 'o.created_at >= :start_date';
    $params[':start_date'] = $startDate . ' 00:00:00';
}
if (!empty($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $whereClauses[] = 'o.created_at <= :end_date';
    $params[':end_date'] = $endDate . ' 23:59:59';
}

$whereSql = implode(' AND ', $whereClauses);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o WHERE {$whereSql}");
foreach ($params as $k => $v) {
    $countStmt->bindValue($k, $v);
}
$countStmt->execute();
$totalOrders = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalOrders / $limit));

// Total all-time orders for badge count
$allOrdersCountStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid");
$allOrdersCountStmt->execute([':uid' => $userId]);
$userTotalAllOrders = (int) $allOrdersCountStmt->fetchColumn();

// Fetch orders list
$sortMap = [
    'order_number' => 'o.order_number',
    'created_at' => 'o.created_at',
    'total_amount' => 'o.total_amount',
    'status' => 'o.status'
];
$sortColumn = $sortMap[$sortBy] ?? 'o.created_at';

$listSql = "
    SELECT 
        o.id,
        o.order_number,
        o.created_at,
        o.status,
        o.property_type,
        o.total_amount,
        COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = o.id), 0) AS item_count
    FROM orders o
    WHERE {$whereSql}
    ORDER BY {$sortColumn} {$sortDir}, o.id DESC
    LIMIT :limit OFFSET :offset
";
$listStmt = $db->prepare($listSql);
foreach ($params as $k => $v) {
    $listStmt->bindValue($k, $v);
}
$listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$initialOrders = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Cart hydration & notifications
$cart_notices = [];
if (!empty($_SESSION['cart_notifications']) && is_array($_SESSION['cart_notifications'])) {
    $cart_notices = array_merge($cart_notices, $_SESSION['cart_notifications']);
    unset($_SESSION['cart_notifications']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Apex Diurnal Solar Panels</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../styles.css?v=5">
    <link rel="stylesheet" href="../account.css?v=<?php echo filemtime(__DIR__ . '/../account.css'); ?>">
    <link rel="stylesheet"
        href="../../assets/css/order-history.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/order-history.css'); ?>">
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
                        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2"
                            fill="none">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                    <div class="search-bar" id="search-bar" role="search">
                        <input type="search" class="search-input" id="search-input" placeholder="Search"
                            aria-label="Search products and navigation" autocomplete="off">
                        <button type="button" class="search-close" id="search-close"
                            aria-label="Close search">×</button>
                    </div>
                    <div class="search-results" id="search-results" aria-live="polite"></div>
                </div>

                <button type="button" class="icon-btn cart-btn" id="cart-trigger" aria-label="Shopping Cart">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2-1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <span class="cart-badge" id="cart-count" data-count="<?php echo cart_item_count(); ?>" <?php if (cart_item_count() === 0)
                           echo 'style="display:none"'; ?>><?php echo cart_item_count(); ?></span>
                </button>

                <div class="user-dropdown" id="user-dropdown">
                    <button type="button" class="user-avatar-btn" aria-label="User Account" id="user-account-btn"
                        title="<?php echo htmlspecialchars($userEmail); ?>">
                        <span
                            class="user-avatar"><?php echo htmlspecialchars(strtoupper(substr($userEmail, 0, 1))); ?></span>
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
            <!-- Account Navigation Tabs -->
            <div class="account-nav-tabs" role="tablist" aria-label="Account Tabs">
                <a href="../settings.php" class="account-nav-tab">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Settings & Security
                </a>
                <a href="index.php" class="account-nav-tab active">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-2z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                    Order History
                    <span class="tab-badge"><?php echo $userTotalAllOrders; ?></span>
                </a>
            </div>

            <!-- Page Title -->
            <div class="orders-header">
                <div class="orders-title-group">
                    <h1>Order History</h1>
                </div>
            </div>

            <!-- Orders Table Card -->
            <div class="orders-table-card" id="orders-table-card" <?php if (empty($initialOrders))
                echo 'style="display:none;"'; ?>>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="order_number">Order Number <span class="sort-icon">↕</span>
                            </th>
                            <th class="sortable" data-sort="created_at" data-dir="desc">Date Placed <span
                                    class="sort-icon">↓</span></th>
                            <th class="sortable" data-sort="status">Status <span class="sort-icon">↕</span></th>
                            <th>Items</th>
                            <th class="sortable" data-sort="total_amount">Total Amount <span class="sort-icon">↕</span>
                            </th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="orders-table-body">
                        <?php foreach ($initialOrders as $ord): ?>
                            <tr data-order-id="<?php echo (int) $ord['id']; ?>">
                                <td>
                                    <a href="view.php?id=<?php echo (int) $ord['id']; ?>" class="order-num-link">
                                        <?php echo htmlspecialchars($ord['order_number']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($ord['created_at'])); ?>
                                </td>
                                <td>
                                    <?php
                                        $st = $ord['status'];
                                        $isComm = (($ord['property_type'] ?? '') === 'Commercial');
                                        if ($st === 'confirmed') {
                                            $stLabel = 'Already Confirmed';
                                        } elseif ($st === 'client_confirmed') {
                                            $stLabel = 'Confirmed by Client';
                                        } elseif ($st === 'pending' && $isComm) {
                                            $stLabel = 'Pending Client Confirmation';
                                        } else {
                                            $stLabel = ucfirst($st);
                                        }
                                    ?>
                                    <span class="status-pill status-<?php echo htmlspecialchars($st); ?>">
                                        <?php echo htmlspecialchars($stLabel); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo (int) $ord['item_count']; ?>
                                    <?php echo (int) $ord['item_count'] === 1 ? 'item' : 'items'; ?>
                                </td>
                                <td class="order-price">
                                    &#8369;<?php echo number_format($ord['total_amount'], 2); ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="view.php?id=<?php echo (int) $ord['id']; ?>" class="btn-view-order">
                                        View Order &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination-wrapper" id="orders-pagination" <?php if ($totalPages <= 1)
                    echo 'style="display:none;"'; ?>>
                    <div class="pagination-info">
                        Showing <?php echo min(1, $totalOrders); ?> - <?php echo min($totalOrders, $limit); ?> of
                        <?php echo $totalOrders; ?> orders
                    </div>
                    <div class="pagination-buttons">
                        <button class="page-btn" disabled>&lsaquo; Prev</button>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <button class="page-btn <?php echo $p === 1 ? 'active' : ''; ?>"><?php echo $p; ?></button>
                        <?php endfor; ?>
                        <button class="page-btn" <?php echo $totalPages <= 1 ? 'disabled' : ''; ?>>Next &rsaquo;</button>
                    </div>
                </div>
            </div>

            <!-- Empty State Box -->
            <div class="orders-table-card empty-state-box" id="orders-empty-state" <?php if (!empty($initialOrders))
                echo 'style="display:none;"'; ?>>
                <div class="empty-state-icon">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-2z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                </div>
                <div class="empty-state-title">No Orders Found</div>
                <div class="empty-state-desc">You have not placed any solar equipment orders yet.</div>
                <a href="../../index.php#products" class="btn-primary-cta">Explore Solar Solutions</a>
            </div>

            <div style="text-align:center; margin-top:20px;">
                <a href="../../index.php" class="return-home-link">
                    <span class="link-arrow">&larr;</span> Return to Homepage
                </a>
            </div>
        </div>
    </main>

    <!-- Cart Drawer -->
    <aside class="cart-drawer" id="cart-drawer" aria-label="Shopping Cart" aria-hidden="true">
        <div class="cart-drawer-header">
            <h3>Your Cart <span class="cart-drawer-count" id="cart-drawer-count"><?php echo cart_item_count(); ?>
                    item(s)</span></h3>
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
                <button type="button"
                    class="btn btn-outline btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>" id="cart-clear"
                    aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>">Clear Cart</button>
                <button type="button" class="btn btn-yellow btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>"
                    id="cart-checkout"
                    aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>">Checkout</button>
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
    <script src="../../cart/cart.js?v=<?php echo filemtime(__DIR__ . '/../../cart/cart.js'); ?>" defer></script>
    <script src="../account.js" defer></script>
    <script src="../../assets/js/order-history.js" defer></script>
    <script src="../../js/main.js" defer></script>
</body>

</html>