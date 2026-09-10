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

    const cardBox = document.getElementById('card-details-fields');
    if (cardBox) {
      if (radio.value === 'Credit Card') {
        cardBox.style.display = 'block';
      } else {
        cardBox.style.display = 'none';
      }
    }
  }
}

function updateCardBrandBadge(rawNum) {
  const icon = document.getElementById('card-brand-icon');
  const visaBadge = document.getElementById('badge-visa');
  const mcBadge = document.getElementById('badge-mastercard');
  const amexBadge = document.getElementById('badge-amex');

  [visaBadge, mcBadge, amexBadge].forEach(function(b) {
    if (b) b.classList.remove('active');
  });

  if (/^4/.test(rawNum)) {
    if (visaBadge) visaBadge.classList.add('active');
    if (icon) icon.textContent = '💳 Visa';
  } else if (/^(5[1-5]|2[2-7])/.test(rawNum)) {
    if (mcBadge) mcBadge.classList.add('active');
    if (icon) icon.textContent = '💳 MC';
  } else if (/^3[47]/.test(rawNum)) {
    if (amexBadge) amexBadge.classList.add('active');
    if (icon) icon.textContent = '💳 Amex';
  } else {
    if (icon) icon.textContent = '💳';
  }
}

function showCheckoutNotif(msg) {
  const t = document.getElementById('checkout-toast');
  if (!t) return;
  t.innerHTML = '<span>' + msg + '</span>';
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
    showCheckoutNotif('You cannot checkout without an order! Please select at least one product first.');
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

      // Phone - exactly 11 digits
      const phoneEl = document.getElementById('phone');
      if (phoneEl) {
        const cleanPhone = phoneEl.value.replace(/\D/g, '');
        if (!cleanPhone) {
          addrValid = false;
          phoneEl.classList.add('has-error');
          showCheckoutNotif('Phone number is required.');
        } else if (cleanPhone.length !== 11) {
          addrValid = false;
          phoneEl.classList.add('has-error');
          showCheckoutNotif('Please enter a valid 11-digit mobile number (e.g., 09171234567).');
        }
      }

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

      // Check standard card details if Credit / Debit Card is selected
      const payRadio = document.querySelector('input[name="payment_method"]:checked');
      if (payRadio && payRadio.value === 'Credit Card') {
        const cardNameEl = document.getElementById('card_name');
        const cardNumEl  = document.getElementById('card_number');
        const cardExpEl  = document.getElementById('card_expiry');
        const cardCvvEl  = document.getElementById('card_cvv');

        if (!cardNameEl || !cardNameEl.value.trim()) {
          addrValid = false;
          if (cardNameEl) cardNameEl.classList.add('has-error');
          showCheckoutNotif('Cardholder Name is required.');
        } else if (cardNameEl.value.trim().length < 3) {
          addrValid = false;
          cardNameEl.classList.add('has-error');
          showCheckoutNotif('Cardholder Name must be at least 3 characters.');
        }

        const rawCard = cardNumEl ? cardNumEl.value.replace(/\D/g, '') : '';
        if (!rawCard || (rawCard.length < 15 || rawCard.length > 16)) {
          addrValid = false;
          if (cardNumEl) cardNumEl.classList.add('has-error');
          showCheckoutNotif('Please enter a valid 15 or 16-digit card number.');
        }

        const expVal = cardExpEl ? cardExpEl.value.trim() : '';
        const expMatch = expVal.match(/^(0[1-9]|1[0-2])\/(\d{2})$/);
        if (!expMatch) {
          addrValid = false;
          if (cardExpEl) cardExpEl.classList.add('has-error');
          showCheckoutNotif('Please enter expiration date in MM/YY format.');
        } else {
          const expM = parseInt(expMatch[1], 10);
          const expY = 2000 + parseInt(expMatch[2], 10);
          const now = new Date();
          const curY = now.getFullYear();
          const curM = now.getMonth() + 1;
          if (expY < curY || (expY === curY && expM < curM)) {
            addrValid = false;
            if (cardExpEl) cardExpEl.classList.add('has-error');
            showCheckoutNotif('The card expiration date has already passed.');
          }
        }

        const cleanCvv = cardCvvEl ? cardCvvEl.value.replace(/\D/g, '') : '';
        if (!cleanCvv || (cleanCvv.length < 3 || cleanCvv.length > 4)) {
          addrValid = false;
          if (cardCvvEl) cardCvvEl.classList.add('has-error');
          showCheckoutNotif('Please enter a valid 3 or 4-digit CVV / security code.');
        }
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

    // Sync initial payment method highlight and card box visibility
    const initialPayRadio = document.querySelector('input[name="payment_method"]:checked');
    if (initialPayRadio) {
      updatePaymentHighlight(initialPayRadio);
    }

    // Standard card number formatting (4-digit blocks) & brand detection
    const cardNumInput = document.getElementById('card_number');
    if (cardNumInput) {
      cardNumInput.addEventListener('input', function() {
        const raw = this.value.replace(/\D/g, '').slice(0, 16);
        let formatted = '';
        for (let i = 0; i < raw.length; i++) {
          if (i > 0 && i % 4 === 0) formatted += ' ';
          formatted += raw[i];
        }
        this.value = formatted;
        updateCardBrandBadge(raw);
        this.classList.remove('has-error');
        const errEl = this.parentElement ? this.parentElement.parentElement.querySelector('.field-error-message') : null;
        if (errEl) errEl.style.display = 'none';
      });
      if (cardNumInput.value) {
        updateCardBrandBadge(cardNumInput.value.replace(/\D/g, ''));
      }
    }

    // Expiry date formatting (MM/YY)
    const cardExpInput = document.getElementById('card_expiry');
    if (cardExpInput) {
      cardExpInput.addEventListener('input', function() {
        let val = this.value.replace(/\D/g, '').slice(0, 4);
        if (val.length >= 2) {
          let m = parseInt(val.slice(0, 2), 10);
          if (m > 12) m = 12;
          if (m === 0) m = '01';
          else m = m < 10 ? '0' + m : '' + m;
          val = m + (val.length > 2 ? '/' + val.slice(2) : '/');
        }
        this.value = val;
        this.classList.remove('has-error');
        const errEl = this.parentElement ? this.parentElement.querySelector('.field-error-message') : null;
        if (errEl) errEl.style.display = 'none';
      });
    }

    // CVV input numeric only (3-4 digits)
    const cardCvvInput = document.getElementById('card_cvv');
    if (cardCvvInput) {
      cardCvvInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
        this.classList.remove('has-error');
        const errEl = this.parentElement ? this.parentElement.querySelector('.field-error-message') : null;
        if (errEl) errEl.style.display = 'none';
      });
    }

    // Cardholder name clear error on input
    const cardNameInput = document.getElementById('card_name');
    if (cardNameInput) {
      cardNameInput.addEventListener('input', function() {
        this.classList.remove('has-error');
        const errEl = this.parentElement ? this.parentElement.querySelector('.field-error-message') : null;
        if (errEl) errEl.style.display = 'none';
      });
    }

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

    // Mobile contact numeric filter & 11-digit enforcement
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
      phoneInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
        this.classList.remove('has-error');
        const errEl = this.parentElement ? this.parentElement.querySelector('.field-error-message') : null;
        if (errEl) errEl.style.display = 'none';
      });
    }

    // Clear has-error on input/change
    ['street_address', 'province', 'city', 'postal_code', 'phone', 'card_name', 'card_number', 'card_expiry', 'card_cvv'].forEach(function(id) {
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
