/**
 * admin/orders/orders.js - Orders Feature Client Scripts
 */
function openStatusModal(orderId, orderNum, defaultStatus) {
  const orderIdEl = document.getElementById('modal-order-id');
  if (orderIdEl) orderIdEl.value = orderId;
  const titleEl = document.getElementById('modal-order-title');
  if (titleEl && orderNum) titleEl.textContent = 'Process Order: ' + orderNum;
  const statusEl = document.getElementById('modal-new-status');
  if (statusEl) statusEl.value = defaultStatus;
  if (typeof openAdminModal === 'function') {
    openAdminModal('status-modal');
  }
}

window.openStatusModal = openStatusModal;
