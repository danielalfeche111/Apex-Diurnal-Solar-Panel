/**
 * admin/assets/js/schedule.js - FullCalendar Integration for Service Bookings
 */

let calendar = null;

document.addEventListener('DOMContentLoaded', function() {
  const calendarEl = document.getElementById('calendar-container');
  if (!calendarEl) return;

  if (typeof FullCalendar === 'undefined') {
    calendarEl.innerHTML = `
      <div style="padding: 2.5rem; text-align: center; color: var(--text-muted); background: #ffffff; border-radius: 8px;">
        <p style="font-weight: 600; margin-bottom: 0.5rem; color: var(--navy-primary);">Calendar View Unavailable Offline</p>
        <p style="font-size: 0.85rem; margin-bottom: 1rem;">FullCalendar CDN could not be loaded in this offline session. Please switch to the Bookings List view to manage appointments.</p>
        <a href="bookings.php" class="btn btn-primary btn-sm">Switch to Bookings List &rarr;</a>
      </div>
    `;
    return;
  }

  calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,listMonth'
    },
    buttonText: {
      today: 'Today',
      month: 'Month',
      week: 'Week',
      list: 'List'
    },
    height: 'auto',
    themeSystem: 'standard',
    events: function(info, successCallback, failureCallback) {
      const typeFilter = document.getElementById('filter-service-type')?.value || '';
      const statusFilter = document.getElementById('filter-status')?.value || '';
      
      let url = `calendar.php?start=${encodeURIComponent(info.startStr)}&end=${encodeURIComponent(info.endStr)}`;
      if (typeFilter) url += `&service_type=${encodeURIComponent(typeFilter)}`;
      if (statusFilter) url += `&status=${encodeURIComponent(statusFilter)}`;

      fetch(url)
        .then(res => res.json())
        .then(data => successCallback(data))
        .catch(err => {
          console.error('Failed to load appointments:', err);
          failureCallback(err);
        });
    },
    eventClick: function(info) {
      const props = info.event.extendedProps;
      openAppointmentDetails(info.event.id, props);
    },
    eventTimeFormat: {
      hour: 'numeric',
      minute: '2-digit',
      meridiem: 'short'
    }
  });

  calendar.render();

  // Wire up filter dropdowns
  const typeEl = document.getElementById('filter-service-type');
  const statusEl = document.getElementById('filter-status');

  if (typeEl) typeEl.addEventListener('change', () => calendar.refetchEvents());
  if (statusEl) statusEl.addEventListener('change', () => calendar.refetchEvents());
});

/**
 * Open Appointment modal with event props
 */
function openAppointmentDetails(id, props) {
  document.getElementById('modal-booking-id').value = id;
  document.getElementById('modal-booking-ref').textContent = props.booking_reference;
  document.getElementById('modal-cust-name').textContent = props.customer_name;
  document.getElementById('modal-cust-email').textContent = props.customer_email;
  document.getElementById('modal-cust-phone').textContent = props.customer_phone;
  document.getElementById('modal-service-type').textContent = props.service_type.toUpperCase();
  document.getElementById('modal-time-slot').textContent = props.time_slot;
  document.getElementById('modal-address').textContent = props.address || 'N/A';
  document.getElementById('modal-technician-input').value = (props.assigned_technician !== 'Unassigned') ? props.assigned_technician : '';
  document.getElementById('modal-status-select').value = props.status;
  document.getElementById('modal-notes').textContent = props.access_notes || 'No special site access instructions.';

  const detailLink = document.getElementById('modal-view-detail-link');
  if (detailLink) {
    detailLink.href = `appointments.php?id=${id}`;
  }

  openAdminModal('appointment-modal');
}
