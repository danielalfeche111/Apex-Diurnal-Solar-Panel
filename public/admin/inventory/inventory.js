/**
 * admin/inventory/inventory.js - Inventory Feature Client Scripts
 */
let currentModalStock = 0;

function openStockModal(productId, productName, currentStock) {
  currentModalStock = currentStock;
  const prodIdEl = document.getElementById('modal-product-id');
  if (prodIdEl) prodIdEl.value = productId;
  const nameEl = document.getElementById('modal-product-name');
  if (nameEl) nameEl.textContent = 'Adjust Stock: ' + productName;
  const currStockEl = document.getElementById('modal-current-stock');
  if (currStockEl) currStockEl.textContent = currentStock;
  const qtyEl = document.getElementById('modal-quantity');
  if (qtyEl) qtyEl.value = 5;
  const reasonEl = document.getElementById('reason');
  if (reasonEl) reasonEl.value = '';
  updateCalcPreview();
  if (typeof openAdminModal === 'function') {
    openAdminModal('stock-modal');
  }
}

function updateCalcPreview() {
  const typeEl = document.getElementById('adjustment_type');
  const qtyEl = document.getElementById('modal-quantity');
  const projEl = document.getElementById('modal-projected-stock');
  if (!typeEl || !qtyEl || !projEl) return;
  const type = typeEl.value;
  const qty = parseInt(qtyEl.value || 0, 10);
  let projected = (type === 'add') ? (currentModalStock + qty) : (currentModalStock - qty);
  if (projected < 0) projected = 0;
  projEl.textContent = projected;
}

window.openStockModal = openStockModal;
window.updateCalcPreview = updateCalcPreview;
