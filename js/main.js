// ---- Global cart helpers (called from onclick) ----
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

// User Dropdown functionality
function toggleUserDropdown() {
  const dropdownMenu = document.getElementById('user-dropdown-menu');
  if (dropdownMenu) {
    dropdownMenu.classList.toggle('show');
  }
}

function closeUserDropdown() {
  const dropdownMenu = document.getElementById('user-dropdown-menu');
  if (dropdownMenu) {
    dropdownMenu.classList.remove('show');
  }
}

// Make globally accessible for inline onclick fallback
window.toggleUserDropdown = toggleUserDropdown;
window.closeUserDropdown = closeUserDropdown;

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  const userDropdown = document.getElementById('user-dropdown');
  const dropdownMenu = document.getElementById('user-dropdown-menu');
  if (userDropdown && dropdownMenu && !userDropdown.contains(e.target)) {
    dropdownMenu.classList.remove('show');
  }
});

// Authentication check for cart operations
function isUserAuthenticated() {
  // Simple implementation - check for PHPSESSID cookie
  return document.cookie.split(';').some(item =>
    item.trim().startsWith('PHPSESSID=')
  );
}

// Enhanced fetchCart with authentication handling
function fetchCart(action, productId, quantity) {
  const body = new URLSearchParams();
  body.set('action', action);
  if (productId) body.set('product_id', productId);
  if (quantity !== undefined) body.set('quantity', quantity);

  return fetch('cart_action.php', {
    method: 'POST',
    body: body,
    headers: { 'Accept': 'application/json' }
  })
  .then(r => {
    if (!r.ok) {
      if (r.status === 401 || r.status === 403) {
        // Redirect to login with return URL
        const redirect = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.href = `login.php?redirect=${redirect}`;
        throw new Error('Authentication required');
      }
      throw new Error('Cart request failed ' + r.status);
    }
    return r.json();
  })
  .then(data => {
    renderCart(data);
    return data;
  })
  .catch(err => {
    console.error(err);
    // Only show generic toast for non-auth errors
    if (err.message !== 'Authentication required') {
      showToast('Cart error. Is XAMPP running?');
    }
    throw err;
  });
}

// Protected cart functions
function addToCart(id, qty) {
  if (!isUserAuthenticated()) {
    showToast('Please log in to add items to cart', true);
    const redirect = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = `login.php?redirect=${redirect}`;
    return;
  }
  fetchCart('add', id, qty || 1).then(() => showToast('Added to cart'));
}

function changeQty(id, qty) {
  if (!isUserAuthenticated()) {
    showToast('Please log in to modify cart', true);
    const redirect = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = `login.php?redirect=${redirect}`;
    return;
  }
  fetchCart('update', id, qty);
}

function removeItem(id) {
  if (!isUserAuthenticated()) {
    showToast('Please log in to modify cart', true);
    const redirect = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = `login.php?redirect=${redirect}`;
    return;
  }
  fetchCart('remove', id, 1).then(() => showToast('Removed from cart'));
}
function openCart() {
  document.getElementById('cart-drawer')?.classList.add('open');
  document.getElementById('cart-overlay')?.classList.add('active');
  document.getElementById('cart-drawer')?.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
}
function closeCart() {
  document.getElementById('cart-drawer')?.classList.remove('open');
  document.getElementById('cart-overlay')?.classList.remove('active');
  document.getElementById('cart-drawer')?.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
}
function updateCartBadge(count) {
  const b = document.getElementById('cart-count');
  if (!b) return;
  b.textContent = count;
  b.setAttribute('data-count', String(count));
  b.style.display = count > 0 ? 'flex' : 'none';
}
function renderCart(data) {
  const list = document.getElementById('cart-items');
  const totalEl = document.getElementById('cart-total');
  const drawerCount = document.getElementById('cart-drawer-count');
  const emptyHint = document.getElementById('cart-empty-hint');
  if (!list) return;
  updateCartBadge(data.itemCount);
  if (totalEl) totalEl.textContent = '$' + data.grandTotal;
  if (drawerCount) drawerCount.textContent = data.itemCount + ' item(s)';
  
  const checkoutBtn = document.getElementById('cart-checkout');
  const clearBtn = document.getElementById('cart-clear');
  const count = parseInt(data.itemCount || 0, 10);
  const hasItems = Boolean(data.items && data.items.length > 0 && count > 0);

  if (checkoutBtn) {
    checkoutBtn.removeAttribute('disabled');
    if (!hasItems) {
      checkoutBtn.classList.add('disabled');
      checkoutBtn.setAttribute('aria-disabled', 'true');
      checkoutBtn.setAttribute('title', 'Your cart is empty. Please add items before checking out.');
    } else {
      checkoutBtn.classList.remove('disabled');
      checkoutBtn.removeAttribute('aria-disabled');
      checkoutBtn.removeAttribute('title');
    }
  }

  if (clearBtn) {
    clearBtn.removeAttribute('disabled');
    if (!hasItems) {
      clearBtn.classList.add('disabled');
      clearBtn.setAttribute('aria-disabled', 'true');
    } else {
      clearBtn.classList.remove('disabled');
      clearBtn.removeAttribute('aria-disabled');
    }
  }

  list.innerHTML = '';
  if (!hasItems) {
    list.innerHTML = '<div class="cart-empty-state"><p>Your cart is empty.</p><a href="#products" onclick="closeCart()" class="btn btn-yellow">Browse Products</a></div>';
    if (emptyHint) emptyHint.style.display = 'none';
    return;
  }
  if (emptyHint) emptyHint.style.display = 'none';
  data.items.forEach(item => {
    const row = document.createElement('div');
    row.className = 'cart-item';
    row.innerHTML = ''
      + '<img src="' + (item.image || '') + '" alt="' + (item.alt || item.title) + '">'
      + '<div class="cart-item-info"><h4>' + item.title + '</h4><div class="cart-item-price">' + item.price + '</div>'
      + '<div class="cart-item-qty">'
      + '<button type="button" class="qty-btn" onclick="changeQty(\'' + item.id + '\', ' + (item.quantity - 1) + ')">−</button>'
      + '<span class="qty-val">' + item.quantity + '</span>'
      + '<button type="button" class="qty-btn" onclick="changeQty(\'' + item.id + '\', ' + (item.quantity + 1) + ')">+</button>'
      + '</div></div>'
      + '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">'
      + '<div class="cart-item-line">$' + item.line_total + '</div>'
      + '<button type="button" class="cart-item-remove" onclick="removeItem(\'' + item.id + '\')">Remove</button>'
      + '</div>';
    list.appendChild(row);
  });
}
// expose globally for inline onclick
window.addToCart = addToCart;
window.openCart = openCart;
window.closeCart = closeCart;
window.changeQty = changeQty;
window.removeItem = removeItem;

document.addEventListener('DOMContentLoaded', function () {
  console.log('DOMContentLoaded fired');

  // --- Init cart UI from PHP ---
  try {
    console.log('Initializing cart UI');
    renderCart(CART_INITIAL);
  } catch (e) {
    console.error('Error initializing cart UI:', e);
  }

  try {
    if (parseInt(CART_INITIAL.itemCount || 0) === 0) updateCartBadge(0);
    else updateCartBadge(CART_INITIAL.itemCount);
  } catch (e) {
    console.error('Error updating cart badge:', e);
  }

  try {
    const cartTrigger = document.getElementById('cart-trigger');
    const cartOverlay = document.getElementById('cart-overlay');
    const cartClose = document.getElementById('cart-close');
    const cartClear = document.getElementById('cart-clear');
    const cartCheckout = document.getElementById('cart-checkout');

    if (cartTrigger) {
      console.log('Adding cart trigger listener');
      cartTrigger.addEventListener('click', openCart);
    }
    if (cartOverlay) {
      console.log('Adding cart overlay listener');
      cartOverlay.addEventListener('click', closeCart);
    }
    if (cartClose) {
      console.log('Adding cart close listener');
      cartClose.addEventListener('click', closeCart);
    }
    if (cartClear) {
      console.log('Adding cart clear listener');
      cartClear.addEventListener('click', function () {
        if (confirm('Clear your cart?')) fetchCart('clear').then(() => showToast('Cart cleared'));
      });
    }
    if (cartCheckout) {
      console.log('Adding cart checkout listener');
      cartCheckout.addEventListener('click', function (e) {
        try {
          const badge = document.getElementById('cart-count');
          const count = parseInt(badge?.getAttribute('data-count') || badge?.textContent || '0', 10);
          const isEmpty = count <= 0 || cartCheckout.classList.contains('disabled') || cartCheckout.getAttribute('aria-disabled') === 'true';
          if (isEmpty) {
            e.preventDefault();
            showToast('⚠️ You cannot checkout without an order! Please add products to your cart first.', true);
            const hint = document.getElementById('cart-empty-hint');
            if (hint) {
              hint.textContent = 'Please add products to your cart first before checking out.';
              hint.style.display = 'block';
              hint.style.color = '#b91c1c';
              hint.style.fontWeight = '700';
            }
            return;
          }
          window.location.href = 'checkout.php';
        } catch (err) {
          console.error('Error in cart checkout handler:', err);
        }
      });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeCart();
    });
  } catch (e) {
    console.error('Error setting up cart event listeners:', e);
  }

  // --- User Dropdown functionality ---
  try {
    console.log('Setting up user dropdown functionality');
    const userAccountBtn = document.getElementById('user-account-btn');
    const userDropdownMenu = document.getElementById('user-dropdown-menu');
    console.log('User account btn:', userAccountBtn);
    console.log('User dropdown menu:', userDropdownMenu);

    if (userAccountBtn && userDropdownMenu) {
      console.log('Adding click event listener to user account button');
      userAccountBtn.addEventListener('click', function(e) {
        console.log('User account button clicked');
        e.stopPropagation(); // Prevents click from bubbling up to document
        toggleUserDropdown();
      });

      // Also add a fallback direct onclick attribute for reliability
      userAccountBtn.setAttribute('onclick', 'toggleUserDropdown(); event.stopPropagation();');
    } else {
      console.log('User account btn or dropdown menu not found');
      console.log('Looking for elements...');
      if (!userAccountBtn) console.log('Missing: user-account-btn');
      if (!userDropdownMenu) console.log('Missing: user-dropdown-menu');
    }
  } catch (e) {
    console.error('Error setting up user dropdown:', e);
  }

  console.log('DOMContentLoaded initialization complete');

  // --- Search functionality (moved inside DOMContentLoaded to ensure DOM ready and prevent syntax error) ---
  try {
    const toggle = document.getElementById('search-toggle');
    const bar = document.getElementById('search-bar');
    const input = document.getElementById('search-input');
    const closeBtn = document.getElementById('search-close');
    const container = document.getElementById('search-container');
    const results = document.getElementById('search-results');
    const cards = document.querySelectorAll('.product-card');
    const navLinks = document.querySelectorAll('.nav-link');
    const footerLinks = document.querySelectorAll('.footer-links a');

    if (!toggle || !bar || !input || !closeBtn || !container || !results) {
      console.log('Search elements not found, skipping search setup');
      return;
    }

    function openSearch() {
      bar.classList.add('active');
      toggle.setAttribute('aria-expanded', 'true');
      setTimeout(function () { input.focus(); }, 100);
    }

    function closeSearch() {
      bar.classList.remove('active');
      results.classList.remove('active');
      results.innerHTML = '';
      toggle.setAttribute('aria-expanded', 'false');
      input.value = '';
      document.body.classList.remove('is-searching');
    }

    function navigateTo(href) {
      closeSearch();
      const target = document.querySelector(href);
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      else window.location.hash = href;
    }

    function buildResultItem(label, href, type) {
      const div = document.createElement('div');
      div.className = 'search-result-item';
      div.innerHTML = '<span class="result-type">' + type + '</span> ' + label;
      div.addEventListener('click', function () { navigateTo(href); });
      return div;
    }

    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      if (bar.classList.contains('active')) closeSearch(); else openSearch();
    });

    closeBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      closeSearch();
    });

    document.addEventListener('click', function (e) {
      if (!container.contains(e.target) && bar.classList.contains('active')) closeSearch();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && bar.classList.contains('active')) closeSearch();
    });

    input.addEventListener('input', function () {
      const q = input.value.toLowerCase().trim();
      results.innerHTML = '';
      results.classList.remove('active');

      if (!q) {
        document.body.classList.remove('is-searching');
        return;
      }
      document.body.classList.add('is-searching');

      // --- Product search with direct card direction ---
      let productMatches = [];
      cards.forEach(function (card) {
        const titleEl = card.querySelector('.product-title');
        const title = (titleEl?.textContent || '').toLowerCase();
        const price = (card.querySelector('.product-price')?.textContent || '').toLowerCase();
        // handle common typo / partial matches for the 4 products
        const match = title.includes(q) || price.includes(q) ||
          (q.includes('professional') && title.includes('professional')) ||
          (q.includes('proffesional') && title.includes('professional'));
        if (match) productMatches.push({ card: card, title: titleEl?.textContent.trim() || 'Product', href: '#' + card.id });
      });

      // --- Navigation search (no highlight) ---
      let navMatches = [];
      navLinks.forEach(function (link) {
        const text = (link.textContent || '').toLowerCase();
        const href = (link.getAttribute('href') || '').toLowerCase();
        const match = text.includes(q) || href.includes(q);
        if (match) {
          navMatches.push({ label: link.textContent.trim(), href: link.getAttribute('href'), type: 'NAV' });
        }
      });
      footerLinks.forEach(function (link) {
        const text = (link.textContent || '').toLowerCase();
        if (text.includes(q)) {
          // avoid duplicate if already in header
          if (!navMatches.some(function (m) { return m.href === link.getAttribute('href'); })) {
            navMatches.push({ label: link.textContent.trim(), href: link.getAttribute('href'), type: 'NAV' });
          }
        }
      });

      // If searching for "product"/"products" show all products with direction to each card
      const isProductSearch = q.includes('product') || navMatches.some(function (m) { return m.href === '#products'; });
      if (isProductSearch && productMatches.length === 0) {
        productMatches = Array.from(cards).map(function (card) {
          return { card: card, title: card.querySelector('.product-title')?.textContent.trim() || 'Product', href: '#' + card.id };
        });
      }

      // --- Build dropdown results ---
      let hasResults = false;
      navMatches.forEach(function (m) {
        const div = document.createElement('div');
        div.className = 'search-result-item';
        div.textContent = m.label;
        div.addEventListener('click', function () { navigateTo(m.href); });
        results.appendChild(div);
        hasResults = true;
      });
      productMatches.forEach(function (m) {
        results.appendChild(buildResultItem(m.title, m.href, 'PRODUCT'));
        hasResults = true;
      });

      if (hasResults) {
        results.classList.add('active');
      } else {
        const empty = document.createElement('div');
        empty.className = 'search-result-item';
        empty.style.opacity = '0.6';
        empty.style.cursor = 'default';
        empty.textContent = 'No results for "' + input.value + '"';
        results.appendChild(empty);
        results.classList.add('active');
      }
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const firstResult = results.querySelector('.search-result-item');
        if (firstResult && firstResult.textContent.indexOf('No results') === -1) {
          firstResult.click();
          return;
        }
        const qNav = input.value.toLowerCase().trim();
        let firstNav = null;
        if (qNav) {
          firstNav = Array.from(navLinks).find(function (l) {
            return (l.textContent || '').toLowerCase().includes(qNav) || (l.getAttribute('href') || '').toLowerCase().includes(qNav);
          });
        }
        if (firstNav) {
          const href = firstNav.getAttribute('href');
          const target = href ? document.querySelector(href) : null;
          if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          return;
        }
        const firstCard = document.querySelector('.product-card:not([style*="display: none"])');
        if (firstCard) firstCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  } catch (e) {
    console.error('Error setting up search:', e);
  }
});

// =========================================================================
// COMMERCIAL GRID SOLAR DUAL-MODE MODAL: CONSULTATION & CORPORATE RFQ
// =========================================================================
(function initConsultationModal() {
  function setup() {
    const overlay = document.getElementById('consultation-overlay');
    const modal = document.getElementById('consultation-modal');
    const closeBtn = document.getElementById('consultation-close');
    const form = document.getElementById('consultation-form');
    const errorAlert = document.getElementById('consultation-error-alert');
    const successPanel = document.getElementById('consultation-success');
    const successTitle = document.getElementById('consultation-success-title');
    const successDesc = document.getElementById('consultation-success-desc');
    const successSummary = document.getElementById('success-summary');
    const rfqDisclaimer = document.getElementById('rfq-success-disclaimer');
    const closeSuccessBtn = document.getElementById('btn-close-success');
    const leadTypeInput = document.getElementById('lead_type');

    if (!modal || !overlay || !form) return;

    let currentMode = 'consultation'; // 'consultation' or 'rfq'
    let currentStep = 1;

    // Province -> City maps for dynamic filtering
    function getCitiesForProvince(province) {
      if (typeof consultProvinceCityMap !== 'undefined' && consultProvinceCityMap[province]) {
        return consultProvinceCityMap[province];
      }
      return [];
    }

    function setupProvinceCityDropdowns(provSelectId, citySelectId, errCityId) {
      const pSelect = document.getElementById(provSelectId);
      const cSelect = document.getElementById(citySelectId);
      if (!pSelect || !cSelect) return;

      function updateCities(preserve) {
        const prov = pSelect.value;
        const cities = getCitiesForProvince(prov);
        const oldVal = cSelect.value;
        cSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = cities.length > 0 ? 'Select City' : (prov ? 'Select City (showing all)' : 'Select City');
        cSelect.appendChild(placeholder);

        let list = cities;
        if (list.length === 0 && prov && typeof consultAllCities !== 'undefined' && Array.isArray(consultAllCities)) {
          list = consultAllCities.slice().sort();
        }

        list.forEach(city => {
          const opt = document.createElement('option');
          opt.value = city;
          opt.textContent = city;
          cSelect.appendChild(opt);
        });

        if (preserve && oldVal && list.includes(oldVal)) {
          cSelect.value = oldVal;
        } else {
          cSelect.value = '';
        }
      }

      updateCities(false);

      pSelect.addEventListener('change', function () {
        updateCities(false);
        const err = document.getElementById(errCityId);
        if (err) { err.textContent = ''; err.classList.remove('show'); }
        cSelect.classList.remove('is-invalid');
      });
    }

    // Initialize both dropdown pairs
    setupProvinceCityDropdowns('consult_province', 'consult_city', 'err-consult_city');
    setupProvinceCityDropdowns('rfq_province', 'rfq_city', 'err-rfq_city');

    function clearErrors() {
      if (errorAlert) {
        errorAlert.style.display = 'none';
        errorAlert.textContent = '';
      }
      modal.querySelectorAll('.consultation-field-error').forEach(el => {
        el.classList.remove('show');
        el.textContent = '';
      });
      modal.querySelectorAll('.consultation-input.is-invalid').forEach(inp => {
        inp.classList.remove('is-invalid');
      });
    }

    function showFieldError(fieldId, message) {
      const input = document.getElementById(fieldId);
      const errDiv = document.getElementById('err-' + fieldId);
      if (input) input.classList.add('is-invalid');
      if (errDiv) {
        errDiv.textContent = message;
        errDiv.classList.add('show');
      }
    }

    function setMode(mode) {
      currentMode = (mode === 'rfq') ? 'rfq' : 'consultation';
      if (leadTypeInput) leadTypeInput.value = currentMode;

      const badge = document.getElementById('consultation-mode-badge');
      const title = document.getElementById('consultation-modal-title');
      const subtitle = document.getElementById('consultation-modal-subtitle');
      const stepText1 = document.getElementById('step-text-1');
      const stepText2 = document.getElementById('step-text-2');
      const stepText3 = document.getElementById('step-text-3');

      if (currentMode === 'rfq') {
        if (badge) badge.style.display = 'inline-flex';
        if (title) title.textContent = 'Request Commercial Quote';
        if (subtitle) subtitle.textContent = 'Submit your corporate specifications to receive a formal commercial solar equipment proposal.';
        if (stepText1) stepText1.textContent = 'Company Details';
        if (stepText2) stepText2.textContent = 'Installation Address';
        if (stepText3) stepText3.textContent = 'Timeline & Contact';

        // Disable consultation inputs so FormData ignores them
        for (let i = 1; i <= 3; i++) {
          const panel = document.getElementById('step-panel-consult-' + i);
          if (panel) {
            panel.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
          }
          const rfqPanel = document.getElementById('step-panel-rfq-' + i);
          if (rfqPanel) {
            rfqPanel.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
          }
        }
      } else {
        if (badge) badge.style.display = 'none';
        if (title) title.textContent = 'Schedule On-Site Consultation';
        if (subtitle) subtitle.textContent = 'Comprehensive site audit & engineering feasibility analysis for high-capacity solar setups.';
        if (stepText1) stepText1.textContent = 'Facility Details';
        if (stepText2) stepText2.textContent = 'Contact Details';
        if (stepText3) stepText3.textContent = 'Audit Schedule';

        // Disable RFQ inputs so FormData ignores them
        for (let i = 1; i <= 3; i++) {
          const rfqPanel = document.getElementById('step-panel-rfq-' + i);
          if (rfqPanel) {
            rfqPanel.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
          }
          const panel = document.getElementById('step-panel-consult-' + i);
          if (panel) {
            panel.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
          }
        }
      }

      setStep(1);
    }

    function setStep(step) {
      currentStep = step;

      // Update stepper indicators
      for (let i = 1; i <= 3; i++) {
        const navItem = document.getElementById('step-nav-' + i);
        const divider = document.getElementById('step-div-' + (i - 1));

        if (navItem) {
          navItem.classList.toggle('active', i === step);
          navItem.classList.toggle('completed', i < step);
        }
        if (divider) {
          divider.classList.toggle('active', i <= step);
        }
      }

      // Toggle panels according to mode and step
      for (let i = 1; i <= 3; i++) {
        const consultPanel = document.getElementById('step-panel-consult-' + i);
        const rfqPanel = document.getElementById('step-panel-rfq-' + i);

        if (consultPanel) {
          consultPanel.style.display = (currentMode === 'consultation' && i === step) ? 'block' : 'none';
          consultPanel.classList.toggle('active', currentMode === 'consultation' && i === step);
        }
        if (rfqPanel) {
          rfqPanel.style.display = (currentMode === 'rfq' && i === step) ? 'block' : 'none';
          rfqPanel.classList.toggle('active', currentMode === 'rfq' && i === step);
        }
      }

      const modalBody = modal.querySelector('.consultation-modal-body');
      if (modalBody) modalBody.scrollTop = 0;
    }

    function resetModal() {
      if (form) form.reset();
      clearErrors();
      if (successPanel) successPanel.style.display = 'none';
      if (form) form.style.display = 'block';

      const consultSubmitBtn = document.getElementById('btn-submit-consultation');
      if (consultSubmitBtn) {
        consultSubmitBtn.disabled = false;
        const txt = consultSubmitBtn.querySelector('.btn-text');
        if (txt) txt.textContent = 'Confirm Site Consultation';
      }
      const rfqSubmitBtn = document.getElementById('btn-submit-rfq');
      if (rfqSubmitBtn) {
        rfqSubmitBtn.disabled = false;
        const txt = rfqSubmitBtn.querySelector('.btn-text');
        if (txt) txt.textContent = 'Submit Quote Request';
      }
      const cSpin = document.getElementById('consultation-spinner');
      if (cSpin) cSpin.style.display = 'none';
      const rSpin = document.getElementById('rfq-spinner');
      if (rSpin) rSpin.style.display = 'none';

      setStep(1);
    }

    function openModal(mode) {
      setMode(mode || 'consultation');
      overlay.style.display = 'block';
      modal.style.display = 'flex';
      requestAnimationFrame(() => {
        overlay.classList.add('active');
        modal.classList.add('open');
      });
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      if (successPanel && successPanel.style.display === 'block') {
        resetModal();
      }
    }

    function closeModal() {
      overlay.classList.remove('active');
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      setTimeout(() => {
        if (!modal.classList.contains('open')) {
          overlay.style.display = 'none';
          modal.style.display = 'none';
        }
      }, 300);
    }

    // Expose globals
    window.setConsultationMode = setMode;
    window.openConsultationModal = openModal;
    window.closeConsultationModal = closeModal;
    window.openContactSalesModal = () => openModal('rfq');
    window.closeContactSalesModal = closeModal;

    // Validation - Consultation Mode
    function validateConsultStep1() {
      clearErrors();
      let valid = true;
      const company = document.getElementById('company_name');
      const street = document.getElementById('consult_street');
      const province = document.getElementById('consult_province');
      const city = document.getElementById('consult_city');
      const postal = document.getElementById('consult_postal');
      const facility = document.getElementById('facility_type');
      const power = document.getElementById('power_supply');

      if (!company || company.value.trim().length < 2) {
        showFieldError('company_name', 'Please enter your full name (at least 2 characters).');
        valid = false;
      }
      if (!street || street.value.trim().length < 5) {
        showFieldError('consult_street', 'Street address must be at least 5 characters.');
        valid = false;
      }
      if (!province || !province.value) {
        showFieldError('consult_province', 'Please select a province.');
        valid = false;
      }
      if (!city || !city.value) {
        showFieldError('consult_city', 'Please select a city.');
        valid = false;
      } else if (province && province.value) {
        const allowedCities = getCitiesForProvince(province.value);
        if (allowedCities.length > 0 && !allowedCities.includes(city.value)) {
          const stripped = city.value.replace(/\s+City$/i, '');
          const withCity = stripped + ' City';
          if (!allowedCities.includes(stripped) && !allowedCities.includes(withCity)) {
            showFieldError('consult_city', 'Selected city does not belong to ' + province.value + '.');
            valid = false;
          }
        }
      }
      if (!postal || !/^\d{4}$/.test(postal.value.trim())) {
        showFieldError('consult_postal', 'Please enter a valid 4-digit postal code.');
        valid = false;
      }
      if (!facility || !facility.value) {
        showFieldError('facility_type', 'Please select a facility type.');
        valid = false;
      }
      if (!power || !power.value) {
        showFieldError('power_supply', 'Please select a power supply connection.');
        valid = false;
      }
      return valid;
    }

    function validateConsultStep2() {
      clearErrors();
      let valid = true;
      const contact = document.getElementById('contact_person');
      const email = document.getElementById('corporate_email');
      const phone = document.getElementById('phone_number');

      if (!contact || contact.value.trim().length < 2) {
        showFieldError('contact_person', 'Contact person name is required.');
        valid = false;
      }
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!email || !emailRegex.test(email.value.trim())) {
        showFieldError('corporate_email', 'Please enter a valid email address.');
        valid = false;
      }
      const cleanPhone = (phone?.value || '').replace(/[^\d]/g, '');
      if (!phone || cleanPhone.length < 7) {
        showFieldError('phone_number', 'Please enter a valid phone number (at least 7 digits).');
        valid = false;
      }
      return valid;
    }

    function validateConsultStep3() {
      clearErrors();
      let valid = true;
      const dateInput = document.getElementById('preferred_date');
      const timeSlot = document.getElementById('preferred_time_slot');

      if (!dateInput || !dateInput.value) {
        showFieldError('preferred_date', 'Please select a preferred site audit date.');
        valid = false;
      } else {
        const today = new Date().toISOString().split('T')[0];
        if (dateInput.value < today) {
          showFieldError('preferred_date', 'Preferred audit date cannot be in the past.');
          valid = false;
        }
      }
      if (!timeSlot || !timeSlot.value) {
        showFieldError('preferred_time_slot', 'Please select a preferred time slot.');
        valid = false;
      }
      return valid;
    }

    // Validation - RFQ Mode
    function validateRfqStep1() {
      clearErrors();
      let valid = true;
      const company = document.getElementById('rfq_company_name');
      const regType = document.getElementById('rfq_business_registration_type');

      if (!company || company.value.trim().length < 2) {
        showFieldError('rfq_company_name', 'Company Name is required (minimum 2 characters).');
        valid = false;
      }
      if (!regType || !regType.value) {
        showFieldError('rfq_business_registration_type', 'Please select a business registration type.');
        valid = false;
      }
      return valid;
    }

    function validateRfqStep2() {
      clearErrors();
      let valid = true;
      const street = document.getElementById('rfq_street');
      const province = document.getElementById('rfq_province');
      const city = document.getElementById('rfq_city');
      const postal = document.getElementById('rfq_postal');

      if (!street || street.value.trim().length < 5) {
        showFieldError('rfq_street', 'Installation street address must be at least 5 characters.');
        valid = false;
      }
      if (!province || !province.value) {
        showFieldError('rfq_province', 'Please select a province.');
        valid = false;
      }
      if (!city || !city.value) {
        showFieldError('rfq_city', 'Please select a city.');
        valid = false;
      } else if (province && province.value) {
        const allowedCities = getCitiesForProvince(province.value);
        if (allowedCities.length > 0 && !allowedCities.includes(city.value)) {
          showFieldError('rfq_city', 'Selected city does not belong to ' + province.value + '.');
          valid = false;
        }
      }
      if (!postal || !/^\d{4}$/.test(postal.value.trim())) {
        showFieldError('rfq_postal', 'Please enter a valid 4-digit postal code (e.g. 1000).');
        valid = false;
      }
      return valid;
    }

    function validateRfqStep3() {
      clearErrors();
      let valid = true;
      const timeline = document.getElementById('rfq_target_timeline');
      const contact = document.getElementById('rfq_contact_person');
      const email = document.getElementById('rfq_corporate_email');
      const phone = document.getElementById('rfq_phone_number');

      if (!timeline || !timeline.value) {
        showFieldError('rfq_target_timeline', 'Please select a target project completion timeline.');
        valid = false;
      }
      if (!contact || contact.value.trim().length < 2) {
        showFieldError('rfq_contact_person', 'Contact person name is required (minimum 2 characters).');
        valid = false;
      }
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!email || !emailRegex.test(email.value.trim())) {
        showFieldError('rfq_corporate_email', 'Please enter a valid corporate email address.');
        valid = false;
      }
      const cleanPhone = (phone?.value || '').replace(/[^\d]/g, '');
      if (!phone || cleanPhone.length < 7) {
        showFieldError('rfq_phone_number', 'Please enter a valid phone number (at least 7 digits).');
        valid = false;
      }
      return valid;
    }

    // Bind Navigation Buttons
    // Consultation nav
    const consultNext1 = document.getElementById('btn-consult-next-1');
    if (consultNext1) consultNext1.addEventListener('click', () => { if (validateConsultStep1()) setStep(2); });
    const consultPrev2 = document.getElementById('btn-consult-prev-2');
    if (consultPrev2) consultPrev2.addEventListener('click', () => setStep(1));
    const consultNext2 = document.getElementById('btn-consult-next-2');
    if (consultNext2) consultNext2.addEventListener('click', () => { if (validateConsultStep2()) setStep(3); });
    const consultPrev3 = document.getElementById('btn-consult-prev-3');
    if (consultPrev3) consultPrev3.addEventListener('click', () => setStep(2));

    // RFQ nav
    const rfqNext1 = document.getElementById('btn-rfq-next-1');
    if (rfqNext1) rfqNext1.addEventListener('click', () => { if (validateRfqStep1()) setStep(2); });
    const rfqPrev2 = document.getElementById('btn-rfq-prev-2');
    if (rfqPrev2) rfqPrev2.addEventListener('click', () => setStep(1));
    const rfqNext2 = document.getElementById('btn-rfq-next-2');
    if (rfqNext2) rfqNext2.addEventListener('click', () => { if (validateRfqStep2()) setStep(3); });
    const rfqPrev3 = document.getElementById('btn-rfq-prev-3');
    if (rfqPrev3) rfqPrev3.addEventListener('click', () => setStep(2));

    // Auto-clear input error highlights
    function clearTargetError(target) {
      if (target && target.classList.contains('consultation-input')) {
        target.classList.remove('is-invalid');
        const err = document.getElementById('err-' + target.id);
        if (err) {
          err.classList.remove('show');
          err.textContent = '';
        }
      }
    }
    form.addEventListener('input', (e) => clearTargetError(e.target));
    form.addEventListener('change', (e) => clearTargetError(e.target));

    // Form Submission (Ajax)
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      let activeSubmitBtn = null;
      let activeSpinner = null;
      let defaultBtnText = '';

      if (currentMode === 'rfq') {
        if (!validateRfqStep1()) { setStep(1); return; }
        if (!validateRfqStep2()) { setStep(2); return; }
        if (!validateRfqStep3()) { setStep(3); return; }

        activeSubmitBtn = document.getElementById('btn-submit-rfq');
        activeSpinner = document.getElementById('rfq-spinner');
        defaultBtnText = 'Submit Quote Request';
      } else {
        if (!validateConsultStep1()) { setStep(1); return; }
        if (!validateConsultStep2()) { setStep(2); return; }
        if (!validateConsultStep3()) { setStep(3); return; }

        activeSubmitBtn = document.getElementById('btn-submit-consultation');
        activeSpinner = document.getElementById('consultation-spinner');
        defaultBtnText = 'Confirm Site Consultation';
      }

      if (activeSubmitBtn) activeSubmitBtn.disabled = true;
      if (activeSpinner) activeSpinner.style.display = 'inline-block';
      const btnText = activeSubmitBtn?.querySelector('.btn-text');
      if (btnText) btnText.textContent = (currentMode === 'rfq') ? 'Submitting RFP...' : 'Scheduling...';
      clearErrors();

      const formData = new FormData(form);

      fetch('commercial_consultation.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json' }
      })
        .then(async res => {
          const data = await res.json();
          if (!res.ok) throw data;
          return data;
        })
        .then(data => {
          if (data.success) {
            form.style.display = 'none';
            if (successPanel) {
              successPanel.style.display = 'block';

              if (data.lead_type === 'rfq') {
                if (successTitle) successTitle.textContent = 'Quote Request Submitted!';
                if (successDesc) successDesc.textContent = 'Thank you! Your Request-for-Quote has been received. Our commercial sales engineering team will review your specifications and deliver a formal equipment proposal.';
                if (successSummary) {
                  successSummary.innerHTML = `
                    <div><strong>Company:</strong> ${escapeHtml(data.company_name || '')}</div>
                    <div><strong>Inquiry Reference:</strong> #RFQ-${data.lead_id || Date.now()}</div>
                  `;
                }
                if (rfqDisclaimer) rfqDisclaimer.style.display = 'block';
              } else {
                if (successTitle) successTitle.textContent = 'Consultation Request Confirmed!';
                if (successDesc) successDesc.textContent = 'Thank you! Your commercial solar audit booking has been received. A dedicated solar systems engineer will review your facility\'s satellite profile and contact you shortly.';
                if (successSummary) {
                  const comp = document.getElementById('company_name')?.value || '';
                  const dt = document.getElementById('preferred_date')?.value || '';
                  const sl = document.getElementById('preferred_time_slot')?.value || '';
                  successSummary.innerHTML = `
                    <div><strong>Facility:</strong> ${escapeHtml(comp)}</div>
                    <div><strong>Preferred Visit:</strong> ${escapeHtml(dt)} (${escapeHtml(sl)})</div>
                    <div><strong>Confirmation Reference:</strong> #COMM-${data.lead_id || Date.now()}</div>
                  `;
                }
                if (rfqDisclaimer) rfqDisclaimer.style.display = 'none';
              }
            }
          }
        })
        .catch(err => {
          console.error('Submission failed:', err);
          const msg = (err && err.message) ? err.message : 'Submission failed. Please check your network and try again.';
          if (err && err.errors && typeof err.errors === 'object') {
            const errorKeys = Object.keys(err.errors);
            const errorList = Object.values(err.errors);

            if (errorAlert) {
              let html = '<strong>' + escapeHtml(msg) + '</strong>';
              if (errorList.length > 0) {
                html += '<ul style="margin: 6px 0 0 18px; padding: 0; font-size: 0.85rem; line-height: 1.4;">' +
                  errorList.map(e => '<li>' + escapeHtml(e) + '</li>').join('') +
                  '</ul>';
              }
              errorAlert.innerHTML = html;
              errorAlert.style.display = 'block';
            }

            errorKeys.forEach(key => {
              showFieldError(key, err.errors[key]);
              showFieldError('rfq_' + key, err.errors[key]);
            });

            // Auto-navigate user to the earliest step containing an error
            const step1Fields = ['company_name', 'consult_street', 'consult_province', 'consult_city', 'consult_postal', 'facility_type', 'power_supply', 'business_registration_type', 'rfq_company_name', 'rfq_business_registration_type'];
            const step2Fields = ['contact_person', 'corporate_email', 'phone_number', 'best_call_time', 'rfq_street', 'rfq_province', 'rfq_city', 'rfq_postal'];
            const step3Fields = ['preferred_date', 'preferred_time_slot', 'access_notes', 'target_timeline', 'rfq_target_timeline', 'rfq_contact_person', 'rfq_corporate_email', 'rfq_phone_number'];

            if (errorKeys.some(k => step1Fields.includes(k) || step1Fields.includes('rfq_' + k))) {
              setStep(1);
            } else if (errorKeys.some(k => step2Fields.includes(k) || step2Fields.includes('rfq_' + k))) {
              setStep(2);
            } else if (errorKeys.some(k => step3Fields.includes(k) || step3Fields.includes('rfq_' + k))) {
              setStep(3);
            }
          } else if (errorAlert) {
            errorAlert.textContent = msg;
            errorAlert.style.display = 'block';
          }
        })
        .finally(() => {
          if (activeSubmitBtn) activeSubmitBtn.disabled = false;
          if (activeSpinner) activeSpinner.style.display = 'none';
          if (btnText) btnText.textContent = defaultBtnText;
        });
    });

    // Close & Trigger bindings
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', closeModal);

    if (closeSuccessBtn) {
      closeSuccessBtn.addEventListener('click', () => {
        closeModal();
        setTimeout(resetModal, 350);
      });
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal && modal.classList.contains('open')) {
        closeModal();
      }
    });

    // Global click delegation for triggers
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('button, a');
      if (!btn) return;

      const txt = (btn.textContent || '').trim().toUpperCase();
      const isRfqClass = btn.classList.contains('rfq-trigger-btn');
      const isConsultClass = btn.classList.contains('consultation-trigger-btn') || btn.id === 'consultation-trigger';

      if (isRfqClass || txt === 'CONTACT SALES' || txt === 'REQUEST QUOTE') {
        e.preventDefault();
        openModal('rfq');
      } else if (isConsultClass || txt === 'BOOK A CONSULTATION') {
        e.preventDefault();
        openModal('consultation');
      }
    });

    function escapeHtml(str) {
      if (!str) return '';
      const d = document.createElement('div');
      d.textContent = str;
      return d.innerHTML;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setup);
  } else {
    setup();
  }
})();

