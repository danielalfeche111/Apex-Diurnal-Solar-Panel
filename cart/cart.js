/**
 * cart/cart.js - Apex Diurnal Cart Client Operations
 * Handles cart state, drawer interactions, badge updates, and server AJAX sync
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

// Authentication check for cart operations
function isUserAuthenticated() {
  if (typeof window.USER_LOGGED_IN !== 'undefined') {
    return Boolean(window.USER_LOGGED_IN);
  }
  if (document.getElementById('user-dropdown') || document.getElementById('user-account-btn')) {
    return true;
  }
  return document.cookie.split(';').some(item => {
    const trimmed = item.trim();
    return trimmed.startsWith('app_logged_in=1');
  });
}

// Base URL helper to ensure links and endpoints resolve correctly from any directory or subfolder
function getAppBaseUrl() {
  let base = '';
  const script = document.querySelector('script[src*="cart/cart.js"], script[src*="cart.js"]');
  if (script && script.src) {
    base = script.src.replace(/cart\/cart\.js(?:\?.*)?$/i, '');
  } else {
    const path = window.location.pathname;
    const match = path.match(/^(.*?\/(?:account|cart|services|auth|admin|products))\//i);
    if (match) {
      base = window.location.origin + match[1].substring(0, match[1].lastIndexOf('/') + 1);
    } else {
      const dir = path.substring(0, path.lastIndexOf('/') + 1);
      base = window.location.origin + (dir || '/');
    }
  }
  if (!base.endsWith('/')) {
    base += '/';
  }
  return base;
}

// Enhanced fetchCart with notices handling and automatic drawer sync
function fetchCart(action, productId, quantity) {
  const body = new URLSearchParams();
  body.set('action', action);
  if (productId) body.set('product_id', productId);
  if (quantity !== undefined) body.set('quantity', quantity);

  const actionEndpoint = getAppBaseUrl() + 'cart/cart_action.php';

  return fetch(actionEndpoint, {
    method: 'POST',
    body: body,
    headers: { 'Accept': 'application/json' }
  })
    .then(r => {
      if (!r.ok) {
        throw new Error('Cart request failed ' + r.status);
      }
      return r.json();
    })
    .then(data => {
      if (data && data.requiresAuth) {
        const redirectUrl = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.href = getAppBaseUrl() + (data.redirect || 'auth/register.php') + '?redirect=' + redirectUrl;
        return data;
      }
      renderCart(data);
      if (Array.isArray(data.notices) && data.notices.length > 0) {
        data.notices.forEach(notice => {
          if (notice && notice.message) {
            showToast(notice.message, true);
          }
        });
      }
      return data;
    })
    .catch(err => {
      console.error('Cart action failed:', err);
      showToast('Cart update failed: ' + (err.message || 'Please try again'), true);
      throw err;
    });
}

function addToCart(id, qty) {
  if (!isUserAuthenticated()) {
    const redirectUrl = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = getAppBaseUrl() + 'auth/register.php?redirect=' + redirectUrl;
    return;
  }
  fetchCart('add', id, qty || 1).then(() => showToast('Added to cart'));
}

function changeQty(id, qty) {
  fetchCart('update', id, qty);
}

function removeItem(id) {
  fetchCart('remove', id, 1).then(() => showToast('Removed from cart'));
}

function openCart() {
  document.getElementById('cart-drawer')?.classList.add('open');
  document.getElementById('cart-overlay')?.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeCart() {
  document.getElementById('cart-drawer')?.classList.remove('open');
  document.getElementById('cart-overlay')?.classList.remove('active');
  document.body.style.overflow = '';
}

function updateCartBadge(count) {
  const badge = document.getElementById('cart-count');
  if (!badge) return;
  const num = parseInt(count, 10) || 0;
  badge.textContent = num > 0 ? num : '';
  badge.setAttribute('data-count', num);
  if (num > 0) {
    badge.style.display = '';
  } else {
    badge.style.display = 'none';
  }
}

function renderCart(data) {
  if (!data) return;

  const itemCount = parseInt(data.itemCount !== undefined ? data.itemCount : (data.items ? data.items.length : 0), 10) || 0;
  updateCartBadge(itemCount);

  const container = document.getElementById('cart-items') || document.getElementById('cart-items-container');
  const drawerCountEl = document.getElementById('cart-drawer-count');
  const subtotalEl = document.getElementById('cart-subtotal');
  const totalEl = document.getElementById('cart-total');
  const taxEl = document.getElementById('cart-tax');
  const checkoutBtn = document.getElementById('cart-checkout');
  const clearBtn = document.getElementById('cart-clear');
  const emptyHint = document.getElementById('cart-empty-hint');

  if (drawerCountEl) {
    drawerCountEl.textContent = `${itemCount} item${itemCount === 1 ? '' : 's'}`;
  }

  // Format grand total cleanly from grandTotal or total
  const rawTotal = data.grandTotal !== undefined ? data.grandTotal : (data.total !== undefined ? data.total : 0);
  let formattedTotal = '0.00';
  if (typeof rawTotal === 'number') {
    formattedTotal = rawTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  } else if (typeof rawTotal === 'string') {
    const cleaned = rawTotal.replace(/[₱$PHPphp,\s]/g, '');
    const num = parseFloat(cleaned);
    formattedTotal = isNaN(num) ? rawTotal : num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  if (totalEl) totalEl.innerHTML = '&#8369;' + formattedTotal;

  if (subtotalEl) {
    const rawSubtotal = data.subtotal !== undefined ? data.subtotal : rawTotal;
    let subtotalStr = formattedTotal;
    if (typeof rawSubtotal === 'number') {
      subtotalStr = rawSubtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    } else if (typeof rawSubtotal === 'string') {
      const cleaned = rawSubtotal.replace(/[₱$PHPphp,\s]/g, '');
      const num = parseFloat(cleaned);
      subtotalStr = isNaN(num) ? rawSubtotal : num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    subtotalEl.innerHTML = '&#8369;' + subtotalStr;
  }

  if (taxEl) {
    const rawTax = data.tax !== undefined ? data.tax : 0;
    const taxNum = typeof rawTax === 'number' ? rawTax : parseFloat(String(rawTax).replace(/[^\d.-]/g, '')) || 0;
    taxEl.innerHTML = '&#8369;' + taxNum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  const hasItems = itemCount > 0 && Array.isArray(data.items) && data.items.length > 0;

  if (checkoutBtn) {
    if (!hasItems) {
      checkoutBtn.classList.add('disabled');
      checkoutBtn.setAttribute('aria-disabled', 'true');
      checkoutBtn.setAttribute('title', 'Your cart is empty. Please add items before checking out.');
      checkoutBtn.style.opacity = '0.5';
      checkoutBtn.style.cursor = 'not-allowed';
      if (emptyHint) emptyHint.style.display = 'block';
    } else {
      checkoutBtn.classList.remove('disabled');
      checkoutBtn.setAttribute('aria-disabled', 'false');
      checkoutBtn.setAttribute('title', 'Proceed to Checkout');
      checkoutBtn.style.opacity = '1';
      checkoutBtn.style.cursor = 'pointer';
      if (emptyHint) emptyHint.style.display = 'none';
    }
  }

  if (clearBtn) {
    if (!hasItems) {
      clearBtn.classList.add('disabled');
      clearBtn.setAttribute('aria-disabled', 'true');
      clearBtn.style.opacity = '0.5';
      clearBtn.style.cursor = 'not-allowed';
    } else {
      clearBtn.classList.remove('disabled');
      clearBtn.setAttribute('aria-disabled', 'false');
      clearBtn.style.opacity = '1';
      clearBtn.style.cursor = 'pointer';
    }
  }

  if (!container) return;

  if (!hasItems) {
    container.innerHTML = `
      <div class="cart-empty-state">
        <svg viewBox="0 0 24 24" width="48" height="48" stroke="currentColor" stroke-width="1.5" fill="none" style="margin:0 auto 16px; opacity:0.4; display:block;">
          <circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
        </svg>
        <p>Your cart is empty.</p>
        <button type="button" class="btn btn-yellow" onclick="closeCart()" style="font-size:0.85rem; padding:8px 20px;">Continue Browsing</button>
      </div>`;
    return;
  }

  const baseUrl = getAppBaseUrl();
  const fallbackLogo = baseUrl + 'assets/images/logo-clean.png';

  let html = '';
  data.items.forEach(item => {
    const id = item.id || '';
    const title = item.title || item.name || 'Solar Product';
    let image = item.image || 'assets/images/logo-clean.png';
    if (image && !image.startsWith('http://') && !image.startsWith('https://') && !image.startsWith('/')) {
      const cleanRel = image.replace(/^(\.\.\/|\.\/)+/, '');
      image = baseUrl + cleanRel;
    }
    const alt = item.alt || title;
    const qty = parseInt(item.quantity !== undefined ? item.quantity : (item.qty !== undefined ? item.qty : 1), 10) || 1;

    // Unit price display
    let priceDisplay = item.price;
    if (typeof priceDisplay === 'number') {
      priceDisplay = '&#8369;' + priceDisplay.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    } else if (typeof priceDisplay === 'string') {
      if (!priceDisplay.includes('₱') && !priceDisplay.includes('&#8369;') && !priceDisplay.includes('$')) {
        priceDisplay = '&#8369;' + priceDisplay;
      }
    } else {
      priceDisplay = '&#8369;0.00';
    }

    // Line total display
    let lineTotalDisplay = '';
    if (item.line_total !== undefined) {
      if (typeof item.line_total === 'number') {
        lineTotalDisplay = '&#8369;' + item.line_total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      } else {
        const cleaned = String(item.line_total).replace(/[₱$PHPphp,\s]/g, '');
        const num = parseFloat(cleaned);
        lineTotalDisplay = '&#8369;' + (isNaN(num) ? item.line_total : num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
      }
    } else {
      lineTotalDisplay = priceDisplay;
    }

    html += `
      <div class="cart-item" data-id="${id}">
        <img src="${image}" alt="${alt}" onerror="this.onerror=null; this.src='${fallbackLogo}';">
        <div class="cart-item-info">
          <h4>${title}</h4>
          <div class="cart-item-price">${priceDisplay}</div>
          <div class="cart-item-qty">
            <button type="button" class="qty-btn minus" onclick="changeQty('${id}', ${qty - 1})" aria-label="Decrease quantity">&minus;</button>
            <span class="qty-val">${qty}</span>
            <button type="button" class="qty-btn plus" onclick="changeQty('${id}', ${qty + 1})" aria-label="Increase quantity">+</button>
          </div>
        </div>
        <div style="display:flex; flex-direction:column; align-items:flex-end; justify-content:space-between; height:100%; min-height:56px;">
          <button type="button" class="cart-item-remove" onclick="removeItem('${id}')" title="Remove item" aria-label="Remove item">&times;</button>
          <div class="cart-item-line">${lineTotalDisplay}</div>
        </div>
      </div>`;
  });
  container.innerHTML = html;
}

// Expose functions globally for inline triggers
window.showToast = showToast;
window.addToCart = addToCart;
window.openCart = openCart;
window.closeCart = closeCart;
window.changeQty = changeQty;
window.removeItem = removeItem;
window.renderCart = renderCart;
window.updateCartBadge = updateCartBadge;

document.addEventListener('DOMContentLoaded', function () {
  // Init cart UI from PHP state if present
  if (typeof CART_INITIAL !== 'undefined') {
    try {
      renderCart(CART_INITIAL);
      if (parseInt(CART_INITIAL.itemCount || 0) === 0) updateCartBadge(0);
      else updateCartBadge(CART_INITIAL.itemCount);
      if (Array.isArray(CART_INITIAL.notices) && CART_INITIAL.notices.length > 0) {
        setTimeout(() => {
          CART_INITIAL.notices.forEach(n => {
            if (n && n.message) showToast(n.message, true);
          });
        }, 300);
      }
    } catch (e) {
      console.error('Error initializing cart UI:', e);
    }
  }

  const cartTrigger = document.getElementById('cart-trigger');
  const cartOverlay = document.getElementById('cart-overlay');
  const cartClose = document.getElementById('cart-close');
  const cartClear = document.getElementById('cart-clear');
  const cartCheckout = document.getElementById('cart-checkout');

  if (cartTrigger) cartTrigger.addEventListener('click', openCart);
  if (cartOverlay) cartOverlay.addEventListener('click', closeCart);
  if (cartClose) cartClose.addEventListener('click', closeCart);

  if (cartClear) {
    cartClear.addEventListener('click', function () {
      if (cartClear.classList.contains('disabled') || cartClear.getAttribute('aria-disabled') === 'true') {
        return;
      }
      if (confirm('Clear your cart?')) {
        fetchCart('clear').then(() => showToast('Cart cleared'));
      }
    });
  }

  if (cartCheckout) {
    cartCheckout.addEventListener('click', function (e) {
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
      window.location.href = getAppBaseUrl() + 'cart/checkout.php';
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeCart();
  });
});
