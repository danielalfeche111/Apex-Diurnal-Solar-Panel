<?php
/**
 * admin/quotes/index.php - Commercial Grid Quote Requests Ticketing Inbox
 */

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

$page_title = 'Quote Requests Queue';
$active_nav = 'quotes';

$db = (new Database())->getConnection();

$status_filter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($status_filter !== 'all' && in_array($status_filter, ['new', 'reviewed', 'quoted', 'accepted', 'rejected', 'expired'])) {
    $where[] = "status = :st";
    $params[':st'] = $status_filter;
}

if (!empty($search)) {
    $where[] = "(quote_number LIKE :srch OR company_name LIKE :srch OR contact_person LIKE :srch OR email LIKE :srch)";
    $params[':srch'] = '%' . $search . '%';
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT * FROM quote_requests $where_sql ORDER BY (status = 'new') DESC, created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// KPIs
$kpi_total = (int)$db->query("SELECT COUNT(*) FROM quote_requests")->fetchColumn();
$kpi_new = (int)$db->query("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'")->fetchColumn();
$kpi_confirmed = (int)$db->query("SELECT COUNT(*) FROM quote_requests WHERE status IN ('confirmed', 'accepted')")->fetchColumn();
$kpi_in_progress = (int)$db->query("SELECT COUNT(*) FROM quote_requests WHERE status = 'in_progress'")->fetchColumn();

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

$extra_head = '<link rel="stylesheet" href="quotes.css">';
$extra_scripts = '<script src="quotes.js" defer></script>';

include __DIR__ . '/../includes/header.php';
?>

<!-- Metrics Header -->
<div class="metrics-grid">
  <div class="metric-card">
    <div class="metric-details">
      <h3>Pending Confirmation</h3>
      <div class="metric-value" style="<?php echo $kpi_new > 0 ? 'color:#b45309;' : ''; ?>">
        <?php echo $kpi_new; ?>
      </div>
      <div class="metric-subtext">Awaiting admin review & confirmation</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Confirmed Installations</h3>
      <div class="metric-value" style="color:#047857;"><?php echo $kpi_confirmed; ?></div>
      <div class="metric-subtext">Commercial solar builds confirmed</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Active Builds</h3>
      <div class="metric-value" style="color:#1d4ed8;"><?php echo $kpi_in_progress; ?></div>
      <div class="metric-subtext">Installations currently in progress</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Total Requests</h3>
      <div class="metric-value"><?php echo $kpi_total; ?></div>
      <div class="metric-subtext">Commercial & industrial inquiries</div>
    </div>
  </div>
</div>

<?php if ($msg): ?>
  <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.85rem;">
    <span><?php echo htmlspecialchars($msg); ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.85rem;">
    <span><?php echo htmlspecialchars($err); ?></span>
  </div>
<?php endif; ?>

<!-- Filters and Search Toolbar -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
  <div class="filter-nav" style="margin-bottom:0;">
    <a href="index.php?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'all') ? 'active' : ''; ?>">
      All (<?php echo $kpi_total; ?>)
    </a>
    <a href="index.php?status=new<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'new') ? 'active' : ''; ?>">
      Pending (<?php echo $kpi_new; ?>)
    </a>
    <a href="index.php?status=confirmed<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'confirmed') ? 'active' : ''; ?>">
      Confirmed (<?php echo $kpi_confirmed; ?>)
    </a>
    <a href="index.php?status=in_progress<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'in_progress') ? 'active' : ''; ?>">
      In Progress (<?php echo $kpi_in_progress; ?>)
    </a>
    <a href="index.php?status=completed<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'completed') ? 'active' : ''; ?>">
      Completed
    </a>
    <a href="index.php?status=cancelled<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'cancelled') ? 'active' : ''; ?>">
      Cancelled
    </a>
  </div>

  <form method="GET" action="index.php" style="display:flex; gap:0.5rem; align-items:center;">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
    <input type="text" name="search" class="form-control" placeholder="Search company, contact..." value="<?php echo htmlspecialchars($search); ?>" style="width:230px; padding:0.45rem 0.85rem;">
    <button type="submit" class="btn btn-secondary btn-sm" style="padding:0.55rem 0.85rem;">Search</button>
  </form>
</div>

<!-- Table Card -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <span>Commercial Grid Installation Requests (<?php echo count($quotes); ?>)</span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Quote #</th>
          <th>Company / Contact</th>
          <th style="width: 110px;">Facility Specs</th>
          <th style="width: 140px;">Installation Head</th>
          <th style="width: 110px;">Target / Date</th>
          <th style="width: 90px;">Status</th>
          <th style="text-align: right; width: 75px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($quotes)): ?>
          <tr>
            <td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">
              No commercial installation requests found.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($quotes as $q): 
            $status = $q['status'];
            $badgeClass = 'badge-pending';
            $statusLabel = 'Pending';
            if ($status === 'confirmed' || $status === 'accepted') {
                $badgeClass = 'badge-accepted';
                $statusLabel = 'Confirmed';
            } elseif ($status === 'in_progress') {
                $badgeClass = 'badge-processing';
                $statusLabel = 'In Progress';
            } elseif ($status === 'completed') {
                $badgeClass = 'badge-accepted';
                $statusLabel = 'Completed';
            } elseif ($status === 'cancelled' || $status === 'rejected' || $status === 'expired') {
                $badgeClass = 'badge-rejected';
                $statusLabel = ucfirst($status);
            } elseif ($status === 'reviewed') {
                $badgeClass = 'badge-processing';
                $statusLabel = 'Reviewed';
            } elseif ($status === 'quoted') {
                $badgeClass = 'badge-quoted';
                $statusLabel = 'Quoted';
            }
          ?>
          <tr>
            <td>
              <a href="view.php?id=<?php echo $q['id']; ?>" style="font-weight:700; color:var(--navy-primary); font-family:monospace; text-decoration:underline;">
                <?php echo htmlspecialchars($q['quote_number']); ?>
              </a>
              <div style="font-size:0.68rem; color:var(--text-muted); white-space:nowrap;"><?php echo date('M j', strtotime($q['created_at'])); ?></div>
            </td>
            <td>
              <div style="font-weight:700; color:var(--text-main); font-size:0.8rem;"><?php echo htmlspecialchars($q['company_name']); ?></div>
              <div style="font-size:0.7rem; color:var(--text-muted);"><?php echo htmlspecialchars($q['contact_person']); ?> &bull; <?php echo htmlspecialchars($q['email']); ?></div>
            </td>
            <td>
              <div style="font-weight:600; color:var(--navy-primary); font-size:0.76rem;"><?php echo htmlspecialchars($q['facility_type'] ?? 'Commercial'); ?></div>
              <div style="font-size:0.68rem; color:var(--text-muted); white-space:nowrap;"><?php echo number_format($q['facility_size']); ?> sqm</div>
            </td>
            <td>
              <?php if (!empty($q['installation_head'])): ?>
                <div style="font-weight:700; color:#047857; font-size:0.78rem;">
                  &#10003; <?php echo htmlspecialchars($q['installation_head']); ?>
                </div>
              <?php else: ?>
                <span style="font-size:0.7rem; color:#b45309; background:#fef3c7; padding:0.15rem 0.45rem; border-radius:4px; font-weight:600;">
                  Unassigned
                </span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($q['installation_date'])): ?>
                <div style="font-weight:600; color:var(--text-main); font-size:0.75rem;">
                  <?php echo date('M j, Y', strtotime($q['installation_date'])); ?>
                </div>
              <?php else: ?>
                <span style="font-size:0.7rem; padding:0.15rem 0.45rem; background:#f1f5f9; border-radius:4px; font-weight:500; color:var(--text-muted); white-space:nowrap;">
                  <?php echo htmlspecialchars($q['target_timeline'] ?? 'Flexible'); ?>
                </span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?php echo $badgeClass; ?>" style="font-size: 0.66rem; padding: 0.15rem 0.5rem;">
                <?php echo htmlspecialchars($statusLabel); ?>
              </span>
            </td>
            <td style="text-align: right; width: 75px; white-space:nowrap;">
              <a href="view.php?id=<?php echo $q['id']; ?>" class="btn btn-secondary btn-sm" style="padding:0.25rem 0.55rem; font-size:0.72rem;">
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
