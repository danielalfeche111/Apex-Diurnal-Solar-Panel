<?php
/**
 * admin/orders/index.php - Order Management Dashboard
 */

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

$page_title = 'Order Management';
$active_nav = 'orders';

$db = (new Database())->getConnection();

// Filter parameter
$status_filter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');

$where_clauses = [];
$params = [];

if ($status_filter !== 'all' && in_array($status_filter, ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'])) {
    $where_clauses[] = "o.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $where_clauses[] = "(o.order_number LIKE :srch OR o.customer_name LIKE :srch OR o.customer_email LIKE :srch OR o.shipping_address LIKE :srch)";
    $params[':srch'] = '%' . $search . '%';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$sql = "
    SELECT 
        o.*,
        COUNT(oi.id) AS item_count,
        GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.product_name) SEPARATOR ', ') AS item_summary
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $where_sql
    GROUP BY o.id
    ORDER BY o.created_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overall KPIs
$kpi_total_orders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$kpi_pending = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$kpi_processing = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status IN ('processing', 'shipped')")->fetchColumn();
$kpi_revenue = (float)$db->query("SELECT SUM(total_amount) FROM orders WHERE status NOT IN ('cancelled', 'refunded')")->fetchColumn();

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

$extra_head = '<link rel="stylesheet" href="orders.css?v=' . (file_exists(__DIR__ . '/orders.css') ? filemtime(__DIR__ . '/orders.css') : time()) . '">';
$extra_scripts = '<script src="orders.js" defer></script>';

include __DIR__ . '/../includes/header.php';
?>

<!-- KPI Header Grid -->
<div class="metrics-grid metrics-grid-3">
  <div class="metric-card">
    <div class="metric-details">
      <h3>Pending Approvals</h3>
      <div class="metric-value" style="<?php echo $kpi_pending > 0 ? 'color:#b45309;' : ''; ?>">
        <?php echo $kpi_pending; ?>
      </div>
      <div class="metric-subtext">Requires stock allocation</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>In Fulfillment</h3>
      <div class="metric-value"><?php echo $kpi_processing; ?></div>
      <div class="metric-subtext">Processing & Shipped</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Total Lifetime Orders</h3>
      <div class="metric-value"><?php echo $kpi_total_orders; ?></div>
      <div class="metric-subtext">All customer transactions</div>
    </div>
  </div>
</div>

<?php if ($msg): ?>
  <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.85rem; display:flex; align-items:center; gap:0.5rem;">
    <span><?php echo htmlspecialchars($msg); ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.85rem; display:flex; align-items:center; gap:0.5rem;">
    <span><?php echo htmlspecialchars($err); ?></span>
  </div>
<?php endif; ?>

<!-- Filters and Search Toolbar -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
  <div class="filter-nav" style="margin-bottom:0;">
    <a href="index.php?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'all') ? 'active' : ''; ?>">
      All (<?php echo $kpi_total_orders; ?>)
    </a>
    <a href="index.php?status=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'pending') ? 'active' : ''; ?>">
      Pending (<?php echo $kpi_pending; ?>)
    </a>
    <a href="index.php?status=processing<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'processing') ? 'active' : ''; ?>">
      Processing
    </a>
    <a href="index.php?status=shipped<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'shipped') ? 'active' : ''; ?>">
      Shipped
    </a>
    <a href="index.php?status=delivered<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'delivered') ? 'active' : ''; ?>">
      Delivered
    </a>
    <a href="index.php?status=cancelled<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'cancelled') ? 'active' : ''; ?>">
      Cancelled
    </a>
  </div>

  <!-- Search form -->
  <form method="GET" action="index.php" style="display:flex; gap:0.5rem; align-items:center;">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
    <input 
      type="text" 
      name="search" 
      class="form-control" 
      placeholder="Search order #, customer..." 
      value="<?php echo htmlspecialchars($search); ?>" 
      style="width: 240px; padding: 0.45rem 0.85rem;">
    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 0.55rem 0.85rem;">Search</button>
    <?php if (!empty($search)): ?>
      <a href="index.php?status=<?php echo htmlspecialchars($status_filter); ?>" class="btn btn-outline btn-sm">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Orders Table Card -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <span>Customer Purchase Records (<?php echo count($orders); ?>)</span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Order Reference</th>
          <th style="width: 85px;">Date</th>
          <th>Customer</th>
          <th>Location</th>
          <th>Items</th>
          <th style="width: 85px;">Total</th>
          <th style="width: 75px;">Status</th>
          <th style="text-align: right; width: 115px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
              No orders matched your selected criteria.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($orders as $ord): 
            $status = $ord['status'];
            $badgeClass = 'badge-pending';
            if ($status === 'processing') $badgeClass = 'badge-processing';
            elseif ($status === 'shipped') $badgeClass = 'badge-shipped';
            elseif ($status === 'delivered') $badgeClass = 'badge-delivered';
            elseif ($status === 'cancelled' || $status === 'refunded') $badgeClass = 'badge-cancelled';
          ?>
          <tr>
            <td>
              <a href="view.php?id=<?php echo $ord['id']; ?>" style="font-weight: 700; color: var(--navy-primary); font-family: monospace; text-decoration: underline;">
                <?php echo htmlspecialchars($ord['order_number']); ?>
              </a>
              <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo htmlspecialchars($ord['payment_method']); ?></div>
            </td>
            <td style="font-size: 0.74rem; color: var(--text-muted); white-space: nowrap;">
              <?php echo date('M j', strtotime($ord['created_at'])); ?><br>
              <span style="font-size: 0.67rem;"><?php echo date('g:i A', strtotime($ord['created_at'])); ?></span>
            </td>
            <td>
              <div style="font-weight: 700; color: var(--text-main); font-size: 0.78rem;"><?php echo htmlspecialchars($ord['customer_name']); ?></div>
              <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo htmlspecialchars($ord['customer_email']); ?></div>
            </td>
            <td>
              <div style="font-size: 0.78rem; font-weight: 600; color: var(--navy-primary);"><?php echo htmlspecialchars($ord['property_type']); ?></div>
              <div style="font-size: 0.7rem; color: var(--text-muted); max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($ord['shipping_address']); ?>">
                <?php echo htmlspecialchars($ord['shipping_address']); ?>
              </div>
            </td>
            <td>
              <span style="font-weight: 600; font-size: 0.78rem;"><?php echo $ord['item_count']; ?> item(s)</span>
              <div style="font-size: 0.68rem; color: var(--text-muted); max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($ord['item_summary']); ?>">
                <?php echo htmlspecialchars($ord['item_summary']); ?>
              </div>
            </td>
            <td style="font-weight: 700; color: var(--navy-primary); font-size: 0.88rem; white-space: nowrap;">
              &#8369;<?php echo number_format($ord['total_amount'], 2); ?>
            </td>
            <td>
              <span class="badge <?php echo $badgeClass; ?>" style="font-size: 0.66rem; padding: 0.15rem 0.5rem;">
                <?php echo ucfirst($status); ?>
              </span>
            </td>
            <td style="text-align: right; width: 115px; white-space: nowrap;">
              <a href="view.php?id=<?php echo $ord['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.45rem; font-size: 0.7rem;" title="Inspect Order Details">
                View
              </a>
              <?php if ($status === 'pending'): ?>
                <button 
                  type="button" 
                  class="btn btn-primary btn-sm"
                  style="padding: 0.25rem 0.45rem; font-size: 0.7rem;"
                  onclick="openStatusModal(<?php echo $ord['id']; ?>, '<?php echo htmlspecialchars($ord['order_number']); ?>', 'processing')">
                  Accept
                </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Order Status Quick Modal -->
<div class="modal-overlay" id="status-modal">
  <div class="modal-container">
    <div class="modal-header">
      <div class="modal-title" id="modal-order-title">Process Order</div>
      <button type="button" class="modal-close" onclick="closeAdminModal('status-modal')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>
    <form method="POST" action="process.php">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="order_id" id="modal-order-id" value="">
        <input type="hidden" name="redirect_source" value="index">

        <div class="form-group">
          <label class="form-label" for="new_status">Change Status To</label>
          <select class="form-control" name="new_status" id="modal-new-status" required>
            <option value="processing">Processing (Accept order & Deduct Stock)</option>
            <option value="shipped">Shipped</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled (Restore Stock)</option>
            <option value="refunded">Refunded (Restore Stock)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="admin_note">Optional Note / Tracking Info</label>
          <input type="text" class="form-control" name="admin_note" id="admin_note" placeholder="e.g. Courier tracking # PH-1029482">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeAdminModal('status-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Confirm Status Change</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
