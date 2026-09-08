// =========================================================================
// COMMERCIAL GRID SOLAR DUAL-MODE MODAL: CONSULTATION & CORPORATE RFQ
// =========================================================================
(function initConsultationModal() {
  function setup() {
    const overlay = document.getElementById('consultation-overlay');
    const modal = document.getElementById('consultation-modal');
    const closeBtn = document.getElementById('consultation-close');
    const form = document.getElementById('consultation-form');
    const errorAlert = document.getElementById('consultation-error-alert');
    const successPanel = document.getElementById('consultation-success');
    const successTitle = document.getElementById('consultation-success-title');
    const successDesc = document.getElementById('consultation-success-desc');
    const successSummary = document.getElementById('success-summary');
    const rfqDisclaimer = document.getElementById('rfq-success-disclaimer');
    const closeSuccessBtn = document.getElementById('btn-close-success');
    const leadTypeInput = document.getElementById('lead_type');

    if (!modal || !overlay || !form) return;

    let currentMode = 'consultation'; // 'consultation' or 'rfq'
    let currentStep = 1;

    // Province -> City maps for dynamic filtering
    function getCitiesForProvince(province) {
      if (typeof consultProvinceCityMap !== 'undefined' && consultProvinceCityMap[province]) {
        return consultProvinceCityMap[province];
      }
      return [];
    }

    function setupProvinceCityDropdowns(provSelectId, citySelectId, errCityId) {
      const pSelect = document.getElementById(provSelectId);
      const cSelect = document.getElementById(citySelectId);
      if (!pSelect || !cSelect) return;

      function updateCities(preserve) {
        const prov = pSelect.value;
        const cities = getCitiesForProvince(prov);
        const oldVal = cSelect.value;
        cSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = cities.length > 0 ? 'Select City' : (prov ? 'Select City (showing all)' : 'Select City');
        cSelect.appendChild(placeholder);

        let list = cities;
        if (list.length === 0 && prov && typeof consultAllCities !== 'undefined' && Array.isArray(consultAllCities)) {
          list = consultAllCities.slice().sort();
        }

        list.forEach(city => {
          const opt = document.createElement('option');
          opt.value = city;
          opt.textContent = city;
          cSelect.appendChild(opt);
        });

        if (preserve && oldVal && list.includes(oldVal)) {
          cSelect.value = oldVal;
        } else {
          cSelect.value = '';
        }
      }

      updateCities(false);

      pSelect.addEventListener('change', function () {
        updateCities(false);
        const err = document.getElementById(errCityId);
        if (err) { err.textContent = ''; err.classList.remove('show'); }
        cSelect.classList.remove('is-invalid');
      });
    }

    // Initialize both dropdown pairs
    setupProvinceCityDropdowns('consult_province', 'consult_city', 'err-consult_city');
    setupProvinceCityDropdowns('rfq_province', 'rfq_city', 'err-rfq_city');

    // Restrict mobile contact to 11 numbers max in real-time
    ['phone_number', 'rfq_phone_number'].forEach(id => {
      const p = document.getElementById(id);
      if (p) {
        p.addEventListener('input', function () {
          this.value = this.value.replace(/\D/g, '').slice(0, 11);
          this.classList.remove('is-invalid');
          const errDiv = document.getElementById('err-' + id);
          if (errDiv) { errDiv.textContent = ''; errDiv.classList.remove('show'); }
        });
      }
    });

    function clearErrors() {
      if (errorAlert) {
        errorAlert.style.display = 'none';
        errorAlert.textContent = '';
      }
      modal.querySelectorAll('.consultation-field-error').forEach(el => {
        el.classList.remove('show');
        el.textContent = '';
      });
      modal.querySelectorAll('.consultation-input.is-invalid').forEach(inp => {
        inp.classList.remove('is-invalid');
      });
    }

    function showFieldError(fieldId, message) {
      const input = document.getElementById(fieldId);
      const errDiv = document.getElementById('err-' + fieldId);
      if (input) input.classList.add('is-invalid');
      if (errDiv) {
        errDiv.textContent = message;
        errDiv.classList.add('show');
      }
    }

    function setMode(mode) {
      currentMode = (mode === 'rfq') ? 'rfq' : 'consultation';
      if (leadTypeInput) leadTypeInput.value = currentMode;

      const badge = document.getElementById('consultation-mode-badge');
      const title = document.getElementById('consultation-modal-title');
      const subtitle = document.getElementById('consultation-modal-subtitle');
      const stepText1 = document.getElementById('step-text-1');
      const stepText2 = document.getElementById('step-text-2');
      const stepText3 = document.getElementById('step-text-3');

      if (currentMode === 'rfq') {
        if (badge) badge.style.display = 'inline-flex';
        if (title) title.textContent = 'Request Commercial Quote';
        if (subtitle) subtitle.textContent = 'Submit your corporate specifications to receive a formal commercial solar equipment proposal.';
        if (stepText1) stepText1.textContent = 'Company Details';
        if (stepText2) stepText2.textContent = 'Installation Address';
        if (stepText3) stepText3.textContent = 'Timeline & Contact';

        // Disable consultation inputs so FormData ignores them
        for (let i = 1; i <= 3; i++) {
          const panel = document.getElementById('step-panel-consult-' + i);
          if (panel) {
            panel.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
          }
          const rfqPanel = document.getElementById('step-panel-rfq-' + i);
          if (rfqPanel) {
            rfqPanel.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
          }
        }
      } else {
        if (badge) badge.style.display = 'none';
        if (title) title.textContent = 'Schedule On-Site Consultation';
        if (subtitle) subtitle.textContent = 'Comprehensive site audit & engineering feasibility analysis for high-capacity solar setups.';
        if (stepText1) stepText1.textContent = 'Facility Details';
        if (stepText2) stepText2.textContent = 'Contact Details';
        if (stepText3) stepText3.textContent = 'Audit Schedule';

        // Disable RFQ inputs so FormData ignores them
        for (let i = 1; i <= 3; i++) {
          const rfqPanel = document.getElementById('step-panel-rfq-' + i);
          if (rfqPanel) {
            rfqPanel.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
          }
          const panel = document.getElementById('step-panel-consult-' + i);
          if (panel) {
            panel.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
          }
        }
      }

      setStep(1);
    }

    function setStep(step) {
      currentStep = step;

      // Update stepper indicators
      for (let i = 1; i <= 3; i++) {
        const navItem = document.getElementById('step-nav-' + i);
        const divider = document.getElementById('step-div-' + (i - 1));

        if (navItem) {
          navItem.classList.toggle('active', i === step);
          navItem.classList.toggle('completed', i < step);
        }
        if (divider) {
          divider.classList.toggle('active', i <= step);
        }
      }

      // Toggle panels according to mode and step
      for (let i = 1; i <= 3; i++) {
        const consultPanel = document.getElementById('step-panel-consult-' + i);
        const rfqPanel = document.getElementById('step-panel-rfq-' + i);

        if (consultPanel) {
          consultPanel.style.display = (currentMode === 'consultation' && i === step) ? 'block' : 'none';
          consultPanel.classList.toggle('active', currentMode === 'consultation' && i === step);
        }
        if (rfqPanel) {
          rfqPanel.style.display = (currentMode === 'rfq' && i === step) ? 'block' : 'none';
          rfqPanel.classList.toggle('active', currentMode === 'rfq' && i === step);
        }
      }

      const modalBody = modal.querySelector('.consultation-modal-body');
      if (modalBody) modalBody.scrollTop = 0;
    }

    function resetModal() {
      if (form) form.reset();
      clearErrors();
      if (successPanel) successPanel.style.display = 'none';
      if (form) form.style.display = 'block';

      const consultSubmitBtn = document.getElementById('btn-submit-consultation');
      if (consultSubmitBtn) {
        consultSubmitBtn.disabled = false;
        const txt = consultSubmitBtn.querySelector('.btn-text');
        if (txt) txt.textContent = 'Confirm Site Consultation';
      }
      const rfqSubmitBtn = document.getElementById('btn-submit-rfq');
      if (rfqSubmitBtn) {
        rfqSubmitBtn.disabled = false;
        const txt = rfqSubmitBtn.querySelector('.btn-text');
        if (txt) txt.textContent = 'Submit Quote Request';
      }
      const cSpin = document.getElementById('consultation-spinner');
      if (cSpin) cSpin.style.display = 'none';
      const rSpin = document.getElementById('rfq-spinner');
      if (rSpin) rSpin.style.display = 'none';

      setStep(1);
    }

    function openModal(mode) {
      setMode(mode || 'consultation');
      overlay.style.display = 'block';
      modal.style.display = 'flex';
      requestAnimationFrame(() => {
        overlay.classList.add('active');
        modal.classList.add('open');
      });
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      if (successPanel && successPanel.style.display === 'block') {
        resetModal();
      }
    }

    function closeModal() {
      overlay.classList.remove('active');
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      setTimeout(() => {
        if (!modal.classList.contains('open')) {
          overlay.style.display = 'none';
          modal.style.display = 'none';
        }
      }, 300);
    }

    // Expose globals
    window.setConsultationMode = setMode;
    window.openConsultationModal = openModal;
    window.closeConsultationModal = closeModal;
    window.openContactSalesModal = () => openModal('rfq');
    window.closeContactSalesModal = closeModal;

    // Validation - Consultation Mode
    function validateConsultStep1() {
      clearErrors();
      let valid = true;
      const company = document.getElementById('company_name');
      const street = document.getElementById('consult_street');
      const province = document.getElementById('consult_province');
      const city = document.getElementById('consult_city');
      const postal = document.getElementById('consult_postal');
      const facility = document.getElementById('facility_type');
      const power = document.getElementById('power_supply');

      if (!company || company.value.trim().length < 2) {
        showFieldError('company_name', 'Please enter your full name (at least 2 characters).');
        valid = false;
      }
      if (!street || street.value.trim().length < 5) {
        showFieldError('consult_street', 'Street address must be at least 5 characters.');
        valid = false;
      }
      if (!province || !province.value) {
        showFieldError('consult_province', 'Please select a province.');
        valid = false;
      }
      if (!city || !city.value) {
        showFieldError('consult_city', 'Please select a city.');
        valid = false;
      } else if (province && province.value) {
        const allowedCities = getCitiesForProvince(province.value);
        if (allowedCities.length > 0 && !allowedCities.includes(city.value)) {
          const stripped = city.value.replace(/\s+City$/i, '');
          const withCity = stripped + ' City';
          if (!allowedCities.includes(stripped) && !allowedCities.includes(withCity)) {
            showFieldError('consult_city', 'Selected city does not belong to ' + province.value + '.');
            valid = false;
          }
        }
      }
      if (!postal || !/^\d{4}$/.test(postal.value.trim())) {
        showFieldError('consult_postal', 'Please enter a valid 4-digit postal code.');
        valid = false;
      }
      if (!facility || !facility.value) {
        showFieldError('facility_type', 'Please select a facility type.');
        valid = false;
      }
      if (!power || !power.value) {
        showFieldError('power_supply', 'Please select a power supply connection.');
        valid = false;
      }
      return valid;
    }

    function validateConsultStep2() {
      clearErrors();
      let valid = true;
      const contact = document.getElementById('contact_person');
      const email = document.getElementById('corporate_email');
      const phone = document.getElementById('phone_number');

      if (!contact || contact.value.trim().length < 2) {
        showFieldError('contact_person', 'Contact person name is required.');
        valid = false;
      }
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!email || !emailRegex.test(email.value.trim())) {
        showFieldError('corporate_email', 'Please enter a valid email address.');
        valid = false;
      }
      const cleanPhone = (phone?.value || '').replace(/[^\d]/g, '').slice(0, 11);
      if (!phone || cleanPhone.length !== 11) {
        showFieldError('phone_number', 'Please enter a valid 11-digit mobile number (e.g., 09171234567).');
        valid = false;
      }
      return valid;
    }

    function validateConsultStep3() {
      clearErrors();
      let valid = true;
      const dateInput = document.getElementById('preferred_date');
      const timeSlot = document.getElementById('preferred_time_slot');

      if (!dateInput || !dateInput.value) {
        showFieldError('preferred_date', 'Please select a preferred site audit date.');
        valid = false;
      } else {
        const today = new Date().toISOString().split('T')[0];
        if (dateInput.value < today) {
          showFieldError('preferred_date', 'Preferred audit date cannot be in the past.');
          valid = false;
        }
      }
      if (!timeSlot || !timeSlot.value) {
        showFieldError('preferred_time_slot', 'Please select a preferred time slot.');
        valid = false;
      }
      return valid;
    }

    // Validation - RFQ Mode
    function validateRfqStep1() {
      clearErrors();
      let valid = true;
      const company = document.getElementById('rfq_company_name');
      const regType = document.getElementById('rfq_business_registration_type');
      const facilitySize = document.getElementById('rfq_facility_size');
      const monthlyBill = document.getElementById('rfq_current_monthly_bill');

      if (!company || company.value.trim().length < 2) {
        showFieldError('rfq_company_name', 'Company Name is required (minimum 2 characters).');
        valid = false;
      }
      if (!regType || !regType.value) {
        showFieldError('rfq_business_registration_type', 'Please select a business registration type.');
        valid = false;
      }
      if (!facilitySize || !facilitySize.value || parseFloat(facilitySize.value) <= 0) {
        showFieldError('rfq_facility_size', 'Facility Rooftop / Land Area (in sqm) is required.');
        valid = false;
      }
      if (!monthlyBill || !monthlyBill.value || parseFloat(monthlyBill.value) <= 0) {
        showFieldError('rfq_current_monthly_bill', 'Current Monthly Electricity Bill (in ₱) is required.');
        valid = false;
      }
      return valid;
    }

    function validateRfqStep2() {
      clearErrors();
      let valid = true;
      const street = document.getElementById('rfq_street');
      const province = document.getElementById('rfq_province');
      const city = document.getElementById('rfq_city');
      const postal = document.getElementById('rfq_postal');

      if (!street || street.value.trim().length < 5) {
        showFieldError('rfq_street', 'Installation street address must be at least 5 characters.');
        valid = false;
      }
      if (!province || !province.value) {
        showFieldError('rfq_province', 'Please select a province.');
        valid = false;
      }
      if (!city || !city.value) {
        showFieldError('rfq_city', 'Please select a city.');
        valid = false;
      } else if (province && province.value) {
        const allowedCities = getCitiesForProvince(province.value);
        if (allowedCities.length > 0 && !allowedCities.includes(city.value)) {
          showFieldError('rfq_city', 'Selected city does not belong to ' + province.value + '.');
          valid = false;
        }
      }
      if (!postal || !/^\d{4}$/.test(postal.value.trim())) {
        showFieldError('rfq_postal', 'Please enter a valid 4-digit postal code (e.g. 1000).');
        valid = false;
      }
      return valid;
    }

    function validateRfqStep3() {
      clearErrors();
      let valid = true;
      const timeline = document.getElementById('rfq_target_timeline');
      const contact = document.getElementById('rfq_contact_person');
      const email = document.getElementById('rfq_corporate_email');
      const phone = document.getElementById('rfq_phone_number');

      if (!timeline || !timeline.value) {
        showFieldError('rfq_target_timeline', 'Please select a target project completion timeline.');
        valid = false;
      }
      if (!contact || contact.value.trim().length < 2) {
        showFieldError('rfq_contact_person', 'Contact person name is required (minimum 2 characters).');
        valid = false;
      }
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!email || !emailRegex.test(email.value.trim())) {
        showFieldError('rfq_corporate_email', 'Please enter a valid corporate email address.');
        valid = false;
      }
      const cleanPhone = (phone?.value || '').replace(/[^\d]/g, '').slice(0, 11);
      if (!phone || cleanPhone.length !== 11) {
        showFieldError('rfq_phone_number', 'Please enter a valid 11-digit mobile number (e.g., 09171234567).');
        valid = false;
      }
      return valid;
    }

    // Bind Navigation Buttons
    // Consultation nav
    const consultNext1 = document.getElementById('btn-consult-next-1');
    if (consultNext1) consultNext1.addEventListener('click', () => { if (validateConsultStep1()) setStep(2); });
    const consultPrev2 = document.getElementById('btn-consult-prev-2');
    if (consultPrev2) consultPrev2.addEventListener('click', () => setStep(1));
    const consultNext2 = document.getElementById('btn-consult-next-2');
    if (consultNext2) consultNext2.addEventListener('click', () => { if (validateConsultStep2()) setStep(3); });
    const consultPrev3 = document.getElementById('btn-consult-prev-3');
    if (consultPrev3) consultPrev3.addEventListener('click', () => setStep(2));

    // RFQ nav
    const rfqNext1 = document.getElementById('btn-rfq-next-1');
    if (rfqNext1) rfqNext1.addEventListener('click', () => { if (validateRfqStep1()) setStep(2); });
    const rfqPrev2 = document.getElementById('btn-rfq-prev-2');
    if (rfqPrev2) rfqPrev2.addEventListener('click', () => setStep(1));
    const rfqNext2 = document.getElementById('btn-rfq-next-2');
    if (rfqNext2) rfqNext2.addEventListener('click', () => { if (validateRfqStep2()) setStep(3); });
    const rfqPrev3 = document.getElementById('btn-rfq-prev-3');
    if (rfqPrev3) rfqPrev3.addEventListener('click', () => setStep(2));

    // Auto-clear input error highlights
    function clearTargetError(target) {
      if (target && target.classList.contains('consultation-input')) {
        target.classList.remove('is-invalid');
        const err = document.getElementById('err-' + target.id);
        if (err) {
          err.classList.remove('show');
          err.textContent = '';
        }
      }
    }
    form.addEventListener('input', (e) => clearTargetError(e.target));
    form.addEventListener('change', (e) => clearTargetError(e.target));

    // Form Submission (Ajax)
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      let activeSubmitBtn = null;
      let activeSpinner = null;
      let defaultBtnText = '';

      if (currentMode === 'rfq') {
        if (!validateRfqStep1()) { setStep(1); return; }
        if (!validateRfqStep2()) { setStep(2); return; }
        if (!validateRfqStep3()) { setStep(3); return; }

        activeSubmitBtn = document.getElementById('btn-submit-rfq');
        activeSpinner = document.getElementById('rfq-spinner');
        defaultBtnText = 'Submit Quote Request';
      } else {
        if (!validateConsultStep1()) { setStep(1); return; }
        if (!validateConsultStep2()) { setStep(2); return; }
        if (!validateConsultStep3()) { setStep(3); return; }

        activeSubmitBtn = document.getElementById('btn-submit-consultation');
        activeSpinner = document.getElementById('consultation-spinner');
        defaultBtnText = 'Confirm Site Consultation';
      }

      if (activeSubmitBtn) activeSubmitBtn.disabled = true;
      if (activeSpinner) activeSpinner.style.display = 'inline-block';
      const btnText = activeSubmitBtn?.querySelector('.btn-text');
      if (btnText) btnText.textContent = (currentMode === 'rfq') ? 'Submitting RFP...' : 'Scheduling...';
      clearErrors();

      const formData = new FormData(form);

      fetch('services/commercial_consultation.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json' }
      })
        .then(async res => {
          const data = await res.json();
          if (!res.ok) throw data;
          return data;
        })
        .then(data => {
          if (data.success) {
            form.style.display = 'none';
            if (successPanel) {
              successPanel.style.display = 'block';

              if (data.lead_type === 'rfq') {
                if (successTitle) successTitle.textContent = 'Quote Request Submitted!';
                if (successDesc) successDesc.textContent = 'Thank you! Your Request-for-Quote has been received. Our commercial sales engineering team will review your specifications and deliver a formal equipment proposal.';
                if (successSummary) {
                  successSummary.innerHTML = `
                    <div><strong>Company:</strong> ${escapeHtml(data.company_name || '')}</div>
                    <div><strong>Inquiry Reference:</strong> #RFQ-${data.lead_id || Date.now()}</div>
                  `;
                }
                if (rfqDisclaimer) rfqDisclaimer.style.display = 'block';
              } else {
                if (successTitle) successTitle.textContent = 'Consultation Request Confirmed!';
                if (successDesc) successDesc.textContent = 'Thank you! Your commercial solar audit booking has been received. A dedicated solar systems engineer will review your facility\'s satellite profile and contact you shortly.';
                if (successSummary) {
                  const comp = document.getElementById('company_name')?.value || '';
                  const dt = document.getElementById('preferred_date')?.value || '';
                  const sl = document.getElementById('preferred_time_slot')?.value || '';
                  successSummary.innerHTML = `
                    <div><strong>Facility:</strong> ${escapeHtml(comp)}</div>
                    <div><strong>Preferred Visit:</strong> ${escapeHtml(dt)} (${escapeHtml(sl)})</div>
                    <div><strong>Confirmation Reference:</strong> #COMM-${data.lead_id || Date.now()}</div>
                  `;
                }
                if (rfqDisclaimer) rfqDisclaimer.style.display = 'none';
              }
            }
          }
        })
        .catch(err => {
          console.error('Submission failed:', err);
          const msg = (err && err.message) ? err.message : 'Submission failed. Please check your network and try again.';
          if (err && err.errors && typeof err.errors === 'object') {
            const errorKeys = Object.keys(err.errors);
            const errorList = Object.values(err.errors);

            if (errorAlert) {
              let html = '<strong>' + escapeHtml(msg) + '</strong>';
              if (errorList.length > 0) {
                html += '<ul style="margin: 6px 0 0 18px; padding: 0; font-size: 0.85rem; line-height: 1.4;">' +
                  errorList.map(e => '<li>' + escapeHtml(e) + '</li>').join('') +
                  '</ul>';
              }
              errorAlert.innerHTML = html;
              errorAlert.style.display = 'block';
            }

            errorKeys.forEach(key => {
              showFieldError(key, err.errors[key]);
              showFieldError('rfq_' + key, err.errors[key]);
            });

            // Auto-navigate user to the earliest step containing an error
            const step1Fields = ['company_name', 'consult_street', 'consult_province', 'consult_city', 'consult_postal', 'facility_type', 'power_supply', 'business_registration_type', 'rfq_company_name', 'rfq_business_registration_type', 'facility_size', 'current_monthly_bill', 'rfq_facility_size', 'rfq_current_monthly_bill'];
            const step2Fields = ['contact_person', 'corporate_email', 'phone_number', 'best_call_time', 'rfq_street', 'rfq_province', 'rfq_city', 'rfq_postal'];
            const step3Fields = ['preferred_date', 'preferred_time_slot', 'access_notes', 'target_timeline', 'rfq_target_timeline', 'rfq_contact_person', 'rfq_corporate_email', 'rfq_phone_number'];

            if (errorKeys.some(k => step1Fields.includes(k) || step1Fields.includes('rfq_' + k))) {
              setStep(1);
            } else if (errorKeys.some(k => step2Fields.includes(k) || step2Fields.includes('rfq_' + k))) {
              setStep(2);
            } else if (errorKeys.some(k => step3Fields.includes(k) || step3Fields.includes('rfq_' + k))) {
              setStep(3);
            }
          } else if (errorAlert) {
            errorAlert.textContent = msg;
            errorAlert.style.display = 'block';
          }
        })
        .finally(() => {
          if (activeSubmitBtn) activeSubmitBtn.disabled = false;
          if (activeSpinner) activeSpinner.style.display = 'none';
          if (btnText) btnText.textContent = defaultBtnText;
        });
    });

    // Close & Trigger bindings
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', closeModal);

    if (closeSuccessBtn) {
      closeSuccessBtn.addEventListener('click', () => {
        closeModal();
        setTimeout(resetModal, 350);
      });
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal && modal.classList.contains('open')) {
        closeModal();
      }
    });

    // Global click delegation for triggers
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('button, a');
      if (!btn) return;

      const txt = (btn.textContent || '').trim().toUpperCase();
      const isRfqClass = btn.classList.contains('rfq-trigger-btn');
      const isConsultClass = btn.classList.contains('consultation-trigger-btn') || btn.id === 'consultation-trigger';

      if (isRfqClass || txt === 'CONTACT SALES' || txt === 'REQUEST QUOTE') {
        e.preventDefault();
        openModal('rfq');
      } else if (isConsultClass || txt === 'BOOK A CONSULTATION') {
        e.preventDefault();
        openModal('consultation');
      }
    });

    function escapeHtml(str) {
      if (!str) return '';
      const d = document.createElement('div');
      d.textContent = str;
      return d.innerHTML;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setup);
  } else {
    setup();
  }
})();

