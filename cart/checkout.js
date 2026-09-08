/**
 * cart/checkout.js - Apex Diurnal Customer Checkout Interactive Scripts
 * Handles dynamic recalculation, payment method highlights, validation guards, and notifications
 */

const TAX_RATE = 0.08;

function recalculateTotals() {
  const checkboxes = document.querySelectorAll('.product-checkbox');
  let subtotal = 0;
  let checkedCount = 0;

  checkboxes.forEach(function(chk) {
    const cardId = chk.getAttribute('data-card-id');
    const qtyId = chk.getAttribute('data-qty-id');
    const cardElem = document.getElementById(cardId);
    const qtyElem = document.getElementById(qtyId);

    if (chk.checked) {
      checkedCount++;
      if (cardElem) cardElem.classList.add('is-selected');
      const unitPrice = parseFloat(chk.getAttribute('data-price')) || 0;
      const qty = parseInt(qtyElem.value, 10) || 1;
      subtotal += unitPrice * Math.max(1, qty);
    } else {
      if (cardElem) cardElem.classList.remove('is-selected');
    }
  });

  const tax = subtotal * TAX_RATE;
  const grandTotal = subtotal + tax;

  const subtotalElem = document.getElementById('display-subtotal');
  const taxElem = document.getElementById('display-tax');
  const grandElem = document.getElementById('display-grandtotal');

  if (subtotalElem) subtotalElem.textContent = '₱' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (taxElem) taxElem.textContent = '₱' + tax.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (grandElem) grandElem.textContent = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  // Dynamically disable or enable the place order button & warning notice
  const submitBtn = document.getElementById('btn-submit');
  const warningElem = document.getElementById('no-products-warning');

  if (submitBtn) {
    submitBtn.removeAttribute('disabled');
    if (checkedCount === 0 || subtotal <= 0) {
      submitBtn.classList.add('disabled');
      submitBtn.setAttribute('aria-disabled', 'true');
      submitBtn.setAttribute('title', 'Please select at least one product to checkout');
      if (warningElem) warningElem.style.display = 'flex';
    } else {
      submitBtn.classList.remove('disabled');
      submitBtn.removeAttribute('aria-disabled');
      submitBtn.removeAttribute('title');
      if (warningElem) warningElem.style.display = 'none';
    }
  }
}

function updatePaymentHighlight(radio) {
  document.querySelectorAll('.payment-option-item').forEach(function(item) {
    item.classList.remove('selected');
  });
  if (radio && radio.checked) {
    const parent = radio.closest('.payment-option-item');
    if (parent) parent.classList.add('selected');
  }
}

function showCheckoutNotif(msg) {
  const t = document.getElementById('checkout-toast');
  if (!t) return;
  t.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg><span>' + msg + '</span>';
  t.classList.add('show');
  clearTimeout(window.checkoutToastTimer);
  window.checkoutToastTimer = setTimeout(function() {
    t.classList.remove('show');
  }, 3500);
}

function blockEmptyCheckout(e) {
  const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
  if (checkedBoxes.length === 0) {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    const warningElem = document.getElementById('no-products-warning');
    if (warningElem) {
      warningElem.style.display = 'flex';
      warningElem.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    showCheckoutNotif('⚠️ You cannot checkout without an order! Please select at least one product first.');
    return false;
  }
  return true;
}

window.recalculateTotals = recalculateTotals;
window.updatePaymentHighlight = updatePaymentHighlight;
window.showCheckoutNotif = showCheckoutNotif;
window.blockEmptyCheckout = blockEmptyCheckout;

document.addEventListener('DOMContentLoaded', function() {
  recalculateTotals();

  // Intercept click on submit button
  const submitBtn = document.getElementById('btn-submit');
  if (submitBtn) {
    submitBtn.addEventListener('click', function(e) {
      if (!blockEmptyCheckout(e)) {
        return false;
      }
    });
  }

  // Guard form submission against zero products + dependent province/city validation
  const checkoutForm = document.getElementById('apex-checkout-form');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', function(e) {
      const provEl = document.getElementById('province');
      const cityEl2 = document.getElementById('city');
      const postalEl = document.getElementById('postal_code');
      const streetEl = document.getElementById('street_address');
      let addrValid = true;

      // Street - free text
      if (streetEl && !streetEl.value.trim()) {
        addrValid = false;
        streetEl.classList.add('has-error');
        showCheckoutNotif('Please enter street address.');
      } else if (streetEl && streetEl.value.trim().length < 5) {
        addrValid = false;
        streetEl.classList.add('has-error');
        showCheckoutNotif('Street address must be at least 5 characters.');
      }

      // Province
      if (provEl && !provEl.value) {
        addrValid = false;
        provEl.classList.add('has-error');
        showCheckoutNotif('Please select a province.');
      }

      // City / Municipality
      if (cityEl2 && !cityEl2.value) {
        addrValid = false;
        cityEl2.classList.add('has-error');
        showCheckoutNotif('Please select a city or municipality.');
      } else if (provEl && provEl.value && cityEl2 && cityEl2.value && window.provinceCityMap) {
        const allowed = window.provinceCityMap[provEl.value] || [];
        if (allowed.length > 0 && !allowed.includes(cityEl2.value)) {
          addrValid = false;
          cityEl2.classList.add('has-error');
          showCheckoutNotif('Selected city / municipality does not belong to ' + provEl.value + '.');
        }
      }

      // Postal
      if (postalEl && !/^\d{4}$/.test(postalEl.value.trim())) {
        addrValid = false;
        postalEl.classList.add('has-error');
        if (postalEl.value.trim() === '') showCheckoutNotif('Postal code is required.');
        else showCheckoutNotif('Please enter a valid 4-digit postal code.');
      }

      if (!addrValid) {
        e.preventDefault();
        e.stopPropagation();
        const firstErr = document.querySelector('.form-control.has-error');
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
      }

      if (!blockEmptyCheckout(e)) {
        return false;
      }
    });

    // Dynamic Province -> City / Municipality dropdown filtering
    const provSelect = document.getElementById('province');
    const citySelect = document.getElementById('city');

    if (provSelect && citySelect) {
      function updateCityDropdown(preserveSelected) {
        const selectedProv = provSelect.value;
        const currentCity = citySelect.value;
        const list = (window.provinceCityMap && window.provinceCityMap[selectedProv]) 
          ? window.provinceCityMap[selectedProv] 
          : [];

        citySelect.innerHTML = '';
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = selectedProv 
          ? (list.length > 0 ? 'Select City / Municipality' : 'No cities or municipalities found')
          : 'Select Province First';
        citySelect.appendChild(defaultOpt);

        list.forEach(function(item) {
          const opt = document.createElement('option');
          opt.value = item;
          opt.textContent = item;
          if (preserveSelected && currentCity === item) {
            opt.selected = true;
          }
          citySelect.appendChild(opt);
        });

        if (preserveSelected && currentCity && list.includes(currentCity)) {
          citySelect.value = currentCity;
        } else if (!preserveSelected) {
          citySelect.value = '';
        }
      }

      if (provSelect.value) {
        if (citySelect.options.length <= 1) {
          updateCityDropdown(true);
        }
      } else {
        if (citySelect.options.length <= 1) {
          citySelect.innerHTML = '<option value="">Select Province First</option>';
        }
      }

      provSelect.addEventListener('change', function() {
        updateCityDropdown(false);
        citySelect.classList.remove('has-error');
        const errEl = citySelect.parentElement ? citySelect.parentElement.querySelector('.field-error-message') : null;
        if (errEl) errEl.style.display = 'none';
      });
    }

    // Clear has-error on input/change
    ['street_address', 'province', 'city', 'postal_code'].forEach(function(id) {
      const el = document.getElementById(id);
      if (el) {
        el.addEventListener('input', function() { this.classList.remove('has-error'); });
        el.addEventListener('change', function() { this.classList.remove('has-error'); });
      }
    });
  }

  // Check if empty cart warning flag is present on body
  if (document.body.getAttribute('data-empty-cart-alert') === 'true') {
    showCheckoutNotif('Notice: You must order first before proceeding to checkout.');
  }
});
