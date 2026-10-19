<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $is_success ? 'Order Confirmation' : 'Secure Checkout'; ?> | Apex Diurnal Solar Panels</title>
  <meta name="description" content="Official checkout portal for Apex Diurnal solar arrays, smart inverters, and professional installation bookings.">

  <!-- Brand Typography: Verdana, sans-serif -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link rel="stylesheet" href="checkout.css?v=<?php echo $view->assetMtime(__DIR__ . '/checkout.css'); ?>">
</head>
<body data-empty-cart-alert="<?php echo (!$is_post && empty($selected_items)) ? 'true' : 'false'; ?>">

  <!-- Brand Navigation Header -->
  <header class="brand-header">
    <div class="brand-nav-container">
      <a href="../index.php" class="brand-logo-link" title="Return to Apex Diurnal Homepage">
        <img src="../assets/images/logo-clean.png?v=<?php echo $view->assetMtime(__DIR__ . '/../assets/images/logo-clean.png'); ?>" alt="Apex Diurnal Solar Logo" class="brand-logo-img">
        <div class="brand-name">
          <span class="brand-name-title">APEX</span>
          <span class="brand-name-sub">DIURNAL</span>
        </div>
      </a>
    </div>
  </header>

  <main class="page-wrapper">
    <!-- Interactive Notification Toast Container -->
    <div id="checkout-toast" class="checkout-toast" role="alert" aria-live="assertive"></div>

    <?php if ($is_success): ?>
      <!-- ================================================================= -->
      <!-- DISPLAY MODE: POST Validation Passes (Order Confirmation Screen)   -->
      <!-- ================================================================= -->
      <div class="confirmation-container">
        
        <div class="conf-hero-banner">
          <h1 class="conf-hero-title">Order Confirmed!</h1>
          <p class="conf-hero-subtitle">
            Thank you, <strong><?php echo $safe($full_name); ?></strong>. Your solar technology and service request has been safely received. A consultation and installation team member will reach out shortly.
          </p>
          <div class="conf-ref-badge">
            <span>Reference ID:</span>
            <strong><?php echo $safe($order_id); ?></strong>
          </div>
        </div>

        <div class="conf-content-grid">
          
          <!-- Customer & Property Details Card -->
          <div class="card-panel">
            <div class="card-header">
              <h2 class="card-header-title">Customer Details</h2>
              <span class="card-step-badge">Verified</span>
            </div>
            <div class="card-body">
              <ul class="conf-details-list">
                <li class="conf-details-item">
                  <span class="conf-details-label">Full Name</span>
                  <span class="conf-details-value"><?php echo $safe($full_name); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Email Address</span>
                  <span class="conf-details-value"><?php echo $safe($email); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Contact Phone</span>
                  <span class="conf-details-value"><?php echo $safe($phone); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Property Type</span>
                  <span class="conf-details-value">
                    <?php echo $safe($property_type); ?> Property
                  </span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Street Address</span>
                  <span class="conf-details-value"><?php echo $safe($street_address); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">City</span>
                  <span class="conf-details-value"><?php echo $safe($city); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Province</span>
                  <span class="conf-details-value"><?php echo $safe($province); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Postal Code</span>
                  <span class="conf-details-value"><?php echo $safe($postal_code); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Full Installation Address</span>
                  <span class="conf-details-value"><?php echo $safe($address); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Payment Method Selected</span>
                  <span class="conf-details-value">
                    <?php echo $safe($payment_method); ?>
                    <?php if (!empty($masked_card)): ?>
                      <span class="conf-card-sub" style="display:block; font-size:0.84rem; color:var(--color-text-muted); margin-top:2px;">
                        Paid via <?php echo $safe($masked_card); ?>
                      </span>
                    <?php endif; ?>
                  </span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Order Placed On</span>
                  <span class="conf-details-value"><?php echo $safe($order_date); ?></span>
                </li>
                <?php if ($notes !== ''): ?>
                <li class="conf-details-item">
                  <span class="conf-details-label">Special Site Notes</span>
                  <span class="conf-details-value"><?php echo nl2br($safe($notes)); ?></span>
                </li>
                <?php endif; ?>
              </ul>
            </div>
          </div>

          <!-- Order Cost & Summary Card -->
          <div class="card-panel">
            <div class="card-header">
              <h2 class="card-header-title">Itemized Summary</h2>
              <span class="card-step-badge">8% Tax Applied</span>
            </div>
            <div class="card-body">
              <table class="receipt-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th class="qty-col">Qty</th>
                    <th class="num-col">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($selected_items as $p_name => $p_qty): 
                    $u_price = $catalog_products[$p_name];
                    $line_sum = $u_price * $p_qty;
                  ?>
                    <tr>
                      <td>
                        <span class="receipt-item-name"><?php echo $safe($p_name); ?></span>
                        <div style="font-size:0.75rem; color:var(--color-text-muted);">
                          ₱<?php echo number_format($u_price, 2); ?> each
                        </div>
                      </td>
                      <td class="qty-col"><?php echo (int)$p_qty; ?></td>
                      <td class="num-col font-weight-bold">
                        <strong>₱<?php echo number_format($line_sum, 2); ?></strong>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <div class="receipt-summary-totals">
                <div class="summary-line">
                  <span>Subtotal</span>
                  <strong>₱<?php echo number_format($subtotal, 2); ?></strong>
                </div>
                <div class="summary-line">
                  <span>State & Clean Energy Tax (8%)</span>
                  <strong>₱<?php echo number_format($tax_amount, 2); ?></strong>
                </div>
                <div class="summary-line total-line">
                  <span>Total Amount</span>
                  <span class="grand-value">₱<?php echo number_format($grand_total, 2); ?></span>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Confirmation Actions -->
        <div class="conf-actions">
          <a href="receipt.php?order=<?php echo urlencode($order_id); ?>" class="btn-action-primary" id="btn-view-receipt">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px; vertical-align:text-bottom;">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
              <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            View Receipt
          </a>

          <a href="../index.php#products" class="btn-action-outline">
            Order More Products
          </a>

          <a href="../index.php" class="btn-action-outline">
            Return to Homepage
          </a>
        </div>

      </div>

    <?php elseif (!$is_post && empty($selected_items)): ?>
      <!-- ================================================================= -->
      <!-- DISPLAY MODE 2: Empty Cart / No Order Made State                   -->
      <!-- ================================================================= -->
      <div class="empty-cart-container">
        <div class="checkout-breadcrumb-bar">
          <a href="../index.php" class="back-pill-link" title="Return to Apex Diurnal Homepage">
            <span>&larr; Back to Homepage</span>
          </a>
        </div>

        <div class="empty-cart-card">
          <span class="checkout-badge">No Order Found</span>
          <div class="empty-selection-alert" style="display:inline-flex; align-items:center; gap:10px; margin: 12px auto 18px; font-size:0.92rem; padding:12px 20px; background:#fef2f2; color:#b91c1c; border:1.5px solid #f87171; border-radius:8px;">
            <span><strong>Notification:</strong> You cannot checkout without an order! Please select products from our catalog first.</span>
          </div>
          <h1 class="empty-cart-title">Your Cart is Currently Empty</h1>
          <p class="empty-cart-desc">
            You cannot proceed with checkout because no order has been made yet. Please browse our certified solar hardware, smart hybrid inverters, or professional installation bookings and add items to your cart first.
          </p>

          <div class="empty-cart-steps">
            <div class="empty-step-item">
              <span class="empty-step-num">1</span>
              <span>Browse solar systems &amp; services</span>
            </div>
            <div class="empty-step-arrow">&rarr;</div>
            <div class="empty-step-item">
              <span class="empty-step-num">2</span>
              <span>Add products to your cart</span>
            </div>
            <div class="empty-step-arrow">&rarr;</div>
            <div class="empty-step-item">
              <span class="empty-step-num">3</span>
              <span>Complete secure checkout</span>
            </div>
          </div>

          <div class="empty-cart-actions">
            <a href="../index.php#products" class="btn-action-accent">
              Browse Solar Products
            </a>
            <a href="../index.php" class="btn-action-outline">
              Return to Homepage
            </a>
          </div>
        </div>
      </div>

    <?php else: ?>
      <!-- ================================================================= -->
      <!-- DISPLAY MODE 3: Checkout Form (Active Order OR POST Validation)    -->
      <!-- ================================================================= -->
      
      <!-- Top Back Navigation Option -->
      <div class="checkout-breadcrumb-bar">
        <a href="../index.php" class="back-pill-link" title="Return to Apex Diurnal Homepage">
          <span>&larr; Back to Homepage</span>
        </a>
      </div>

      <div class="checkout-intro">
        <h1 class="checkout-title">Complete Your Solar Order</h1>
        <p class="checkout-subtitle">Secure configuration, certified solar hardware, and professional booking in one step.</p>
      </div>

      <?php if (!empty($errors)): ?>
        <!-- Error summary banner highlighting validation failures -->
        <div class="error-summary-banner" role="alert" aria-live="assertive">
          <div class="error-summary-header">
            <span>Please correct the <?php echo count($errors); ?> highlighted issue(s) below:</span>
          </div>
          <ul class="error-summary-list">
            <?php foreach ($errors as $field_err): ?>
              <li><?php echo $safe($field_err); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!$view->loggedIn()): ?>
        <!-- Guest Checkout Encouragement Banner -->
        <div class="guest-checkout-banner" style="display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, #1b335f 0%, #25467d 100%); color: #ffffff; padding: 1.15rem 1.4rem; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 4px 14px rgba(27,51,95,0.12); flex-wrap: wrap; gap: 12px;">
          <div>
            <div style="font-weight: 700; font-size: 0.98rem; color: #ffffff;">Checking out as Guest</div>
            <div style="font-size: 0.85rem; color: #cbd5e1; margin-top: 2px;">Have an Apex account? Sign in to automatically pre-fill your saved shipping address and track orders.</div>
          </div>
          <a href="../auth/login.php?redirect=<?php echo urlencode('../cart/checkout.php'); ?>" style="background: #fee000; color: #1b335f; font-weight: 700; font-size: 0.88rem; padding: 0.6rem 1.15rem; border-radius: 8px; text-decoration: none; white-space: nowrap; transition: background 0.2s; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">Sign In &amp; Auto-fill</a>
        </div>
      <?php endif; ?>

      <form action="checkout.php" method="POST" id="apex-checkout-form" novalidate>
        
        <div class="checkout-grid">

          <!-- Left Column: Customer Details, Address, Property, Payment -->
          <div class="form-column">

            <!-- Customer Contact Information Card -->
            <div class="card-panel">
              <div class="card-header">
                <h2 class="card-header-title">1. Customer Details</h2>
                <span class="card-step-badge">Required</span>
              </div>
              <div class="card-body">
                
                <?php if (!empty($is_prefilled)): ?>
                  <div class="checkout-prefill-badge" style="display: flex; align-items: center; justify-content: space-between; gap: 8px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 10px 14px; border-radius: 8px; font-size: 0.86rem; font-weight: 500; margin-bottom: 1.25rem;">
                    <span>Delivery details auto-filled from your saved profile.</span>
                    <a href="../account/settings.php#profile-details" target="_blank" style="color: #047857; font-weight: 600; text-decoration: underline; white-space: nowrap; font-size: 0.82rem;">Manage in Settings &rarr;</a>
                  </div>
                <?php endif; ?>

                <!-- Full Name -->
                <div class="form-group">
                  <label for="full_name" class="form-label">
                    Full Name <span class="required-mark">*</span>
                  </label>
                  <input 
                    type="text" 
                    id="full_name" 
                    name="full_name" 
                    class="form-control <?php echo isset($errors['full_name']) ? 'has-error' : ''; ?>" 
                    placeholder="e.g. Eleanor Vance" 
                    value="<?php echo $safe($full_name); ?>"
                    required
                    autocomplete="name"
                  >
                  <?php if (isset($errors['full_name'])): ?>
                    <div class="field-error-message">
                      <?php echo $safe($errors['full_name']); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Email & Phone in 2-column row -->
                <div class="form-row">
                  <div class="form-group">
                    <label for="email" class="form-label">
                      Email Address <span class="required-mark">*</span>
                    </label>
                    <input 
                      type="email" 
                      id="email" 
                      name="email" 
                      class="form-control <?php echo isset($errors['email']) ? 'has-error' : ''; ?>" 
                      placeholder="e.g. eleanor@apexsolar.com" 
                      value="<?php echo $safe($email); ?>"
                      required
                      autocomplete="email"
                    >
                    <?php if (isset($errors['email'])): ?>
                      <div class="field-error-message">
                        <?php echo $safe($errors['email']); ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="form-group">
                    <label for="phone" class="form-label">
                      Phone Number <span class="required-mark">*</span>
                    </label>
                    <input 
                      type="tel" 
                      id="phone" 
                      name="phone" 
                      class="form-control <?php echo isset($errors['phone']) ? 'has-error' : ''; ?>" 
                      placeholder="e.g. 09171234567" 
                      value="<?php echo $safe($phone); ?>"
                      maxlength="11"
                      inputmode="numeric"
                      pattern="[0-9]{11}"
                      oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)"
                      required
                      autocomplete="tel"
                    >
                    <?php if (isset($errors['phone'])): ?>
                      <div class="field-error-message">
                        <?php echo $safe($errors['phone']); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label for="province" class="form-label">
                      Province <span class="required-mark">*</span>
                    </label>
                    <select 
                      id="province" 
                      name="province" 
                      class="form-control <?php echo isset($errors['province']) ? 'has-error' : ''; ?>" 
                      required
                      autocomplete="address-level1"
                    >
                      <option value="">Select Province</option>
                      <?php foreach ($allowed_provinces as $p): ?>
                        <option value="<?php echo $safe($p); ?>" <?php echo ($province === $p) ? 'selected' : ''; ?>><?php echo $safe($p); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['province'])): ?>
                      <div class="field-error-message">
                        <?php echo $safe($errors['province']); ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="form-group">
                    <label for="city" class="form-label">
                      City / Municipality <span class="required-mark">*</span>
                    </label>
                    <select 
                      id="city" 
                      name="city" 
                      class="form-control <?php echo isset($errors['city']) ? 'has-error' : ''; ?>" 
                      required
                      autocomplete="address-level2"
                    >
                      <option value=""><?php echo !empty($province) ? 'Select City / Municipality' : 'Select Province First'; ?></option>
                      <?php
                      // Populate only the cities/municipalities under the selected province
                      $available_cities = (!empty($province) && isset($provinceCityMap[$province])) ? $provinceCityMap[$province] : [];
                      foreach ($available_cities as $c):
                      ?>
                        <option value="<?php echo $safe($c); ?>" <?php echo ($city === $c) ? 'selected' : ''; ?>><?php echo $safe($c); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['city'])): ?>
                      <div class="field-error-message">
                        <?php echo $safe($errors['city']); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="form-group">
                  <label for="street_address" class="form-label">
                    Barangay / Street Address <span class="required-mark">*</span>
                  </label>
                  <input 
                    type="text"
                    id="street_address" 
                    name="street_address" 
                    class="form-control <?php echo isset($errors['street_address']) ? 'has-error' : ''; ?>" 
                    placeholder="e.g. Brgy. San Isidro, 123 Rizal St."
                    value="<?php echo $safe($street_address); ?>"
                    required
                    autocomplete="street-address"
                  >
                  <?php if (isset($errors['street_address'])): ?>
                    <div class="field-error-message">
                      <?php echo $safe($errors['street_address']); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Postal Code - Text input only (no dropdown) -->
                <div class="form-group">
                  <label for="postal_code" class="form-label">
                    Postal Code <span class="required-mark">*</span>
                  </label>
                  <input 
                    type="text" 
                    id="postal_code" 
                    name="postal_code" 
                    class="form-control <?php echo isset($errors['postal_code']) ? 'has-error' : ''; ?>" 
                    placeholder="e.g. 1000"
                    value="<?php echo $safe($postal_code); ?>"
                    required
                    inputmode="numeric"
                    pattern="\d{4}"
                    maxlength="4"
                    autocomplete="postal-code"
                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,4)"
                    onkeypress="return event.charCode>=48 && event.charCode<=57"
                  >
                  <?php if (isset($errors['postal_code'])): ?>
                    <div class="field-error-message">
                      <?php echo $safe($errors['postal_code']); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <?php if ($view->loggedIn()): ?>
                  <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px dashed rgba(27,51,95,0.15); font-size: 0.82rem; color: #64748b; display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; color:#64748b;">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="16" x2="12" y2="12"></line>
                      <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <span>To update your default shipping address, please configure your details in <a href="../account/settings.php#profile-details" target="_blank" style="color: var(--color-navy); font-weight: 600; text-decoration: underline;">Account Settings</a>.</span>
                  </div>
                <?php endif; ?>

              </div>
            </div>

            <!-- Property Type Card (Residential Only) -->
            <div class="card-panel">
              <div class="card-header">
                <h2 class="card-header-title">2. Property Type</h2>
                <span class="card-step-badge">Residential</span>
              </div>
              <div class="card-body">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">
                    Property Category <span class="required-mark">*</span>
                  </label>
                  <div class="property-single-wrap">
                    <label class="radio-card-label single-choice">
                      <input 
                        type="radio" 
                        name="property_type" 
                        value="Residential" 
                        checked
                      >
                      <div class="radio-card-content is-selected">
                        <div class="radio-card-header-flex">
                          <div class="radio-card-title-row">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="radio-card-svg">
                              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                              <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            <span class="radio-card-title">Residential</span>
                          </div>
                          <span class="badge-tag-selected">Default Plan</span>
                        </div>
                        <div class="radio-card-desc">Single-family, townhouse, or duplex rooftop setup (Apex Diurnal certified consumer array)</div>
                      </div>
                    </label>
                  </div>
                  <?php if (isset($errors['property_type'])): ?>
                    <div class="field-error-message">
                      <?php echo $safe($errors['property_type']); ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Payment Method Card -->
            <div class="card-panel">
              <div class="card-header">
                <h2 class="card-header-title">3. Payment Method</h2>
                <span class="card-step-badge">Required</span>
              </div>
              <div class="card-body">
                <div class="form-group">
                  <label class="form-label">
                    Choose Your Preferred Payment Option <span class="required-mark">*</span>
                  </label>
                  <div class="payment-options-list">
                    <?php foreach ($allowed_payment_methods as $pay_key => $pay_label): 
                      $is_pay_selected = ($payment_method === $pay_key) || ($payment_method === '' && $pay_key === 'Credit Card');
                    ?>
                      <label class="payment-option-item <?php echo $is_pay_selected ? 'selected' : ''; ?>" data-method="<?php echo $safe($pay_key); ?>">
                        <input 
                          type="radio" 
                          name="payment_method" 
                          value="<?php echo $safe($pay_key); ?>" 
                          <?php echo $is_pay_selected ? 'checked' : ''; ?>
                          onchange="updatePaymentHighlight(this)"
                        >
                        <span class="payment-option-title"><?php echo $safe($pay_label); ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <?php if (isset($errors['payment_method'])): ?>
                    <div class="field-error-message">
                      <?php echo $safe($errors['payment_method']); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Dynamic Standard Card Details Form -->
                <?php 
                  $is_card_active = ($payment_method === 'Credit Card' || $payment_method === '');
                ?>
                <div id="card-details-fields" class="card-details-box" style="<?php echo $is_card_active ? 'display: block;' : 'display: none;'; ?>">
                  <div class="card-details-header">
                    <div class="card-details-header-title">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                      </svg>
                      <span>Card Details</span>
                    </div>
                    <div class="card-brands-badges">
                      <span class="card-badge-pill" id="badge-visa">VISA</span>
                      <span class="card-badge-pill" id="badge-mastercard">MC</span>
                      <span class="card-badge-pill" id="badge-amex">AMEX</span>
                    </div>
                  </div>

                  <div class="card-inputs-grid">
                    <!-- Cardholder Name -->
                    <div class="form-group">
                      <label for="card_name" class="form-label">
                        Cardholder Name <span class="required-mark">*</span>
                      </label>
                      <input 
                        type="text" 
                        id="card_name" 
                        name="card_name" 
                        class="form-control <?php echo isset($errors['card_name']) ? 'has-error' : ''; ?>" 
                        placeholder="e.g. Eleanor Vance" 
                        value="<?php echo $safe($card_name); ?>"
                        autocomplete="cc-name"
                      >
                      <?php if (isset($errors['card_name'])): ?>
                        <div class="field-error-message">
                          <?php echo $safe($errors['card_name']); ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <!-- Card Number -->
                    <div class="form-group">
                      <label for="card_number" class="form-label">
                        Card Number <span class="required-mark">*</span>
                      </label>
                      <div class="card-number-wrapper">
                        <input 
                          type="text" 
                          id="card_number" 
                          name="card_number" 
                          class="form-control <?php echo isset($errors['card_number']) ? 'has-error' : ''; ?>" 
                          placeholder="0000 0000 0000 0000" 
                          value="<?php echo $safe($card_number); ?>"
                          maxlength="19"
                          inputmode="numeric"
                          autocomplete="cc-number"
                        >
                        <span class="card-brand-icon" id="card-brand-icon" title="Card Type">💳</span>
                      </div>
                      <?php if (isset($errors['card_number'])): ?>
                        <div class="field-error-message">
                          <?php echo $safe($errors['card_number']); ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <!-- Expiration & CVV in 2-column row -->
                    <div class="form-row">
                      <div class="form-group">
                        <label for="card_expiry" class="form-label">
                          Expiration Date <span class="required-mark">*</span>
                        </label>
                        <input 
                          type="text" 
                          id="card_expiry" 
                          name="card_expiry" 
                          class="form-control <?php echo isset($errors['card_expiry']) ? 'has-error' : ''; ?>" 
                          placeholder="MM/YY" 
                          value="<?php echo $safe($card_expiry); ?>"
                          maxlength="5"
                          inputmode="numeric"
                          autocomplete="cc-exp"
                        >
                        <?php if (isset($errors['card_expiry'])): ?>
                          <div class="field-error-message">
                            <?php echo $safe($errors['card_expiry']); ?>
                          </div>
                        <?php endif; ?>
                      </div>

                      <div class="form-group">
                        <label for="card_cvv" class="form-label">
                          Security Code (CVV) <span class="required-mark">*</span>
                        </label>
                        <input 
                          type="password" 
                          id="card_cvv" 
                          name="card_cvv" 
                          class="form-control <?php echo isset($errors['card_cvv']) ? 'has-error' : ''; ?>" 
                          placeholder="123" 
                          maxlength="4" 
                          inputmode="numeric"
                          autocomplete="cc-csc"
                        >
                        <?php if (isset($errors['card_cvv'])): ?>
                          <div class="field-error-message">
                            <?php echo $safe($errors['card_cvv']); ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="card-security-note">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                      </svg>
                      <span>Standard 256-Bit SSL Encrypted. Security code (CVV) is never stored.</span>
                    </div>
                  </div>
                </div>

                <!-- Optional Special Notes -->
                <div class="form-group" style="margin-top:16px;">
                  <label for="notes" class="form-label">
                    Site Access / Special Notes (Optional)
                  </label>
                  <textarea 
                    id="notes" 
                    name="notes" 
                    rows="2" 
                    class="form-control" 
                    placeholder="Provide gate codes, roof type details, or preferred appointment times..."
                  ><?php echo $safe($notes); ?></textarea>
                </div>
              </div>
            </div>

          </div>

          <!-- Right Column: Product Selection & Pricing Breakdown -->
          <div class="sidebar-column">

            <div class="card-panel">
              <div class="card-header">
                <h2 class="card-header-title">Product Selection</h2>
                <span class="card-step-badge">Configurator</span>
              </div>
              <div class="card-body">
                
                <?php if (isset($errors['products'])): ?>
                  <div class="field-error-message" style="margin-bottom:14px;">
                    <?php echo $safe($errors['products']); ?>
                  </div>
                <?php endif; ?>

                <div class="products-checklist">
                  <?php foreach ($catalog_products as $item_name => $price_val): 
                    $is_checked = isset($selected_items[$item_name]);
                    $current_qty = $is_checked ? (int)$selected_items[$item_name] : 1;
                    $info = $product_details[$item_name] ?? ['description' => '', 'image' => '', 'badge' => ''];
                  ?>
                    <div class="product-select-card <?php echo $is_checked ? 'is-selected' : ''; ?>" id="card-<?php echo md5($item_name); ?>">
                      <div class="product-card-top">
                        <div class="product-checkbox-wrapper">
                          <input 
                            type="checkbox" 
                            name="products[]" 
                            value="<?php echo $safe($item_name); ?>" 
                            id="chk-<?php echo md5($item_name); ?>"
                            class="product-checkbox"
                            data-price="<?php echo $price_val; ?>"
                            data-card-id="card-<?php echo md5($item_name); ?>"
                            data-qty-id="qty-<?php echo md5($item_name); ?>"
                            <?php echo $is_checked ? 'checked' : ''; ?>
                            onchange="recalculateTotals()"
                          >
                        </div>
                        <?php if (!empty($info['image']) && $view->assetExists(__DIR__ . '/' . $info['image'])): ?>
                          <img src="<?php echo $safe($info['image']); ?>" alt="<?php echo $safe($item_name); ?>" class="product-thumbnail">
                        <?php endif; ?>
                        <div class="product-info">
                          <div class="product-title-row">
                            <label for="chk-<?php echo md5($item_name); ?>" class="product-name-label">
                              <?php echo $safe($item_name); ?>
                              <?php if (!empty($info['badge'])): ?>
                                <span class="product-badge-tag"><?php echo $safe($info['badge']); ?></span>
                              <?php endif; ?>
                            </label>
                            <span class="product-unit-price">₱<?php echo number_format($price_val, 2); ?></span>
                          </div>
                          <p class="product-desc-text"><?php echo $safe($info['description']); ?></p>
                        </div>
                      </div>

                      <div class="product-qty-row">
                        <span class="qty-label">Quantity:</span>
                        <div class="qty-input-group">
                          <input 
                            type="number" 
                            name="quantities[<?php echo $safe($item_name); ?>]" 
                            id="qty-<?php echo md5($item_name); ?>" 
                            value="<?php echo $current_qty; ?>" 
                            min="1" 
                            max="99" 
                            class="qty-number-input"
                            oninput="recalculateTotals()"
                          >
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <!-- Price Summary Box with 8% Tax -->
                <div class="order-summary-box">
                  <div class="summary-heading">Order Total Breakdown</div>
                  
                  <div class="summary-line">
                    <span>Products Subtotal</span>
                    <strong id="display-subtotal">₱<?php echo number_format($subtotal, 2); ?></strong>
                  </div>
                  
                  <div class="summary-line">
                    <span>Clean Energy Tax (8%)</span>
                    <strong id="display-tax">₱<?php echo number_format($tax_amount, 2); ?></strong>
                  </div>
                  <div class="tax-badge-note">
                    <span>Applicable diurnal solar equipment rate</span>
                  </div>

                  <div class="summary-line total-line">
                    <span>Total Cost</span>
                    <span class="grand-value" id="display-grandtotal">₱<?php echo number_format($grand_total, 2); ?></span>
                  </div>
                </div>

                <!-- Product Selection Warning -->
                <div id="no-products-warning" class="empty-selection-alert" style="<?php echo empty($selected_items) ? 'display:flex;' : 'display:none;'; ?>">
                  <span>At least one product must be selected to complete checkout.</span>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit-order <?php echo empty($selected_items) ? 'disabled' : ''; ?>" id="btn-submit" aria-disabled="<?php echo empty($selected_items) ? 'true' : 'false'; ?>" title="<?php echo empty($selected_items) ? 'Please select at least one product to checkout' : 'Confirm and place order'; ?>">
                  Confirm &amp; Place Order
                </button>

                <!-- Back to Homepage Option -->
                <a href="../index.php" class="btn-cancel-return" title="Cancel Order">
                  <span>Cancel Order</span>
                </a>

                <div class="security-guarantee-note">
                  <span>100% Satisfaction Guarantee & Certified Warranty</span>
                </div>

              </div>
            </div>

          </div>

        </div>

      </form>

    <?php endif; ?>

  </main>

  <!-- Brand Footer -->
  <footer class="brand-footer">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;">
      <div>
        &copy; <?php echo date('Y'); ?> <strong>Apex Diurnal Solar Panels</strong>. All rights reserved.
      </div>
      <div>
        Clean Energy &bull; Intelligent Future &bull; Commercial & Residential Solar Solutions
      </div>
    </div>
  </footer>

  <script>
    window.provinceCityMap = <?php echo json_encode($provinceCityMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  </script>
  <script src="checkout.js?v=<?php echo $view->assetMtime(__DIR__ . '/checkout.js'); ?>" defer></script>
</body>
</html>
