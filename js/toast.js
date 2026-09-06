/**
 * Apex Diurnal - Toast Notification Utility
 */
let cartToastTimer;

function showToast(msg, isWarning = false) {
  const t = document.getElementById('cart-toast');
  if (!t) return;
  t.textContent = msg;
  if (isWarning) {
    t.classList.add('toast-warning');
  } else {
    t.classList.remove('toast-warning');
  }
  t.classList.add('show');
  clearTimeout(cartToastTimer);
  cartToastTimer = setTimeout(() => {
    t.classList.remove('show');
    t.classList.remove('toast-warning');
  }, 3200);
}

// Expose globally
window.showToast = showToast;
