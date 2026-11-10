<?php include __DIR__ . '/../includes/header.php';
?>

<!-- Metrics Header -->
<div class="metrics-grid">
  <div class="metric-card">
    <div class="metric-details">
      <h3>Total Personnel</h3>
      <div class="metric-value"><?php echo (int) ($kpi_total ?? 0); ?></div>
      <div class="metric-subtext">Registered staff, engineers &amp; admins</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Engineers &amp; Staff</h3>
      <div class="metric-value" style="color:#047857;"><?php echo (int) ($kpi_staff ?? 0); ?></div>
      <div class="metric-subtext">Assignable to schedules &amp; orders</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Admins &amp; Managers</h3>
      <div class="metric-value" style="color:var(--navy-primary);"><?php echo (int) ($kpi_admins ?? 0); ?></div>
      <div class="metric-subtext">System management accounts</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-details">
      <h3>Active Assignments</h3>
      <div class="metric-value" style="color:#1d4ed8;"><?php echo (int) ($kpi_assigned_projects ?? 0); ?></div>
      <div class="metric-subtext">Scheduled installations &amp; builds</div>
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

<!-- Filters, Search & Add Personnel Toolbar -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
  <div class="filter-nav" style="margin-bottom:0;">
    <a href="index.php?role=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($role_filter === 'all') ? 'active' : ''; ?>">
      All (<?php echo (int) ($kpi_total ?? 0); ?>)
    </a>
    <a href="index.php?role=staff<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($role_filter === 'staff') ? 'active' : ''; ?>">
      Staff &amp; Engineers (<?php echo (int) ($kpi_staff ?? 0); ?>)
    </a>
    <a href="index.php?role=manager<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($role_filter === 'manager') ? 'active' : ''; ?>">
      Managers
    </a>
    <a href="index.php?role=superadmin<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-pill <?php echo ($role_filter === 'superadmin') ? 'active' : ''; ?>">
      Superadmins
    </a>
  </div>

  <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap;">
    <form method="GET" action="index.php" style="display:flex; gap:0.5rem; align-items:center;">
      <input type="hidden" name="role" value="<?php echo htmlspecialchars($role_filter); ?>">
      <input type="text" name="search" class="form-control" placeholder="Search name, role, email..." value="<?php echo htmlspecialchars($search); ?>" style="width:220px; padding:0.45rem 0.85rem;">
      <button type="submit" class="btn btn-secondary btn-sm" style="padding:0.55rem 0.85rem;">Search</button>
      <?php if (!empty($search)): ?>
        <a href="index.php?role=<?php echo htmlspecialchars($role_filter); ?>" class="btn btn-outline btn-sm">Clear</a>
      <?php endif; ?>
    </form>

    <button type="button" class="btn btn-primary btn-sm" style="padding:0.55rem 0.95rem;" onclick="openStaffModal()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>Add Staff / Engineer</span>
    </button>
  </div>
</div>

<!-- Staffs & Engineers Directory Card -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <span>Staffs &amp; Engineering Personnel Directory (<?php echo count($staffs); ?>)</span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width: 60px;">ID</th>
          <th>Personnel / Engineer Name</th>
          <th>Email Address</th>
          <th style="width: 135px;">Role</th>
          <th style="width: 165px;">Assigned Projects</th>
          <th style="width: 115px;">Date Added</th>
          <th style="text-align: right; width: 140px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($staffs)): ?>
          <tr>
            <td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">
              No staff or engineer records found matching your criteria.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($staffs as $member):
            $mRole = strtolower((string) ($member['role'] ?? 'staff'));
            $roleBadge = 'badge-processing';
            $roleLabel = 'Staff / Engineer';
            if ($mRole === 'superadmin') {
                $roleBadge = 'badge-accepted';
                $roleLabel = 'Superadmin';
            } elseif ($mRole === 'manager') {
                $roleBadge = 'badge-quoted';
                $roleLabel = 'Manager';
            }
            $quoteCount = (int) ($member['quote_assignments'] ?? 0);
            $bookingCount = (int) ($member['booking_assignments'] ?? 0);
            $totalAssigned = $quoteCount + $bookingCount;
            $isCurrentUser = ((int) ($admin['id'] ?? 0)) === ((int) $member['id']);
            $staffJson = htmlspecialchars(json_encode([
                'id' => (int) $member['id'],
                'username' => (string) $member['username'],
                'email' => (string) $member['email'],
                'role' => $mRole,
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
          ?>
          <tr>
            <td style="font-family:monospace; font-weight:700; color:var(--text-muted);">
              #<?php echo (int) $member['id']; ?>
            </td>
            <td>
              <div style="display:flex; align-items:center; gap:0.65rem;">
                <div style="width:32px; height:32px; border-radius:50%; background:var(--navy-primary); color:var(--yellow-accent); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.78rem; flex-shrink:0;">
                  <?php echo strtoupper(substr((string) $member['username'], 0, 1)); ?>
                </div>
                <div>
                  <div style="font-weight:700; color:var(--navy-primary); font-size:0.82rem;">
                    <?php echo htmlspecialchars((string) $member['username']); ?>
                    <?php if ($isCurrentUser): ?>
                      <span style="font-size:0.66rem; background:#e0f2fe; color:#0369a1; padding:0.1rem 0.4rem; border-radius:4px; margin-left:0.35rem; font-weight:600;">You</span>
                    <?php endif; ?>
                  </div>
                  <div style="font-size:0.7rem; color:var(--text-muted);">
                    <?php echo $mRole === 'staff' ? 'Assignable to Commercial Builds &amp; Service Schedules' : 'Full Administrative Access'; ?>
                  </div>
                </div>
              </div>
            </td>
            <td>
              <a href="mailto:<?php echo htmlspecialchars((string) $member['email']); ?>" style="color:var(--navy-light); text-decoration:underline; font-size:0.78rem;">
                <?php echo htmlspecialchars((string) $member['email']); ?>
              </a>
            </td>
            <td>
              <span class="badge <?php echo $roleBadge; ?>" style="font-size:0.68rem; padding:0.2rem 0.55rem;">
                <?php echo htmlspecialchars($roleLabel); ?>
              </span>
            </td>
            <td>
              <?php if ($totalAssigned > 0): ?>
                <div style="font-size:0.75rem; font-weight:700; color:#047857;">
                  <?php echo $totalAssigned; ?> Active Assignment<?php echo $totalAssigned === 1 ? '' : 's'; ?>
                </div>
                <div style="font-size:0.68rem; color:var(--text-muted);">
                  <?php echo $quoteCount; ?> Quote(s) &bull; <?php echo $bookingCount; ?> Schedule(s)
                </div>
              <?php else: ?>
                <span style="font-size:0.73rem; color:var(--text-muted);">No active projects</span>
              <?php endif; ?>
            </td>
            <td style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap;">
              <?php echo !empty($member['created_at']) ? date('M j, Y', strtotime((string) $member['created_at'])) : 'N/A'; ?>
            </td>
            <td style="text-align: right; white-space:nowrap;">
              <button
                type="button"
                class="btn btn-secondary btn-sm"
                style="padding:0.25rem 0.55rem; font-size:0.72rem;"
                data-staff="<?php echo $staffJson; ?>"
                onclick="openStaffModal(this)">
                Edit
              </button>

              <?php if (!$isCurrentUser): ?>
                <form method="POST" action="action.php" style="display:inline;" onsubmit="return window.apexConfirmForm(event, { title: 'Delete Personnel', message: 'Are you sure you want to delete <?php echo htmlspecialchars(addslashes((string) $member['username']), ENT_QUOTES, 'UTF-8'); ?>? Any active project assignments for this person will be unassigned.', confirmText: 'Delete Personnel' });">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                  <input type="hidden" name="mode" value="delete">
                  <input type="hidden" name="staff_id" value="<?php echo (int) $member['id']; ?>">
                  <button type="submit" class="btn btn-danger btn-sm" style="padding:0.25rem 0.55rem; font-size:0.72rem;">
                    Delete
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create / Edit Staff & Engineer Modal -->
<div class="modal-overlay" id="staff-modal">
  <div class="modal-container" style="max-width: 500px;">
    <div class="modal-header">
      <div class="modal-title" id="staff-modal-title">Add Staff / Engineer</div>
      <button type="button" class="modal-close" onclick="closeAdminModal('staff-modal')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>
    <form method="POST" action="action.php" id="staff-modal-form">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="mode" id="staff-form-mode" value="create">
        <input type="hidden" name="staff_id" id="staff-form-id" value="">

        <div class="form-group">
          <label class="form-label" for="staff-username">Personnel / Engineer Name &amp; Designation <span style="color:#b91c1c;">*</span></label>
          <input
            type="text"
            name="username"
            id="staff-username"
            class="form-control"
            maxlength="50"
            placeholder="e.g. Engr. Mark Santos (Lead Solar Engineer)"
            required>
          <small style="color:var(--text-muted); font-size:0.73rem; margin-top:0.25rem; display:block;">
            For engineers and field technicians, include their title (e.g., <em>Engr. Name (Specialty)</em>) so it appears clearly in schedule and quote assignment lists.
          </small>
        </div>

        <div class="form-group">
          <label class="form-label" for="staff-email">Corporate Email Address <span style="color:#b91c1c;">*</span></label>
          <input
            type="email"
            name="email"
            id="staff-email"
            class="form-control"
            placeholder="e.g. engineer@apexdiurnal.com"
            required>
        </div>

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" for="staff-role">Designation / Role <span style="color:#b91c1c;">*</span></label>
          <select name="role" id="staff-role" class="form-control" required>
            <option value="staff">Staff / Field Engineer (Assignable to Projects &amp; Operations)</option>
            <option value="manager">Manager (Operations Lead)</option>
            <option value="superadmin">Superadmin (Administrator)</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeAdminModal('staff-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm" id="staff-modal-submit">Save Personnel</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

