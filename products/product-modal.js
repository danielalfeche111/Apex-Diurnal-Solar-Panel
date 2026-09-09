/**
 * products/product-modal.js - Apex Diurnal Product "Learn More" Detail Modal Controller
 */
(function () {
  'use strict';

  function getCatalog() {
    if (window.PRODUCT_CATALOG && typeof window.PRODUCT_CATALOG === 'object') {
      return window.PRODUCT_CATALOG;
    }
    if (typeof PRODUCT_CATALOG !== 'undefined' && PRODUCT_CATALOG) {
      return PRODUCT_CATALOG;
    }
    return {};
  }

  function getModalElements() {
    const overlay = document.getElementById('product-modal-overlay');
    if (!overlay) return null;
    return {
      overlay: overlay,
      card: overlay.querySelector('.product-modal-card'),
      closeBtn: document.getElementById('product-modal-close'),
      primaryActionBtn: document.getElementById('modal-primary-action'),
      secondaryCloseBtn: document.getElementById('modal-secondary-close'),
      imgEl: document.getElementById('modal-product-img'),
      badgeEl: document.getElementById('modal-product-badge'),
      titleEl: document.getElementById('modal-product-title'),
      priceEl: document.getElementById('modal-product-price'),
      vatEl: document.getElementById('modal-product-vat'),
      descEl: document.getElementById('modal-product-desc'),
      specsListEl: document.getElementById('modal-product-specs-list'),
      specsWrapEl: document.getElementById('modal-product-specs-wrap')
    };
  }

  /**
   * Open modal and populate details for a specific product
   * @param {string} productId
   */
  function openProductModal(productId) {
    const els = getModalElements();
    if (!els || !els.overlay) {
      console.warn('[ProductModal] Modal overlay element not found in DOM');
      return;
    }

    const catalog = getCatalog();
    let product = catalog[productId];

    // Fallback: Extract details from the rendered product card in DOM if catalog lookup is missing
    if (!product) {
      const card = document.querySelector(`[data-id="${productId}"]`)?.closest('.product-card') 
                || document.querySelector(`[data-product-id="${productId}"]`)?.closest('.product-card')
                || document.getElementById(`product-${productId}`);
      if (card) {
        product = {
          id: productId,
          title: card.querySelector('.product-title')?.textContent.trim() || 'Solar Product',
          price: card.querySelector('.product-price')?.textContent.trim() || '₱0.00',
          image: card.querySelector('.card-img')?.getAttribute('src') || '',
          alt: card.querySelector('.card-img')?.getAttribute('alt') || 'Product',
          badge: 'Premium Solar Technology',
          description: 'High-efficiency solar equipment engineered by Apex Diurnal for maximum energy capture and durability.',
          specs: {
            'Quality': 'Tier-1 Certified Solar Components',
            'Warranty': '25-Year Performance Guarantee',
            'Deployment': 'Residential & Commercial Solar Solutions'
          }
        };
      }
    }

    if (!product) {
      console.warn('[ProductModal] Unable to find product data for ID:', productId);
      return;
    }

    // Populate Media
    if (els.imgEl) {
      els.imgEl.src = product.image || '';
      els.imgEl.alt = product.alt || product.title || 'Product Image';
    }
    if (els.badgeEl) {
      els.badgeEl.textContent = product.badge || 'Premium Solar';
      els.badgeEl.style.display = product.badge ? 'inline-block' : 'none';
    }

    // Populate Header Info
    if (els.titleEl) els.titleEl.textContent = product.title || '';
    if (els.priceEl) els.priceEl.textContent = product.price || '';
    if (els.vatEl) {
      const isQuote = product.price && product.price.toLowerCase().includes('call');
      els.vatEl.style.display = isQuote ? 'none' : 'inline';
    }

    // Populate Description
    if (els.descEl) {
      els.descEl.textContent = product.description || 'Engineered by Apex Diurnal for optimal efficiency and long-term durability.';
    }

    // Populate Specifications List
    if (els.specsListEl) {
      els.specsListEl.innerHTML = '';
      const specs = product.specs || {};
      const specEntries = Object.entries(specs);

      if (specEntries.length > 0) {
        if (els.specsWrapEl) els.specsWrapEl.style.display = 'block';
        specEntries.forEach(([key, val]) => {
          const li = document.createElement('li');
          li.className = 'product-modal-spec-item';

          const labelSpan = document.createElement('span');
          labelSpan.className = 'product-modal-spec-label';
          labelSpan.textContent = key;

          const valSpan = document.createElement('span');
          valSpan.className = 'product-modal-spec-val';
          valSpan.textContent = val;

          li.appendChild(labelSpan);
          li.appendChild(valSpan);
          els.specsListEl.appendChild(li);
        });
      } else {
        if (els.specsWrapEl) els.specsWrapEl.style.display = 'none';
      }
    }

    // Configure Primary Action Button
    if (els.primaryActionBtn) {
      const newActionBtn = els.primaryActionBtn.cloneNode(true);
      els.primaryActionBtn.parentNode.replaceChild(newActionBtn, els.primaryActionBtn);

      const isQuote = product.price && product.price.toLowerCase().includes('call');
      const isBooking = product.id === 'installation-booking';

      if (isQuote) {
        newActionBtn.innerHTML = `
          <span>Request Quote / Consultation</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        `;
        newActionBtn.onclick = function () {
          closeProductModal();
          if (typeof window.openConsultationModal === 'function') {
            window.openConsultationModal('rfq');
          }
        };
      } else {
        newActionBtn.innerHTML = `
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
          <span>${isBooking ? 'Book Now' : 'Add to Cart & Checkout'}</span>
        `;
        newActionBtn.onclick = function () {
          const isAuth = (typeof window.isUserAuthenticated === 'function')
            ? window.isUserAuthenticated()
            : Boolean(window.USER_LOGGED_IN);

          if (!isAuth) {
            closeProductModal();
            if (typeof window.showAuthRequiredModal === 'function') {
              window.showAuthRequiredModal('buy');
            }
            return;
          }

          if (typeof window.addToCart === 'function') {
            window.addToCart(product.id);
          }
          closeProductModal();
          if (typeof window.openCart === 'function') {
            window.openCart();
          }
        };
      }
    }

    // Display the modal
    els.overlay.style.display = 'flex';
    void els.overlay.offsetWidth; // Trigger reflow for animation
    els.overlay.classList.add('is-active');
    els.overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    if (els.closeBtn) {
      els.closeBtn.focus();
    }
  }

  /**
   * Close the product modal immediately without exit animation
   */
  function closeProductModal() {
    const overlay = document.getElementById('product-modal-overlay');
    if (!overlay) return;
    overlay.classList.remove('is-active');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.style.display = 'none'; // Instant close, no exit animation or delay
    document.body.style.overflow = '';
  }

  // Make globally available immediately
  window.openProductModal = openProductModal;
  window.closeProductModal = closeProductModal;

  // Bind events once DOM is ready
  function bindModalEvents() {
    const overlay = document.getElementById('product-modal-overlay');
    const closeBtn = document.getElementById('product-modal-close');
    const secondaryCloseBtn = document.getElementById('modal-secondary-close');

    if (closeBtn) closeBtn.addEventListener('click', closeProductModal);
    if (secondaryCloseBtn) secondaryCloseBtn.addEventListener('click', closeProductModal);

    if (overlay) {
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
          closeProductModal();
        }
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay && overlay.classList.contains('is-active')) {
        closeProductModal();
      }
    });

    // Delegated click listener across the document for data-action="learn-more"
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-action="learn-more"]');
      if (btn) {
        e.preventDefault();
        e.stopPropagation();
        const productId = btn.getAttribute('data-product-id') || btn.getAttribute('data-id');
        if (productId) {
          openProductModal(productId);
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindModalEvents);
  } else {
    bindModalEvents();
  }
})();
