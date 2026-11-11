<?php include __DIR__ . '/../includes/header.php';
$prof = is_array($profile ?? null) ? $profile : [];
$profId = (int) ($prof['id'] ?? ($admin['id'] ?? 0));
$profName = (string) ($prof['username'] ?? ($admin['username'] ?? 'Admin'));
$profEmail = (string) ($prof['email'] ?? ($admin['email'] ?? ''));
$profRole = strtolower((string) ($prof['role'] ?? ($admin['role'] ?? 'staff')));
$isStaffRole = isset($is_staff) ? (bool) $is_staff : ($profRole === 'staff');

$roleBadge = 'badge-processing';
$roleLabel = 'Staff / Field Engineer';
if ($profRole === 'superadmin') {
    $roleBadge = 'badge-accepted';
    $roleLabel = 'Superadmin (Administrator)';
} elseif ($profRole === 'manager' || $profRole === 'admin') {
    $roleBadge = 'badge-quoted';
    $roleLabel = ucfirst($profRole);
}

$quoteAssignments = (int) ($prof['quote_assignments'] ?? 0);
$bookingAssignments = (int) ($prof['booking_assignments'] ?? 0);
$createdRecords = (int) ($prof['created_records'] ?? 0);
?>

<?php if (!empty($msg)): ?>
  <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.85rem; display:flex; align-items:center; gap:0.5rem;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
    <span><?php echo htmlspecialchars((string) $msg); ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($err)): ?>
  <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.85rem; display:flex; align-items:center; gap:0.5rem;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
    <span><?php echo htmlspecialchars((string) $err); ?></span>
  </div>
<?php endif; ?>

<div class="admin-grid-2col">
  <!-- Left Column: Own Account Profile -->
  <div>
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          <span>Account Profile Details</span>
        </div>
        <span class="badge <?php echo $roleBadge; ?>"><?php echo htmlspecialchars($roleLabel); ?></span>
      </div>

      <div class="card-body">
        <div style="display:flex; align-items:center; gap:1rem; padding-bottom:1.25rem; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color);">
          <div style="width:56px; height:56px; border-radius:50%; background:var(--navy-primary); color:var(--yellow-accent); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.35rem; flex-shrink:0;">
            <?php echo strtoupper(substr($profName, 0, 1)); ?>
          </div>
          <div>
            <div style="font-size:1.1rem; font-weight:700; color:var(--navy-primary);">
              <?php echo htmlspecialchars($profName); ?>
            </div>
            <div style="font-size:0.82rem; color:var(--text-muted); margin-top:0.15rem;">
              <?php echo $profEmail !== '' ? htmlspecialchars($profEmail) : 'No corporate email linked'; ?>
            </div>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
          <div style="background:#f8fafc; padding:0.85rem 1rem; border-radius:8px; border:1px solid var(--border-color);">
            <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); font-weight:600;">Account ID</div>
            <div style="font-size:0.95rem; font-weight:700; color:var(--navy-primary); font-family:monospace; margin-top:0.2rem;">
              #<?php echo $profId; ?>
            </div>
          </div>

          <div style="background:#f8fafc; padding:0.85rem 1rem; border-radius:8px; border:1px solid var(--border-color);">
            <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); font-weight:600;">System Role</div>
            <div style="font-size:0.95rem; font-weight:700; color:var(--navy-primary); margin-top:0.2rem;">
              <?php echo htmlspecialchars(ucfirst($profRole)); ?>
            </div>
          </div>

          <div style="background:#f8fafc; padding:0.85rem 1rem; border-radius:8px; border:1px solid var(--border-color);">
            <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); font-weight:600;">Date Created</div>
            <div style="font-size:0.88rem; font-weight:600; color:var(--text-main); margin-top:0.2rem;">
              <?php echo !empty($prof['created_at']) ? date('M j, Y', strtotime((string) $prof['created_at'])) : 'Active Account'; ?>
            </div>
          </div>

          <div style="background:#f8fafc; padding:0.85rem 1rem; border-radius:8px; border:1px solid var(--border-color);">
            <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); font-weight:600;">Own Catalog Records</div>
            <div style="font-size:0.88rem; font-weight:700; color:#047857; margin-top:0.2rem;">
              <?php echo $createdRecords; ?> Product(s) Created
            </div>
          </div>
        </div>

        <div style="margin-top:1rem; background:#f8fafc; padding:0.95rem 1rem; border-radius:8px; border:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
          <div>
            <div style="font-size:0.78rem; font-weight:700; color:var(--navy-primary);">Active Field &amp; Project Assignments</div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.15rem;">
              <?php echo $quoteAssignments; ?> Commercial Quote(s) &bull; <?php echo $bookingAssignments; ?> Service Schedule(s)
            </div>
          </div>
          <span class="badge <?php echo ($quoteAssignments + $bookingAssignments) > 0 ? 'badge-accepted' : 'badge-pending'; ?>">
            <?php echo ($quoteAssignments + $bookingAssignments); ?> Active
          </span>
        </div>

        <div style="margin-top:1.25rem; padding-top:1rem; border-top:1px solid var(--border-color); display:flex; justify-content:flex-end;">
          <a href="../logout.php" class="btn btn-danger btn-sm">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            <span>Log Out of Account</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Change Own Password (Staff Only) -->
  <div>
    <?php if ($isStaffRole): ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <span>Change Own Password</span>
          </div>
        </div>
        <div class="card-body">
          <form method="POST" action="action.php" id="staff-change-password-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="form-group">
              <label class="form-label" for="current_password">Current Password <span style="color:#b91c1c;">*</span></label>
              <input
                type="password"
                name="current_password"
                id="current_password"
                class="form-control"
                required
                autocomplete="current-password"
                placeholder="Enter your current password">
            </div>

            <div class="form-group">
              <label class="form-label" for="new_password">New Password <span style="color:#b91c1c;">*</span></label>
              <input
                type="password"
                name="new_password"
                id="new_password"
                class="form-control"
                minlength="6"
                required
                autocomplete="new-password"
                placeholder="Minimum 6 characters">
            </div>

            <div class="form-group">
              <label class="form-label" for="confirm_password">Confirm New Password <span style="color:#b91c1c;">*</span></label>
              <input
                type="password"
                name="confirm_password"
                id="confirm_password"
                class="form-control"
                minlength="6"
                required
                autocomplete="new-password"
                placeholder="Re-enter your new password">
            </div>

            <div style="display:flex; justify-content:flex-end; margin-top:1.25rem;">
              <button type="submit" class="btn btn-primary btn-sm" style="padding:0.6rem 1.25rem;">
                Update Password
              </button>
            </div>
          </form>
        </div>
      </div>
    <?php else: ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <span>Credential &amp; Staff Security Management</span>
          </div>
        </div>
        <div class="card-body">
          <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.6; margin-bottom:1.25rem;">
            Self-service password updates on this profile page are enabled exclusively for <strong>Staff / Engineer</strong> accounts. As an administrator, you can manage staff accounts and reset staff passwords from the Staffs &amp; Engineers directory.
          </p>
          <a href="../staffs/index.php" class="btn btn-primary btn-sm">
            Manage Staff Accounts &rarr;
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

