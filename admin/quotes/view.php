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
if ($status === 'reviewed') $badgeClass = 'badge-processing';
elseif ($status === 'quoted') $badgeClass = 'badge-quoted';
elseif ($status === 'accepted') $badgeClass = 'badge-accepted';
elseif ($status === 'rejected' || $status === 'expired') $badgeClass = 'badge-rejected';

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
        <?php echo ucfirst($status); ?>
      </span>
    </div>
    <p style="font-size:0.8rem; color:var(--text-muted);">
      Inquiry from <strong><?php echo htmlspecialchars($quote['company_name']); ?></strong> &bull; Received on <?php echo date('F j, Y - g:i A', strtotime($quote['created_at'])); ?>
    </p>
  </div>

  <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap;">
    <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Queue</a>
    
    <?php if ($status !== 'accepted'): ?>
      <form method="POST" action="convert.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to convert this accepted quote into a confirmed commercial purchase order?');">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">
        <button type="submit" class="btn btn-primary btn-sm">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Convert to Order</span>
        </button>
      </form>
    <?php endif; ?>

    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
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
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
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

    <!-- Solar Energy Engineering Estimates -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
          <span>Photovoltaic Solar Sizing & ROI Projections</span>
        </div>
      </div>
      <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:1rem; font-size:0.85rem; text-align:center;">
          <div style="background:var(--navy-subtle); padding:1rem; border-radius:8px;">
            <div style="color:var(--text-muted); font-size:0.72rem; text-transform:uppercase;">System Sizing</div>
            <div style="font-size:1.35rem; font-weight:700; color:var(--navy-primary); margin-top:0.25rem;">
              <?php echo $quote['estimated_system_size'] ? $quote['estimated_system_size'] . ' kW' : 'Custom'; ?>
            </div>
            <div style="font-size:0.7rem; color:var(--text-muted);">PV array capacity</div>
          </div>

          <div style="background:#ecfdf5; padding:1rem; border-radius:8px;">
            <div style="color:#065f46; font-size:0.72rem; text-transform:uppercase;">Estimated Cost</div>
            <div style="font-size:1.35rem; font-weight:700; color:#047857; margin-top:0.25rem;">
              &#8369;<?php echo $quote['estimated_installation_cost'] ? number_format($quote['estimated_installation_cost'], 2) : 'TBD'; ?>
            </div>
            <div style="font-size:0.7rem; color:#065f46;">Turnkey equipment</div>
          </div>

          <div style="background:#eff6ff; padding:1rem; border-radius:8px;">
            <div style="color:#1e40af; font-size:0.72rem; text-transform:uppercase;">Annual Savings</div>
            <div style="font-size:1.35rem; font-weight:700; color:#1d4ed8; margin-top:0.25rem;">
              &#8369;<?php echo $quote['estimated_annual_savings'] ? number_format($quote['estimated_annual_savings'], 2) : 'TBD'; ?>
            </div>
            <div style="font-size:0.7rem; color:#1e40af;">Per year electricity offset</div>
          </div>

          <div style="background:#fffbeb; padding:1rem; border-radius:8px;">
            <div style="color:#92400e; font-size:0.72rem; text-transform:uppercase;">Payback Period</div>
            <div style="font-size:1.35rem; font-weight:700; color:#b45309; margin-top:0.25rem;">
              <?php echo $quote['estimated_payback_period'] ? $quote['estimated_payback_period'] . ' yrs' : 'TBD'; ?>
            </div>
            <div style="font-size:0.7rem; color:#92400e;">CapEx amortization</div>
          </div>
        </div>

        <?php if (!empty($discounts)): ?>
          <div style="margin-top:1.25rem; font-size:0.8rem; border-top:1px solid var(--border-color); padding-top:1rem;">
            <strong>Applied Commercial Incentives & Discounts:</strong>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-top:0.5rem;">
              <?php foreach ($discounts as $d): ?>
                <span style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; border-radius:9999px; padding:0.25rem 0.75rem; font-weight:600; font-size:0.75rem;">
                  &check; <?php echo htmlspecialchars(is_array($d) ? ($d['name'] ?? json_encode($d)) : $d); ?>
                </span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Official Quotation Generator Form -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
          <span>Commercial Quotation Proposal & Response</span>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" action="respond.php">
          <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
          <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" for="quoted_amount">Formal Proposal Price (₱ / PHP)</label>
              <input 
                type="number" 
                step="0.01" 
                name="quoted_amount" 
                id="quoted_amount" 
                class="form-control" 
                value="<?php echo htmlspecialchars($quote['quoted_amount'] ?? $quote['estimated_installation_cost'] ?? ''); ?>" 
                placeholder="e.g. 850000.00" 
                required>
            </div>

            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" for="status">Ticket Lifecycle Status</label>
              <select name="status" id="status" class="form-control" required>
                <option value="new" <?php echo ($status === 'new') ? 'selected' : ''; ?>>New (Unreviewed)</option>
                <option value="reviewed" <?php echo ($status === 'reviewed') ? 'selected' : ''; ?>>Reviewed by Engineering</option>
                <option value="quoted" <?php echo ($status === 'quoted') ? 'selected' : ''; ?>>Quoted (Formal Proposal Sent)</option>
                <option value="accepted" <?php echo ($status === 'accepted') ? 'selected' : ''; ?>>Accepted by Client</option>
                <option value="rejected" <?php echo ($status === 'rejected') ? 'selected' : ''; ?>>Rejected / Declined</option>
                <option value="expired" <?php echo ($status === 'expired') ? 'selected' : ''; ?>>Expired</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="admin_notes">Internal Engineering Notes / Quotation Terms</label>
            <textarea name="admin_notes" id="admin_notes" class="form-control" rows="4" placeholder="Terms of payment, equipment warranty specifications, solar module model numbers..."><?php echo htmlspecialchars($quote['admin_notes'] ?? ''); ?></textarea>
          </div>

          <div style="text-align: right; border-top:1px solid var(--border-color); padding-top:1.25rem;">
            <button type="submit" class="btn btn-primary">Save Proposal & Update Status</button>
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
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
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

        <?php if (!empty($quote['quoted_by_name'])): ?>
          <div style="border-top:1px solid var(--border-color); padding-top:1rem;">
            <div style="font-size:0.75rem; color:var(--text-muted);">Quoted By</div>
            <div style="font-weight:600;"><?php echo htmlspecialchars($quote['quoted_by_name']); ?></div>
            <div style="font-size:0.72rem; color:var(--text-muted);"><?php echo date('M j, Y - g:i A', strtotime($quote['quoted_at'])); ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
