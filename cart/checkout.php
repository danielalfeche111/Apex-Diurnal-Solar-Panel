<?php
/**
 * checkout.php - Apex Diurnal Solar Panels Customer Checkout & Order Processing
 *
 * Brand Requirements:
 * - Primary Color: Deep Navy Blue (#1b335f)
 * - Accent Color: Warm Yellow (#fee000)
 * - Background/Secondary Color: Cloud Dancer (#c7c4be)
 * - Typography: Verdana, sans-serif
 *
 * PHP Logic & Requirements:
 * - Associative array with products:
 *     Residential Arrays => $145.00
 *     Advanced Solar Inverter => $450.00
 *     Professional Installation Booking => $150.00
 * - POST handling with sanitation (htmlspecialchars, trim)
 * - Required validations: Full Name, Email, Phone, Address, Property Type, Payment Method, Products
 * - 8% Tax calculation on subtotal
 * - 3 display modes: GET form, POST invalid (sticky + highlighted errors), POST valid (clean confirmation)
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../validation.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../User.php';
require_once __DIR__ . '/../Cart.php';

// Guest checkout is fully permitted; authenticated users receive auto-prefilling

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------
// 1. Product Selection & Pricing Array (Associative Array)
// ---------------------------------------------------------------------
$catalog_products = [
    'Residential Arrays' => 8120.00,
    'Advanced Solar Inverter' => 25200.00,
    'Professional Installation Booking' => 8400.00,
];

// Product metadata for rich presentation (images & descriptions)
$product_details = [
    'Residential Arrays' => [
        'description' => 'High-efficiency monocrystalline solar panels engineered for residential rooftops.',
        'image' => '../assets/images/residential arrays.png',
        'badge' => 'Bestseller',
    ],
    'Advanced Solar Inverter' => [
        'description' => 'Pure sine-wave hybrid smart inverter with 98.4% peak grid conversion efficiency.',
        'image' => '../assets/images/advance power inverter.png',
        'badge' => 'Smart Tech',
    ],
    'Professional Installation Booking' => [
        'description' => 'Certified master technician site assessment, 3D solar layout modeling & turnkey mounting.',
        'image' => '../assets/images/product-booking.png',
        'badge' => 'Full Service',
    ],
];

// Tax rate constant (8%)
const TAX_RATE = 0.08;

// Available options for validation
$allowed_property_types = ['Residential', 'Commercial'];
$allowed_payment_methods = [
    'Credit Card' => 'Credit / Debit Card (Visa, Mastercard, Amex)',
    'Bank Transfer' => 'Direct Bank Wire Transfer / ACH',
    'PayPal' => 'PayPal',
    'Cash on Delivery' => 'Cash on Delivery',
];
// Province -> Cities/Municipalities mapping using standard Philippine locations
require_once __DIR__ . '/../philippine_locations.php';
$allowed_provinces = $consult_provinces;
$allowed_cities = $consult_cities;
$provinceCityMap = $consultProvinceCityMap;
$cityProvinceMap = $consultCityProvinceMap;

// ---------------------------------------------------------------------
// 2. Initial State & Default Field Values
// ---------------------------------------------------------------------
$full_name      = '';
$email          = '';
$phone          = '';
$street_address = '';
$city           = '';
$province       = '';
$postal_code    = '';
$address        = ''; // composite for confirmation display & backwards compatibility
$property_type  = '';
$payment_method = '';
$notes          = '';
$selected_items = []; // associative: [product_name => quantity]
$errors         = [];
$is_post        = ($_SERVER['REQUEST_METHOD'] === 'POST');
$is_success     = false;
$is_prefilled   = false;

$subtotal    = 0.00;
$tax_amount  = 0.00;
$grand_total = 0.00;
$order_id    = '';
$order_date  = '';

// ---------------------------------------------------------------------
// 3. Form Processing on POST Request
// ---------------------------------------------------------------------
if ($is_post) {
    // Single centralized validation & sanitization
    $validator = new Validator($_POST);
    $clean = $validator->sanitized();

    $full_name      = $clean['full_name'] ?? '';
    $email          = $clean['email'] ?? '';
    $phone          = trim((string)($clean['phone'] ?? ''));
    $street_address = $clean['street_address'] ?? '';
    $city           = $clean['city'] ?? '';
    $province       = $clean['province'] ?? '';
    $postal_code    = $clean['postal_code'] ?? '';

    // Normalize ASCII aliases to canonical UTF-8 city names
    $cityAliasMap = [
      'Binan City' => 'Biñan City',
      'Dasmarinas City' => 'Dasmariñas City',
      'Las Pinas' => 'Las Piñas',
      'Paranaque' => 'Parañaque',
      'Munoz City' => 'Muñoz City',
      'Science City of Munoz' => 'Muñoz City',
      'Science City of Muñoz' => 'Muñoz City',
    ];
    if (isset($cityAliasMap[$city])) { $city = $cityAliasMap[$city]; }
    $property_type  = $clean['property_type'] ?? '';
    $payment_method = $clean['payment_method'] ?? '';
    $notes          = $clean['notes'] ?? '';

    // Re-compose full address for confirmation / display
    $address = trim(implode(', ', array_filter([$street_address, $city, $province])) . ($postal_code !== '' ? ' ' . $postal_code : ''));

    // Process selected products & quantities
    $posted_products   = $_POST['products'] ?? [];
    $posted_quantities = $_POST['quantities'] ?? [];

    if (is_array($posted_products)) {
        foreach ($posted_products as $prod_key) {
            if (array_key_exists($prod_key, $catalog_products)) {
                $raw_qty = isset($posted_quantities[$prod_key]) ? (int)$posted_quantities[$prod_key] : 1;
                $qty = max(1, min(99, $raw_qty));
                $selected_items[$prod_key] = $qty;
            }
        }
    }

    // --- Validation Rules via Centralized Validator ---
    $validator->required([
        'full_name'      => 'Full Name is required.',
        'email'          => 'Email address is required.',
        'phone'          => 'Phone number is required.',
        'street_address' => 'Street address is required (house #, street, barangay).',
        'city'           => 'Please select a city from the dropdown.',
        'province'       => 'Please select a province from the dropdown.',
        'postal_code'    => 'Postal code is required.',
        'property_type'  => 'Please select a Property Type (Residential or Commercial).',
        'payment_method' => 'Please select a preferred Payment Method.'
    ]);
    $validator->minLength('full_name', 3, 'Full Name must be at least 3 characters.');
    $validator->pattern('full_name', "/^[a-zA-Z\s\.\'\-]+$/", 'Full Name contains invalid characters.');
    $validator->email('email', 'Please enter a valid email address (e.g., alex@example.com).');
    $validator->pattern('phone', '/^\d{11}$/', 'Please enter a valid 11-digit mobile number (e.g., 09171234567).');
    $validator->minLength('street_address', 5, 'Please provide a complete street address (at least 5 characters).');
    $validator->in('province', $allowed_provinces, 'Invalid province selected. Please choose from the dropdown list.');
    if ($province !== '' && isset($provinceCityMap[$province])) {
        if (!checkCityMatchesProvince($city, $province, $provinceCityMap)) {
            $validator->addError('city', ($city !== '' ? htmlspecialchars($city) : 'City / Municipality') . ' is not located in ' . htmlspecialchars($province) . '. Please select a valid city or municipality.');
        }
    } else {
        $validator->in('city', $allowed_cities, 'Invalid city or municipality selected. Please choose from the dropdown list.');
    }
    $validator->pattern('postal_code', '/^\d{4}$/', 'Please enter a valid 4-digit postal code (e.g., 1000).');
    $validator->in('property_type', $allowed_property_types, 'Invalid Property Type selected.');
    $validator->in('payment_method', array_keys($allowed_payment_methods), 'Invalid Payment Method selected.');

    if (empty($selected_items)) {
        $validator->addError('products', 'You cannot checkout without an order! Please select at least one product or service to complete checkout.');
    }

    $errors = $validator->errors();

    // --- Calculate Total Price (Tax 8%) ---
    foreach ($selected_items as $prod_name => $quantity) {
        $unit_price = $catalog_products[$prod_name];
        $subtotal += $unit_price * $quantity;
    }
    $tax_amount  = $subtotal * TAX_RATE;
    $grand_total = $subtotal + $tax_amount;

    // --- Success Check ---
    if (empty($errors)) {
        $is_success  = true;
        $order_id    = 'APD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
        $order_date  = date('F j, Y - g:i A');

        // Persist order and line items to database
        try {
            require_once __DIR__ . '/../config.php';
            $db = getConnection();
            if ($db) {
                $user_id = getCurrentUserId();
                $stmtOrd = $db->prepare("
                    INSERT INTO orders (
                        order_number, user_id, customer_name, customer_email, customer_phone,
                        property_type, payment_method, status, subtotal, tax_amount, total_amount,
                        shipping_address, notes
                    ) VALUES (
                        :ord_num, :uid, :name, :email, :phone,
                        :prop, :pmethod, 'pending', :subtotal, :tax, :total,
                        :addr, :notes
                    )
                ");
                $stmtOrd->execute([
                    ':ord_num' => $order_id,
                    ':uid' => $user_id ? (int)$user_id : null,
                    ':name' => $full_name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':prop' => $property_type,
                    ':pmethod' => $payment_method,
                    ':subtotal' => $subtotal,
                    ':tax' => $tax_amount,
                    ':total' => $grand_total,
                    ':addr' => $address,
                    ':notes' => $notes
                ]);
                $new_order_db_id = (int)$db->lastInsertId();

                // Map product names to catalog product_ids
                $name_to_id = [
                    'Residential Arrays' => 'residential-arrays',
                    'Advanced Solar Inverter' => 'advanced-solar-inverter',
                    'Professional Installation Booking' => 'installation-booking'
                ];

                $stmtItem = $db->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price)
                    VALUES (:oid, :pid, :pname, :qty, :uprice, :tprice)
                ");

                $hasInstallationBooking = false;
                foreach ($selected_items as $prod_name => $quantity) {
                    $pid = $name_to_id[$prod_name] ?? strtolower(str_replace(' ', '-', $prod_name));
                    $uprice = $catalog_products[$prod_name] ?? 0.00;
                    $tprice = $uprice * $quantity;
                    $stmtItem->execute([
                        ':oid' => $new_order_db_id,
                        ':pid' => $pid,
                        ':pname' => $prod_name,
                        ':qty' => $quantity,
                        ':uprice' => $uprice,
                        ':tprice' => $tprice
                    ]);

                    if ($pid === 'installation-booking') {
                        $hasInstallationBooking = true;
                    }
                }

                // If installation booking was selected, create linked service booking
                if ($hasInstallationBooking) {
                    $bookingRef = 'INST-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
                    $stmtBk = $db->prepare("
                        INSERT INTO service_bookings (
                            booking_reference, order_id, customer_name, customer_email, customer_phone,
                            service_type, preferred_date, preferred_time_slot, status, address, access_notes
                        ) VALUES (
                            :bref, :oid, :name, :email, :phone,
                            'installation', :pdate, 'morning', 'pending', :addr, :notes
                        )
                    ");
                    $stmtBk->execute([
                        ':bref' => $bookingRef,
                        ':oid' => $new_order_db_id,
                        ':name' => $full_name,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':pdate' => date('Y-m-d', strtotime('+7 days')),
                        ':addr' => $address,
                        ':notes' => $notes
                    ]);
                }

                // Mark active cart as converted and reset user's active cart state
                if ($user_id) {
                    try {
                        require_once __DIR__ . '/../Cart.php';
                        $cartService = new Cart($db);
                        $cartService->markConverted((int)$user_id);
                    } catch (Exception $cartEx) {
                        error_log('[checkout] Error converting cart: ' . $cartEx->getMessage());
                        $stmtClear = $db->prepare("DELETE FROM user_carts WHERE user_id = :uid");
                        $stmtClear->execute([':uid' => $user_id]);
                    }

                    // Save or update profile if requested and user is logged in
                    if (!empty($_POST['save_to_profile'])) {
                        $currentUserModel = new User($db);
                        if ($currentUserModel->findById($user_id)) {
                            $currentUserModel->updateProfile([
                                'full_name'           => $full_name,
                                'phone'               => $phone,
                                'street_address'      => $street_address,
                                'city'                => $city,
                                'province'            => $province,
                                'postal_code'         => $postal_code,
                                'is_default_shipping' => 1,
                                'is_default_billing'  => 1
                            ]);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Order persistence error: ' . $e->getMessage());
        }

        // Clear active session cart if present to prevent duplicate order submissions
        if (isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }
} else {
    // If logged in, validate and hydrate active cart from database
    if (isLoggedIn()) {
        try {
            require_once __DIR__ . '/../Cart.php';
            $cartDb = getConnection();
            if ($cartDb) {
                $cartService = new Cart($cartDb);
                $cartService->validateAndHydrate((int)getCurrentUserId());
            }
        } catch (Exception $e) {
            error_log('[checkout] Error hydrating cart on GET: ' . $e->getMessage());
        }
    }

    // GET Request: Populate selected products from active cart session if items were added
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $cart_map = [
            'residential-arrays'      => 'Residential Arrays',
            'advanced-solar-inverter' => 'Advanced Solar Inverter',
            'installation-booking'    => 'Professional Installation Booking',
        ];
        foreach ($_SESSION['cart'] as $c_id => $c_qty) {
            $qty = (int)$c_qty;
            if (isset($cart_map[$c_id]) && $qty > 0) {
                $selected_items[$cart_map[$c_id]] = max(1, min(99, $qty));
            }
        }
    }

    // Calculate initial totals for items selected from cart (remains $0.00 if cart is empty)
    foreach ($selected_items as $prod_name => $quantity) {
        if (isset($catalog_products[$prod_name])) {
            $subtotal += $catalog_products[$prod_name] * $quantity;
        }
    }
    $tax_amount  = $subtotal * TAX_RATE;
    $grand_total = $subtotal + $tax_amount;

    // If user is authenticated, pre-fill form fields from saved user profile
    if (isLoggedIn()) {
        try {
            $db = getConnection();
            if ($db) {
                $uid = getCurrentUserId();
                $userModel = new User($db);
                if ($userModel->findById($uid)) {
                    $prof = $userModel->getProfile();
                    if (!empty($prof['full_name'])) {
                        $full_name = $prof['full_name'];
                        $is_prefilled = true;
                    }
                    if (!empty($userModel->email)) {
                        $email = $userModel->email;
                    }
                    if (!empty($prof['phone'])) {
                        $phone = substr(preg_replace('/\D/', '', $prof['phone']), 0, 11);
                        $is_prefilled = true;
                    }
                    if (!empty($prof['street_address'])) {
                        $street_address = $prof['street_address'];
                        $is_prefilled = true;
                    }
                    if (!empty($prof['province'])) {
                        $province = $prof['province'];
                        $is_prefilled = true;
                    }
                    if (!empty($prof['city'])) {
                        $city = $prof['city'];
                    }
                    if (!empty($prof['postal_code'])) {
                        $postal_code = $prof['postal_code'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Error loading user profile for checkout: ' . $e->getMessage());
        }
    }
}

// Helper to sanitize display strings
function safe(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
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

  <link rel="stylesheet" href="checkout.css?v=<?php echo filemtime(__DIR__ . '/checkout.css'); ?>">
</head>
<body data-empty-cart-alert="<?php echo (!$is_post && empty($selected_items)) ? 'true' : 'false'; ?>">

  <!-- Brand Navigation Header -->
  <header class="brand-header">
    <div class="brand-nav-container">
      <a href="../index.php" class="brand-logo-link" title="Return to Apex Diurnal Homepage">
        <img src="../assets/images/logo-clean.png?v=<?php echo filemtime(__DIR__ . '/../assets/images/logo-clean.png'); ?>" alt="Apex Diurnal Solar Logo" class="brand-logo-img">
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
            Thank you, <strong><?php echo safe($full_name); ?></strong>. Your solar technology and service request has been safely received. A consultation and installation team member will reach out shortly.
          </p>
          <div class="conf-ref-badge">
            <span>Reference ID:</span>
            <strong><?php echo safe($order_id); ?></strong>
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
                  <span class="conf-details-value"><?php echo safe($full_name); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Email Address</span>
                  <span class="conf-details-value"><?php echo safe($email); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Contact Phone</span>
                  <span class="conf-details-value"><?php echo safe($phone); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Property Type</span>
                  <span class="conf-details-value">
                    <?php echo safe($property_type); ?> Property
                  </span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Street Address</span>
                  <span class="conf-details-value"><?php echo safe($street_address); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">City</span>
                  <span class="conf-details-value"><?php echo safe($city); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Province</span>
                  <span class="conf-details-value"><?php echo safe($province); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Postal Code</span>
                  <span class="conf-details-value"><?php echo safe($postal_code); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Full Installation Address</span>
                  <span class="conf-details-value"><?php echo safe($address); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Payment Method Selected</span>
                  <span class="conf-details-value"><?php echo safe($payment_method); ?></span>
                </li>
                <li class="conf-details-item">
                  <span class="conf-details-label">Order Placed On</span>
                  <span class="conf-details-value"><?php echo safe($order_date); ?></span>
                </li>
                <?php if ($notes !== ''): ?>
                <li class="conf-details-item">
                  <span class="conf-details-label">Special Site Notes</span>
                  <span class="conf-details-value"><?php echo nl2br(safe($notes)); ?></span>
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
                        <span class="receipt-item-name"><?php echo safe($p_name); ?></span>
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
          <a href="../index.php#products" class="btn-action-outline">
            Order More Products
          </a>

          <a href="../index.php" class="btn-action-primary">
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
              <li><?php echo safe($field_err); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!isLoggedIn()): ?>
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
                  <div class="checkout-prefill-badge" style="display: flex; align-items: center; gap: 8px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 10px 14px; border-radius: 8px; font-size: 0.86rem; font-weight: 600; margin-bottom: 1.25rem;">
                    <span>Delivery details auto-filled from your saved profile.</span>
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
                    value="<?php echo safe($full_name); ?>"
                    required
                    autocomplete="name"
                  >
                  <?php if (isset($errors['full_name'])): ?>
                    <div class="field-error-message">
                      <?php echo safe($errors['full_name']); ?>
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
                      value="<?php echo safe($email); ?>"
                      required
                      autocomplete="email"
                    >
                    <?php if (isset($errors['email'])): ?>
                      <div class="field-error-message">
                        <?php echo safe($errors['email']); ?>
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
                      value="<?php echo safe($phone); ?>"
                      maxlength="11"
                      inputmode="numeric"
                      pattern="[0-9]{11}"
                      oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)"
                      required
                      autocomplete="tel"
                    >
                    <?php if (isset($errors['phone'])): ?>
                      <div class="field-error-message">
                        <?php echo safe($errors['phone']); ?>
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
                        <option value="<?php echo safe($p); ?>" <?php echo ($province === $p) ? 'selected' : ''; ?>><?php echo safe($p); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['province'])): ?>
                      <div class="field-error-message">
                        <?php echo safe($errors['province']); ?>
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
                        <option value="<?php echo safe($c); ?>" <?php echo ($city === $c) ? 'selected' : ''; ?>><?php echo safe($c); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['city'])): ?>
                      <div class="field-error-message">
                        <?php echo safe($errors['city']); ?>
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
                    value="<?php echo safe($street_address); ?>"
                    required
                    autocomplete="street-address"
                  >
                  <?php if (isset($errors['street_address'])): ?>
                    <div class="field-error-message">
                      <?php echo safe($errors['street_address']); ?>
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
                    value="<?php echo safe($postal_code); ?>"
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
                      <?php echo safe($errors['postal_code']); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <?php if (isLoggedIn()): ?>
                  <div class="form-group" style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px dashed rgba(27,51,95,0.15);">
                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 0.92rem; color: var(--color-navy); font-weight: 600;">
                      <input type="checkbox" name="save_to_profile" value="1" checked style="width: 1.2rem; height: 1.2rem; margin-top: 2px; accent-color: var(--color-navy); cursor: pointer;">
                      <span>Save / update these address details to my default delivery profile</span>
                    </label>
                    <p style="margin: 4px 0 0 28px; font-size: 0.8rem; color: #64748b;">Future orders will automatically use this shipping information for fast 1-click checkout.</p>
                  </div>
                <?php endif; ?>

              </div>
            </div>

            <!-- Property Type Card (Residential vs Commercial) -->
            <div class="card-panel">
              <div class="card-header">
                <h2 class="card-header-title">2. Property Type</h2>
                <span class="card-step-badge">Required</span>
              </div>
              <div class="card-body">
                <div class="form-group">
                  <label class="form-label">
                    Select Your Property Category <span class="required-mark">*</span>
                  </label>
                  <div class="radio-card-grid">
                    <label class="radio-card-label">
                      <input 
                        type="radio" 
                        name="property_type" 
                        value="Residential" 
                        <?php echo ($property_type === 'Residential' || $property_type === '') ? 'checked' : ''; ?>
                      >
                      <div class="radio-card-content">
                        <div class="radio-card-title">Residential</div>
                        <div class="radio-card-desc">Single-family, townhouse, or duplex rooftop setup</div>
                      </div>
                    </label>

                    <label class="radio-card-label">
                      <input 
                        type="radio" 
                        name="property_type" 
                        value="Commercial" 
                        <?php echo ($property_type === 'Commercial') ? 'checked' : ''; ?>
                      >
                      <div class="radio-card-content">
                        <div class="radio-card-title">Commercial</div>
                        <div class="radio-card-desc">Office building, warehouse, or enterprise solar grid</div>
                      </div>
                    </label>
                  </div>
                  <?php if (isset($errors['property_type'])): ?>
                    <div class="field-error-message">
                      <?php echo safe($errors['property_type']); ?>
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
                      <label class="payment-option-item <?php echo $is_pay_selected ? 'selected' : ''; ?>">
                        <input 
                          type="radio" 
                          name="payment_method" 
                          value="<?php echo safe($pay_key); ?>" 
                          <?php echo $is_pay_selected ? 'checked' : ''; ?>
                          onchange="updatePaymentHighlight(this)"
                        >
                        <span class="payment-option-title"><?php echo safe($pay_label); ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <?php if (isset($errors['payment_method'])): ?>
                    <div class="field-error-message">
                      <?php echo safe($errors['payment_method']); ?>
                    </div>
                  <?php endif; ?>
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
                  ><?php echo safe($notes); ?></textarea>
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
                    <?php echo safe($errors['products']); ?>
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
                            value="<?php echo safe($item_name); ?>" 
                            id="chk-<?php echo md5($item_name); ?>"
                            class="product-checkbox"
                            data-price="<?php echo $price_val; ?>"
                            data-card-id="card-<?php echo md5($item_name); ?>"
                            data-qty-id="qty-<?php echo md5($item_name); ?>"
                            <?php echo $is_checked ? 'checked' : ''; ?>
                            onchange="recalculateTotals()"
                          >
                        </div>
                        <?php if (!empty($info['image']) && file_exists(__DIR__ . '/' . $info['image'])): ?>
                          <img src="<?php echo safe($info['image']); ?>" alt="<?php echo safe($item_name); ?>" class="product-thumbnail">
                        <?php endif; ?>
                        <div class="product-info">
                          <div class="product-title-row">
                            <label for="chk-<?php echo md5($item_name); ?>" class="product-name-label">
                              <?php echo safe($item_name); ?>
                              <?php if (!empty($info['badge'])): ?>
                                <span class="product-badge-tag"><?php echo safe($info['badge']); ?></span>
                              <?php endif; ?>
                            </label>
                            <span class="product-unit-price">₱<?php echo number_format($price_val, 2); ?></span>
                          </div>
                          <p class="product-desc-text"><?php echo safe($info['description']); ?></p>
                        </div>
                      </div>

                      <div class="product-qty-row">
                        <span class="qty-label">Quantity:</span>
                        <div class="qty-input-group">
                          <input 
                            type="number" 
                            name="quantities[<?php echo safe($item_name); ?>]" 
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
  <script src="checkout.js?v=<?php echo filemtime(__DIR__ . '/checkout.js'); ?>" defer></script>
</body>
</html>
