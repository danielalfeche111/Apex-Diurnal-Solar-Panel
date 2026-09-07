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
$kpi_quoted = (int)$db->query("SELECT COUNT(*) FROM quote_requests WHERE status = 'quoted'")->fetchColumn();
$kpi_accepted = (int)$db->query("SELECT COUNT(*) FROM quote_requests WHERE status = 'accepted'")->fetchColumn();

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<!-- Metrics Header -->
<div class="metrics-grid">
  <div class="metric-card">
    <div class="metric-details">
      <h3>New RFQ Inquiries</h3>
      <div class="metric-value" style="<?php echo $kpi_new > 0 ? 'color:#b45309;' : ''; ?>">
        <?php echo $kpi_new; ?>
      </div>
      <div class="metric-subtext">Awaiting sales engineering review</div>
    </div>
    <div class="metric-icon warning">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Proposals Issued</h3>
      <div class="metric-value"><?php echo $kpi_quoted; ?></div>
      <div class="metric-subtext">Commercial pricing sent</div>
    </div>
    <div class="metric-icon info">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Contracts Accepted</h3>
      <div class="metric-value" style="color:#047857;"><?php echo $kpi_accepted; ?></div>
      <div class="metric-subtext">Converted to active solar builds</div>
    </div>
    <div class="metric-icon success">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Lifetime Inquiries</h3>
      <div class="metric-value"><?php echo $kpi_total; ?></div>
      <div class="metric-subtext">Commercial & Industrial grid RFQs</div>
    </div>
    <div class="metric-icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
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

<!-- Filters and Search Toolbar -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
  <div class="filter-nav" style="margin-bottom:0;">
    <a href="index.php?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'all') ? 'active' : ''; ?>">
      All (<?php echo $kpi_total; ?>)
    </a>
    <a href="index.php?status=new<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'new') ? 'active' : ''; ?>">
      New (<?php echo $kpi_new; ?>)
    </a>
    <a href="index.php?status=reviewed<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'reviewed') ? 'active' : ''; ?>">
      Reviewed
    </a>
    <a href="index.php?status=quoted<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'quoted') ? 'active' : ''; ?>">
      Quoted
    </a>
    <a href="index.php?status=accepted<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'accepted') ? 'active' : ''; ?>">
      Accepted
    </a>
    <a href="index.php?status=rejected<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($status_filter === 'rejected') ? 'active' : ''; ?>">
      Rejected
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
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
      <span>Commercial Grid Inquiry Queue (<?php echo count($quotes); ?>)</span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Quote #</th>
          <th>Company / Contact</th>
          <th style="width: 100px;">Facility</th>
          <th style="width: 85px;">Bill</th>
          <th style="width: 90px;">System</th>
          <th style="width: 80px;">Timeline</th>
          <th style="width: 75px;">Status</th>
          <th style="text-align: right; width: 75px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($quotes)): ?>
          <tr>
            <td colspan="8" style="text-align:center; padding:3rem; color:var(--text-muted);">
              No commercial quote requests found.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($quotes as $q): 
            $status = $q['status'];
            $badgeClass = 'badge-pending';
            if ($status === 'reviewed') $badgeClass = 'badge-processing';
            elseif ($status === 'quoted') $badgeClass = 'badge-quoted';
            elseif ($status === 'accepted') $badgeClass = 'badge-accepted';
            elseif ($status === 'rejected' || $status === 'expired') $badgeClass = 'badge-rejected';
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
            <td style="white-space:nowrap; font-weight:600; font-size:0.76rem;">
              &#8369;<?php echo number_format($q['current_monthly_bill'], 2); ?>
            </td>
            <td>
              <div style="font-weight:700; color:var(--navy-primary); font-size:0.78rem;"><?php echo $q['estimated_system_size'] ? $q['estimated_system_size'] . ' kW' : 'Custom'; ?></div>
              <?php if (!empty($q['quoted_amount'])): ?>
                <div style="font-size:0.68rem; color:#047857; font-weight:700; white-space:nowrap;">Quoted: &#8369;<?php echo number_format($q['quoted_amount'], 2); ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-size:0.7rem; padding:0.15rem 0.45rem; background:#f1f5f9; border-radius:4px; font-weight:600; color:#334155; white-space:nowrap;">
                <?php echo htmlspecialchars($q['target_timeline'] ?? 'Flexible'); ?>
              </span>
            </td>
            <td>
              <span class="badge <?php echo $badgeClass; ?>" style="font-size: 0.66rem; padding: 0.15rem 0.5rem;">
                <?php echo ucfirst($status); ?>
              </span>
            </td>
            <td style="text-align: right; width: 75px; white-space:nowrap;">
              <a href="view.php?id=<?php echo $q['id']; ?>" class="btn btn-secondary btn-sm" style="padding:0.25rem 0.5rem; font-size:0.7rem;">
                Quote
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
