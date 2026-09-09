/**
 * js/auth-modal.js - Authentication Required Error Handling Modal
 * Apex Diurnal Solar Solutions
 */

(function () {
  'use strict';

  function getBaseUrl() {
    let base = '';
    const script = document.querySelector('script[src*="auth-modal.js"]');
    if (script && script.src) {
      base = script.src.replace(/js\/auth-modal\.js(?:\?.*)?$/i, '');
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

  // Check if current user is logged in
  window.isUserAuthenticated = function () {
    if (typeof window.USER_LOGGED_IN !== 'undefined') {
      return Boolean(window.USER_LOGGED_IN);
    }
    if (document.getElementById('user-dropdown') || document.getElementById('user-account-btn')) {
      return true;
    }
    return document.cookie.split(';').some(item => item.trim().startsWith('app_logged_in=1'));
  };

  const CONTEXT_MESSAGES = {
    consultation: {
      title: 'Sign In to Book a Consultation',
      desc: 'An active account is required to schedule an on-site solar engineering audit and structural feasibility assessment.'
    },
    commercial: {
      title: 'Sign In for Commercial Grids',
      desc: 'An active account is required to request corporate RFQs, utility-scale specifications, and commercial grid quotations.'
    },
    buy: {
      title: 'Sign In to Purchase Products',
      desc: 'Please sign in or create an account to purchase products, add solar hardware to your cart, and track your orders.'
    },
    default: {
      title: 'Sign In Required',
      desc: 'You need to be signed in to perform this action. Please sign in to your account or create a new one to continue.'
    }
  };

  function showAuthRequiredModal(context, customDesc) {
    const overlay = document.getElementById('auth-modal-overlay');
    const modal = document.getElementById('auth-modal');
    if (!overlay || !modal) return;

    const config = CONTEXT_MESSAGES[context] || CONTEXT_MESSAGES.default;

    const titleEl = document.getElementById('auth-modal-title');
    const descEl = document.getElementById('auth-modal-desc');
    const loginBtn = document.getElementById('auth-modal-btn-login');
    const registerBtn = document.getElementById('auth-modal-btn-register');

    if (titleEl) titleEl.textContent = config.title;
    if (descEl) descEl.textContent = customDesc || config.desc;

    // Set dynamic redirect URLs
    const currentPath = window.location.pathname + window.location.search + (window.location.hash || '');
    const redirectParam = encodeURIComponent(currentPath);
    const baseUrl = getBaseUrl();

    if (loginBtn) {
      loginBtn.href = `${baseUrl}auth/login.php?redirect=${redirectParam}`;
    }
    if (registerBtn) {
      registerBtn.href = `${baseUrl}auth/register.php?redirect=${redirectParam}`;
    }

    overlay.style.display = 'block';
    modal.style.display = 'flex';
    requestAnimationFrame(() => {
      overlay.classList.add('active');
      modal.classList.add('open');
    });

    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeAuthRequiredModal() {
    const overlay = document.getElementById('auth-modal-overlay');
    const modal = document.getElementById('auth-modal');
    if (!overlay || !modal) return;

    overlay.classList.remove('active');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';

    setTimeout(() => {
      if (!modal.classList.contains('open')) {
        overlay.style.display = 'none';
        modal.style.display = 'none';
      }
    }, 320);
  }

  // Expose global methods
  window.showAuthRequiredModal = showAuthRequiredModal;
  window.closeAuthRequiredModal = closeAuthRequiredModal;

  // Bind close buttons and keyboard listeners on DOMContentLoaded
  function initAuthModalEvents() {
    const closeBtn = document.getElementById('auth-modal-close');
    const dismissBtn = document.getElementById('auth-modal-btn-dismiss');
    const overlay = document.getElementById('auth-modal-overlay');

    if (closeBtn) closeBtn.addEventListener('click', closeAuthRequiredModal);
    if (dismissBtn) dismissBtn.addEventListener('click', closeAuthRequiredModal);
    if (overlay) {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
          closeAuthRequiredModal();
        }
      });
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const modal = document.getElementById('auth-modal');
        if (modal && modal.classList.contains('open')) {
          closeAuthRequiredModal();
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAuthModalEvents);
  } else {
    initAuthModalEvents();
  }
})();
