/**
 * js/session-timer.js - Client-Side Inactivity Tracking & Keepalive
 * Apex Diurnal Solar Panels
 */

(function () {
  'use strict';

  // Check if user or admin is authenticated
  function isAuthenticated() {
    if (typeof window.USER_LOGGED_IN !== 'undefined') {
      return Boolean(window.USER_LOGGED_IN);
    }
    if (document.getElementById('user-dropdown') || document.getElementById('user-account-btn')) {
      return true;
    }
    return document.cookie.split(';').some(c => c.trim().startsWith('app_logged_in=1'));
  }

  if (!isAuthenticated()) {
    return; // Don't run idle timer for guests
  }

  const IDLE_TIMEOUT_MS = 30 * 60 * 1000;       // 30 minutes
  const WARNING_THRESHOLD_MS = 27 * 60 * 1000;   // 27 minutes (warning 3 mins before)
  const PING_INTERVAL_MS = 5 * 60 * 1000;        // 5 minutes background keepalive if active

  let lastActivityTime = Date.now();
  let warningModal = null;
  let countdownInterval = null;
  let pingTimer = null;

  // Determine relative path to session.php
  function getSessionEndpoint() {
    const p = window.location.pathname;
    if (p.includes('/cart/') || p.includes('/account/') || p.includes('/auth/') || p.includes('/services/')) {
      return '../session.php?action=ping';
    }
    if (p.includes('/admin/orders/') || p.includes('/admin/inventory/') || p.includes('/admin/schedule/') || p.includes('/admin/quotes/')) {
      return '../../session.php?action=ping';
    }
    if (p.includes('/admin/')) {
      return '../session.php?action=ping';
    }
    return 'session.php?action=ping';
  }

  function getLoginEndpoint() {
    const p = window.location.pathname;
    const redirect = encodeURIComponent(window.location.pathname + window.location.search);
    if (p.includes('/admin/')) {
      return (p.includes('/admin/orders/') || p.includes('/admin/inventory/') || p.includes('/admin/schedule/') || p.includes('/admin/quotes/'))
        ? `../login.php?redirect=${redirect}`
        : `login.php?redirect=${redirect}`;
    }
    const isInsideSubdir = p.includes('/cart/') || p.includes('/account/') || p.includes('/auth/') || p.includes('/services/');
    return `${isInsideSubdir ? '../auth/login.php' : 'auth/login.php'}?redirect=${redirect}`;
  }

  // Record user activity
  function recordActivity() {
    const now = Date.now();
    const elapsedSinceLast = now - lastActivityTime;
    lastActivityTime = now;

    // If warning was active and user interacts, dismiss warning and refresh session
    if (warningModal && warningModal.classList.contains('active')) {
      dismissWarningAndPing();
    }
  }

  // Ping server to keep session alive
  function pingServer() {
    fetch(getSessionEndpoint(), { method: 'GET', credentials: 'same-origin' })
      .then(res => res.json())
      .catch(() => {});
  }

  function dismissWarningAndPing() {
    if (warningModal) {
      warningModal.classList.remove('active');
    }
    if (countdownInterval) {
      clearInterval(countdownInterval);
      countdownInterval = null;
    }
    pingServer();
  }

  // Create or get the warning modal element
  function createWarningModal() {
    if (warningModal) return warningModal;

    const modal = document.createElement('div');
    modal.id = 'session-timeout-modal';
    modal.className = 'session-timeout-overlay';
    modal.innerHTML = `
      <div class="session-timeout-card" role="dialog" aria-labelledby="timeout-title" aria-modal="true">
        <div class="session-timeout-icon">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
        </div>
        <h3 id="timeout-title" class="session-timeout-title">Session Expiring Soon</h3>
        <p class="session-timeout-desc">
          You have been inactive. For your security, your session will automatically end in
          <strong id="session-timeout-countdown" style="color:#b91c1c;">3:00</strong>.
        </p>
        <div class="session-timeout-actions">
          <button type="button" id="session-btn-stay" class="session-btn session-btn-primary">Stay Signed In</button>
          <button type="button" id="session-btn-logout" class="session-btn session-btn-secondary">Sign Out Now</button>
        </div>
      </div>
    `;

    // Inject minimal inline stylesheet for modal
    const style = document.createElement('style');
    style.textContent = `
      .session-timeout-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 30, 58, 0.65);
        backdrop-filter: blur(4px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
        padding: 20px;
      }
      .session-timeout-overlay.active {
        opacity: 1;
        visibility: visible;
      }
      .session-timeout-card {
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        max-width: 420px;
        width: 100%;
        padding: 28px 24px;
        text-align: center;
        transform: scale(0.95);
        transition: transform 0.25s ease;
      }
      .session-timeout-overlay.active .session-timeout-card {
        transform: scale(1);
      }
      .session-timeout-icon {
        width: 56px;
        height: 56px;
        margin: 0 auto 16px;
        border-radius: 50%;
        background: #fef3c7;
        color: #d97706;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .session-timeout-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: #1b335f;
        margin-bottom: 8px;
      }
      .session-timeout-desc {
        font-size: 0.92rem;
        color: #4b5563;
        line-height: 1.5;
        margin-bottom: 22px;
      }
      .session-timeout-actions {
        display: flex;
        gap: 10px;
      }
      .session-btn {
        flex: 1;
        padding: 11px 16px;
        font-size: 0.9rem;
        font-weight: 700;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
      }
      .session-btn-primary {
        background: #fee000;
        color: #1b335f;
      }
      .session-btn-primary:hover {
        background: #edd100;
      }
      .session-btn-secondary {
        background: #f3f4f6;
        color: #4b5563;
      }
      .session-btn-secondary:hover {
        background: #e5e7eb;
      }
    `;

    document.head.appendChild(style);
    document.body.appendChild(modal);

    modal.querySelector('#session-btn-stay').addEventListener('click', dismissWarningAndPing);
    modal.querySelector('#session-btn-logout').addEventListener('click', function () {
      window.location.href = getLoginEndpoint();
    });

    warningModal = modal;
    return modal;
  }

  function showWarning(secondsRemaining) {
    const modal = createWarningModal();
    modal.classList.add('active');

    const countdownEl = modal.querySelector('#session-timeout-countdown');

    function updateCountdown() {
      const now = Date.now();
      const timeLeftMs = IDLE_TIMEOUT_MS - (now - lastActivityTime);
      const secondsLeft = Math.max(0, Math.floor(timeLeftMs / 1000));

      const mins = Math.floor(secondsLeft / 60);
      const secs = secondsLeft % 60;
      if (countdownEl) {
        countdownEl.textContent = `${mins}:${secs < 10 ? '0' : ''}${secs}`;
      }

      if (secondsLeft <= 0) {
        if (countdownInterval) clearInterval(countdownInterval);
        if (countdownEl) countdownEl.textContent = '0:00';
        window.location.href = getLoginEndpoint();
      }
    }

    if (countdownInterval) clearInterval(countdownInterval);
    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
  }

  // Periodic check for idle time
  function checkIdle() {
    const idleTime = Date.now() - lastActivityTime;

    if (idleTime >= IDLE_TIMEOUT_MS) {
      window.location.href = getLoginEndpoint();
      return;
    }

    if (idleTime >= WARNING_THRESHOLD_MS) {
      if (!warningModal || !warningModal.classList.contains('active')) {
        const secondsRemaining = Math.max(0, Math.floor((IDLE_TIMEOUT_MS - idleTime) / 1000));
        showWarning(secondsRemaining);
      }
    }
  }

  // Bind activity listeners
  const activityEvents = ['mousedown', 'keydown', 'scroll', 'touchstart'];
  activityEvents.forEach(evt => {
    window.addEventListener(evt, recordActivity, { passive: true });
  });

  // Check every 10 seconds
  setInterval(checkIdle, 10000);

  // Background keepalive every 5 minutes if active
  pingTimer = setInterval(function () {
    const idleTime = Date.now() - lastActivityTime;
    if (idleTime < PING_INTERVAL_MS) {
      pingServer();
    }
  }, PING_INTERVAL_MS);
})();
