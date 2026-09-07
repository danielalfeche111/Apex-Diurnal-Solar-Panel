<?php
/**
 * admin/inventory/alerts.php - Low Stock Notifications & Audit Trail
 */

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

require_once __DIR__ . '/sync.php';

$page_title = 'Stock Alerts & Audit Log';
$active_nav = 'inventory';

$alerts = getLowStockAlerts();

// Fetch recent inventory transactions
$db = (new Database())->getConnection();
$transactions = [];
if ($db) {
    $sql = "
        SELECT 
            t.*,
            p.name AS product_name,
            COALESCE(u.username, 'System') as admin_name
        FROM inventory_transactions t
        JOIN products p ON t.product_id = p.id
        LEFT JOIN admin_users u ON t.created_by = u.id
        ORDER BY t.created_at DESC
        LIMIT 50
    ";
    $transactions = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
  <div>
    <h2 style="font-size:1.15rem; color:var(--navy-primary); font-weight:700;">Reorder Triggers & Audit Trail</h2>
    <p style="font-size:0.8rem; color:var(--text-muted);">Real-time alerts for items at or below safety stock levels.</p>
  </div>
  <a href="index.php" class="btn btn-secondary btn-sm">
    &larr; Back to Inventory
  </a>
</div>

<!-- Active Alerts Section -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg>
      <span>Critical Reorder Notifications (<?php echo count($alerts); ?>)</span>
    </div>
  </div>

  <?php if (empty($alerts)): ?>
    <div style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="1.5" style="margin-bottom: 0.75rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
      <div style="font-weight: 700; color: var(--navy-primary); margin-bottom: 0.25rem;">All Warehouses Healthy</div>
      <p style="font-size: 0.82rem;">All physical items are currently above their respective reorder thresholds.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>Current Stock</th>
            <th>Reorder Point</th>
            <th>Safety Min</th>
            <th>Suggested Reorder</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($alerts as $a): 
            $curr = (int)$a['current_stock'];
            $reorder = (int)$a['reorder_point'];
            $max = (int)$a['max_stock_level'];
            $suggested = max(1, $max - $curr);
          ?>
          <tr>
            <td style="font-weight: 700; color: var(--navy-primary);">
              <?php echo htmlspecialchars($a['product_name']); ?>
            </td>
            <td style="font-family: monospace;"><?php echo htmlspecialchars($a['sku']); ?></td>
            <td>
              <span style="font-weight: 700; color: <?php echo ($curr === 0) ? '#dc2626' : '#ea580c'; ?>;">
                <?php echo $curr; ?> units
              </span>
            </td>
            <td><?php echo $reorder; ?> units</td>
            <td><?php echo $a['min_stock_level']; ?> units</td>
            <td>
              <span style="font-weight: 700; color: #15803d;">+<?php echo $suggested; ?> units</span>
              <span style="font-size: 0.72rem; color: var(--text-muted);">(to reach max <?php echo $max; ?>)</span>
            </td>
            <td style="text-align: right;">
              <a href="index.php" class="btn btn-primary btn-sm">
                Restock Now
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Stock Transaction Audit Log -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
      <span>Historical Stock Movement & Audit Log</span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>Product</th>
          <th>Type</th>
          <th>Adjustment</th>
          <th>Reference</th>
          <th>Auditor / Author</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($transactions)): ?>
          <tr>
            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
              No transaction history recorded yet.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($transactions as $t): 
            $delta = (int)$t['change_amount'];
            $deltaStr = ($delta > 0) ? "+$delta" : "$delta";
            $deltaColor = ($delta > 0) ? "#10b981" : "#ef4444";
          ?>
          <tr>
            <td style="font-size: 0.76rem; color: var(--text-muted); white-space: nowrap;">
              <?php echo date('M j, Y - g:i A', strtotime($t['created_at'])); ?>
            </td>
            <td style="font-weight: 600; color: var(--navy-primary);">
              <?php echo htmlspecialchars($t['product_name']); ?>
            </td>
            <td>
              <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #475569;">
                <?php echo str_replace('_', ' ', htmlspecialchars($t['transaction_type'])); ?>
              </span>
            </td>
            <td style="font-weight: 700; color: <?php echo $deltaColor; ?>;">
              <?php echo $deltaStr; ?>
            </td>
            <td style="font-family: monospace; font-size: 0.78rem;">
              <?php echo htmlspecialchars($t['reference_id'] ?? '&mdash;'); ?>
            </td>
            <td style="font-size: 0.8rem;">
              <?php echo htmlspecialchars($t['admin_name']); ?>
            </td>
            <td style="font-size: 0.78rem; color: var(--text-muted);">
              <?php echo htmlspecialchars($t['notes'] ?? ''); ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
