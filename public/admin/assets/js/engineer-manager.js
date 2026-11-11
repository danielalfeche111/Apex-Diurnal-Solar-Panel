/**
 * admin/assets/js/engineer-manager.js
 * 1. Synchronizes Engineer / Staff datalist suggestions across Quote Requests and Service Schedules.
 * 2. Powers the Staffs & Engineers CRUD Modal on /admin/staffs/index.php.
 * 3. Provides Assigned Task CRUD (Add, Edit/Update, Mark Done, Delete) controls for Staff and Admin.
 */
(function () {
  'use strict';

  window.openStaffModal = function (button) {
    const form = document.getElementById('staff-modal-form');
    if (!form) return;
    form.reset();

    const item = button && button.dataset.staff ? JSON.parse(button.dataset.staff) : null;
    const titleEl = document.getElementById('staff-modal-title');
    const modeInput = document.getElementById('staff-form-mode');
    const idInput = document.getElementById('staff-form-id');
    const usernameInput = document.getElementById('staff-username');
    const emailInput = document.getElementById('staff-email');
    const roleSelect = document.getElementById('staff-role');
    const passwordInput = document.getElementById('staff-password');
    const passwordLabel = document.getElementById('staff-password-label');
    const passwordHint = document.getElementById('staff-password-hint');
    const submitBtn = document.getElementById('staff-modal-submit');

    if (passwordInput) {
      passwordInput.value = '';
    }

    if (item) {
      titleEl.textContent = 'Update Staff / Engineer Details';
      modeInput.value = 'update';
      idInput.value = String(item.id);
      usernameInput.value = item.username || '';
      emailInput.value = item.email || '';
      roleSelect.value = item.role || 'staff';
      if (passwordLabel) passwordLabel.textContent = 'Reset Password (Optional)';
      if (passwordInput) passwordInput.placeholder = 'Leave blank to keep current password';
      if (passwordHint) passwordHint.textContent = 'Enter a new password (min. 6 characters) only if you want to reset this account\'s password.';
      if (submitBtn) submitBtn.textContent = 'Update Details';
    } else {
      titleEl.textContent = 'Add Staff / Engineer';
      modeInput.value = 'create';
      idInput.value = '';
      usernameInput.value = '';
      emailInput.value = '';
      roleSelect.value = 'staff';
      if (passwordLabel) passwordLabel.textContent = 'Initial Account Password';
      if (passwordInput) passwordInput.placeholder = 'Enter password (min. 6 characters)';
      if (passwordHint) passwordHint.innerHTML = 'Set initial login password (leave blank to use default <code>staff123</code>).';
      if (submitBtn) submitBtn.textContent = 'Add Personnel';
    }

    if (typeof window.openAdminModal === 'function') {
      window.openAdminModal('staff-modal');
    } else {
      const modal = document.getElementById('staff-modal');
      if (modal) modal.classList.add('open');
    }

    setTimeout(function () {
      usernameInput.focus();
    }, 50);
  };

  function getCsrfToken() {
    if (window.APEX_CURRENT_ADMIN && window.APEX_CURRENT_ADMIN.csrfToken) {
      return window.APEX_CURRENT_ADMIN.csrfToken;
    }
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
  }

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function injectTaskCreateModal(currentAdmin, csrfToken) {
    if (document.getElementById('staff-task-modal')) return;
    const isStaff = currentAdmin && currentAdmin.role === 'staff';
    const assignedValue = isStaff ? (currentAdmin.username || '') : '';
    const today = new Date().toISOString().slice(0, 10);

    const wrapper = document.createElement('div');
    wrapper.id = 'staff-task-modal';
    wrapper.className = 'modal-backdrop';
    wrapper.innerHTML =
      '<div class="modal-panel" style="max-width: 560px;">' +
        '<div class="modal-header">' +
          '<h3 class="modal-title">Add Assigned Service Task</h3>' +
          '<button type="button" class="modal-close" onclick="closeAdminModal(\'staff-task-modal\')">&times;</button>' +
        '</div>' +
        '<form method="POST" action="appointments.php">' +
          '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
          '<input type="hidden" name="mode" value="create">' +
          '<div class="modal-body">' +
            '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">' +
              '<div class="form-group">' +
                '<label class="form-label">Customer / Client Name <span style="color:#ef4444;">*</span></label>' +
                '<input type="text" name="customer_name" class="form-control" required placeholder="e.g. Roberto Villanueva">' +
              '</div>' +
              '<div class="form-group">' +
                '<label class="form-label">Contact Phone</label>' +
                '<input type="text" name="customer_phone" class="form-control" placeholder="e.g. +63 917 555 0140">' +
              '</div>' +
            '</div>' +
            '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">' +
              '<div class="form-group">' +
                '<label class="form-label">Client Email</label>' +
                '<input type="email" name="customer_email" class="form-control" placeholder="client@example.com">' +
              '</div>' +
              '<div class="form-group">' +
                '<label class="form-label">Service Type</label>' +
                '<select name="service_type" class="form-control">' +
                  '<option value="installation">Installation</option>' +
                  '<option value="maintenance">Maintenance</option>' +
                  '<option value="consultation">Consultation</option>' +
                '</select>' +
              '</div>' +
            '</div>' +
            '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">' +
              '<div class="form-group">' +
                '<label class="form-label">Scheduled Date <span style="color:#ef4444;">*</span></label>' +
                '<input type="date" name="preferred_date" class="form-control" required value="' + escapeHtml(today) + '">' +
              '</div>' +
              '<div class="form-group">' +
                '<label class="form-label">Time Window Slot</label>' +
                '<select name="preferred_time_slot" class="form-control">' +
                  '<option value="8:00 AM - 10:00 AM">8:00 AM - 10:00 AM (Morning)</option>' +
                  '<option value="10:00 AM - 12:00 PM">10:00 AM - 12:00 PM (Morning)</option>' +
                  '<option value="1:00 PM - 3:00 PM">1:00 PM - 3:00 PM (Afternoon)</option>' +
                  '<option value="3:00 PM - 5:00 PM">3:00 PM - 5:00 PM (Afternoon)</option>' +
                '</select>' +
              '</div>' +
            '</div>' +
            '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">' +
              '<div class="form-group">' +
                '<label class="form-label">Task Status</label>' +
                '<select name="status" class="form-control">' +
                  '<option value="confirmed">Confirmed (Scheduled)</option>' +
                  '<option value="pending">Pending Confirmation</option>' +
                  '<option value="completed">Completed / Done</option>' +
                '</select>' +
              '</div>' +
              '<div class="form-group">' +
                '<label class="form-label">Assigned Staff / Engineer</label>' +
                '<input type="text" name="assigned_technician" class="form-control" list="engineer_suggestions" value="' + escapeHtml(assignedValue) + '" ' + (isStaff ? 'readonly style="background:#f8fafc;"' : '') + '>' +
              '</div>' +
            '</div>' +
            '<div class="form-group">' +
              '<label class="form-label">Site Address <span style="color:#ef4444;">*</span></label>' +
              '<input type="text" name="address" class="form-control" required placeholder="Full project or residential site address">' +
            '</div>' +
            '<div class="form-group" style="margin-bottom:0;">' +
              '<label class="form-label">Task / Completion Notes</label>' +
              '<textarea name="access_notes" class="form-control" rows="2" placeholder="Site instructions or work scope notes..."></textarea>' +
            '</div>' +
          '</div>' +
          '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" onclick="closeAdminModal(\'staff-task-modal\')">Cancel</button>' +
            '<button type="submit" class="btn btn-primary">Create Assigned Task</button>' +
          '</div>' +
        '</form>' +
      '</div>';
    document.body.appendChild(wrapper);
  }

  document.addEventListener('DOMContentLoaded', function () {
    const engineers = window.APEX_STAFF_ENGINEERS;
    const currentAdmin = window.APEX_CURRENT_ADMIN || { role: 'staff', username: '' };
    const isStaff = currentAdmin.role === 'staff';
    const csrfToken = getCsrfToken();
    const path = window.location.pathname || '';

    if (Array.isArray(engineers) && engineers.length > 0) {
      let datalist = document.getElementById('engineer_suggestions');
      if (!datalist) {
        datalist = document.createElement('datalist');
        datalist.id = 'engineer_suggestions';
        document.body.appendChild(datalist);
      }

      datalist.innerHTML = '';
      engineers.forEach(function (name) {
        const opt = document.createElement('option');
        opt.value = name;
        datalist.appendChild(opt);
      });

      const techInput = document.getElementById('assigned_technician');
      if (techInput && !techInput.getAttribute('list')) {
        techInput.setAttribute('list', 'engineer_suggestions');
      }

      const modalTechInput = document.getElementById('modal-technician-input');
      if (modalTechInput && !modalTechInput.getAttribute('list')) {
        modalTechInput.setAttribute('list', 'engineer_suggestions');
      }
    }

    if (isStaff) {
      ['assigned_technician', 'modal-technician-input', 'installation_head'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
          el.readOnly = true;
          el.style.backgroundColor = '#f8fafc';
          if (!el.value && currentAdmin.username) {
            el.value = currentAdmin.username;
          }
        }
      });
    }

    // 1. Schedule Bookings List & Calendar: Add Task Modal + Quick Mark Done & Delete buttons
    if (path.indexOf('/admin/schedule/') !== -1) {
      injectTaskCreateModal(currentAdmin, csrfToken);

      const switchBtn = document.querySelector('a.btn[href="index.php"], a.btn[href="bookings.php"]');
      if (switchBtn && switchBtn.parentElement && !document.getElementById('open-create-task-btn')) {
        const bar = document.createElement('div');
        bar.style.display = 'flex';
        bar.style.gap = '0.5rem';
        bar.style.alignItems = 'center';
        switchBtn.parentNode.insertBefore(bar, switchBtn);
        bar.appendChild(switchBtn);

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.id = 'open-create-task-btn';
        addBtn.className = 'btn btn-primary btn-sm';
        addBtn.innerHTML = '+ Add Assigned Task';
        addBtn.addEventListener('click', function () {
          if (typeof window.openAdminModal === 'function') {
            window.openAdminModal('staff-task-modal');
          } else {
            const modal = document.getElementById('staff-task-modal');
            if (modal) modal.classList.add('open');
          }
        });
        bar.appendChild(addBtn);
      }
    }

    if (path.indexOf('/admin/schedule/bookings.php') !== -1) {
      const rows = document.querySelectorAll('table.admin-table tbody tr');
      rows.forEach(function (row) {
        const manageLink = row.querySelector('a[href*="appointments.php?id="]');
        if (!manageLink) return;
        const match = manageLink.getAttribute('href').match(/id=(\d+)/);
        if (!match) return;
        const bookingId = match[1];
        const refEl = row.querySelector('td:first-child a');
        const refText = refEl ? refEl.textContent.trim() : '#' + bookingId;
        const badgeEl = row.querySelector('.badge');
        const isCompleted = badgeEl && badgeEl.textContent.trim().toLowerCase() === 'completed';
        const actionCell = manageLink.parentElement;
        actionCell.style.width = 'auto';

        const container = document.createElement('div');
        container.style.display = 'inline-flex';
        container.style.gap = '0.35rem';
        container.style.alignItems = 'center';
        manageLink.parentNode.insertBefore(container, manageLink);
        container.appendChild(manageLink);

        if (!isCompleted) {
          const doneForm = document.createElement('form');
          doneForm.method = 'POST';
          doneForm.action = 'appointments.php';
          doneForm.style.display = 'inline';
          doneForm.innerHTML =
            '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
            '<input type="hidden" name="booking_id" value="' + escapeHtml(bookingId) + '">' +
            '<input type="hidden" name="mode" value="complete">' +
            '<input type="hidden" name="redirect_source" value="bookings">' +
            '<button type="submit" class="btn btn-primary btn-sm" style="padding:0.25rem 0.5rem; font-size:0.7rem; background:#059669; border-color:#059669; color:#fff;">Mark Done</button>';
          container.appendChild(doneForm);
        }

        const delForm = document.createElement('form');
        delForm.method = 'POST';
        delForm.action = 'appointments.php';
        delForm.style.display = 'inline';
        delForm.onsubmit = function (event) {
          if (typeof window.apexConfirmForm === 'function') {
            return window.apexConfirmForm(event, {
              title: 'Delete Assigned Task',
              message: 'Are you sure you want to permanently delete task ' + refText + '?',
              confirmText: 'Delete Task',
              danger: true
            });
          }
          return window.confirm('Delete task ' + refText + '?');
        };
        delForm.innerHTML =
          '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
          '<input type="hidden" name="booking_id" value="' + escapeHtml(bookingId) + '">' +
          '<input type="hidden" name="mode" value="delete">' +
          '<button type="submit" class="btn btn-danger btn-sm" style="padding:0.25rem 0.5rem; font-size:0.7rem;">Delete</button>';
        container.appendChild(delForm);
      });
    }

    // 2. Schedule Appointment Detail Page: Add Mark Done + Delete buttons
    if (path.indexOf('/admin/schedule/appointments.php') !== -1) {
      const form = document.querySelector('form[action="appointments.php"]');
      if (form) {
        const bookingIdInput = form.querySelector('input[name="booking_id"]');
        const statusSelect = form.querySelector('select[name="status"]');
        const submitBtn = form.querySelector('button[type="submit"]');
        if (bookingIdInput && submitBtn && submitBtn.parentElement) {
          const bookingId = bookingIdInput.value;
          const footer = submitBtn.parentElement;
          footer.style.display = 'flex';
          footer.style.justifyContent = 'space-between';
          footer.style.alignItems = 'center';
          footer.style.flexWrap = 'wrap';
          footer.style.gap = '0.5rem';

          const leftGroup = document.createElement('div');
          leftGroup.style.display = 'flex';
          leftGroup.style.gap = '0.5rem';

          const delBtn = document.createElement('button');
          delBtn.type = 'button';
          delBtn.className = 'btn btn-danger btn-sm';
          delBtn.textContent = 'Delete Task';
          delBtn.addEventListener('click', function (event) {
            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.action = 'appointments.php';
            tempForm.innerHTML =
              '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
              '<input type="hidden" name="booking_id" value="' + escapeHtml(bookingId) + '">' +
              '<input type="hidden" name="mode" value="delete">';
            document.body.appendChild(tempForm);
            if (typeof window.apexConfirmForm === 'function') {
              const fakeEvent = { preventDefault: function () {}, target: tempForm, currentTarget: tempForm };
              if (window.apexConfirmForm(fakeEvent, {
                title: 'Delete Assigned Task',
                message: 'Are you sure you want to delete this assigned service appointment?',
                confirmText: 'Delete Task',
                danger: true
              })) {
                tempForm.submit();
              }
            } else if (window.confirm('Delete this assigned service appointment?')) {
              tempForm.submit();
            }
          });
          leftGroup.appendChild(delBtn);

          const rightGroup = document.createElement('div');
          rightGroup.style.display = 'flex';
          rightGroup.style.gap = '0.5rem';

          if (statusSelect && statusSelect.value !== 'completed') {
            const doneBtn = document.createElement('button');
            doneBtn.type = 'button';
            doneBtn.className = 'btn btn-secondary';
            doneBtn.style.background = '#059669';
            doneBtn.style.borderColor = '#059669';
            doneBtn.style.color = '#fff';
            doneBtn.textContent = '✓ Mark Task Done';
            doneBtn.addEventListener('click', function () {
              statusSelect.value = 'completed';
              form.submit();
            });
            rightGroup.appendChild(doneBtn);
          }

          rightGroup.appendChild(submitBtn);
          footer.innerHTML = '';
          footer.appendChild(leftGroup);
          footer.appendChild(rightGroup);
        }
      }
    }

    // 3. Quotes List & Detail Page: Add Mark Done + Delete buttons
    if (path.indexOf('/admin/quotes/index.php') !== -1 || (path.endsWith('/admin/quotes/') || path.endsWith('/admin/quotes'))) {
      const rows = document.querySelectorAll('table.admin-table tbody tr');
      rows.forEach(function (row) {
        const manageLink = row.querySelector('a[href*="view.php?id="]');
        if (!manageLink) return;
        const match = manageLink.getAttribute('href').match(/id=(\d+)/);
        if (!match) return;
        const quoteId = match[1];
        const refEl = row.querySelector('td:first-child a');
        const refText = refEl ? refEl.textContent.trim() : '#' + quoteId;
        const badgeEl = row.querySelector('.badge');
        const isCompleted = badgeEl && badgeEl.textContent.trim().toLowerCase() === 'completed';
        const actionCell = manageLink.parentElement;
        actionCell.style.width = 'auto';

        const container = document.createElement('div');
        container.style.display = 'inline-flex';
        container.style.gap = '0.35rem';
        container.style.alignItems = 'center';
        manageLink.parentNode.insertBefore(container, manageLink);
        container.appendChild(manageLink);

        if (!isCompleted) {
          const doneForm = document.createElement('form');
          doneForm.method = 'POST';
          doneForm.action = 'respond.php';
          doneForm.style.display = 'inline';
          doneForm.innerHTML =
            '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
            '<input type="hidden" name="quote_id" value="' + escapeHtml(quoteId) + '">' +
            '<input type="hidden" name="mode" value="complete">' +
            '<input type="hidden" name="redirect_source" value="index">' +
            '<button type="submit" class="btn btn-primary btn-sm" style="padding:0.25rem 0.5rem; font-size:0.7rem; background:#059669; border-color:#059669; color:#fff;">Mark Done</button>';
          container.appendChild(doneForm);
        }

        const delForm = document.createElement('form');
        delForm.method = 'POST';
        delForm.action = 'respond.php';
        delForm.style.display = 'inline';
        delForm.onsubmit = function (event) {
          if (typeof window.apexConfirmForm === 'function') {
            return window.apexConfirmForm(event, {
              title: 'Delete Assigned Quote',
              message: 'Are you sure you want to permanently delete quote ' + refText + '?',
              confirmText: 'Delete Quote',
              danger: true
            });
          }
          return window.confirm('Delete quote ' + refText + '?');
        };
        delForm.innerHTML =
          '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
          '<input type="hidden" name="quote_id" value="' + escapeHtml(quoteId) + '">' +
          '<input type="hidden" name="mode" value="delete">' +
          '<button type="submit" class="btn btn-danger btn-sm" style="padding:0.25rem 0.5rem; font-size:0.7rem;">Delete</button>';
        container.appendChild(delForm);
      });
    }

    if (path.indexOf('/admin/quotes/view.php') !== -1) {
      const form = document.querySelector('form[action="respond.php"]');
      if (form) {
        const quoteIdInput = form.querySelector('input[name="quote_id"]');
        const statusSelect = form.querySelector('select[name="status"]');
        const submitBtn = form.querySelector('button[type="submit"]');
        if (quoteIdInput && submitBtn && submitBtn.parentElement) {
          const quoteId = quoteIdInput.value;
          const footer = submitBtn.parentElement;
          footer.style.display = 'flex';
          footer.style.justifyContent = 'space-between';
          footer.style.alignItems = 'center';
          footer.style.flexWrap = 'wrap';
          footer.style.gap = '0.5rem';

          const leftGroup = document.createElement('div');
          leftGroup.style.display = 'flex';
          leftGroup.style.gap = '0.5rem';

          const delBtn = document.createElement('button');
          delBtn.type = 'button';
          delBtn.className = 'btn btn-danger btn-sm';
          delBtn.textContent = 'Delete Assigned Record';
          delBtn.addEventListener('click', function () {
            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.action = 'respond.php';
            tempForm.innerHTML =
              '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
              '<input type="hidden" name="quote_id" value="' + escapeHtml(quoteId) + '">' +
              '<input type="hidden" name="mode" value="delete">';
            document.body.appendChild(tempForm);
            if (typeof window.apexConfirmForm === 'function') {
              const fakeEvent = { preventDefault: function () {}, target: tempForm, currentTarget: tempForm };
              if (window.apexConfirmForm(fakeEvent, {
                title: 'Delete Assigned Quote',
                message: 'Are you sure you want to delete this installation project record?',
                confirmText: 'Delete Record',
                danger: true
              })) {
                tempForm.submit();
              }
            } else if (window.confirm('Delete this installation project record?')) {
              tempForm.submit();
            }
          });
          leftGroup.appendChild(delBtn);

          const rightGroup = document.createElement('div');
          rightGroup.style.display = 'flex';
          rightGroup.style.gap = '0.5rem';

          if (statusSelect && statusSelect.value !== 'completed') {
            const doneBtn = document.createElement('button');
            doneBtn.type = 'button';
            doneBtn.className = 'btn btn-secondary';
            doneBtn.style.background = '#059669';
            doneBtn.style.borderColor = '#059669';
            doneBtn.style.color = '#fff';
            doneBtn.textContent = '✓ Mark Installation Done';
            doneBtn.addEventListener('click', function () {
              statusSelect.value = 'completed';
              form.submit();
            });
            rightGroup.appendChild(doneBtn);
          }

          rightGroup.appendChild(submitBtn);
          footer.innerHTML = '';
          footer.appendChild(leftGroup);
          footer.appendChild(rightGroup);
        }
      }
    }
  });
})();
