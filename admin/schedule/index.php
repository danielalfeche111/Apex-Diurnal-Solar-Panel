<?php
/**
 * admin/schedule/index.php - Service Scheduling Calendar Interface
 */

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

$page_title = 'Service Scheduling Calendar';
$active_nav = 'schedule';

$db = getConnection();

// Booking KPIs
$kpi_total = (int)$db->query("SELECT COUNT(*) FROM service_bookings")->fetchColumn();
$kpi_pending = (int)$db->query("SELECT COUNT(*) FROM service_bookings WHERE status = 'pending'")->fetchColumn();
$kpi_confirmed = (int)$db->query("SELECT COUNT(*) FROM service_bookings WHERE status = 'confirmed'")->fetchColumn();
$kpi_completed = (int)$db->query("SELECT COUNT(*) FROM service_bookings WHERE status = 'completed'")->fetchColumn();

// Include FullCalendar CDN and schedule assets
$extra_head = '
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
  <link rel="stylesheet" href="schedule.css?v=' . (file_exists(__DIR__ . '/schedule.css') ? filemtime(__DIR__ . '/schedule.css') : time()) . '">
';

$extra_scripts = '
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
  <script src="schedule.js?v=' . (file_exists(__DIR__ . '/schedule.js') ? filemtime(__DIR__ . '/schedule.js') : time()) . '" defer></script>
';

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<!-- Metrics Header -->
<div class="metrics-grid">
  <div class="metric-card">
    <div class="metric-details">
      <h3>Total Appointments</h3>
      <div class="metric-value"><?php echo $kpi_total; ?></div>
      <div class="metric-subtext">Consultations & Turnkey Installs</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Pending Confirmation</h3>
      <div class="metric-value" style="<?php echo $kpi_pending > 0 ? 'color:#b45309;' : ''; ?>">
        <?php echo $kpi_pending; ?>
      </div>
      <div class="metric-subtext">Requires engineer assignment</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Confirmed / Scheduled</h3>
      <div class="metric-value" style="color:var(--navy-primary);"><?php echo $kpi_confirmed; ?></div>
      <div class="metric-subtext">Assigned to field team</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Completed Visits</h3>
      <div class="metric-value"><?php echo $kpi_completed; ?></div>
      <div class="metric-subtext">Audited & Commissioned</div>
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

<!-- Calendar Controls & Filters Toolbar -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
  <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
    <div>
      <select id="filter-service-type" class="form-control" style="width: auto; padding: 0.45rem 0.85rem;">
        <option value="">All Service Types</option>
        <option value="consultation">Commercial Consultation</option>
        <option value="installation">Professional Installation</option>
        <option value="maintenance">Site Maintenance</option>
      </select>
    </div>
    <div>
      <select id="filter-status" class="form-control" style="width: auto; padding: 0.45rem 0.85rem;">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="confirmed">Confirmed</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>
  </div>

  <div style="display:flex; gap:0.5rem;">
    <a href="bookings.php" class="btn btn-secondary btn-sm">
      <span>Switch to Table List View</span>
    </a>
  </div>
</div>

<!-- Calendar Card -->
<div class="card">
  <div class="card-body" style="padding: 1.5rem;">
    <div id="calendar-container"></div>
  </div>
</div>

<!-- Appointment Quick Details Modal -->
<div class="modal-overlay" id="appointment-modal">
  <div class="modal-container" style="max-width: 520px;">
    <div class="modal-header">
      <div class="modal-title">
        Appointment <span id="modal-booking-ref" style="font-family: monospace; font-size: 0.95rem;"></span>
      </div>
      <button type="button" class="modal-close" onclick="closeAdminModal('appointment-modal')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>
    <form method="POST" action="appointments.php">
      <div class="modal-body" style="font-size: 0.85rem;">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="booking_id" id="modal-booking-id" value="">
        <input type="hidden" name="redirect_source" value="index">

        <!-- Appointment Info Box -->
        <div style="background: var(--navy-subtle); padding: 1rem; border-radius: 8px; margin-bottom: 1.25rem;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
            <span style="color: var(--text-muted); font-size: 0.78rem;">Service Type:</span>
            <strong id="modal-service-type" style="color: var(--navy-primary);"></strong>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
            <span style="color: var(--text-muted); font-size: 0.78rem;">Time Slot:</span>
            <strong id="modal-time-slot"></strong>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
            <span style="color: var(--text-muted); font-size: 0.78rem;">Customer:</span>
            <strong id="modal-cust-name"></strong>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
            <span style="color: var(--text-muted); font-size: 0.78rem;">Contact:</span>
            <span><span id="modal-cust-phone"></span> &bull; <span id="modal-cust-email"></span></span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span style="color: var(--text-muted); font-size: 0.78rem;">Address:</span>
            <span id="modal-address" style="text-align: right; max-width: 250px;"></span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="modal-status-select">Appointment Status</label>
          <select name="status" id="modal-status-select" class="form-control" required>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="modal-technician-input">Assigned Technician / Lead Engineer</label>
          <input type="text" name="assigned_technician" id="modal-technician-input" class="form-control" placeholder="e.g. Engr. Mark Santos">
        </div>

        <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.5rem;">
          Site Access Notes: <em id="modal-notes"></em>
        </div>
      </div>
      <div class="modal-footer">
        <a href="#" id="modal-view-detail-link" class="btn btn-outline btn-sm" style="margin-right: auto;">
          Full Appointment Details &rarr;
        </a>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeAdminModal('appointment-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Update Booking</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
