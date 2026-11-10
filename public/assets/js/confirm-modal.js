/**
 * public/assets/js/confirm-modal.js
 * Centralized, reusable confirmation modal component for Apex Diurnal.
 *
 * API:
 *   window.showApexConfirm({
 *     title: 'Confirm Action',
 *     message: 'Are you sure you want to proceed?',
 *     confirmText: 'Confirm',
 *     cancelText: 'Cancel',
 *     variant: 'primary' | 'danger' | 'warning',
 *     onConfirm: function () { ... },
 *     onCancel: function () { ... }
 *   });
 *
 *   window.apexConfirmForm(event, {
 *     title: 'Delete Personnel',
 *     message: 'Are you sure?',
 *     confirmText: 'Delete',
 *     variant: 'danger'
 *   });
 */
(function () {
  'use strict';

  var MODAL_ID = 'apex-unified-confirm-modal';
  var STYLE_ID = 'apex-unified-confirm-styles';
  var activeCallback = null;
  var activeCancelCallback = null;

  function injectStyles() {
    if (document.getElementById(STYLE_ID)) return;
    var style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = [
      '#' + MODAL_ID + ' {',
      '  position: fixed;',
      '  inset: 0;',
      '  z-index: 99999;',
      '  display: flex;',
      '  align-items: center;',
      '  justify-content: center;',
      '  padding: 1.25rem;',
      '  background: rgba(10, 37, 64, 0.62);',
      '  backdrop-filter: blur(4px);',
      '  -webkit-backdrop-filter: blur(4px);',
      '  opacity: 0;',
      '  visibility: hidden;',
      '  pointer-events: none;',
      '  transition: opacity 0.2s ease, visibility 0.2s ease;',
      '  font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;',
      '}',
      '#' + MODAL_ID + '.apex-confirm-open {',
      '  opacity: 1;',
      '  visibility: visible;',
      '  pointer-events: auto;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-dialog {',
      '  width: 100%;',
      '  max-width: 440px;',
      '  background: #ffffff;',
      '  border-radius: 12px;',
      '  overflow: hidden;',
      '  box-shadow: 0 22px 45px -12px rgba(10, 37, 64, 0.38), 0 0 0 1px rgba(10, 37, 64, 0.08);',
      '  transform: translateY(12px) scale(0.97);',
      '  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);',
      '}',
      '#' + MODAL_ID + '.apex-confirm-open .apex-confirm-dialog {',
      '  transform: translateY(0) scale(1);',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-header {',
      '  background: #0A2540;',
      '  color: #ffffff;',
      '  padding: 1.05rem 1.35rem;',
      '  display: flex;',
      '  align-items: center;',
      '  justify-content: space-between;',
      '  gap: 0.75rem;',
      '  border-bottom: 3px solid #FDE047;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-header-left {',
      '  display: flex;',
      '  align-items: center;',
      '  gap: 0.7rem;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-badge {',
      '  width: 32px;',
      '  height: 32px;',
      '  border-radius: 8px;',
      '  background: rgba(253, 224, 71, 0.16);',
      '  border: 1px solid rgba(253, 224, 71, 0.45);',
      '  color: #FDE047;',
      '  display: inline-flex;',
      '  align-items: center;',
      '  justify-content: center;',
      '  flex-shrink: 0;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-title {',
      '  font-size: 1rem;',
      '  font-weight: 700;',
      '  letter-spacing: -0.01em;',
      '  color: #ffffff;',
      '  margin: 0;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-close {',
      '  background: transparent;',
      '  border: none;',
      '  color: rgba(255, 255, 255, 0.72);',
      '  cursor: pointer;',
      '  padding: 0.25rem;',
      '  border-radius: 6px;',
      '  display: inline-flex;',
      '  align-items: center;',
      '  justify-content: center;',
      '  transition: color 0.15s ease, background 0.15s ease;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-close:hover {',
      '  color: #FDE047;',
      '  background: rgba(255, 255, 255, 0.08);',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-body {',
      '  padding: 1.35rem 1.4rem 1.2rem;',
      '  color: #1e293b;',
      '  font-size: 0.92rem;',
      '  line-height: 1.55;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-message {',
      '  margin: 0;',
      '  color: #334155;',
      '  font-weight: 500;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-footer {',
      '  padding: 0.9rem 1.4rem;',
      '  background: #f8fafc;',
      '  border-top: 1px solid #e2e8f0;',
      '  display: flex;',
      '  align-items: center;',
      '  justify-content: flex-end;',
      '  gap: 0.65rem;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-btn {',
      '  appearance: none;',
      '  font-family: inherit;',
      '  font-size: 0.84rem;',
      '  font-weight: 700;',
      '  padding: 0.55rem 1.15rem;',
      '  border-radius: 7px;',
      '  cursor: pointer;',
      '  transition: all 0.15s ease;',
      '  display: inline-flex;',
      '  align-items: center;',
      '  justify-content: center;',
      '  gap: 0.4rem;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-btn-cancel {',
      '  background: #ffffff;',
      '  color: #0A2540;',
      '  border: 1px solid #cbd5e1;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-btn-cancel:hover {',
      '  background: #f1f5f9;',
      '  border-color: #94a3b8;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-btn-confirm {',
      '  background: #FDE047;',
      '  color: #0A2540;',
      '  border: 1px solid #facc15;',
      '  box-shadow: 0 2px 6px rgba(10, 37, 64, 0.12);',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-btn-confirm:hover {',
      '  background: #facc15;',
      '  transform: translateY(-1px);',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-btn-confirm.is-danger {',
      '  background: #0A2540;',
      '  color: #FDE047;',
      '  border: 1px solid #0A2540;',
      '}',
      '#' + MODAL_ID + ' .apex-confirm-btn-confirm.is-danger:hover {',
      '  background: #132F4C;',
      '}'
    ].join('\n');
    document.head.appendChild(style);
  }

  function ensureModalElement() {
    injectStyles();
    var existing = document.getElementById(MODAL_ID);
    if (existing) return existing;

    var overlay = document.createElement('div');
    overlay.id = MODAL_ID;
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', MODAL_ID + '-title');
    overlay.innerHTML = [
      '<div class="apex-confirm-dialog">',
      '  <div class="apex-confirm-header">',
      '    <div class="apex-confirm-header-left">',
      '      <span class="apex-confirm-badge" aria-hidden="true">',
      '        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">',
      '          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>',
      '          <line x1="12" y1="9" x2="12" y2="13"></line>',
      '          <line x1="12" y1="17" x2="12.01" y2="17"></line>',
      '        </svg>',
      '      </span>',
      '      <h3 class="apex-confirm-title" id="' + MODAL_ID + '-title">Confirm Action</h3>',
      '    </div>',
      '    <button type="button" class="apex-confirm-close" data-apex-confirm-dismiss aria-label="Close modal">',
      '      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">',
      '        <line x1="18" y1="6" x2="6" y2="18"></line>',
      '        <line x1="6" y1="6" x2="18" y2="18"></line>',
      '      </svg>',
      '    </button>',
      '  </div>',
      '  <div class="apex-confirm-body">',
      '    <p class="apex-confirm-message" id="' + MODAL_ID + '-message"></p>',
      '  </div>',
      '  <div class="apex-confirm-footer">',
      '    <button type="button" class="apex-confirm-btn apex-confirm-btn-cancel" id="' + MODAL_ID + '-cancel" data-apex-confirm-dismiss>Cancel</button>',
      '    <button type="button" class="apex-confirm-btn apex-confirm-btn-confirm" id="' + MODAL_ID + '-confirm">Confirm</button>',
      '  </div>',
      '</div>'
    ].join('\n');

    document.body.appendChild(overlay);

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay || e.target.closest('[data-apex-confirm-dismiss]')) {
        closeApexConfirm(false);
      }
    });

    var confirmBtn = document.getElementById(MODAL_ID + '-confirm');
    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        closeApexConfirm(true);
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('apex-confirm-open')) {
        closeApexConfirm(false);
      }
    });

    return overlay;
  }

  function closeApexConfirm(confirmed) {
    var overlay = document.getElementById(MODAL_ID);
    if (overlay) {
      overlay.classList.remove('apex-confirm-open');
    }
    var cb = activeCallback;
    var cancelCb = activeCancelCallback;
    activeCallback = null;
    activeCancelCallback = null;

    if (confirmed && typeof cb === 'function') {
      cb();
    } else if (!confirmed && typeof cancelCb === 'function') {
      cancelCb();
    }
  }

  /**
   * Opens the reusable Apex Diurnal confirmation modal.
   *
   * @param {Object} options
   * @param {string} [options.title]
   * @param {string} options.message
   * @param {string} [options.confirmText]
   * @param {string} [options.cancelText]
   * @param {string} [options.variant] - 'primary' | 'danger' | 'warning'
   * @param {Function} [options.onConfirm]
   * @param {Function} [options.onCancel]
   */
  window.showApexConfirm = function (options) {
    var opts = options || {};
    var overlay = ensureModalElement();
    var titleEl = document.getElementById(MODAL_ID + '-title');
    var msgEl = document.getElementById(MODAL_ID + '-message');
    var confirmBtn = document.getElementById(MODAL_ID + '-confirm');
    var cancelBtn = document.getElementById(MODAL_ID + '-cancel');

    if (titleEl) titleEl.textContent = opts.title || 'Confirm Action';
    if (msgEl) msgEl.textContent = opts.message || 'Are you sure you want to proceed?';
    if (confirmBtn) {
      confirmBtn.textContent = opts.confirmText || 'Confirm';
      confirmBtn.classList.toggle('is-danger', opts.variant === 'danger');
    }
    if (cancelBtn) {
      cancelBtn.textContent = opts.cancelText || 'Cancel';
    }

    activeCallback = typeof opts.onConfirm === 'function' ? opts.onConfirm : null;
    activeCancelCallback = typeof opts.onCancel === 'function' ? opts.onCancel : null;

    overlay.classList.add('apex-confirm-open');
    if (confirmBtn) {
      setTimeout(function () {
        confirmBtn.focus();
      }, 40);
    }
  };

  /**
   * Reusable form/button handler that replaces inline `return confirm(...)`.
   */
  window.apexConfirmForm = function (event, options) {
    var opts = typeof options === 'string' ? { message: options } : (options || {});
    var target = event && event.currentTarget ? event.currentTarget : (event && event.target ? event.target : null);
    var form = target && target.tagName === 'FORM' ? target : (target && target.closest ? target.closest('form') : null);

    if (form && form.dataset.apexConfirmed === '1') {
      delete form.dataset.apexConfirmed;
      return true;
    }

    if (event && typeof event.preventDefault === 'function') {
      event.preventDefault();
    }
    if (event && typeof event.stopPropagation === 'function') {
      event.stopPropagation();
    }

    window.showApexConfirm({
      title: opts.title || 'Confirm Action',
      message: opts.message || 'Are you sure you want to proceed?',
      confirmText: opts.confirmText || 'Confirm',
      cancelText: opts.cancelText || 'Cancel',
      variant: opts.variant || 'primary',
      onConfirm: function () {
        if (typeof opts.onConfirm === 'function') {
          opts.onConfirm();
        } else if (form) {
          form.dataset.apexConfirmed = '1';
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
          } else {
            form.submit();
          }
        }
      }
    });

    return false;
  };
})();

