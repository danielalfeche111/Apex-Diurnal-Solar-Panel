<?php
/**
 * admin/inventory/index.php - Real-Time Stock Levels Dashboard
 */

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

require_once __DIR__ . '/sync.php';

$page_title = 'Inventory Control';
$active_nav = 'inventory';

$items = getInventoryItems();

$totalPhysicalUnits = 0;
$lowStockCount = 0;
$outOfStockCount = 0;
$physicalItemCount = 0;

foreach ($items as $it) {
    if ($it['product_type'] === 'physical') {
        $physicalItemCount++;
        $stock = (int)$it['current_stock'];
        $reorder = (int)$it['reorder_point'];
        $totalPhysicalUnits += $stock;
        if ($stock === 0) {
            $outOfStockCount++;
        } elseif ($stock <= $reorder) {
            $lowStockCount++;
        }
    }
}

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

$extra_head = '<link rel="stylesheet" href="inventory.css">';
$extra_scripts = '<script src="inventory.js" defer></script>';

include __DIR__ . '/../includes/header.php';
?>

<!-- Metrics Header -->
<div class="metrics-grid">
  <div class="metric-card">
    <div class="metric-details">
      <h3>Physical Units on Hand</h3>
      <div class="metric-value"><?php echo number_format($totalPhysicalUnits); ?></div>
      <div class="metric-subtext">Across <?php echo $physicalItemCount; ?> physical SKUs</div>
    </div>
    <div class="metric-icon success">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Low Stock Alerts</h3>
      <div class="metric-value" style="<?php echo $lowStockCount > 0 ? 'color: #ea580c;' : ''; ?>">
        <?php echo $lowStockCount; ?>
      </div>
      <div class="metric-subtext">At or below reorder threshold</div>
    </div>
    <div class="metric-icon warning">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Out of Stock</h3>
      <div class="metric-value" style="<?php echo $outOfStockCount > 0 ? 'color: #dc2626;' : ''; ?>">
        <?php echo $outOfStockCount; ?>
      </div>
      <div class="metric-subtext">Requires immediate restock</div>
    </div>
    <div class="metric-icon" style="background:#fef2f2; color:#ef4444;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Total Catalog Offerings</h3>
      <div class="metric-value"><?php echo count($items); ?></div>
      <div class="metric-subtext">Products, Grids & Services</div>
    </div>
    <div class="metric-icon info">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
    </div>
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

<!-- Inventory Catalog Table Card -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
      <span>Stock Levels & Warehouse Catalog</span>
    </div>
    <div style="display: flex; gap: 0.5rem;">
      <a href="alerts.php" class="btn btn-secondary btn-sm">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg>
        <span>Stock Alert Center</span>
      </a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Product / SKU</th>
          <th style="width: 70px;">Type</th>
          <th style="width: 85px;">Price</th>
          <th style="width: 85px;">Stock</th>
          <th style="width: 105px;">Min / Reorder</th>
          <th style="width: 80px;">Status</th>
          <th style="width: 85px;">Activity</th>
          <th style="text-align: right; width: 85px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): 
          $isPhysical = ($item['product_type'] === 'physical');
          $stock = (int)$item['current_stock'];
          $max = max(1, (int)$item['max_stock_level']);
          $reorder = (int)$item['reorder_point'];
          $pct = min(100, round(($stock / $max) * 100));

          if (!$isPhysical) {
              $badgeClass = 'badge-processing';
              $badgeText = ($item['product_type'] === 'service') ? 'Service' : 'Custom RFQ';
          } elseif ($stock === 0) {
              $badgeClass = 'badge-outofstock';
              $badgeText = 'Out of Stock';
          } elseif ($stock <= $reorder) {
              $badgeClass = 'badge-lowstock';
              $badgeText = 'Low Stock';
          } else {
              $badgeClass = 'badge-instock';
              $badgeText = 'In Stock';
          }
        ?>
        <tr>
          <td>
            <div style="display: flex; align-items: center; gap: 0.6rem;">
              <?php if (!empty($item['image'])): ?>
                <img src="<?php echo $admin_base . '../' . htmlspecialchars($item['image']); ?>" alt="" style="width: 32px; height: 32px; object-fit: contain; background: #f8fafc; border-radius: 4px; border: 1px solid #e2e8f0; padding: 2px; flex-shrink: 0;">
              <?php else: ?>
                <div style="width: 32px; height: 32px; background: #e2e8f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.68rem; color: #64748b; flex-shrink: 0;">N/A</div>
              <?php endif; ?>
              <div style="min-width: 0;">
                <div style="font-weight: 700; color: var(--navy-primary); font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;" title="<?php echo htmlspecialchars($item['product_name']); ?>">
                  <?php echo htmlspecialchars($item['product_name']); ?>
                </div>
                <div style="font-size: 0.68rem; color: var(--text-muted); font-family: monospace; white-space: nowrap;">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
              </div>
            </div>
          </td>
          <td>
            <span style="font-size: 0.68rem; text-transform: uppercase; font-weight: 700; color: #475569; white-space: nowrap;">
              <?php echo htmlspecialchars($item['product_type']); ?>
            </span>
          </td>
          <td style="font-weight: 600; white-space: nowrap;">
            <?php echo ($item['base_price'] > 0) ? '&#8369;' . number_format($item['base_price'], 2) : '<span style="color:var(--text-muted); font-size:0.72rem;">Quote</span>'; ?>
          </td>
          <td>
            <?php if ($isPhysical): ?>
              <div style="display: flex; align-items: center; gap: 0.35rem; white-space: nowrap;">
                <span style="font-weight: 700; font-size: 0.86rem; min-width: 20px;"><?php echo $stock; ?></span>
                <div style="width: 32px; height: 5px; background: #e2e8f0; border-radius: 9999px; overflow: hidden; flex-shrink: 0;" title="<?php echo $stock . ' / ' . $max . ' units (' . $pct . '%)'; ?>">
                  <div style="height: 100%; width: <?php echo $pct; ?>%; background: <?php echo ($stock <= $reorder) ? '#f59e0b' : '#10b981'; ?>;"></div>
                </div>
              </div>
            <?php else: ?>
              <span style="color: #94a3b8; font-size: 0.72rem; white-space: nowrap;">Unlimited</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isPhysical): ?>
              <div style="font-size: 0.72rem; white-space: nowrap; color: var(--text-muted);">
                <span><?php echo $item['min_stock_level']; ?></span> / <strong style="color:var(--text-main);"><?php echo $item['reorder_point']; ?></strong>
              </div>
            <?php else: ?>
              <span style="color: #94a3b8;">&mdash;</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?php echo $badgeClass; ?>" style="font-size: 0.66rem; padding: 0.15rem 0.5rem;">
              <?php echo $badgeText; ?>
            </span>
          </td>
          <td>
            <?php if (!empty($item['last_updated'])): ?>
              <div style="font-size: 0.72rem; font-weight: 600; color: var(--text-main); white-space: nowrap;">
                <?php echo date('M j', strtotime($item['last_updated'])); ?>
              </div>
              <div style="font-size: 0.65rem; color: var(--text-muted); white-space: nowrap;">
                <?php echo date('g:i A', strtotime($item['last_updated'])); ?>
              </div>
            <?php else: ?>
              <span style="color: #94a3b8; font-size: 0.72rem;">Never</span>
            <?php endif; ?>
          </td>
          <td style="text-align: right; width: 85px; white-space: nowrap;">
            <?php if ($isPhysical): ?>
              <button 
                type="button" 
                class="btn btn-primary btn-sm"
                style="padding: 0.25rem 0.5rem; font-size: 0.7rem; gap: 0.25rem;"
                onclick="openStockModal('<?php echo htmlspecialchars($item['product_id']); ?>', '<?php echo htmlspecialchars(addslashes($item['product_name'])); ?>', <?php echo $stock; ?>)">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                <span>Adjust</span>
              </button>
            <?php else: ?>
              <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 0.66rem; font-weight: 600;">Service</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal-overlay" id="stock-modal">
  <div class="modal-container">
    <div class="modal-header">
      <div class="modal-title" id="modal-product-name">Adjust Stock Level</div>
      <button type="button" class="modal-close" onclick="closeAdminModal('stock-modal')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>
    <form method="POST" action="edit.php">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="product_id" id="modal-product-id" value="">

        <div style="background: var(--navy-subtle); padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.83rem; color: var(--navy-primary);">
          Current Physical Stock: <strong id="modal-current-stock">0</strong> units
        </div>

        <div class="form-group">
          <label class="form-label" for="adjustment_type">Action Type</label>
          <select class="form-control" name="adjustment_type" id="adjustment_type" required onchange="updateCalcPreview()">
            <option value="add">Add Stock (Restock / Shipment Arrival)</option>
            <option value="deduct">Deduct Stock (Damage / Inventory Audit Adjustment)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="quantity">Quantity of Units</label>
          <input type="number" class="form-control" name="quantity" id="modal-quantity" min="1" max="500" value="5" required oninput="updateCalcPreview()">
        </div>

        <div style="background: #f8fafc; border: 1px dashed #cbd5e1; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.25rem; font-size: 0.82rem; color: var(--text-muted);">
          New resulting stock level will be: <strong id="modal-projected-stock" style="color: var(--navy-primary);">0</strong> units
        </div>

        <div class="form-group">
          <label class="form-label" for="reason">Reason / Audit Log Note</label>
          <input type="text" class="form-control" name="reason" id="reason" placeholder="e.g., Supplier batch PO-2026-88 arrival" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeAdminModal('stock-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Adjustment</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
