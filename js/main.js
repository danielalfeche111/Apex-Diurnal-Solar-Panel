/**
 * Apex Diurnal - Main Application Bootstrap
 * Coordinates toast.js, navigation.js, cart.js, and consultation.js
 */
(function () {
  'use strict';

  function initApp() {
    console.log('Apex Diurnal Application initialized');

    // Global keyboard shortcuts (Escape key handler)
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        if (typeof window.closeCart === 'function') {
          window.closeCart();
        }
        if (typeof window.closeConsultationModal === 'function') {
          window.closeConsultationModal();
        }
        if (typeof window.closeUserDropdown === 'function') {
          window.closeUserDropdown();
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
  } else {
    initApp();
  }
})();
