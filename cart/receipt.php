<?php
/**
 * cart/receipt.php - Apex Diurnal Solar Panels Official Digital Receipt
 *
 * Dedicated digital receipt viewer for confirmed customer orders.
 * Accessible immediately following checkout confirmation or anytime from Account > Orders.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../product_data.php';

// Strict login verification
if (!isLoggedIn()) {
    $redirectTarget = urlencode($_SERVER['REQUEST_URI'] ?? '../cart/receipt.php');
    header('Location: ../auth/login.php?redirect=' . $redirectTarget);
    exit;
}

$db = getConnection();
$userId = getCurrentUserId();
$userEmail = getCurrentUserEmail() ?? '';
$orderNum = trim($_GET['order'] ?? '');
$orderId = (int)($_GET['id'] ?? 0);

$order = null;
$items = [];
$isAdminUser = function_exists('isAdmin') ? isAdmin() : false;

// Query order with ownership validation (or administrative access)
if ($orderId > 0) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id AND (user_id = :uid OR :is_admin = 1) LIMIT 1");
    $stmt->execute([':id' => $orderId, ':uid' => $userId, ':is_admin' => $isAdminUser ? 1 : 0]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($orderNum)) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = :num AND (user_id = :uid OR :is_admin = 1) LIMIT 1");
    $stmt->execute([':num' => $orderNum, ':uid' => $userId, ':is_admin' => $isAdminUser ? 1 : 0]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($order) {
    $itemsStmt = $db->prepare("
        SELECT oi.*, p.image 
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :oid
        ORDER BY oi.id ASC
    ");
    $itemsStmt->execute([':oid' => $order['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function for safe HTML output
function safe_html(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$pageTitle = $order ? ("Receipt " . safe_html($order['order_number'])) : "Receipt Not Found";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pageTitle; ?> | Apex Diurnal Solar Panels</title>
  <meta name="description" content="Official purchase receipt for Apex Diurnal solar power systems and installation services.">

  <!-- Brand Typography -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="receipt.css?v=<?php echo filemtime(__DIR__ . '/receipt.css'); ?>">
</head>
<body>

  <!-- Brand Header Bar -->
  <header class="brand-header">
    <div class="brand-nav-container">
      <a href="../index.php" class="brand-logo-link" title="Return to Apex Diurnal Homepage">
        <img src="../assets/images/logo-clean.png" alt="Apex Diurnal Solar Logo" class="brand-logo-img">
        <div class="brand-name">
          <span class="brand-name-title">APEX</span>
          <span class="brand-name-sub">DIURNAL</span>
        </div>
      </a>

      <div>
        <a href="../account/orders/index.php" class="header-back-link">
          &larr; Back to My Orders
        </a>
      </div>
    </div>
  </header>

  <main class="receipt-wrapper">

    <?php if (!$order): ?>
      <!-- State: Order Not Found or Unauthorized -->
      <div class="receipt-error-box">
        <div class="receipt-error-icon">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
        </div>
        <h1 class="receipt-error-title">Receipt Not Found</h1>
        <p class="receipt-error-desc">
          We could not locate this receipt, or your account does not have authorization to view this transaction.
        </p>
        <div class="receipt-actions">
          <a href="../account/orders/index.php" class="btn-receipt-action btn-receipt-primary">
            View My Orders
          </a>
          <a href="../index.php" class="btn-receipt-action btn-receipt-outline">
            Return to Homepage
          </a>
        </div>
      </div>

    <?php else: ?>
      <?php
        $isCardPayment = (stripos($order['payment_method'], 'card') !== false);
        $statusText = $isCardPayment ? 'Paid & Confirmed' : 'Confirmed • Cash on Delivery';
        $statusClass = $isCardPayment ? 'status-paid' : 'status-pending';
        $orderDate = !empty($order['created_at']) ? date('F j, Y - g:i A', strtotime($order['created_at'])) : date('F j, Y');
      ?>

      <!-- Breadcrumbs -->
      <nav class="receipt-breadcrumbs" aria-label="Breadcrumb">
        <a href="../index.php">Home</a>
        <span>/</span>
        <a href="../account/orders/index.php">My Orders</a>
        <span>/</span>
        <strong>Receipt (<?php echo safe_html($order['order_number']); ?>)</strong>
      </nav>

      <!-- Digital Receipt Card -->
      <div class="receipt-card">
        <div class="receipt-stripe"></div>

        <!-- Receipt Header -->
        <div class="receipt-header">
          <div class="receipt-company-info">
            <img src="../assets/images/logo-clean.png" alt="Apex Diurnal Logo" class="receipt-header-logo">
            <div>
              <h1 class="receipt-company-name">APEX DIURNAL</h1>
              <p class="receipt-company-sub">Renewable Energy Systems &amp; Solar Solutions</p>
            </div>
          </div>

          <div class="receipt-meta-badge-group">
            <span class="receipt-title-badge">Official Digital Receipt</span>
            <span class="receipt-status-pill <?php echo $statusClass; ?>">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <?php echo $statusText; ?>
            </span>
          </div>
        </div>

        <!-- Transaction Details Row -->
        <div class="receipt-meta-grid">
          <div class="receipt-meta-item">
            <span class="receipt-meta-label">Receipt / Order Ref</span>
            <span class="receipt-meta-val highlight"><?php echo safe_html($order['order_number']); ?></span>
          </div>

          <div class="receipt-meta-item">
            <span class="receipt-meta-label">Date &amp; Time Issued</span>
            <span class="receipt-meta-val"><?php echo safe_html($orderDate); ?></span>
          </div>

          <div class="receipt-meta-item">
            <span class="receipt-meta-label">Payment Method</span>
            <span class="receipt-meta-val"><?php echo safe_html($order['payment_method']); ?></span>
          </div>

          <div class="receipt-meta-item">
            <span class="receipt-meta-label">Property Type</span>
            <span class="receipt-meta-val"><?php echo safe_html($order['property_type']); ?> Property</span>
          </div>
        </div>

        <!-- Parties Information -->
        <div class="receipt-parties-grid">
          <div class="receipt-party-box">
            <div class="receipt-party-title">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              Billed To Customer
            </div>
            <div class="receipt-party-name"><?php echo safe_html($order['customer_name']); ?></div>
            <div class="receipt-party-text">
              <div>Email: <?php echo safe_html($order['customer_email']); ?></div>
              <div>Contact: <?php echo safe_html($order['customer_phone']); ?></div>
            </div>
          </div>

          <div class="receipt-party-box">
            <div class="receipt-party-title">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              Installation / Shipping Destination
            </div>
            <div class="receipt-party-name"><?php echo safe_html($order['customer_name']); ?></div>
            <div class="receipt-party-text">
              <?php echo nl2br(safe_html($order['shipping_address'])); ?>
            </div>
          </div>
        </div>

        <!-- Itemized Breakdown -->
        <div class="receipt-table-section">
          <h2 class="receipt-section-heading">Itemized Order Summary</h2>
          <table class="receipt-items-table">
            <thead>
              <tr>
                <th>Item &amp; Description</th>
                <th class="col-qty">Qty</th>
                <th class="col-price">Unit Price</th>
                <th class="col-total">Line Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($items)): ?>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td>
                      <div class="receipt-item-title"><?php echo safe_html($item['product_name']); ?></div>
                      <div class="receipt-item-sub">SKU: <?php echo safe_html($item['product_id']); ?></div>
                    </td>
                    <td class="col-qty"><?php echo (int)$item['quantity']; ?></td>
                    <td class="col-price">&#8369;<?php echo number_format((float)$item['unit_price'], 2); ?></td>
                    <td class="col-total"><strong>&#8369;<?php echo number_format((float)$item['total_price'], 2); ?></strong></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" style="text-align:center; color:var(--color-text-muted); padding:20px;">
                    Solar equipment &amp; professional service package
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>

          <!-- Financial Calculation Totals -->
          <div class="receipt-totals-container">
            <div class="receipt-totals-box">
              <div class="receipt-calc-row">
                <span>Subtotal</span>
                <span>&#8369;<?php echo number_format((float)$order['subtotal'], 2); ?></span>
              </div>
              <div class="receipt-calc-row">
                <span>State &amp; Clean Energy Tax (8%)</span>
                <span>&#8369;<?php echo number_format((float)$order['tax_amount'], 2); ?></span>
              </div>
              <div class="receipt-calc-row grand-total">
                <span>Grand Total</span>
                <span class="grand-total-val">&#8369;<?php echo number_format((float)$order['total_amount'], 2); ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Special Notes If Present -->
        <?php if (!empty($order['notes'])): ?>
          <div class="receipt-notes-card">
            <strong>Site / Special Delivery Notes:</strong>
            <div><?php echo nl2br(safe_html($order['notes'])); ?></div>
          </div>
        <?php endif; ?>

        <!-- Footer / Official Acknowledgement -->
        <footer class="receipt-footer">
          <p class="receipt-footer-text">
            Thank you for investing in clean, sustainable solar energy with <strong>Apex Diurnal Solar Panels Co.</strong><br>
            Please retain this digital receipt for your warranty coverage, technician verification, and official records.
          </p>
          <div class="receipt-footer-contact">
            Customer Support: support@apexdiurnal.com | Technical Hotline: +63 (02) 8123-4567 | SEC / DTI Registered
          </div>
        </footer>
      </div>

      <!-- Receipt Navigation Actions -->
      <div class="receipt-actions">
        <a href="../account/orders/index.php" class="btn-receipt-action btn-receipt-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
          </svg>
          All My Orders
        </a>

        <a href="../index.php" class="btn-receipt-action btn-receipt-outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          Return to Homepage
        </a>
      </div>

    <?php endif; ?>

  </main>

</body>
</html>
