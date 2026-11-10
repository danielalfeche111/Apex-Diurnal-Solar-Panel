/**
 * admin/assets/js/engineer-manager.js
 * 1. Synchronizes Engineer / Staff datalist suggestions across Quote Requests and Service Schedules.
 * 2. Powers the Staffs & Engineers CRUD Modal on /admin/staffs/index.php.
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
    const submitBtn = document.getElementById('staff-modal-submit');

    if (item) {
      titleEl.textContent = 'Update Staff / Engineer Details';
      modeInput.value = 'update';
      idInput.value = String(item.id);
      usernameInput.value = item.username || '';
      emailInput.value = item.email || '';
      roleSelect.value = item.role || 'staff';
      if (submitBtn) submitBtn.textContent = 'Update Details';
    } else {
      titleEl.textContent = 'Add Staff / Engineer';
      modeInput.value = 'create';
      idInput.value = '';
      usernameInput.value = '';
      emailInput.value = '';
      roleSelect.value = 'staff';
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

  document.addEventListener('DOMContentLoaded', function () {
    const engineers = window.APEX_STAFF_ENGINEERS;
    if (!Array.isArray(engineers) || engineers.length === 0) return;

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
  });
})();
