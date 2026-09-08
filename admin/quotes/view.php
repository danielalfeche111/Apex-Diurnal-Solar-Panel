<?php
/**
 * admin/quotes/view.php - Detailed Commercial Grid Quote Inspector & Quotation Form
 */

require_once __DIR__ . '/../auth.php';
requireAdminLogin();

$quoteId = (int)($_GET['id'] ?? 0);
$db = (new Database())->getConnection();

$stmt = $db->prepare("
    SELECT q.*, u.username as quoted_by_name 
    FROM quote_requests q
    LEFT JOIN admin_users u ON q.quoted_by = u.id
    WHERE q.id = :id
");
$stmt->execute([':id' => $quoteId]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    header('Location: index.php?err=' . urlencode('Quote request not found.'));
    exit;
}

$page_title = 'Quote ' . $quote['quote_number'];
$active_nav = 'quotes';

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

$status = $quote['status'];
$badgeClass = 'badge-pending';
$statusLabel = 'Pending Review';

// Check if client has confirmed order in My Orders
$chkOrder = $db->prepare("SELECT status, installation_head, installation_date FROM orders WHERE order_number = :ordNum OR notes LIKE :qnum LIMIT 1");
$chkOrder->execute([':ordNum' => 'APD-INST-' . $quote['quote_number'], ':qnum' => '%' . $quote['quote_number'] . '%']);
$linkedOrder = $chkOrder->fetch(PDO::FETCH_ASSOC);
if ($linkedOrder && $linkedOrder['status'] === 'client_confirmed' && $status !== 'confirmed') {
    $status = 'client_confirmed';
}

if ($status === 'confirmed') {
    $badgeClass = 'badge-accepted';
    $statusLabel = 'Confirmed for Installation';
} elseif ($status === 'client_confirmed') {
    $badgeClass = 'badge-processing';
    $statusLabel = 'Confirmed by Client';
} elseif ($status === 'in_progress') {
    $badgeClass = 'badge-processing';
    $statusLabel = 'Installation In Progress';
} elseif ($status === 'completed') {
    $badgeClass = 'badge-accepted';
    $statusLabel = 'Installation Completed';
} elseif ($status === 'cancelled' || $status === 'rejected' || $status === 'expired') {
    $badgeClass = 'badge-rejected';
    $statusLabel = ucfirst($status);
} elseif ($status === 'reviewed') {
    $badgeClass = 'badge-processing';
    $statusLabel = 'Reviewed';
} elseif ($status === 'quoted') {
    $badgeClass = 'badge-quoted';
    $statusLabel = 'Quoted';
} elseif ($status === 'accepted') {
    $badgeClass = 'badge-accepted';
    $statusLabel = 'Accepted';
}

// Parse applicable discounts if stored as JSON
$discounts = [];
if (!empty($quote['applicable_discounts'])) {
    $decoded = json_decode($quote['applicable_discounts'], true);
    if (is_array($decoded)) $discounts = $decoded;
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Header Action Bar -->
<div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
  <div>
    <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.25rem;">
      <h2 style="font-size:1.4rem; color:var(--navy-primary); font-weight:700;"><?php echo htmlspecialchars($quote['quote_number']); ?></h2>
      <span class="badge <?php echo $badgeClass; ?>" style="font-size:0.82rem; padding:0.35rem 0.85rem;">
        <?php echo htmlspecialchars($statusLabel); ?>
      </span>
    </div>
    <p style="font-size:0.8rem; color:var(--text-muted);">
      Inquiry from <strong><?php echo htmlspecialchars($quote['company_name']); ?></strong> &bull; Received on <?php echo date('F j, Y - g:i A', strtotime($quote['created_at'])); ?>
    </p>
  </div>

  <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap;">
    <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Queue</a>
    
    <?php if ($status !== 'accepted' && $status !== 'confirmed'): ?>
      <form method="POST" action="convert.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to convert this accepted quote into a confirmed commercial purchase order?');">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">
        <button type="submit" class="btn btn-primary btn-sm">
          <span>Convert to Order</span>
        </button>
      </form>
    <?php endif; ?>

    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
      <span>Print Spec Sheet</span>
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

<div class="admin-grid-2col">
  <!-- Left Column: Specs, Estimates, Quotation Form -->
  <div>
    <!-- Facility Specifications Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <span>Commercial Facility Specifications</span>
        </div>
      </div>
      <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:1.25rem; margin-bottom:1.25rem; font-size:0.85rem;">
          <div>
            <div style="color:var(--text-muted); font-size:0.75rem;">Facility Classification</div>
            <div style="font-weight:700; color:var(--navy-primary);"><?php echo htmlspecialchars($quote['facility_type'] ?? 'Commercial'); ?></div>
          </div>
          <div>
            <div style="color:var(--text-muted); font-size:0.75rem;">Facility Rooftop / Land Area</div>
            <div style="font-weight:700;"><?php echo number_format($quote['facility_size']); ?> sqm</div>
          </div>
          <div>
            <div style="color:var(--text-muted); font-size:0.75rem;">Current Monthly Electricity Bill</div>
            <div style="font-weight:700; color:#047857;">&#8369;<?php echo number_format($quote['current_monthly_bill'], 2); ?></div>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 2fr; gap:1.25rem; font-size:0.85rem; border-top:1px solid var(--border-color); padding-top:1.25rem;">
          <div>
            <div style="color:var(--text-muted); font-size:0.75rem;">Target Implementation Timeline</div>
            <div style="font-weight:700; margin-top:0.2rem;">
              <span style="padding:0.25rem 0.65rem; background:#f1f5f9; border-radius:4px;"><?php echo htmlspecialchars($quote['target_timeline'] ?? 'Flexible'); ?></span>
            </div>
          </div>
          <div>
            <div style="color:var(--text-muted); font-size:0.75rem;">Project Site Address</div>
            <div style="font-weight:600; line-height:1.5; color:var(--text-main); margin-top:0.2rem;">
              <?php echo nl2br(htmlspecialchars($quote['installation_address'] ?? 'Not provided')); ?>
            </div>
          </div>
        </div>

        <?php if (!empty($quote['access_notes'])): ?>
          <div style="margin-top:1rem; padding:0.85rem; background:#f8fafc; border-radius:6px; font-size:0.82rem; color:var(--text-muted);">
            <strong>Special Site Notes:</strong> <?php echo htmlspecialchars($quote['access_notes']); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Commercial Installation Confirmation & Head Assignment -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <span>Commercial Installation Confirmation & Head Assignment</span>
        </div>
      </div>
      <div class="card-body">
        <?php if ($status === 'confirmed'): ?>
          <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
            <div>
              <div style="font-weight:700; color:#065f46; font-size:0.95rem;">Commercial Grid Installation Confirmed</div>
              <div style="font-size:0.8rem; color:#047857; margin-top:0.2rem;">
                Assigned Head: <strong><?php echo htmlspecialchars($quote['installation_head'] ?: 'Unassigned'); ?></strong>
                <?php if (!empty($quote['installation_date'])): ?>
                  &bull; Scheduled Date: <strong><?php echo date('F j, Y', strtotime($quote['installation_date'])); ?></strong>
                <?php endif; ?>
              </div>
            </div>
            <span class="badge badge-accepted" style="font-size:0.8rem; padding:0.35rem 0.85rem;">Confirmed for Installation</span>
          </div>
        <?php elseif ($status === 'client_confirmed'): ?>
          <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
            <div>
              <div style="font-weight:700; color:#1e40af; font-size:0.95rem;">&#10003; Client Confirmed Installation Request</div>
              <div style="font-size:0.8rem; color:#2563eb; margin-top:0.2rem;">
                The client has reviewed the commercial turnkey quote and confirmed their project in My Orders. Please assign the lead project engineer and confirm the deployment date below.
              </div>
            </div>
            <span class="badge badge-processing" style="font-size:0.8rem; padding:0.35rem 0.85rem;">Client Confirmed</span>
          </div>
        <?php endif; ?>

        <form method="POST" action="respond.php">
          <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
          <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" for="status">Installation Status</label>
              <select name="status" id="status" class="form-control" required>
                <option value="confirmed" <?php echo ($status === 'confirmed' || $status === 'client_confirmed' || $status === 'accepted') ? 'selected' : ''; ?>>Confirmed for Installation</option>
                <option value="new" <?php echo ($status === 'new' || $status === 'pending') ? 'selected' : ''; ?>>Pending Confirmation</option>
                <option value="in_progress" <?php echo ($status === 'in_progress') ? 'selected' : ''; ?>>Installation In Progress</option>
                <option value="completed" <?php echo ($status === 'completed') ? 'selected' : ''; ?>>Installation Completed</option>
                <option value="cancelled" <?php echo ($status === 'cancelled' || $status === 'rejected') ? 'selected' : ''; ?>>Cancelled / Declined</option>
              </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" for="installation_date">Scheduled Installation Date</label>
              <input 
                type="date" 
                name="installation_date" 
                id="installation_date" 
                class="form-control" 
                value="<?php echo htmlspecialchars($quote['installation_date'] ?? ''); ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="installation_head">Assigned Head for Installation / Lead Project Engineer <span style="color:#b91c1c;">*</span></label>
            <input 
              type="text" 
              name="installation_head" 
              id="installation_head" 
              class="form-control" 
              list="engineer_suggestions"
              value="<?php echo htmlspecialchars($quote['installation_head'] ?? ''); ?>" 
              placeholder="e.g. Engr. Mark Santos" 
              required>
            <datalist id="engineer_suggestions">
              <option value="Engr. Mark Santos (Lead Solar Engineer)">
              <option value="Engr. David Reyes (Project Lead)">
              <option value="Lead Tech Alex Cruz (Site Operations)">
              <option value="Engr. Juan Dela Cruz (Electrical Systems)">
            </datalist>
            <small style="color:var(--text-muted); font-size:0.75rem; margin-top:0.25rem; display:block;">
              Designate the lead engineer or installation head accountable for this commercial solar build.
            </small>
          </div>

          <div class="form-group">
            <label class="form-label" for="admin_notes">Engineering & Site Preparation Notes</label>
            <textarea name="admin_notes" id="admin_notes" class="form-control" rows="4" placeholder="Equipment staging notes, structural roof load clearances, high-voltage interconnect protocols, crew dispatch reminders..."><?php echo htmlspecialchars($quote['admin_notes'] ?? ''); ?></textarea>
          </div>

          <div style="text-align: right; border-top:1px solid var(--border-color); padding-top:1.25rem;">
            <button type="submit" class="btn btn-primary">Confirm Installation & Save Assignment</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Right Column: Company & Contact Information -->
  <div>
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <span>Commercial Contact Profile</span>
        </div>
      </div>
      <div class="card-body" style="font-size:0.84rem;">
        <div style="margin-bottom:1rem;">
          <div style="font-size:0.75rem; color:var(--text-muted);">Enterprise / Facility Name</div>
          <div style="font-weight:700; color:var(--navy-primary); font-size:0.95rem;"><?php echo htmlspecialchars($quote['company_name']); ?></div>
        </div>

        <div style="margin-bottom:1rem;">
          <div style="font-size:0.75rem; color:var(--text-muted);">Authorized Contact Person</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars($quote['contact_person']); ?></div>
        </div>

        <div style="margin-bottom:1rem;">
          <div style="font-size:0.75rem; color:var(--text-muted);">Corporate Email</div>
          <div><a href="mailto:<?php echo htmlspecialchars($quote['email']); ?>" style="color:var(--navy-light); text-decoration:underline;"><?php echo htmlspecialchars($quote['email']); ?></a></div>
        </div>

        <div style="margin-bottom:1.25rem;">
          <div style="font-size:0.75rem; color:var(--text-muted);">Telephone / Mobile</div>
          <div style="font-weight:600;"><?php echo htmlspecialchars($quote['phone']); ?></div>
        </div>

        <div style="border-top:1px solid var(--border-color); padding-top:1rem;">
          <div style="font-size:0.75rem; color:var(--text-muted);">Assigned Installation Head</div>
          <div style="font-weight:700; color:var(--navy-primary); font-size:0.9rem; margin-top:0.2rem;">
            <?php if (!empty($quote['installation_head'])): ?>
              <span style="color:#047857;">&#10003; <?php echo htmlspecialchars($quote['installation_head']); ?></span>
            <?php else: ?>
              <span style="color:#b45309;">Unassigned</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($quote['installation_date'])): ?>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">
              Scheduled: <strong><?php echo date('F j, Y', strtotime($quote['installation_date'])); ?></strong>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($quote['quoted_by_name'])): ?>
          <div style="border-top:1px solid var(--border-color); padding-top:1rem; margin-top:1rem;">
            <div style="font-size:0.75rem; color:var(--text-muted);">Confirmed / Updated By</div>
            <div style="font-weight:600;"><?php echo htmlspecialchars($quote['quoted_by_name']); ?></div>
            <div style="font-size:0.72rem; color:var(--text-muted);"><?php echo date('M j, Y - g:i A', strtotime($quote['quoted_at'])); ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
