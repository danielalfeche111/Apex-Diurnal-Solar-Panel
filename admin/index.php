<?php
/**
 * admin/index.php - Apex Diurnal Admin Mission Control Dashboard
 */

require_once __DIR__ . '/auth.php';
requireAdminLogin();

$page_title = 'Dashboard Overview';
$active_nav = 'dashboard';

$db = (new Database())->getConnection();

// --- 1. Key Metrics & Financials ---
$total_revenue = (float) $db->query("SELECT SUM(total_amount) FROM orders WHERE status NOT IN ('cancelled', 'refunded')")->fetchColumn();
$pending_orders = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$low_stock_items = (int) $db->query("SELECT COUNT(*) FROM inventory WHERE current_stock <= reorder_point")->fetchColumn();
$pending_bookings = (int) $db->query("SELECT COUNT(*) FROM service_bookings WHERE status = 'pending'")->fetchColumn();
$new_quotes = (int) $db->query("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'")->fetchColumn();

// --- 2. Recent Orders (Last 5) ---
$sqlRecentOrders = "
    SELECT 
        o.id, o.order_number, o.customer_name, o.total_amount, o.status, o.created_at,
        COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 6
";
$recent_orders = $db->query($sqlRecentOrders)->fetchAll(PDO::FETCH_ASSOC);

// --- 3. Upcoming Bookings (Next 5) ---
$sqlUpcomingBookings = "
    SELECT * FROM service_bookings 
    WHERE preferred_date >= CURDATE() AND status != 'cancelled'
    ORDER BY preferred_date ASC, preferred_time_slot ASC
    LIMIT 5
";
$upcoming_bookings = $db->query($sqlUpcomingBookings)->fetchAll(PDO::FETCH_ASSOC);

// --- 4. Recent Commercial Inquiries (Last 5) ---
$sqlRecentQuotes = "
    SELECT * FROM quote_requests 
    ORDER BY (status = 'new') DESC, created_at DESC 
    LIMIT 5
";
$recent_quotes = $db->query($sqlRecentQuotes)->fetchAll(PDO::FETCH_ASSOC);

// --- 5. Physical Inventory Health ---
$sqlInventory = "
    SELECT p.name, p.image, i.sku, i.current_stock, i.min_stock_level, i.max_stock_level, i.reorder_point
    FROM inventory i
    JOIN products p ON i.product_id = p.id
    ORDER BY (i.current_stock <= i.reorder_point) DESC, i.current_stock ASC
";
$inventory_health = $db->query($sqlInventory)->fetchAll(PDO::FETCH_ASSOC);

$admin = getAdminUser();

include __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div
  style="background: linear-gradient(135deg, var(--navy-dark) 0%, var(--navy-primary) 100%); color:#ffffff; border-radius: var(--radius-md); padding: 1.75rem 2rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem; border: 1px solid rgba(255,255,255,0.08); box-shadow: var(--shadow-md);">
  <div>
    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
      <span
        style="background:var(--yellow-accent); color:var(--navy-primary); font-size:0.7rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:9999px; text-transform:uppercase;">
        <?php echo htmlspecialchars($admin['role'] ?? 'Staff'); ?>
      </span>
      <span style="font-size:0.8rem; color:#cbd5e1;"><?php echo date('l, F j, Y'); ?></span>
    </div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #ffffff; letter-spacing: -0.02em;">
      Welcome back, <?php echo htmlspecialchars($admin['username'] ?? 'Admin'); ?>!
    </h2>
    <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.25rem;">
      Apex Diurnal Solar Panels E-Commerce & Service Operations Mission Control.
    </p>
  </div>

  <div style="display: flex; gap: 0.75rem;">
    <a href="orders/index.php?status=pending" class="btn btn-primary btn-sm">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
      </svg>
      <span>Review Orders (<?php echo $pending_orders; ?>)</span>
    </a>
    <a href="schedule/index.php" class="btn btn-secondary btn-sm"
      style="background:rgba(255,255,255,0.12); color:#ffffff; border-color:rgba(255,255,255,0.2);">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
      </svg>
      <span>View Calendar</span>
    </a>
  </div>
</div>

<!-- 5 Primary Executive KPIs -->
<div class="metrics-grid">
  <!-- Revenue -->
  <div class="metric-card">
    <div class="metric-details">
      <h3>Total Sales Revenue</h3>
      <div class="metric-value">&#8369;<?php echo number_format($total_revenue, 2); ?></div>
      <div class="metric-subtext">Verified customer checkouts</div>
    </div>
    <div class="metric-icon success">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="1" x2="12" y2="23"></line>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
      </svg>
    </div>
  </div>

  <!-- Pending Orders -->
  <div class="metric-card">
    <div class="metric-details">
      <h3>Pending Purchases</h3>
      <div class="metric-value" style="<?php echo $pending_orders > 0 ? 'color:#b45309;' : ''; ?>">
        <?php echo $pending_orders; ?>
      </div>
      <div class="metric-subtext">Awaiting fulfillment & stock check</div>
    </div>
    <div class="metric-icon warning">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <polyline points="12 6 12 12 16 14"></polyline>
      </svg>
    </div>
  </div>

  <!-- Low Stock Alerts -->
  <div class="metric-card">
    <div class="metric-details">
      <h3>Low Stock Alerts</h3>
      <div class="metric-value" style="<?php echo $low_stock_items > 0 ? 'color:#ea580c;' : ''; ?>">
        <?php echo $low_stock_items; ?>
      </div>
      <div class="metric-subtext">Items at reorder threshold</div>
    </div>
    <div class="metric-icon" style="<?php echo $low_stock_items > 0 ? 'background:#fff7ed; color:#ea580c;' : ''; ?>">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
      </svg>
    </div>
  </div>

  <!-- Pending Bookings -->
  <div class="metric-card">
    <div class="metric-details">
      <h3>Pending Service Visits</h3>
      <div class="metric-value"><?php echo $pending_bookings; ?></div>
      <div class="metric-subtext">Installation & audits to confirm</div>
    </div>
    <div class="metric-icon info">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
      </svg>
    </div>
  </div>

  <!-- New Quotes -->
  <div class="metric-card">
    <div class="metric-details">
      <h3>New RFQ Inquiries</h3>
      <div class="metric-value"><?php echo $new_quotes; ?></div>
      <div class="metric-subtext">Commercial solar inquiries</div>
    </div>
    <div class="metric-icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
      </svg>
    </div>
  </div>
</div>

<!-- Main 2-Column Dashboard Layout -->
<div class="admin-grid-2col">
  <!-- Left Main Column: Recent Orders & Service Calendar Feed -->
  <div>
    <!-- Recent Orders Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <path d="M16 10a4 4 0 0 1-8 0"></path>
          </svg>
          <span>Recent Customer Purchases</span>
        </div>
        <a href="orders/index.php" class="btn btn-secondary btn-sm">View All Orders &rarr;</a>
      </div>

      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th style="min-width: 120px;">Order Reference</th>
              <th style="min-width: 120px;">Customer</th>
              <th style="min-width: 85px;">Total</th>
              <th style="min-width: 80px;">Status</th>
              <th style="text-align: right; width: 80px; min-width: 80px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_orders)): ?>
              <tr>
                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">No orders recorded
                  yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($recent_orders as $ord):
                $st = $ord['status'];
                $bClass = 'badge-pending';
                if ($st === 'processing')
                  $bClass = 'badge-processing';
                elseif ($st === 'shipped')
                  $bClass = 'badge-shipped';
                elseif ($st === 'delivered')
                  $bClass = 'badge-delivered';
                elseif ($st === 'cancelled' || $st === 'refunded')
                  $bClass = 'badge-cancelled';
                ?>
                <tr>
                  <td>
                    <a href="orders/view.php?id=<?php echo $ord['id']; ?>"
                      style="font-weight: 700; color: var(--navy-primary); font-family: monospace;">
                      <?php echo htmlspecialchars($ord['order_number']); ?>
                    </a>
                    <div style="font-size: 0.72rem; color: var(--text-muted); white-space: nowrap;">
                      <?php echo date('M j, Y - g:i A', strtotime($ord['created_at'])); ?>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($ord['customer_name']); ?></div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);"><?php echo $ord['item_count']; ?> item(s)
                    </div>
                  </td>
                  <td style="font-weight: 700; color: var(--navy-primary); white-space: nowrap;">
                    &#8369;<?php echo number_format($ord['total_amount'], 2); ?>
                  </td>
                  <td>
                    <span class="badge <?php echo $bClass; ?>"><?php echo ucfirst($st); ?></span>
                  </td>
                  <td style="text-align: right; width: 80px; min-width: 80px; white-space: nowrap;">
                    <a href="orders/view.php?id=<?php echo $ord['id']; ?>" class="btn btn-secondary btn-sm"
                      style="padding: 0.3rem 0.6rem; font-size: 0.72rem;">
                      Inspect
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Commercial Quotes Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
          </svg>
          <span>Commercial Grid Quotation Requests</span>
        </div>
        <a href="quotes/index.php" class="btn btn-secondary btn-sm">All Quotes &rarr;</a>
      </div>

      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th style="min-width: 100px;">Quote #</th>
              <th style="min-width: 120px;">Company</th>
              <th style="min-width: 90px;">Monthly Bill</th>
              <th style="min-width: 95px;">Target Timeline</th>
              <th style="min-width: 75px;">Status</th>
              <th style="text-align: right; width: 80px; min-width: 80px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_quotes)): ?>
              <tr>
                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">No quote requests
                  submitted yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($recent_quotes as $q):
                $qst = $q['status'];
                $qbClass = 'badge-pending';
                if ($qst === 'reviewed')
                  $qbClass = 'badge-processing';
                elseif ($qst === 'quoted')
                  $qbClass = 'badge-quoted';
                elseif ($qst === 'accepted')
                  $qbClass = 'badge-accepted';
                elseif ($qst === 'rejected')
                  $qbClass = 'badge-rejected';
                ?>
                <tr>
                  <td>
                    <a href="quotes/view.php?id=<?php echo $q['id']; ?>"
                      style="font-weight: 700; color: var(--navy-primary); font-family: monospace;">
                      <?php echo htmlspecialchars($q['quote_number']); ?>
                    </a>
                  </td>
                  <td>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($q['company_name']); ?></div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">
                      <?php echo htmlspecialchars($q['contact_person']); ?></div>
                  </td>
                  <td style="font-weight: 600; white-space: nowrap;">
                    &#8369;<?php echo number_format($q['current_monthly_bill'], 2); ?>
                  </td>
                  <td>
                    <span
                      style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap;"><?php echo htmlspecialchars($q['target_timeline'] ?? 'Flexible'); ?></span>
                  </td>
                  <td>
                    <span class="badge <?php echo $qbClass; ?>"><?php echo ucfirst($qst); ?></span>
                  </td>
                  <td style="text-align: right; width: 80px; min-width: 80px; white-space: nowrap;">
                    <a href="quotes/view.php?id=<?php echo $q['id']; ?>" class="btn btn-secondary btn-sm"
                      style="padding: 0.3rem 0.6rem; font-size: 0.72rem;">
                      Respond
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Right Column: Warehouse Stock Health & Upcoming Bookings -->
  <div>
    <!-- Physical Inventory Status Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path
              d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z">
            </path>
          </svg>
          <span>Inventory</span>
        </div>
        <a href="inventory/index.php" class="btn btn-secondary btn-sm">Manage</a>
      </div>
      <div class="card-body">
        <?php foreach ($inventory_health as $inv):
          $curr = (int) $inv['current_stock'];
          $max = max(1, (int) $inv['max_stock_level']);
          $reorder = (int) $inv['reorder_point'];
          $pct = min(100, round(($curr / $max) * 100));
          $isLow = ($curr <= $reorder);
          ?>
          <div style="margin-bottom: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.35rem;">
              <div>
                <span
                  style="font-weight: 700; font-size: 0.84rem; color: var(--navy-primary);"><?php echo htmlspecialchars($inv['name']); ?></span>
                <span
                  style="font-size: 0.72rem; color: var(--text-muted); font-family: monospace;">(<?php echo htmlspecialchars($inv['sku']); ?>)</span>
              </div>
              <div>
                <span style="font-weight: 700; font-size: 0.9rem; color: <?php echo $isLow ? '#ea580c' : '#047857'; ?>;">
                  <?php echo $curr; ?>
                </span>
                <span style="font-size: 0.75rem; color: var(--text-muted);">/ <?php echo $max; ?></span>
              </div>
            </div>
            <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">
              <div
                style="height: 100%; width: <?php echo $pct; ?>%; background: <?php echo $isLow ? '#f59e0b' : '#10b981'; ?>;">
              </div>
            </div>
            <?php if ($isLow): ?>
              <div style="font-size: 0.72rem; color: #ea580c; font-weight: 600; margin-top: 0.2rem;">
                ⚠️ Below reorder point (<?php echo $reorder; ?> units)
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Upcoming Appointments Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
          </svg>
          <span>Upcoming Service Schedule</span>
        </div>
        <a href="schedule/index.php" class="btn btn-secondary btn-sm">Calendar</a>
      </div>
      <div class="card-body" style="padding: 0.75rem 1.25rem;">
        <?php if (empty($upcoming_bookings)): ?>
          <div style="text-align: center; padding: 1.5rem; color: var(--text-muted); font-size: 0.84rem;">
            No upcoming appointments scheduled.
          </div>
        <?php else: ?>
          <?php foreach ($upcoming_bookings as $ub): ?>
            <div
              style="padding: 0.75rem 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
              <div>
                <div style="font-weight: 700; font-size: 0.83rem; color: var(--navy-primary);">
                  <?php echo htmlspecialchars($ub['customer_name']); ?>
                </div>
                <div style="font-size: 0.72rem; color: var(--text-muted);">
                  <?php echo date('M j, Y', strtotime($ub['preferred_date'])); ?> &bull;
                  <?php echo ucfirst($ub['preferred_time_slot']); ?>
                </div>
                <div style="font-size: 0.72rem; font-weight: 600; color: #475569; text-transform: uppercase;">
                  <?php echo htmlspecialchars($ub['service_type']); ?>
                </div>
              </div>
              <a href="schedule/appointments.php?id=<?php echo $ub['id']; ?>" class="btn btn-secondary btn-sm"
                style="padding: 0.25rem 0.55rem; font-size: 0.72rem;">
                Details
              </a>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>