// ---- Global cart helpers (called from onclick) ----
let cartToastTimer;
function showToast(msg) {
  const t = document.getElementById('cart-toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(cartToastTimer);
  cartToastTimer = setTimeout(() => t.classList.remove('show'), 2200);
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
  list.innerHTML = '';
  if (!data.items || data.items.length === 0) {
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
function fetchCart(action, productId, quantity) {
  const body = new URLSearchParams();
  body.set('action', action);
  if (productId) body.set('product_id', productId);
  if (quantity !== undefined) body.set('quantity', quantity);
  return fetch('cart_action.php', { method: 'POST', body: body, headers: { 'Accept': 'application/json' } })
    .then(r => { if (!r.ok) throw new Error('Cart request failed ' + r.status); return r.json(); })
    .then(data => { renderCart(data); return data; })
    .catch(err => { console.error(err); showToast('Cart error. Is XAMPP running?'); throw err; });
}
function addToCart(id, qty) {
  fetchCart('add', id, qty || 1).then(() => showToast('Added to cart'));
}
function changeQty(id, qty) { fetchCart('update', id, qty); }
function removeItem(id) { fetchCart('remove', id, 1).then(() => showToast('Removed from cart')); }
// expose globally for inline onclick
window.addToCart = addToCart;
window.openCart = openCart;
window.closeCart = closeCart;
window.changeQty = changeQty;
window.removeItem = removeItem;

document.addEventListener('DOMContentLoaded', function () {
  // --- Init cart UI from PHP ---
  try { renderCart(CART_INITIAL); } catch (e) { console.error(e); }
  if (parseInt(CART_INITIAL.itemCount || 0) === 0) updateCartBadge(0); else updateCartBadge(CART_INITIAL.itemCount);

  const cartTrigger = document.getElementById('cart-trigger');
  const cartOverlay = document.getElementById('cart-overlay');
  const cartClose = document.getElementById('cart-close');
  const cartClear = document.getElementById('cart-clear');
  const cartCheckout = document.getElementById('cart-checkout');
  if (cartTrigger) cartTrigger.addEventListener('click', openCart);
  if (cartOverlay) cartOverlay.addEventListener('click', closeCart);
  if (cartClose) cartClose.addEventListener('click', closeCart);
  if (cartClear) cartClear.addEventListener('click', function () {
    if (confirm('Clear your cart?')) fetchCart('clear').then(() => showToast('Cart cleared'));
  });
  if (cartCheckout) cartCheckout.addEventListener('click', function () {
    window.location.href = 'checkout.php';
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeCart(); });

  const toggle = document.getElementById('search-toggle');
  const bar = document.getElementById('search-bar');
  const input = document.getElementById('search-input');
  const closeBtn = document.getElementById('search-close');
  const container = document.getElementById('search-container');
  const results = document.getElementById('search-results');
  const cards = document.querySelectorAll('.product-card');
  const navLinks = document.querySelectorAll('.nav-link');
  const footerLinks = document.querySelectorAll('.footer-links a');

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
});
