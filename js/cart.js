/**
 * Apex Diurnal - Shopping Cart Controller
 */

// Authentication check for cart operations
function isUserAuthenticated() {
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
      if (err.message !== 'Authentication required') {
        if (typeof showToast === 'function') {
          showToast('Cart error. Is XAMPP running?');
        }
      }
      throw err;
    });
}

function addToCart(id, qty) {
  if (!isUserAuthenticated()) {
    if (typeof showToast === 'function') {
      showToast('Please log in to add items to cart', true);
    }
    const redirect = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = `login.php?redirect=${redirect}`;
    return;
  }
  fetchCart('add', id, qty || 1).then(() => {
    if (typeof showToast === 'function') {
      showToast('Added to cart');
    }
  });
}

function changeQty(id, qty) {
  if (!isUserAuthenticated()) {
    if (typeof showToast === 'function') {
      showToast('Please log in to modify cart', true);
    }
    const redirect = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = `login.php?redirect=${redirect}`;
    return;
  }
  fetchCart('update', id, qty);
}

function removeItem(id) {
  if (!isUserAuthenticated()) {
    if (typeof showToast === 'function') {
      showToast('Please log in to modify cart', true);
    }
    const redirect = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = `login.php?redirect=${redirect}`;
    return;
  }
  fetchCart('remove', id, 1).then(() => {
    if (typeof showToast === 'function') {
      showToast('Removed from cart');
    }
  });
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

// Expose globally for inline onclick
window.addToCart = addToCart;
window.openCart = openCart;
window.closeCart = closeCart;
window.changeQty = changeQty;
window.removeItem = removeItem;
window.renderCart = renderCart;
window.updateCartBadge = updateCartBadge;

document.addEventListener('DOMContentLoaded', function () {
  try {
    if (typeof CART_INITIAL !== 'undefined') {
      renderCart(CART_INITIAL);
      if (parseInt(CART_INITIAL.itemCount || 0) === 0) updateCartBadge(0);
      else updateCartBadge(CART_INITIAL.itemCount);
    }
  } catch (e) {
    console.error('Error initializing cart UI:', e);
  }

  try {
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
        if (confirm('Clear your cart?')) {
          fetchCart('clear').then(() => {
            if (typeof showToast === 'function') showToast('Cart cleared');
          });
        }
      });
    }

    if (cartCheckout) {
      cartCheckout.addEventListener('click', function (e) {
        try {
          const badge = document.getElementById('cart-count');
          const count = parseInt(badge?.getAttribute('data-count') || badge?.textContent || '0', 10);
          const isEmpty = count <= 0 || cartCheckout.classList.contains('disabled') || cartCheckout.getAttribute('aria-disabled') === 'true';
          if (isEmpty) {
            e.preventDefault();
            if (typeof showToast === 'function') {
              showToast('⚠️ You cannot checkout without an order! Please add products to your cart first.', true);
            }
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
});
