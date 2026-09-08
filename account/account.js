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
});
