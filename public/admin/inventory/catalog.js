function toggleCatalogStock() {
  const physical = document.getElementById('catalog-type').value === 'physical';
  document.getElementById('catalog-stock-fields').hidden = !physical;
  for (const field of document.querySelectorAll('#catalog-stock-fields input')) field.disabled = !physical;
}

function openCatalogEditor(button) {
  const form = document.getElementById('catalog-form');
  form.reset();
  const item = button ? JSON.parse(button.dataset.product) : null;
  document.getElementById('catalog-modal-title').textContent = item ? 'Edit Product' : 'Add Product';
  document.getElementById('catalog-mode').value = item ? 'update' : 'create';
  document.getElementById('catalog-id').readOnly = !!item;
  document.getElementById('catalog-expected-stock').value = item ? item.current_stock : 0;
  if (item) {
    for (const [field, value] of Object.entries({
      'catalog-id': item.product_id,
      'catalog-name': item.product_name,
      'catalog-type': item.product_type,
      'catalog-price': item.base_price,
      'catalog-description': item.description || '',
      'catalog-image': item.image || '',
      'catalog-sku': item.sku === 'N/A' ? '' : item.sku,
      'catalog-stock': item.current_stock,
      'catalog-min': item.min_stock_level,
      'catalog-reorder': item.reorder_point,
      'catalog-max': item.max_stock_level,
    })) document.getElementById(field).value = value;
  }
  toggleCatalogStock();
  openAdminModal('catalog-modal');
}

window.toggleCatalogStock = toggleCatalogStock;
window.openCatalogEditor = openCatalogEditor;
