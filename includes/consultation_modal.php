  <!-- DUAL-MODE COMMERCIAL SOLAR CONSULTATION & RFQ MODAL -->
  <div class="consultation-overlay" id="consultation-overlay" style="display:none;" onclick="closeConsultationModal()">
  </div>
  <div class="consultation-modal" id="consultation-modal" role="dialog" aria-modal="true"
    aria-labelledby="consultation-modal-title" aria-hidden="true" style="display:none;">
    <div class="consultation-modal-header">
      <div class="consultation-header-content">
        <div class="consultation-mode-badge" id="consultation-mode-badge" style="display:none;">Corporate Quote Path
        </div>
        <h2 class="consultation-modal-title" id="consultation-modal-title">Schedule On-Site Consultation</h2>
        <p class="consultation-modal-subtitle" id="consultation-modal-subtitle">Comprehensive site audit &amp;
          engineering feasibility analysis for high-capacity solar setups.</p>
      </div>
      <button type="button" class="consultation-modal-close" id="consultation-close" onclick="closeConsultationModal()"
        aria-label="Close consultation modal">&times;</button>
    </div>

    <!-- Stepper Indicator -->
    <div class="consultation-stepper">
      <div class="stepper-item active" id="step-nav-1">
        <div class="step-badge">1</div>
        <div class="step-text" id="step-text-1">Facility Details</div>
      </div>
      <div class="stepper-divider" id="step-div-1"></div>
      <div class="stepper-item" id="step-nav-2">
        <div class="step-badge">2</div>
        <div class="step-text" id="step-text-2">Contact Details</div>
      </div>
      <div class="stepper-divider" id="step-div-2"></div>
      <div class="stepper-item" id="step-nav-3">
        <div class="step-badge">3</div>
        <div class="step-text" id="step-text-3">Audit Schedule</div>
      </div>
    </div>

    <!-- Modal Form Body -->
    <div class="consultation-modal-body">
      <div class="consultation-error-alert" id="consultation-error-alert" style="display:none;" role="alert"></div>

      <form id="consultation-form" novalidate>
        <input type="hidden" name="lead_type" id="lead_type" value="consultation">

        <!-- ========================================== -->
        <!-- CONSULTATION MODE PANELS                   -->
        <!-- ========================================== -->
        <!-- CONSULTATION STEP 1: Facility Specs -->
        <div class="consultation-step-panel active" id="step-panel-consult-1">
          <div class="form-group">
            <label for="company_name" class="consultation-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" id="company_name" name="company_name" class="consultation-input"
              placeholder="e.g. Juan Dela Cruz" required>
            <div class="consultation-field-error" id="err-company_name"></div>
          </div>

          <div class="form-group">
            <label for="consult_street" class="consultation-label">Street Address / Barangay <span
                class="text-danger">*</span></label>
            <input type="text" id="consult_street" name="consult_street" class="consultation-input"
              placeholder="e.g. 123 Rizal St., Brgy. San Isidro" required>
            <div class="consultation-field-error" id="err-consult_street"></div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="consult_province" class="consultation-label">Province <span
                  class="text-danger">*</span></label>
              <select id="consult_province" name="consult_province" class="consultation-input consultation-select"
                required>
                <option value="">Select Province</option>
                <?php foreach ($consult_provinces as $pp): ?>
                  <option value="<?php echo htmlspecialchars($pp); ?>"><?php echo htmlspecialchars($pp); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="consultation-field-error" id="err-consult_province"></div>
            </div>
            <div class="form-group">
              <label for="consult_city" class="consultation-label">City <span class="text-danger">*</span></label>
              <select id="consult_city" name="consult_city" class="consultation-input consultation-select" required>
                <option value="">Select City</option>
                <?php foreach ($consult_cities as $cc): ?>
                  <option value="<?php echo htmlspecialchars($cc); ?>"><?php echo htmlspecialchars($cc); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="consultation-field-error" id="err-consult_city"></div>
            </div>
          </div>

          <div class="form-group">
            <label for="consult_postal" class="consultation-label">Postal Code <span
                class="text-danger">*</span></label>
            <input type="text" id="consult_postal" name="consult_postal" class="consultation-input"
              placeholder="e.g. 1000" maxlength="4" pattern="\d{4}" inputmode="numeric" required>
            <div class="consultation-field-error" id="err-consult_postal"></div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="facility_type" class="consultation-label">Facility Type <span
                  class="text-danger">*</span></label>
              <select id="facility_type" name="facility_type" class="consultation-input consultation-select" required>
                <option value="">Select Facility Type</option>
                <option value="Manufacturing Plant">Manufacturing Plant</option>
                <option value="Commercial Building">Commercial Building</option>
                <option value="Warehouse">Warehouse</option>
                <option value="Agricultural">Agricultural Facility</option>
                <option value="School">School / Campus</option>
                <option value="House">House / Residential</option>
              </select>
              <div class="consultation-field-error" id="err-facility_type"></div>
            </div>

            <div class="form-group">
              <label for="power_supply" class="consultation-label">Power Supply Connection <span
                  class="text-danger">*</span></label>
              <select id="power_supply" name="power_supply" class="consultation-input consultation-select" required>
                <option value="">Select Power Supply</option>
                <option value="Single-Phase Supply">Single-Phase Supply</option>
                <option value="Three-Phase Supply">Three-Phase Supply</option>
              </select>
              <div class="consultation-field-error" id="err-power_supply"></div>
            </div>
          </div>

          <div class="consultation-actions modal-actions-right">
            <button type="button" class="btn btn-yellow consultation-nav-btn" id="btn-consult-next-1">
              <span>Next: Contact Details</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </button>
          </div>
        </div>

        <!-- CONSULTATION STEP 2: Stakeholder Contact Details -->
        <div class="consultation-step-panel" id="step-panel-consult-2" style="display:none;">
          <div class="form-group">
            <label for="contact_person" class="consultation-label">Contact Person Name <span
                class="text-danger">*</span></label>
            <input type="text" id="contact_person" name="contact_person" class="consultation-input"
              placeholder="e.g. John Doe" required>
            <div class="consultation-field-error" id="err-contact_person"></div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="corporate_email" class="consultation-label">Email Address <span
                  class="text-danger">*</span></label>
              <input type="email" id="corporate_email" name="corporate_email" class="consultation-input"
                placeholder="Enter Email Address" required>
              <div class="consultation-field-error" id="err-corporate_email"></div>
            </div>

            <div class="form-group">
              <label for="phone_number" class="consultation-label">Phone Number <span
                  class="text-danger">*</span></label>
              <input type="tel" id="phone_number" name="phone_number" class="consultation-input"
                placeholder="Enter Phone Number" required>
              <div class="consultation-field-error" id="err-phone_number"></div>
            </div>
          </div>

          <div class="form-group">
            <label class="consultation-label">Best Time for Pre-Inspection Call <span
                class="text-danger">*</span></label>
            <div class="radio-pill-group">
              <label class="radio-pill">
                <input type="radio" name="best_call_time" value="Morning" checked>
                <span class="pill-badge">Morning (8am - 12pm)</span>
              </label>
              <label class="radio-pill">
                <input type="radio" name="best_call_time" value="Afternoon">
                <span class="pill-badge">Afternoon (1pm - 5pm)</span>
              </label>
              <label class="radio-pill">
                <input type="radio" name="best_call_time" value="Anytime">
                <span class="pill-badge">Anytime During Business Hours</span>
              </label>
            </div>
            <div class="consultation-field-error" id="err-best_call_time"></div>
          </div>

          <div class="consultation-actions">
            <button type="button" class="btn btn-outline consultation-nav-btn" id="btn-consult-prev-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
              </svg>
              <span>Back</span>
            </button>
            <button type="button" class="btn btn-yellow consultation-nav-btn" id="btn-consult-next-2">
              <span>Next: Scheduling</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </button>
          </div>
        </div>

        <!-- CONSULTATION STEP 3: On-Site Audit Scheduling -->
        <div class="consultation-step-panel" id="step-panel-consult-3" style="display:none;">
          <div class="form-row-2">
            <div class="form-group">
              <label for="preferred_date" class="consultation-label">Preferred Site Visit Date <span
                  class="text-danger">*</span></label>
              <input type="date" id="preferred_date" name="preferred_date" class="consultation-input"
                min="<?php echo date('Y-m-d'); ?>" required>
              <div class="consultation-field-error" id="err-preferred_date"></div>
            </div>

            <div class="form-group">
              <label for="preferred_time_slot" class="consultation-label">Preferred Time Slot <span
                  class="text-danger">*</span></label>
              <select id="preferred_time_slot" name="preferred_time_slot" class="consultation-input consultation-select"
                required>
                <option value="">Select Time Slot</option>
                <option value="Morning">Morning Window (9:00 AM - 12:00 PM)</option>
                <option value="Afternoon">Afternoon Window (1:00 PM - 4:00 PM)</option>
              </select>
              <div class="consultation-field-error" id="err-preferred_time_slot"></div>
            </div>
          </div>

          <div class="form-group">
            <label for="access_notes" class="consultation-label">Site Logistics / Access Notes <span
                class="consultation-optional">(Optional)</span></label>
            <textarea id="access_notes" name="access_notes" class="consultation-input consultation-textarea" rows="3"
              placeholder="e.g., Gate security check-in codes, roof access hatch details, facility manager on-site contact, PPE requirements..."></textarea>
          </div>

          <div class="consultation-actions">
            <button type="button" class="btn btn-outline consultation-nav-btn" id="btn-consult-prev-3">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
              </svg>
              <span>Back</span>
            </button>
            <button type="submit" class="btn btn-yellow consultation-submit-btn" id="btn-submit-consultation">
              <span class="btn-text">Confirm Site Consultation</span>
              <span class="btn-spinner" id="consultation-spinner" style="display:none;"></span>
            </button>
          </div>
        </div>

        <!-- ========================================== -->
        <!-- CORPORATE RFQ MODE PANELS                  -->
        <!-- ========================================== -->
        <!-- RFQ STEP 1: Company Details -->
        <div class="consultation-step-panel" id="step-panel-rfq-1" style="display:none;">
          <div class="form-group">
            <label for="rfq_company_name" class="consultation-label">Company Name <span
                class="text-danger">*</span></label>
            <input type="text" id="rfq_company_name" name="company_name" class="consultation-input"
              placeholder="e.g. Apex Industrial Corp." required disabled>
            <div class="consultation-field-error" id="err-rfq_company_name"></div>
          </div>

          <div class="form-group">
            <label for="rfq_business_registration_type" class="consultation-label">Business Registration Type <span
                class="text-danger">*</span></label>
            <select id="rfq_business_registration_type" name="business_registration_type"
              class="consultation-input consultation-select" required disabled>
              <option value="">Select Registration Type</option>
              <option value="Sole Proprietorship">Sole Proprietorship</option>
              <option value="Partnership">Partnership</option>
              <option value="Corporation (SEC Registered)">Corporation (SEC Registered)</option>
              <option value="One Person Corporation (OPC)">One Person Corporation (OPC)</option>
              <option value="Cooperative">Cooperative</option>
              <option value="Government Agency / LGU">Government Agency / LGU</option>
            </select>
            <div class="consultation-field-error" id="err-rfq_business_registration_type"></div>
          </div>

          <div class="consultation-actions modal-actions-right">
            <button type="button" class="btn btn-yellow consultation-nav-btn" id="btn-rfq-next-1">
              <span>Next: Installation Address</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </button>
          </div>
        </div>

        <!-- RFQ STEP 2: Installation Address -->
        <div class="consultation-step-panel" id="step-panel-rfq-2" style="display:none;">
          <div class="form-group">
            <label for="rfq_street" class="consultation-label">Installation Street Address / Site Location <span
                class="text-danger">*</span></label>
            <input type="text" id="rfq_street" name="consult_street" class="consultation-input"
              placeholder="e.g. Lot 4 Block 2 Laguna Technopark, Brgy. Don Jose" required disabled>
            <div class="consultation-field-error" id="err-rfq_street"></div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="rfq_province" class="consultation-label">Province <span class="text-danger">*</span></label>
              <select id="rfq_province" name="consult_province" class="consultation-input consultation-select" required
                disabled>
                <option value="">Select Province</option>
                <?php foreach ($consult_provinces as $pp): ?>
                  <option value="<?php echo htmlspecialchars($pp); ?>"><?php echo htmlspecialchars($pp); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="consultation-field-error" id="err-rfq_province"></div>
            </div>
            <div class="form-group">
              <label for="rfq_city" class="consultation-label">City / Municipality <span
                  class="text-danger">*</span></label>
              <select id="rfq_city" name="consult_city" class="consultation-input consultation-select" required
                disabled>
                <option value="">Select City</option>
                <?php foreach ($consult_cities as $cc): ?>
                  <option value="<?php echo htmlspecialchars($cc); ?>"><?php echo htmlspecialchars($cc); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="consultation-field-error" id="err-rfq_city"></div>
            </div>
          </div>

          <div class="form-group">
            <label for="rfq_postal" class="consultation-label">Postal Code <span class="text-danger">*</span></label>
            <input type="text" id="rfq_postal" name="consult_postal" class="consultation-input" placeholder="e.g. 4024"
              maxlength="4" pattern="\d{4}" inputmode="numeric" required disabled>
            <div class="consultation-field-error" id="err-rfq_postal"></div>
          </div>

          <div class="consultation-actions">
            <button type="button" class="btn btn-outline consultation-nav-btn" id="btn-rfq-prev-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
              </svg>
              <span>Back</span>
            </button>
            <button type="button" class="btn btn-yellow consultation-nav-btn" id="btn-rfq-next-2">
              <span>Next: Timeline &amp; Contact</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </button>
          </div>
        </div>

        <!-- RFQ STEP 3: Project Timeline & Contact -->
        <div class="consultation-step-panel" id="step-panel-rfq-3" style="display:none;">
          <div class="form-group">
            <label for="rfq_target_timeline" class="consultation-label">Target Project Completion Timeline <span
                class="text-danger">*</span></label>
            <select id="rfq_target_timeline" name="target_timeline" class="consultation-input consultation-select"
              required disabled>
              <option value="">Select Completion Target</option>
              <option value="Immediate">Immediate (&lt; 1 Month)</option>
              <option value="Within 3 Months">Within 3 Months (Standard Procurement)</option>
              <option value="6+ Months">6+ Months (Capital Planning / Future Budget)</option>
            </select>
            <div class="consultation-field-error" id="err-rfq_target_timeline"></div>
          </div>

          <div class="form-group">
            <label for="rfq_contact_person" class="consultation-label">Contact Person Name <span
                class="text-danger">*</span></label>
            <input type="text" id="rfq_contact_person" name="contact_person" class="consultation-input"
              placeholder="e.g. Maria Santos (Purchasing / Facility Director)" required disabled>
            <div class="consultation-field-error" id="err-rfq_contact_person"></div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="rfq_corporate_email" class="consultation-label">Corporate Email <span
                  class="text-danger">*</span></label>
              <input type="email" id="rfq_corporate_email" name="corporate_email" class="consultation-input"
                placeholder="e.g. m.santos@company.ph" required disabled>
              <div class="consultation-field-error" id="err-rfq_corporate_email"></div>
            </div>

            <div class="form-group">
              <label for="rfq_phone_number" class="consultation-label">Phone Number <span
                  class="text-danger">*</span></label>
              <input type="tel" id="rfq_phone_number" name="phone_number" class="consultation-input"
                placeholder="e.g. 09171234567" required disabled>
              <div class="consultation-field-error" id="err-rfq_phone_number"></div>
            </div>
          </div>

          <div class="form-group">
            <label class="consultation-label">Preferred Time for Follow-up <span
                class="consultation-optional">(Optional)</span></label>
            <div class="radio-pill-group">
              <label class="radio-pill">
                <input type="radio" name="best_call_time" value="Morning" checked disabled>
                <span class="pill-badge">Morning (8am - 12pm)</span>
              </label>
              <label class="radio-pill">
                <input type="radio" name="best_call_time" value="Afternoon" disabled>
                <span class="pill-badge">Afternoon (1pm - 5pm)</span>
              </label>
              <label class="radio-pill">
                <input type="radio" name="best_call_time" value="Anytime" disabled>
                <span class="pill-badge">Anytime During Business Hours</span>
              </label>
            </div>
          </div>

          <div class="consultation-actions">
            <button type="button" class="btn btn-outline consultation-nav-btn" id="btn-rfq-prev-3">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
              </svg>
              <span>Back</span>
            </button>
            <button type="submit" class="btn btn-yellow consultation-submit-btn" id="btn-submit-rfq">
              <span class="btn-text">Submit Quote Request</span>
              <span class="btn-spinner" id="rfq-spinner" style="display:none;"></span>
            </button>
          </div>
        </div>
      </form>

      <!-- SUCCESS STATE -->
      <div class="consultation-success-panel" id="consultation-success" style="display:none;">
        <div class="success-icon-wrap">
          <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
        </div>
        <h3 class="success-title" id="consultation-success-title">Consultation Request Confirmed!</h3>
        <p class="success-desc" id="consultation-success-desc">Thank you! Your commercial solar audit booking has been
          received. A dedicated solar systems engineer will review your facility's satellite profile and contact you
          shortly.</p>
        <div class="success-summary" id="success-summary"></div>
        <p class="estimates-disclaimer" id="rfq-success-disclaimer"
          style="display:none; font-size:0.8rem; color:#64748b; margin-top:14px; text-align:center; line-height:1.4;">
          This is a preliminary quote request. Final pricing requires site assessment and detailed system design.</p>
        <button type="button" class="btn btn-yellow btn-block" id="btn-close-success"
          style="margin-top:16px;">Done</button>
      </div>
    </div>
  </div>
