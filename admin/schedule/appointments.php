<?php
/**
 * admin/schedule/appointments.php - Appointment Detail & Technician Assignment Manager
 */

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

$db = getConnection();

// Handle POST updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        header('Location: index.php?err=' . urlencode('Security token expired.'));
        exit;
    }

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'pending');
    $technician = trim($_POST['assigned_technician'] ?? '');
    $preferredDate = trim($_POST['preferred_date'] ?? '');
    $preferredSlot = trim($_POST['preferred_time_slot'] ?? '');
    $accessNotes = trim($_POST['access_notes'] ?? '');
    $redirectSource = trim($_POST['redirect_source'] ?? 'detail');

    if ($bookingId <= 0) {
        header('Location: index.php?err=' . urlencode('Invalid booking reference.'));
        exit;
    }

    try {
        $updateSql = "
            UPDATE service_bookings 
            SET status = :st, assigned_technician = :tech, preferred_date = :pdate, preferred_time_slot = :pslot, access_notes = :notes 
            WHERE id = :id
        ";
        $stmt = $db->prepare($updateSql);
        $stmt->execute([
            ':st' => $status,
            ':tech' => !empty($technician) ? $technician : null,
            ':pdate' => !empty($preferredDate) ? $preferredDate : date('Y-m-d'),
            ':pslot' => !empty($preferredSlot) ? $preferredSlot : 'morning',
            ':notes' => $accessNotes,
            ':id' => $bookingId
        ]);

        $msg = 'Appointment updated successfully.';
        $dest = ($redirectSource === 'index') ? 'index.php?msg=' . urlencode($msg) : "appointments.php?id=$bookingId&msg=" . urlencode($msg);
        header("Location: $dest");
        exit;
    } catch (Exception $e) {
        $dest = "appointments.php?id=$bookingId&err=" . urlencode($e->getMessage());
        header("Location: $dest");
        exit;
    }
}

// GET View
$bookingId = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM service_bookings WHERE id = :id");
$stmt->execute([':id' => $bookingId]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: bookings.php?err=' . urlencode('Booking not found.'));
    exit;
}

$page_title = 'Appointment ' . $booking['booking_reference'];
$active_nav = 'schedule';

// Check linked order if present
$linkedOrder = null;
if (!empty($booking['order_id'])) {
    $stmtOrd = $db->prepare("SELECT id, order_number, total_amount, status FROM orders WHERE id = :oid");
    $stmtOrd->execute([':oid' => $booking['order_id']]);
    $linkedOrder = $stmtOrd->fetch(PDO::FETCH_ASSOC);
}

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <div style="display:flex; align-items:center; gap:0.75rem;">
      <h2 style="font-size:1.3rem; color:var(--navy-primary); font-weight:700;"><?php echo htmlspecialchars($booking['booking_reference']); ?></h2>
      <span class="badge badge-<?php echo $booking['status']; ?>" style="font-size:0.8rem; padding:0.3rem 0.8rem;">
        <?php echo ucfirst($booking['status']); ?>
      </span>
    </div>
    <p style="font-size:0.8rem; color:var(--text-muted);">
      <?php echo strtoupper($booking['service_type']); ?> Booking &bull; Submitted on <?php echo date('M j, Y - g:i A', strtotime($booking['created_at'])); ?>
    </p>
  </div>

  <div style="display:flex; gap:0.5rem;">
    <a href="index.php" class="btn btn-secondary btn-sm">&larr; Calendar View</a>
    <a href="bookings.php" class="btn btn-secondary btn-sm">&larr; Bookings List</a>
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

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.75rem;">
  <!-- Left Column: Appointment Management Form -->
  <div>
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
          <span>Appointment Scheduling & Assignment</span>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" action="appointments.php">
          <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
          <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
          <input type="hidden" name="redirect_source" value="detail">

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" for="status">Appointment Status</label>
              <select name="status" id="status" class="form-control" required>
                <option value="pending" <?php echo ($booking['status'] === 'pending') ? 'selected' : ''; ?>>Pending Confirmation</option>
                <option value="confirmed" <?php echo ($booking['status'] === 'confirmed') ? 'selected' : ''; ?>>Confirmed (Scheduled)</option>
                <option value="completed" <?php echo ($booking['status'] === 'completed') ? 'selected' : ''; ?>>Completed & Signed Off</option>
                <option value="cancelled" <?php echo ($booking['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
              </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" for="assigned_technician">Assigned Technician / Lead Engineer</label>
              <input type="text" name="assigned_technician" id="assigned_technician" class="form-control" value="<?php echo htmlspecialchars($booking['assigned_technician'] ?? ''); ?>" placeholder="e.g. Engr. Mark Santos">
            </div>
          </div>

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" for="preferred_date">Scheduled Visit Date</label>
              <input type="date" name="preferred_date" id="preferred_date" class="form-control" value="<?php echo htmlspecialchars($booking['preferred_date']); ?>" required>
            </div>

            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" for="preferred_time_slot">Time Window Slot</label>
              <select name="preferred_time_slot" id="preferred_time_slot" class="form-control" required>
                <option value="morning" <?php echo (strtolower($booking['preferred_time_slot']) === 'morning') ? 'selected' : ''; ?>>Morning (9:00 AM - 12:00 PM)</option>
                <option value="afternoon" <?php echo (strtolower($booking['preferred_time_slot']) === 'afternoon') ? 'selected' : ''; ?>>Afternoon (1:30 PM - 5:00 PM)</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="access_notes">Site Access Notes / Technician Instructions</label>
            <textarea name="access_notes" id="access_notes" class="form-control" rows="4" placeholder="Gate pass instructions, roof ladder access notes, etc."><?php echo htmlspecialchars($booking['access_notes'] ?? ''); ?></textarea>
          </div>

          <div style="text-align: right; border-top:1px solid var(--border-color); padding-top:1.25rem;">
            <button type="submit" class="btn btn-primary">Save Appointment Changes</button>
          </div>
        </form>
      </div>
    </div>

    <?php if ($linkedOrder): ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
            <span>Linked Customer Purchase Order</span>
          </div>
          <a href="../orders/view.php?id=<?php echo $linkedOrder['id']; ?>" class="btn btn-secondary btn-sm">Inspect Order &rarr;</a>
        </div>
        <div class="card-body" style="font-size:0.85rem;">
          This installation booking originated from checkout Order <strong><?php echo htmlspecialchars($linkedOrder['order_number']); ?></strong> (Total: &#8369;<?php echo number_format($linkedOrder['total_amount'], 2); ?>).
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Right Column: Customer & Location Details -->
  <div>
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          <span>Client Details</span>
        </div>
      </div>
      <div class="card-body" style="font-size:0.85rem;">
        <div style="margin-bottom:1rem;">
          <div style="font-size:0.75rem; color:var(--text-muted);">Client Name</div>
          <div style="font-weight:700; color:var(--navy-primary);"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
        </div>
        <div style="margin-bottom:1rem;">
          <div style="font-size:0.75rem; color:var(--text-muted);">Email Address</div>
          <div><a href="mailto:<?php echo htmlspecialchars($booking['customer_email']); ?>" style="color:var(--navy-light); text-decoration:underline;"><?php echo htmlspecialchars($booking['customer_email']); ?></a></div>
        </div>
        <div style="margin-bottom:1rem;">
          <div style="font-size:0.75rem; color:var(--text-muted);">Phone Contact</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars($booking['customer_phone']); ?></div>
        </div>
        <div>
          <div style="font-size:0.75rem; color:var(--text-muted);">Site Address</div>
          <div style="line-height:1.6; color:var(--text-main); margin-top:0.25rem;">
            <?php echo nl2br(htmlspecialchars($booking['address'] ?? 'No address specified.')); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
