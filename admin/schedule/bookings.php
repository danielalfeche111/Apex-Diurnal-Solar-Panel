<?php
/**
 * admin/schedule/bookings.php - Tabular List View for Service Bookings
 */

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

$page_title = 'Service Bookings List';
$active_nav = 'schedule';

$db = getConnection();

$status_filter = trim($_GET['status'] ?? 'all');
$service_filter = trim($_GET['service_type'] ?? 'all');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($status_filter !== 'all' && in_array($status_filter, ['pending', 'confirmed', 'completed', 'cancelled'])) {
    $where[] = "status = :st";
    $params[':st'] = $status_filter;
}

if ($service_filter !== 'all' && in_array($service_filter, ['consultation', 'installation', 'maintenance'])) {
    $where[] = "service_type = :stype";
    $params[':stype'] = $service_filter;
}

if (!empty($search)) {
    $where[] = "(booking_reference LIKE :srch1 OR customer_name LIKE :srch2 OR customer_email LIKE :srch3 OR assigned_technician LIKE :srch4)";
    $term = '%' . $search . '%';
    $params[':srch1'] = $term;
    $params[':srch2'] = $term;
    $params[':srch3'] = $term;
    $params[':srch4'] = $term;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT * FROM service_bookings $where_sql ORDER BY preferred_date DESC, created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.2rem; color:var(--navy-primary); font-weight:700;">All Service Appointments</h2>
    <p style="font-size:0.8rem; color:var(--text-muted);">Manage field technician assignments and appointment statuses.</p>
  </div>
  <a href="index.php" class="btn btn-secondary btn-sm">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
    <span>Switch to Calendar View</span>
  </a>
</div>

<?php if ($msg): ?>
  <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.85rem; display:flex; align-items:center; gap:0.5rem;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
    <span><?php echo htmlspecialchars($msg); ?></span>
  </div>
<?php endif; ?>

<!-- Filters -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
  <div class="filter-nav" style="margin-bottom:0;">
    <a href="bookings.php?status=all<?php echo $service_filter !== 'all' ? '&service_type=' . $service_filter : ''; ?>" class="filter-pill <?php echo ($status_filter === 'all') ? 'active' : ''; ?>">All</a>
    <a href="bookings.php?status=pending<?php echo $service_filter !== 'all' ? '&service_type=' . $service_filter : ''; ?>" class="filter-pill <?php echo ($status_filter === 'pending') ? 'active' : ''; ?>">Pending</a>
    <a href="bookings.php?status=confirmed<?php echo $service_filter !== 'all' ? '&service_type=' . $service_filter : ''; ?>" class="filter-pill <?php echo ($status_filter === 'confirmed') ? 'active' : ''; ?>">Confirmed</a>
    <a href="bookings.php?status=completed<?php echo $service_filter !== 'all' ? '&service_type=' . $service_filter : ''; ?>" class="filter-pill <?php echo ($status_filter === 'completed') ? 'active' : ''; ?>">Completed</a>
    <a href="bookings.php?status=cancelled<?php echo $service_filter !== 'all' ? '&service_type=' . $service_filter : ''; ?>" class="filter-pill <?php echo ($status_filter === 'cancelled') ? 'active' : ''; ?>">Cancelled</a>
  </div>

  <form method="GET" action="bookings.php" style="display:flex; gap:0.5rem; align-items:center;">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
    <input type="text" name="search" class="form-control" placeholder="Search reference, name, tech..." value="<?php echo htmlspecialchars($search); ?>" style="width:230px; padding:0.45rem 0.85rem;">
    <button type="submit" class="btn btn-secondary btn-sm" style="padding:0.55rem 0.85rem;">Filter</button>
  </form>
</div>

<!-- Table Card -->
<div class="card">
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Reference #</th>
          <th>Customer</th>
          <th style="width: 100px;">Service</th>
          <th style="width: 110px;">Date & Time</th>
          <th style="width: 100px;">Lead</th>
          <th style="width: 75px;">Status</th>
          <th style="text-align: right; width: 75px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bookings)): ?>
          <tr>
            <td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">
              No appointment bookings found matching criteria.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($bookings as $b): 
            $status = $b['status'];
            $badgeClass = 'badge-pending';
            if ($status === 'confirmed') $badgeClass = 'badge-confirmed';
            elseif ($status === 'completed') $badgeClass = 'badge-completed';
            elseif ($status === 'cancelled') $badgeClass = 'badge-cancelled';
          ?>
          <tr>
            <td>
              <a href="appointments.php?id=<?php echo $b['id']; ?>" style="font-weight:700; color:var(--navy-primary); font-family:monospace; text-decoration:underline;">
                <?php echo htmlspecialchars($b['booking_reference']); ?>
              </a>
            </td>
            <td>
              <div style="font-weight:700; color:var(--text-main); font-size:0.78rem;"><?php echo htmlspecialchars($b['customer_name']); ?></div>
              <div style="font-size:0.7rem; color:var(--text-muted);"><?php echo htmlspecialchars($b['customer_phone']); ?></div>
            </td>
            <td>
              <span style="font-size:0.72rem; text-transform:uppercase; font-weight:700; color:#475569;">
                <?php echo htmlspecialchars($b['service_type']); ?>
              </span>
            </td>
            <td>
              <div style="font-weight:600; font-size:0.74rem;"><?php echo date('M j, Y', strtotime($b['preferred_date'])); ?></div>
              <div style="font-size:0.68rem; color:var(--text-muted); text-transform:capitalize;"><?php echo htmlspecialchars($b['preferred_time_slot']); ?> Slot</div>
            </td>
            <td>
              <?php if (!empty($b['assigned_technician'])): ?>
                <span style="font-weight:600; color:var(--navy-primary); font-size:0.75rem;"><?php echo htmlspecialchars($b['assigned_technician']); ?></span>
              <?php else: ?>
                <span style="color:#94a3b8; font-style:italic; font-size:0.74rem;">Unassigned</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?php echo $badgeClass; ?>" style="font-size: 0.66rem; padding: 0.15rem 0.5rem;">
                <?php echo ucfirst($status); ?>
              </span>
            </td>
            <td style="text-align: right; width: 75px; white-space: nowrap;">
              <a href="appointments.php?id=<?php echo $b['id']; ?>" class="btn btn-secondary btn-sm" style="padding:0.25rem 0.5rem; font-size:0.7rem;">
                Manage
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
