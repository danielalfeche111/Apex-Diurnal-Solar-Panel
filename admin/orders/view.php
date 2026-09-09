<?php
/**
 * admin/orders/view.php - Detailed Order Inspector
 */

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

require_once __DIR__ . '/../inventory/sync.php';

$orderId = (int)($_GET['id'] ?? 0);

$db = getConnection();

$stmt = $db->prepare("SELECT * FROM orders WHERE id = :id");
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: index.php?err=' . urlencode('Order not found.'));
    exit;
}

$page_title = 'Order ' . $order['order_number'];
$active_nav = 'orders';

// Fetch line items
$stmtItems = $db->prepare("
    SELECT oi.*, p.image, p.type AS product_type, COALESCE(i.current_stock, 0) AS current_stock
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE oi.order_id = :id
");
$stmtItems->execute([':id' => $orderId]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Check linked installation booking if exists
$stmtBooking = $db->prepare("SELECT * FROM service_bookings WHERE order_id = :oid LIMIT 1");
$stmtBooking->execute([':oid' => $orderId]);
$linkedBooking = $stmtBooking->fetch(PDO::FETCH_ASSOC);

// Check inventory availability for physical items
$stockCheck = checkOrderStockAvailability($orderId);

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

$status = $order['status'];
$badgeClass = 'badge-pending';
if ($status === 'processing') $badgeClass = 'badge-processing';
elseif ($status === 'shipped') $badgeClass = 'badge-shipped';
elseif ($status === 'delivered') $badgeClass = 'badge-delivered';
elseif ($status === 'cancelled' || $status === 'refunded') $badgeClass = 'badge-cancelled';

$extra_head = '<link rel="stylesheet" href="orders.css">';
$extra_scripts = '<script src="orders.js" defer></script>';

include __DIR__ . '/../includes/header.php';
?>

<!-- Header Action Bar -->
<div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
  <div>
    <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.25rem;">
      <h2 style="font-size:1.4rem; color:var(--navy-primary); font-weight:700;"><?php echo htmlspecialchars($order['order_number']); ?></h2>
      <span class="badge <?php echo $badgeClass; ?>" style="font-size:0.82rem; padding:0.35rem 0.85rem;">
        <?php echo ucfirst($status); ?>
      </span>
    </div>
    <p style="font-size:0.8rem; color:var(--text-muted);">
      Placed on <?php echo date('F j, Y - g:i A', strtotime($order['created_at'])); ?> via <?php echo htmlspecialchars($order['payment_method']); ?>
    </p>
  </div>

  <div style="display:flex; gap:0.6rem; align-items:center;">
    <a href="index.php" class="btn btn-secondary btn-sm">
      &larr; Back to Orders
    </a>

    <?php if ($status === 'pending'): ?>
      <button 
        type="button" 
        class="btn btn-primary btn-sm"
        onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>', 'processing')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span>Accept & Process Order</span>
      </button>
      <button 
        type="button" 
        class="btn btn-danger btn-sm"
        onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>', 'cancelled')">
        Reject / Cancel
      </button>
    <?php elseif ($status === 'processing'): ?>
      <button 
        type="button" 
        class="btn btn-primary btn-sm"
        onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>', 'shipped')">
        Mark as Shipped
      </button>
      <button 
        type="button" 
        class="btn btn-danger btn-sm"
        onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>', 'cancelled')">
        Cancel & Restock
      </button>
    <?php elseif ($status === 'shipped'): ?>
      <button 
        type="button" 
        class="btn btn-primary btn-sm"
        onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>', 'delivered')">
        Mark as Delivered
      </button>
    <?php endif; ?>

    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
      <span>Print Order</span>
    </button>
  </div>
</div>

<?php if ($msg): ?>
  <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.85rem; display:flex; align-items:center; gap:0.5rem;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
    <span><?php echo htmlspecialchars($msg); ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.85rem; display:flex; align-items:center; gap:0.5rem;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
    <span><?php echo htmlspecialchars($err); ?></span>
  </div>
<?php endif; ?>

<!-- Inventory Allocation Warning for Pending Orders -->
<?php if ($status === 'pending' && !$stockCheck['can_fulfill']): ?>
  <div style="background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:1rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.85rem;">
    <strong>⚠️ Insufficient Warehouse Stock Warning:</strong><br>
    The following items cannot currently be fulfilled from live inventory:
    <ul style="margin-left:1.5rem; margin-top:0.35rem;">
      <?php foreach ($stockCheck['shortages'] as $sh): ?>
        <li><?php echo htmlspecialchars($sh); ?></li>
      <?php endforeach; ?>
    </ul>
    You must restock these items in <a href="../inventory/index.php" style="text-decoration:underline; font-weight:700;">Inventory Control</a> before this order can be processed.
  </div>
<?php endif; ?>

<!-- Main 2-Column Grid -->
<div class="admin-grid-2col">
  <!-- Left: Ordered Items & Financial Totals -->
  <div>
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
          <span>Line Items (<?php echo count($items); ?>)</span>
        </div>
      </div>

      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Item</th>
              <th>Unit Price</th>
              <th>Quantity</th>
              <th style="text-align: right;">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <div style="display:flex; align-items:center; gap:0.85rem;">
                  <?php if (!empty($item['image'])): ?>
                    <img src="<?php echo $admin_base . '../' . htmlspecialchars($item['image']); ?>" alt="" style="width:40px; height:40px; object-fit:contain; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:2px;">
                  <?php endif; ?>
                  <div>
                    <div style="font-weight:700; color:var(--navy-primary);"><?php echo htmlspecialchars($item['product_name']); ?></div>
                    <div style="font-size:0.73rem; color:var(--text-muted);">
                      <?php if ($item['product_type'] === 'physical'): ?>
                        Physical Hardware &bull; Current Warehouse Stock: <strong><?php echo $item['current_stock']; ?> units</strong>
                      <?php else: ?>
                        Digital Service Booking
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td>&#8369;<?php echo number_format($item['unit_price'], 2); ?></td>
              <td style="font-weight:700;"><?php echo $item['quantity']; ?></td>
              <td style="text-align: right; font-weight:700; color:var(--navy-primary);">
                &#8369;<?php echo number_format($item['total_price'], 2); ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Financial Totals -->
      <div style="padding:1.25rem 1.5rem; background:#f8fafc; border-top:1px solid var(--border-color); display:flex; justify-content:flex-end;">
        <div style="width:280px;">
          <div style="display:flex; justify-content:space-between; font-size:0.83rem; margin-bottom:0.4rem; color:var(--text-muted);">
            <span>Subtotal:</span>
            <span>&#8369;<?php echo number_format($order['subtotal'], 2); ?></span>
          </div>
          <div style="display:flex; justify-content:space-between; font-size:0.83rem; margin-bottom:0.5rem; color:var(--text-muted);">
            <span>Tax (8%):</span>
            <span>&#8369;<?php echo number_format($order['tax_amount'], 2); ?></span>
          </div>
          <div style="display:flex; justify-content:space-between; font-size:1.1rem; font-weight:700; color:var(--navy-primary); border-top:2px solid var(--border-color); padding-top:0.6rem;">
            <span>Grand Total:</span>
            <span>&#8369;<?php echo number_format($order['total_amount'], 2); ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Linked Service Booking if applicable -->
    <?php if ($linkedBooking): ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span>Linked Turnkey Installation Appointment</span>
          </div>
          <a href="../schedule/appointments.php?id=<?php echo $linkedBooking['id']; ?>" class="btn btn-secondary btn-sm">Manage Schedule</a>
        </div>
        <div class="card-body">
          <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:1rem; font-size:0.82rem;">
            <div>
              <div style="color:var(--text-muted);">Reference:</div>
              <div style="font-weight:700; color:var(--navy-primary); font-family:monospace;"><?php echo htmlspecialchars($linkedBooking['booking_reference']); ?></div>
            </div>
            <div>
              <div style="color:var(--text-muted);">Scheduled Date:</div>
              <div style="font-weight:700;"><?php echo date('M j, Y', strtotime($linkedBooking['preferred_date'])); ?> (<?php echo ucfirst($linkedBooking['preferred_time_slot']); ?>)</div>
            </div>
            <div>
              <div style="color:var(--text-muted);">Technician:</div>
              <div style="font-weight:700;"><?php echo htmlspecialchars($linkedBooking['assigned_technician'] ?? 'Unassigned'); ?></div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Notes & Audit Log -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
          <span>Order Notes & Activity History</span>
        </div>
      </div>
      <div class="card-body">
        <div style="background:#f8fafc; border:1px solid var(--border-color); border-radius:6px; padding:1rem; font-family:monospace; font-size:0.8rem; white-space:pre-wrap; max-height:160px; overflow-y:auto;">
          <?php echo htmlspecialchars($order['notes'] ?: 'No special notes recorded for this transaction.'); ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Customer Info & Shipping Address -->
  <div>
    <!-- Customer Details Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          <span>Customer Information</span>
        </div>
      </div>
      <div class="card-body" style="font-size:0.84rem;">
        <div style="margin-bottom:1rem;">
          <div style="color:var(--text-muted); font-size:0.75rem;">Customer Name</div>
          <div style="font-weight:700; color:var(--navy-primary);"><?php echo htmlspecialchars($order['customer_name']); ?></div>
        </div>
        <div style="margin-bottom:1rem;">
          <div style="color:var(--text-muted); font-size:0.75rem;">Email Address</div>
          <div><a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>" style="color:var(--navy-light); text-decoration:underline;"><?php echo htmlspecialchars($order['customer_email']); ?></a></div>
        </div>
        <div style="margin-bottom:1rem;">
          <div style="color:var(--text-muted); font-size:0.75rem;">Phone Number</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
        </div>
        <div>
          <div style="color:var(--text-muted); font-size:0.75rem;">Property Type</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars($order['property_type']); ?></div>
        </div>
      </div>
    </div>

    <!-- Shipping & Delivery Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"></path><circle cx="12" cy="10" r="3"></circle></svg>
          <span>Delivery Address</span>
        </div>
      </div>
      <div class="card-body" style="font-size:0.84rem;">
        <div style="line-height:1.6; color:var(--text-main);">
          <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Order Status Transition Modal -->
<div class="modal-overlay" id="status-modal">
  <div class="modal-container">
    <div class="modal-header">
      <div class="modal-title" id="modal-order-title">Update Order Status</div>
      <button type="button" class="modal-close" onclick="closeAdminModal('status-modal')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>
    <form method="POST" action="process.php">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
        <input type="hidden" name="redirect_source" value="view">

        <div class="form-group">
          <label class="form-label" for="new_status">Select Next Status</label>
          <select class="form-control" name="new_status" id="modal-new-status" required>
            <option value="processing" <?php echo ($status === 'processing') ? 'selected' : ''; ?>>Processing (Allocates inventory stock)</option>
            <option value="shipped" <?php echo ($status === 'shipped') ? 'selected' : ''; ?>>Shipped (Dispatched to customer)</option>
            <option value="delivered" <?php echo ($status === 'delivered') ? 'selected' : ''; ?>>Delivered (Order fulfilled)</option>
            <option value="cancelled" <?php echo ($status === 'cancelled') ? 'selected' : ''; ?>>Cancelled (Restores physical stock)</option>
            <option value="refunded" <?php echo ($status === 'refunded') ? 'selected' : ''; ?>>Refunded (Restores physical stock)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="admin_note">Add Action Audit Note</label>
          <textarea class="form-control" name="admin_note" id="admin_note" rows="3" placeholder="e.g. Dispatched via LBC Express Tracking # 9928371"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeAdminModal('status-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Status Update</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
