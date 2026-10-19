/**
 * account/account.js - Apex Diurnal User Account & Settings JavaScript
 * Handles user dropdown interactions and settings form controls
 */

document.addEventListener('DOMContentLoaded', function () {
  const btn = document.getElementById('user-account-btn');
  const menu = document.getElementById('user-dropdown-menu');
  const dropdown = document.getElementById('user-dropdown');

  if (btn && menu) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      e.preventDefault();
      menu.classList.toggle('show');
    });

    document.addEventListener('click', function (e) {
      if (dropdown && !dropdown.contains(e.target)) {
        menu.classList.remove('show');
      }
    });
  }

  window.toggleUserDropdown = function () {
    if (menu) menu.classList.toggle('show');
  };

  // Dynamic Province -> City/Municipality dependent dropdown for Profile
  const provEl = document.getElementById('profile_province');
  const cityEl = document.getElementById('profile_city');

  if (provEl && cityEl) {
    provEl.addEventListener('change', function () {
      const selectedProv = this.value;
      const currentCity = cityEl.value;

      cityEl.innerHTML = '';
      if (!selectedProv) {
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'Select Province First';
        cityEl.appendChild(defaultOpt);
        return;
      }

      const defaultOpt = document.createElement('option');
      defaultOpt.value = '';
      defaultOpt.textContent = 'Select City / Municipality';
      cityEl.appendChild(defaultOpt);

      const cities = (window.provinceCityMap && window.provinceCityMap[selectedProv]) 
        ? window.provinceCityMap[selectedProv] 
        : [];

      cities.forEach(function (cityName) {
        const opt = document.createElement('option');
        opt.value = cityName;
        opt.textContent = cityName;
        if (cityName === currentCity) {
          opt.selected = true;
        }
        cityEl.appendChild(opt);
      });
    });
  }

  // Real-time 11-digit limit and numeric filtering for mobile contact
  const profilePhone = document.getElementById('profile_phone');
  if (profilePhone) {
    profilePhone.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });
  }

  // --- Change Password Feature Error Handling at Bottom of Form ---
  const pwForm = document.querySelector('#change-password form');
  if (pwForm) {
    const currentPw = document.getElementById('current_password');
    const newPw = document.getElementById('new_password');
    const confirmPw = document.getElementById('confirm_password');

    // Remove error highlights on input
    [currentPw, newPw, confirmPw].forEach(function (input) {
      if (input) {
        input.addEventListener('input', function () {
          input.classList.remove('has-error');
        });
      }
    });

    pwForm.addEventListener('submit', function (e) {
      let feedbackEl = document.getElementById('password-error-box');
      const successBox = document.getElementById('password-success-box');

      // Reset field error highlights
      [currentPw, newPw, confirmPw].forEach(function (input) {
        if (input) input.classList.remove('has-error');
      });

      const errors = [];

      if (!currentPw || !currentPw.value.trim()) {
        errors.push('Current password is required');
        if (currentPw) currentPw.classList.add('has-error');
      }

      if (!newPw || !newPw.value) {
        errors.push('New password is required');
        if (newPw) newPw.classList.add('has-error');
      } else if (newPw.value.length < 6) {
        errors.push('New password must be at least 6 characters');
        if (newPw) newPw.classList.add('has-error');
      }

      if (!confirmPw || !confirmPw.value) {
        errors.push('Please confirm your new password');
        if (confirmPw) confirmPw.classList.add('has-error');
      } else if (newPw && confirmPw && newPw.value !== confirmPw.value) {
        errors.push('New passwords do not match');
        confirmPw.classList.add('has-error');
        newPw.classList.add('has-error');
      }

      if (errors.length > 0) {
        e.preventDefault();

        // Remove old success message if present
        if (successBox) successBox.remove();

        // Create or update error box at the bottom of the feature
        if (!feedbackEl) {
          feedbackEl = document.createElement('div');
          feedbackEl.id = 'password-error-box';
          feedbackEl.className = 'alert-error password-feedback';
          feedbackEl.setAttribute('role', 'alert');
          pwForm.appendChild(feedbackEl);
        }

        feedbackEl.innerHTML = `
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <div>
            ${errors.map(err => `<div>${err}</div>`).join('')}
          </div>
        `;

        feedbackEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });

    // If server rendered error or success message on page load, ensure it's in view
    const existingFeedback = document.getElementById('password-error-box') || document.getElementById('password-success-box');
    if (existingFeedback && window.location.hash === '#change-password') {
      setTimeout(function () {
        existingFeedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 150);
    }
  }
});
