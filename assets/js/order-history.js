/**
 * assets/js/order-history.js
 * Apex Diurnal Solar - Order History Interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    const listTable = document.getElementById('orders-table-body');
    if (listTable) {
        initOrdersList();
    }
});

/* -------------------------------------------------------------------------- */
/* 1. ORDER LIST PAGE (account/orders/index.php)                              */
/* -------------------------------------------------------------------------- */
function initOrdersList() {
    const filterForm = document.getElementById('orders-filter-form');
    const tableBody = document.getElementById('orders-table-body');
    const paginationWrapper = document.getElementById('orders-pagination');
    const emptyStateBox = document.getElementById('orders-empty-state');
    const tableCard = document.getElementById('orders-table-card');

    if (!tableBody) return;

    // Handle filter form submit (if present)
    if (filterForm) {
        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            fetchOrders(1);
        });
    }

    // Reset button (if present)
    const resetBtn = document.getElementById('btn-reset-filters');
    if (resetBtn) {
        resetBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (filterForm) filterForm.reset();
            fetchOrders(1);
        });
    }

    // Sort column headers
    document.querySelectorAll('.orders-table th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            const sortBy = th.getAttribute('data-sort');
            const currentDir = th.getAttribute('data-dir') || 'desc';
            const newDir = currentDir === 'desc' ? 'asc' : 'desc';

            // Reset other headers
            document.querySelectorAll('.orders-table th.sortable').forEach(h => {
                h.removeAttribute('data-dir');
                h.querySelector('.sort-icon') && (h.querySelector('.sort-icon').textContent = '↕');
            });

            th.setAttribute('data-dir', newDir);
            const icon = th.querySelector('.sort-icon');
            if (icon) icon.textContent = newDir === 'asc' ? '↑' : '↓';

            fetchOrders(1, sortBy, newDir);
        });
    });

    // Delegate row click to view order
    tableBody.addEventListener('click', (e) => {
        const row = e.target.closest('tr[data-order-id]');
        if (row && !e.target.closest('a') && !e.target.closest('button')) {
            const orderId = row.getAttribute('data-order-id');
            window.location.href = `view.php?id=${orderId}`;
        }
    });

    // Fetch orders via API
    window.fetchOrders = async function(page = 1, sortBy = null, sortDir = null) {
        const params = new URLSearchParams();
        params.append('page', page);

        if (filterForm) {
            const formData = new FormData(filterForm);
            const status = formData.get('status');
            if (status) params.append('status', status);

            const startDate = formData.get('start_date');
            if (startDate) params.append('start_date', startDate);

            const endDate = formData.get('end_date');
            if (endDate) params.append('end_date', endDate);
        }

        const activeSortHeader = document.querySelector('.orders-table th.sortable[data-dir]');
        const effectiveSortBy = sortBy || (activeSortHeader ? activeSortHeader.getAttribute('data-sort') : 'created_at');
        const effectiveSortDir = sortDir || (activeSortHeader ? activeSortHeader.getAttribute('data-dir') : 'desc');

        params.append('sort_by', effectiveSortBy);
        params.append('sort_dir', effectiveSortDir);

        // Update URL query string for shareability / back-button
        const newUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.replaceState({}, '', newUrl);

        // Visual loading feedback
        tableBody.style.opacity = '0.5';

        try {
            const apiUrl = `../../api/user/orders/list.php?${params.toString()}`;
            const res = await fetch(apiUrl);
            const json = await res.json();

            tableBody.style.opacity = '1';

            if (!json.success) {
                console.error('Failed to load orders:', json.error);
                return;
            }

            renderOrdersTable(json.data.orders);
            renderPagination(json.data.pagination);

            if (json.data.orders.length === 0) {
                if (tableCard) tableCard.style.display = 'none';
                if (emptyStateBox) emptyStateBox.style.display = 'block';
            } else {
                if (tableCard) tableCard.style.display = 'block';
                if (emptyStateBox) emptyStateBox.style.display = 'none';
            }
        } catch (err) {
            tableBody.style.opacity = '1';
            console.error('Order fetch error:', err);
        }
    };

    function renderOrdersTable(orders) {
        tableBody.innerHTML = '';
        orders.forEach(order => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-order-id', order.id);

            const dateStr = new Date(order.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            const formattedPrice = new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(order.total_amount);

            let statusLabel = order.status.charAt(0).toUpperCase() + order.status.slice(1);
            if (order.status === 'confirmed') {
                statusLabel = 'Already Confirmed';
            } else if (order.status === 'client_confirmed') {
                statusLabel = 'Confirmed by Client';
            } else if (order.status === 'pending' && order.property_type === 'Commercial') {
                statusLabel = 'Pending Client Confirmation';
            }

            tr.innerHTML = `
                <td>
                    <a href="view.php?id=${order.id}" class="order-num-link">
                        ${escapeHtml(order.order_number)}
                    </a>
                </td>
                <td>${dateStr}</td>
                <td>
                    <span class="status-pill status-${order.status}">
                        ${statusLabel}
                    </span>
                </td>
                <td>${order.item_count} ${order.item_count === 1 ? 'item' : 'items'}</td>
                <td class="order-price">${formattedPrice}</td>
                <td style="text-align: right;">
                    <a href="view.php?id=${order.id}" class="btn-view-order" aria-label="View Order ${escapeHtml(order.order_number)}">
                        View Order &rarr;
                    </a>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    }

    function renderPagination(pagination) {
        if (!paginationWrapper) return;
        if (pagination.pages <= 1) {
            paginationWrapper.style.display = 'none';
            return;
        }

        paginationWrapper.style.display = 'flex';
        const infoEl = paginationWrapper.querySelector('.pagination-info');
        if (infoEl) {
            const start = ((pagination.page - 1) * pagination.limit) + 1;
            const end = Math.min(pagination.total, pagination.page * pagination.limit);
            infoEl.textContent = `Showing ${start} - ${end} of ${pagination.total} orders`;
        }

        const buttonsEl = paginationWrapper.querySelector('.pagination-buttons');
        if (buttonsEl) {
            buttonsEl.innerHTML = '';

            // Prev Button
            const prevBtn = document.createElement('button');
            prevBtn.className = 'page-btn';
            prevBtn.innerHTML = '&lsaquo; Prev';
            prevBtn.disabled = pagination.page <= 1;
            prevBtn.onclick = () => fetchOrders(pagination.page - 1);
            buttonsEl.appendChild(prevBtn);

            // Page numbers
            for (let p = 1; p <= pagination.pages; p++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `page-btn ${p === pagination.page ? 'active' : ''}`;
                pageBtn.textContent = p;
                pageBtn.onclick = () => fetchOrders(p);
                buttonsEl.appendChild(pageBtn);
            }

            // Next Button
            const nextBtn = document.createElement('button');
            nextBtn.className = 'page-btn';
            nextBtn.innerHTML = 'Next &rsaquo;';
            nextBtn.disabled = pagination.page >= pagination.pages;
            nextBtn.onclick = () => fetchOrders(pagination.page + 1);
            buttonsEl.appendChild(nextBtn);
        }
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
