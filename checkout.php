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

require_once __DIR__ . '/auth.php';

// Require login for checkout
requireLogin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------
// 1. Product Selection & Pricing Array (Associative Array)
// ---------------------------------------------------------------------
$catalog_products = [
    'Residential Arrays' => 145.00,
    'Advanced Solar Inverter' => 450.00,
    'Professional Installation Booking' => 150.00,
];

// Product metadata for rich presentation (images & descriptions)
$product_details = [
    'Residential Arrays' => [
        'description' => 'High-efficiency monocrystalline solar panels engineered for residential rooftops.',
        'image' => 'assets/images/residential arrays.png',
        'badge' => 'Bestseller',
    ],
    'Advanced Solar Inverter' => [
        'description' => 'Pure sine-wave hybrid smart inverter with 98.4% peak grid conversion efficiency.',
        'image' => 'assets/images/advance power inverter.png',
        'badge' => 'Smart Tech',
    ],
    'Professional Installation Booking' => [
        'description' => 'Certified master technician site assessment, 3D solar layout modeling & turnkey mounting.',
        'image' => 'assets/images/product-booking.png',
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
    'PayPal' => 'PayPal Express Checkout',
    'Financing / Cash on Delivery' => 'Flexible Solar Financing / Pay on Delivery',
];

// ---------------------------------------------------------------------
// 2. Initial State & Default Field Values
// ---------------------------------------------------------------------
$full_name      = '';
$email          = '';
$phone          = '';
$address        = '';
$property_type  = '';
$payment_method = '';
$notes          = '';
$selected_items = []; // associative: [product_name => quantity]
$errors         = [];
$is_post        = ($_SERVER['REQUEST_METHOD'] === 'POST');
$is_success     = false;

$subtotal    = 0.00;
$tax_amount  = 0.00;
$grand_total = 0.00;
$order_id    = '';
$order_date  = '';

// ---------------------------------------------------------------------
// 3. Form Processing on POST Request
// ---------------------------------------------------------------------
if ($is_post) {
    // Sanitize scalar inputs using trim() and strip invalid control characters
    $full_name      = trim($_POST['full_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $property_type  = trim($_POST['property_type'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');

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

    // --- Validation Rules ---
    // Full Name
    if ($full_name === '') {
        $errors['full_name'] = 'Full Name is required.';
    } elseif (mb_strlen($full_name) < 3) {
        $errors['full_name'] = 'Full Name must be at least 3 characters.';
    } elseif (!preg_match("/^[a-zA-Z\s\.\'\-]+$/", $full_name)) {
        $errors['full_name'] = 'Full Name contains invalid characters.';
    }

    // Email
    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address (e.g., alex@example.com).';
    }

    // Phone
    if ($phone === '') {
        $errors['phone'] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9\+\-\s\(\)\.]{7,22}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number (at least 7 digits).';
    }

    // Address
    if ($address === '') {
        $errors['address'] = 'Installation / billing street address is required.';
    } elseif (mb_strlen($address) < 6) {
        $errors['address'] = 'Please provide a complete street address including city & postal code.';
    }

    // Property Type
    if ($property_type === '') {
        $errors['property_type'] = 'Please select a Property Type (Residential or Commercial).';
    } elseif (!in_array($property_type, $allowed_property_types, true)) {
        $errors['property_type'] = 'Invalid Property Type selected.';
    }

    // Payment Method
    if ($payment_method === '') {
        $errors['payment_method'] = 'Please select a preferred Payment Method.';
    } elseif (!array_key_exists($payment_method, $allowed_payment_methods)) {
        $errors['payment_method'] = 'Invalid Payment Method selected.';
    }

    // Product Selection Validation
    if (empty($selected_items)) {
        $errors['products'] = 'You cannot checkout without an order! Please select at least one product or service to complete checkout.';
    }

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

        // Clear active session cart if present to prevent duplicate order submissions
        if (isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }
} else {
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

  <style>
    /* ==========================================================================
       APEX DIURNAL DESIGN SYSTEM & BRAND TOKENS
       - Primary: Deep Navy Blue (#1b335f)
       - Accent: Warm Yellow (#fee000)
       - Background/Secondary: Cloud Dancer (#c7c4be)
       - Typography: Verdana, Geneva, Tahoma, sans-serif
       ========================================================================== */
    :root {
      --color-primary: #1b335f;
      --color-primary-dark: #10203d;
      --color-primary-light: #2c4b82;
      --color-accent: #fee000;
      --color-accent-hover: #e5ca00;
      --color-accent-light: #fff7b3;
      --color-bg-secondary: #c7c4be;
      --color-bg-soft: #f4f3f0;
      --color-card-bg: #ffffff;
      --color-text-main: #1b335f;
      --color-text-body: #2d3748;
      --color-text-muted: #5f6b7c;
      --color-border: #dcd9d4;
      --color-error: #b91c1c;
      --color-error-bg: #fef2f2;
      --color-error-border: #f87171;
      --color-success: #15803d;
      --color-success-bg: #f0fdf4;
      --shadow-sm: 0 2px 4px rgba(27, 51, 95, 0.06);
      --shadow-md: 0 4px 14px rgba(27, 51, 95, 0.1);
      --shadow-lg: 0 10px 28px rgba(27, 51, 95, 0.14);
      --radius-sm: 6px;
      --radius-md: 10px;
      --radius-lg: 16px;
      --font-family: Verdana, Geneva, Tahoma, sans-serif;
      --transition: all 0.25s ease-in-out;
    }

    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: var(--font-family);
      background-color: var(--color-bg-secondary);
      background-image: 
        radial-gradient(circle at 15% 15%, rgba(254, 224, 0, 0.15) 0%, transparent 40%),
        radial-gradient(circle at 85% 85%, rgba(27, 51, 95, 0.12) 0%, transparent 45%);
      background-attachment: fixed;
      color: var(--color-text-body);
      line-height: 1.6;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Top Brand Navigation Bar */
    .brand-header {
      background: var(--color-primary);
      color: #ffffff;
      padding: 16px 24px;
      box-shadow: var(--shadow-md);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .brand-nav-container {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .brand-logo-link {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: #ffffff;
    }

    .brand-logo-link:focus-visible {
      outline: 2px solid var(--color-accent);
      outline-offset: 4px;
    }

    .brand-logo-img {
      height: 42px;
      width: auto;
      object-fit: contain;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
    }

    .brand-name {
      display: flex;
      flex-direction: column;
      line-height: 1.1;
    }

    .brand-name-title {
      font-size: 1.25rem;
      font-weight: 800;
      letter-spacing: 2px;
      color: var(--color-accent);
    }

    .brand-name-sub {
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 3px;
      color: #ffffff;
      opacity: 0.9;
    }

    .brand-header-actions {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .header-back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, 0.12);
      border: 1.5px solid rgba(254, 224, 0, 0.4);
      color: #ffffff;
      padding: 7px 16px;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 700;
      letter-spacing: 0.3px;
      text-decoration: none;
      transition: var(--transition);
    }

    .header-back-btn svg {
      color: var(--color-accent);
      transition: transform 0.2s ease;
    }

    .header-back-btn:hover {
      background: var(--color-accent);
      color: var(--color-primary);
      border-color: var(--color-accent);
      transform: translateY(-1px);
    }

    .header-back-btn:hover svg {
      color: var(--color-primary);
      transform: translateX(-3px);
    }

    .header-security-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(254, 224, 0, 0.4);
      color: #ffffff;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.5px;
    }

    .header-security-badge svg {
      color: var(--color-accent);
    }

    .checkout-breadcrumb-bar {
      margin-bottom: 20px;
    }

    .back-pill-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #ffffff;
      color: var(--color-primary);
      border: 1.5px solid rgba(27, 51, 95, 0.2);
      padding: 8px 18px;
      border-radius: 999px;
      font-size: 0.85rem;
      font-weight: 700;
      text-decoration: none;
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
    }

    .back-pill-link svg {
      color: var(--color-primary);
      transition: transform 0.2s ease;
    }

    .back-pill-link:hover {
      border-color: var(--color-primary);
      background: #faf9f7;
      transform: translateX(-3px);
      box-shadow: var(--shadow-md);
    }

    .back-pill-link:hover svg {
      transform: translateX(-3px);
    }

    .btn-cancel-return {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      background: transparent;
      color: var(--color-primary);
      font-family: var(--font-family);
      font-size: 0.88rem;
      font-weight: 700;
      padding: 12px 20px;
      border: 1.5px solid var(--color-border);
      border-radius: var(--radius-md);
      text-decoration: none;
      transition: var(--transition);
      margin-top: 10px;
    }

    .btn-cancel-return:hover {
      background: #ffffff;
      border-color: var(--color-primary);
      color: var(--color-primary);
      transform: translateY(-1px);
      box-shadow: var(--shadow-sm);
    }

    .btn-cancel-return svg {
      transition: transform 0.2s ease;
    }

    .btn-cancel-return:hover svg {
      transform: translateX(-3px);
    }

    /* Main Container */
    .page-wrapper {
      flex: 1;
      max-width: 1200px;
      width: 100%;
      margin: 32px auto 48px;
      padding: 0 20px;
    }

    /* Page Heading Banner */
    .checkout-intro {
      margin-bottom: 28px;
      text-align: center;
    }

    .checkout-badge {
      display: inline-block;
      background: var(--color-primary);
      color: var(--color-accent);
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      padding: 6px 14px;
      border-radius: 999px;
      margin-bottom: 10px;
    }

    .checkout-title {
      font-size: 2rem;
      color: var(--color-primary);
      font-weight: 800;
      letter-spacing: -0.5px;
    }

    .checkout-subtitle {
      color: #4a5568;
      font-size: 0.95rem;
      margin-top: 6px;
    }

    /* Global Error Banner */
    .error-summary-banner {
      background: var(--color-error-bg);
      border-left: 6px solid var(--color-error);
      border-top: 1px solid var(--color-error-border);
      border-right: 1px solid var(--color-error-border);
      border-bottom: 1px solid var(--color-error-border);
      border-radius: var(--radius-md);
      padding: 16px 20px;
      margin-bottom: 28px;
      box-shadow: var(--shadow-sm);
      animation: shakeFade 0.4s ease-in-out;
    }

    @keyframes shakeFade {
      0% { transform: translateY(-6px); opacity: 0; }
      100% { transform: translateY(0); opacity: 1; }
    }

    .error-summary-header {
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--color-error);
      font-weight: 700;
      font-size: 1rem;
      margin-bottom: 8px;
    }

    .error-summary-list {
      margin-left: 28px;
      color: #7f1d1d;
      font-size: 0.88rem;
    }

    .error-summary-list li {
      margin-bottom: 4px;
    }

    /* Two-Column Checkout Layout */
    .checkout-grid {
      display: grid;
      grid-template-columns: 1.35fr 1fr;
      gap: 32px;
      align-items: start;
    }

    @media (max-width: 960px) {
      .checkout-grid {
        grid-template-columns: 1fr;
      }
    }

    /* Card Panels */
    .card-panel {
      background: var(--color-card-bg);
      border-radius: var(--radius-lg);
      border: 1px solid rgba(27, 51, 95, 0.12);
      box-shadow: var(--shadow-md);
      overflow: hidden;
      margin-bottom: 24px;
      transition: var(--transition);
    }

    .card-panel:hover {
      box-shadow: var(--shadow-lg);
    }

    .card-header {
      background: var(--color-primary);
      color: #ffffff;
      padding: 16px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 3px solid var(--color-accent);
    }

    .card-header-title {
      font-size: 1.1rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card-header-title svg {
      color: var(--color-accent);
    }

    .card-step-badge {
      background: var(--color-accent);
      color: var(--color-primary);
      font-weight: 800;
      font-size: 0.75rem;
      padding: 3px 9px;
      border-radius: 999px;
    }

    .card-body {
      padding: 24px;
    }

    /* Form Fields Styling */
    .form-group {
      margin-bottom: 20px;
    }

    .form-group:last-child {
      margin-bottom: 0;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    @media (max-width: 600px) {
      .form-row {
        grid-template-columns: 1fr;
      }
    }

    .form-label {
      display: block;
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--color-primary);
      margin-bottom: 7px;
      letter-spacing: 0.2px;
    }

    .form-label .required-mark {
      color: var(--color-error);
      margin-left: 2px;
      font-weight: 900;
    }

    .form-control {
      width: 100%;
      padding: 12px 14px;
      font-family: var(--font-family);
      font-size: 0.92rem;
      color: var(--color-text-main);
      background: #faf9f7;
      border: 1.5px solid var(--color-border);
      border-radius: var(--radius-sm);
      transition: var(--transition);
    }

    .form-control:focus {
      outline: none;
      background: #ffffff;
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px rgba(27, 51, 95, 0.15);
    }

    .form-control::placeholder {
      color: #94a3b8;
      font-size: 0.88rem;
    }

    /* Error highlight on form controls: Red border with Deep Navy focus & red badge */
    .form-control.has-error {
      border-color: var(--color-error) !important;
      background-color: var(--color-error-bg) !important;
      box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.15) !important;
    }

    .field-error-message {
      display: flex;
      align-items: center;
      gap: 6px;
      color: var(--color-error);
      font-size: 0.8rem;
      font-weight: 700;
      margin-top: 6px;
    }

    .field-hint {
      font-size: 0.78rem;
      color: var(--color-text-muted);
      margin-top: 5px;
    }

    /* Custom Radio Cards (Property Type) */
    .radio-card-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }

    .radio-card-label {
      cursor: pointer;
      position: relative;
    }

    .radio-card-label input[type="radio"] {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    .radio-card-content {
      border: 2px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: 16px;
      text-align: center;
      background: #faf9f7;
      transition: var(--transition);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
    }

    .radio-card-label:hover .radio-card-content {
      border-color: var(--color-primary-light);
      background: #ffffff;
    }

    .radio-card-label input[type="radio"]:checked + .radio-card-content {
      border-color: var(--color-primary);
      background: rgba(27, 51, 95, 0.04);
      box-shadow: 0 0 0 2px var(--color-primary);
    }

    .radio-card-icon {
      font-size: 1.5rem;
      color: var(--color-primary);
    }

    .radio-card-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--color-primary);
    }

    .radio-card-desc {
      font-size: 0.75rem;
      color: var(--color-text-muted);
    }

    /* Payment Methods Selection */
    .payment-options-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .payment-option-item {
      display: flex;
      align-items: center;
      border: 1.5px solid var(--color-border);
      border-radius: var(--radius-sm);
      padding: 12px 16px;
      background: #faf9f7;
      cursor: pointer;
      transition: var(--transition);
    }

    .payment-option-item:hover {
      border-color: var(--color-primary);
      background: #ffffff;
    }

    .payment-option-item input[type="radio"] {
      margin-right: 12px;
      accent-color: var(--color-primary);
      width: 18px;
      height: 18px;
    }

    .payment-option-item.selected {
      border-color: var(--color-primary);
      background: rgba(27, 51, 95, 0.03);
      font-weight: 700;
    }

    .payment-option-title {
      font-size: 0.9rem;
      color: var(--color-text-main);
    }

    /* Product Selection Item Cards */
    .products-checklist {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .product-select-card {
      border: 2px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: 16px;
      background: #ffffff;
      transition: var(--transition);
      position: relative;
    }

    .product-select-card:hover {
      border-color: var(--color-primary-light);
      box-shadow: var(--shadow-sm);
    }

    .product-select-card.is-selected {
      border-color: var(--color-primary);
      background: #fbfbf9;
      box-shadow: 0 0 0 1px var(--color-primary);
    }

    .product-card-top {
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }

    .product-checkbox-wrapper {
      padding-top: 4px;
    }

    .product-checkbox {
      width: 20px;
      height: 20px;
      accent-color: var(--color-primary);
      cursor: pointer;
    }

    .product-thumbnail {
      width: 60px;
      height: 60px;
      object-fit: contain;
      background: #f1f0ec;
      border-radius: var(--radius-sm);
      padding: 4px;
      border: 1px solid #e2dfd9;
      flex-shrink: 0;
    }

    .product-info {
      flex: 1;
    }

    .product-title-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 8px;
      margin-bottom: 4px;
    }

    .product-name-label {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--color-primary);
      cursor: pointer;
    }

    .product-unit-price {
      font-size: 1rem;
      font-weight: 800;
      color: var(--color-primary);
      white-space: nowrap;
    }

    .product-badge-tag {
      display: inline-block;
      font-size: 0.68rem;
      font-weight: 700;
      background: var(--color-accent);
      color: var(--color-primary);
      padding: 2px 7px;
      border-radius: 999px;
      margin-left: 6px;
      text-transform: uppercase;
    }

    .product-desc-text {
      font-size: 0.78rem;
      color: var(--color-text-muted);
      line-height: 1.4;
      margin-bottom: 10px;
    }

    .product-qty-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-top: 10px;
      border-top: 1px dashed var(--color-border);
    }

    .qty-label {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--color-primary);
    }

    .qty-input-group {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .qty-number-input {
      width: 64px;
      padding: 6px 8px;
      text-align: center;
      font-family: var(--font-family);
      font-weight: 700;
      font-size: 0.9rem;
      color: var(--color-primary);
      border: 1.5px solid var(--color-border);
      border-radius: var(--radius-sm);
      background: #faf9f7;
    }

    .qty-number-input:focus {
      outline: none;
      border-color: var(--color-primary);
      background: #ffffff;
    }

    /* Price Calculation Summary Box */
    .order-summary-box {
      background: #faf9f7;
      border-radius: var(--radius-md);
      padding: 20px;
      border: 1px solid var(--color-border);
      margin-top: 20px;
    }

    .summary-heading {
      font-size: 0.95rem;
      font-weight: 800;
      color: var(--color-primary);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 14px;
      padding-bottom: 8px;
      border-bottom: 2px solid var(--color-accent);
    }

    .summary-line {
      display: flex;
      justify-content: space-between;
      font-size: 0.88rem;
      color: var(--color-text-body);
      margin-bottom: 10px;
    }

    .summary-line.total-line {
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--color-primary);
      margin-top: 14px;
      padding-top: 14px;
      border-top: 2px dashed var(--color-border);
    }

    .summary-line.total-line .grand-value {
      color: var(--color-primary);
      font-size: 1.35rem;
    }

    .tax-badge-note {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 0.72rem;
      color: var(--color-text-muted);
      margin-top: -6px;
      margin-bottom: 10px;
    }

    /* Submit CTA Buttons */
    .btn-submit-order {
      width: 100%;
      background: var(--color-accent);
      color: var(--color-primary);
      font-family: var(--font-family);
      font-size: 1.05rem;
      font-weight: 800;
      letter-spacing: 0.5px;
      padding: 16px 24px;
      border: 2px solid #e0c600;
      border-radius: var(--radius-md);
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(254, 224, 0, 0.4);
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 20px;
      text-transform: uppercase;
    }

    .btn-submit-order:hover {
      background: var(--color-accent-hover);
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(27, 51, 95, 0.25);
    }

    .btn-submit-order:active {
      transform: translateY(0);
    }

    .btn-submit-order:disabled,
    .btn-submit-order.disabled {
      background: #dfdeda !important;
      color: #7b7871 !important;
      border-color: #cac7c0 !important;
      cursor: not-allowed !important;
      box-shadow: none !important;
      transform: none !important;
      opacity: 0.7;
    }

    .empty-selection-alert {
      display: flex;
      align-items: center;
      gap: 10px;
      background: var(--color-error-bg);
      color: var(--color-error);
      border: 1px solid var(--color-error-border);
      border-radius: var(--radius-sm);
      padding: 10px 14px;
      font-size: 0.82rem;
      font-weight: 700;
      margin-top: 14px;
      margin-bottom: 6px;
    }

    .security-guarantee-note {
      text-align: center;
      font-size: 0.75rem;
      color: var(--color-text-muted);
      margin-top: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    /* ==========================================================================
       CONFIRMATION SCREEN STYLES (POST VALIDATION PASSES)
       ========================================================================== */
    .confirmation-container {
      max-width: 860px;
      margin: 0 auto;
    }

    .conf-hero-banner {
      background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
      color: #ffffff;
      border-radius: var(--radius-lg);
      padding: 36px 28px;
      text-align: center;
      box-shadow: var(--shadow-lg);
      margin-bottom: 28px;
      position: relative;
      overflow: hidden;
    }

    .conf-hero-banner::after {
      content: '';
      position: absolute;
      top: -50%;
      right: -20%;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(254, 224, 0, 0.18) 0%, transparent 70%);
      border-radius: 50%;
      pointer-events: none;
    }

    .conf-success-icon {
      width: 72px;
      height: 72px;
      background: var(--color-accent);
      color: var(--color-primary);
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 16px;
      box-shadow: 0 4px 16px rgba(254, 224, 0, 0.4);
    }

    .conf-hero-title {
      font-size: 2rem;
      font-weight: 800;
      letter-spacing: -0.5px;
      margin-bottom: 8px;
    }

    .conf-hero-subtitle {
      font-size: 0.95rem;
      color: rgba(255, 255, 255, 0.9);
      max-width: 580px;
      margin: 0 auto 20px;
    }

    .conf-ref-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, 0.12);
      border: 1px dashed var(--color-accent);
      padding: 8px 18px;
      border-radius: 999px;
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--color-accent);
    }

    .conf-content-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      margin-bottom: 28px;
    }

    @media (max-width: 768px) {
      .conf-content-grid {
        grid-template-columns: 1fr;
      }
    }

    .conf-details-list {
      list-style: none;
    }

    .conf-details-item {
      display: flex;
      flex-direction: column;
      padding: 12px 0;
      border-bottom: 1px solid #edf2f7;
    }

    .conf-details-item:last-child {
      border-bottom: none;
    }

    .conf-details-label {
      font-size: 0.76rem;
      font-weight: 700;
      color: var(--color-text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 2px;
    }

    .conf-details-value {
      font-size: 0.94rem;
      font-weight: 700;
      color: var(--color-primary);
    }

    /* Itemized Table */
    .receipt-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
    }

    .receipt-table th {
      background: #faf9f7;
      color: var(--color-primary);
      text-align: left;
      font-size: 0.78rem;
      font-weight: 800;
      text-transform: uppercase;
      padding: 10px 12px;
      border-bottom: 2px solid var(--color-border);
    }

    .receipt-table td {
      padding: 12px;
      font-size: 0.88rem;
      border-bottom: 1px solid #edf2f7;
      color: var(--color-text-body);
    }

    .receipt-table td.qty-col,
    .receipt-table th.qty-col {
      text-align: center;
      width: 60px;
    }

    .receipt-table td.num-col,
    .receipt-table th.num-col {
      text-align: right;
      white-space: nowrap;
    }

    .receipt-item-name {
      font-weight: 700;
      color: var(--color-primary);
    }

    .receipt-summary-totals {
      margin-top: 16px;
      padding-top: 12px;
      border-top: 2px dashed var(--color-border);
    }

    /* Actions Bar */
    .conf-actions {
      display: flex;
      gap: 16px;
      justify-content: center;
      flex-wrap: wrap;
      margin-top: 32px;
    }

    .btn-action-primary {
      background: var(--color-primary);
      color: #ffffff;
      padding: 14px 28px;
      border-radius: var(--radius-md);
      font-weight: 700;
      font-size: 0.92rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition);
      border: 2px solid var(--color-primary);
    }

    .btn-action-primary:hover {
      background: var(--color-primary-dark);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-action-accent {
      background: var(--color-accent);
      color: var(--color-primary);
      padding: 14px 28px;
      border-radius: var(--radius-md);
      font-weight: 800;
      font-size: 0.92rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition);
      border: 2px solid #e0c600;
      cursor: pointer;
    }

    .btn-action-accent:hover {
      background: var(--color-accent-hover);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-action-outline {
      background: #ffffff;
      color: var(--color-primary);
      padding: 14px 28px;
      border-radius: var(--radius-md);
      font-weight: 700;
      font-size: 0.92rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition);
      border: 2px solid var(--color-primary);
    }

    .btn-action-outline:hover {
      background: rgba(27, 51, 95, 0.05);
      transform: translateY(-2px);
    }

    /* Print Specific Styles */
    @media print {
      body {
        background: #ffffff !important;
        color: #000000 !important;
      }
      .brand-header, .conf-actions, .security-guarantee-note, .header-security-badge {
        display: none !important;
      }
      .card-panel {
        box-shadow: none !important;
        border: 1px solid #ccc !important;
      }
      .conf-hero-banner {
        background: #1b335f !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
    }

    /* Empty Cart State */
    .empty-cart-container {
      max-width: 780px;
      margin: 0 auto;
    }

    .empty-cart-card {
      background: var(--color-card-bg);
      border-radius: var(--radius-lg);
      border: 1px solid rgba(27, 51, 95, 0.12);
      box-shadow: var(--shadow-lg);
      padding: 48px 36px;
      text-align: center;
      margin-top: 20px;
    }

    .empty-cart-icon-wrapper {
      width: 88px;
      height: 88px;
      margin: 0 auto 20px;
      background: rgba(254, 224, 0, 0.2);
      color: var(--color-primary);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 16px rgba(254, 224, 0, 0.35);
      border: 2px solid var(--color-accent);
    }

    .empty-cart-title {
      font-size: 1.85rem;
      font-weight: 800;
      color: var(--color-primary);
      letter-spacing: -0.5px;
      margin: 14px 0 10px;
    }

    .empty-cart-desc {
      font-size: 0.95rem;
      color: var(--color-text-muted);
      max-width: 540px;
      margin: 0 auto 28px;
      line-height: 1.65;
    }

    .empty-cart-steps {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 14px;
      background: var(--color-bg-soft);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: 18px 24px;
      margin: 0 auto 32px;
      max-width: 620px;
      flex-wrap: wrap;
    }

    .empty-step-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.84rem;
      font-weight: 700;
      color: var(--color-primary);
    }

    .empty-step-num {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: var(--color-primary);
      color: var(--color-accent);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.76rem;
      font-weight: 800;
    }

    .empty-step-arrow {
      color: var(--color-accent);
      font-weight: 900;
      font-size: 1.1rem;
    }

    .empty-cart-actions {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 16px;
      flex-wrap: wrap;
    }

    /* Footer */
    .brand-footer {
      background: var(--color-primary);
      color: rgba(255, 255, 255, 0.8);
      font-size: 0.8rem;
      text-align: center;
      padding: 20px;
      margin-top: auto;
      border-top: 3px solid var(--color-accent);
    }

    /* Interactive Notification Toast */
    .checkout-toast {
      position: fixed;
      top: 28px;
      left: 50%;
      transform: translateX(-50%) translateY(-120px);
      background: #b91c1c;
      color: #ffffff;
      padding: 14px 24px;
      border-radius: 50px;
      font-size: 0.92rem;
      font-weight: 700;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
      border: 2px solid #fca5a5;
      z-index: 9999;
      opacity: 0;
      visibility: hidden;
      transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      display: flex;
      align-items: center;
      gap: 12px;
      text-align: center;
      max-width: 90vw;
    }

    .checkout-toast.show {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
      visibility: visible;
    }
  </style>
</head>
<body>

  <!-- Brand Navigation Header -->
  <header class="brand-header">
    <div class="brand-nav-container">
      <a href="index.php" class="brand-logo-link" title="Return to Apex Diurnal Homepage">
        <img src="assets/images/logo.png" alt="Apex Diurnal Solar Logo" class="brand-logo-img">
        <div class="brand-name">
          <span class="brand-name-title">APEX</span>
          <span class="brand-name-sub">DIURNAL</span>
        </div>
      </a>

      <div class="brand-header-actions">
        <a href="index.php" class="header-back-btn" title="Back to Apex Diurnal Homepage">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          <span>Back to Home</span>
        </a>

        <div class="header-security-badge">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <span>256-Bit SSL Encrypted Checkout</span>
        </div>
      </div>
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
          <div class="conf-success-icon">
            <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
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
              <h2 class="card-header-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Customer Details
              </h2>
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
                  <span class="conf-details-label">Site / Installation Address</span>
                  <span class="conf-details-value"><?php echo nl2br(safe($address)); ?></span>
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
              <h2 class="card-header-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                  <line x1="3" y1="6" x2="21" y2="6"></line>
                  <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                Itemized Summary
              </h2>
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
                          $<?php echo number_format($u_price, 2); ?> each
                        </div>
                      </td>
                      <td class="qty-col"><?php echo (int)$p_qty; ?></td>
                      <td class="num-col font-weight-bold">
                        <strong>$<?php echo number_format($line_sum, 2); ?></strong>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <div class="receipt-summary-totals">
                <div class="summary-line">
                  <span>Subtotal</span>
                  <strong>$<?php echo number_format($subtotal, 2); ?></strong>
                </div>
                <div class="summary-line">
                  <span>State & Clean Energy Tax (8%)</span>
                  <strong>$<?php echo number_format($tax_amount, 2); ?></strong>
                </div>
                <div class="summary-line total-line">
                  <span>Total Amount</span>
                  <span class="grand-value">$<?php echo number_format($grand_total, 2); ?></span>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Confirmation Actions -->
        <div class="conf-actions">
          <button type="button" onclick="window.print();" class="btn-action-accent">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 6 2 18 2 18 9"></polyline>
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
              <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print Receipt
          </button>
          
          <a href="index.php#products" class="btn-action-outline">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 5v14M5 12h14"></path>
            </svg>
            Order More Products
          </a>

          <a href="index.php" class="btn-action-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
              <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
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
          <a href="index.php" class="back-pill-link" title="Return to Apex Diurnal Homepage">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>&larr; Back to Homepage</span>
          </a>
        </div>

        <div class="empty-cart-card">
          <div class="empty-cart-icon-wrapper">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="9" cy="21" r="1"></circle>
              <circle cx="20" cy="21" r="1"></circle>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
          </div>

          <span class="checkout-badge">No Order Found</span>
          <div class="empty-selection-alert" style="display:inline-flex; align-items:center; gap:10px; margin: 12px auto 18px; font-size:0.92rem; padding:12px 20px; background:#fef2f2; color:#b91c1c; border:1.5px solid #f87171; border-radius:8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
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
            <a href="index.php#products" class="btn-action-accent">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
              Browse Solar Products
            </a>
            <a href="index.php" class="btn-action-outline">
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
        <a href="index.php" class="back-pill-link" title="Return to Apex Diurnal Homepage">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          <span>&larr; Back to Homepage</span>
        </a>
      </div>

      <div class="checkout-intro">
        <span class="checkout-badge">Apex Diurnal Checkout</span>
        <h1 class="checkout-title">Complete Your Solar Order</h1>
        <p class="checkout-subtitle">Secure configuration, certified solar hardware, and professional booking in one step.</p>
      </div>

      <?php if (!empty($errors)): ?>
        <!-- Error summary banner highlighting validation failures -->
        <div class="error-summary-banner" role="alert" aria-live="assertive">
          <div class="error-summary-header">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span>Please correct the <?php echo count($errors); ?> highlighted issue(s) below:</span>
          </div>
          <ul class="error-summary-list">
            <?php foreach ($errors as $field_err): ?>
              <li><?php echo safe($field_err); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form action="checkout.php" method="POST" id="apex-checkout-form" novalidate>
        
        <div class="checkout-grid">

          <!-- Left Column: Customer Details, Address, Property, Payment -->
          <div class="form-column">

            <!-- Customer Contact Information Card -->
            <div class="card-panel">
              <div class="card-header">
                <h2 class="card-header-title">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  1. Customer Details
                </h2>
                <span class="card-step-badge">Required</span>
              </div>
              <div class="card-body">
                
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
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                      </svg>
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
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="10"></circle>
                          <line x1="12" y1="8" x2="12" y2="12"></line>
                          <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
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
                      placeholder="e.g. +1 (555) 342-8901" 
                      value="<?php echo safe($phone); ?>"
                      required
                      autocomplete="tel"
                    >
                    <?php if (isset($errors['phone'])): ?>
                      <div class="field-error-message">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="10"></circle>
                          <line x1="12" y1="8" x2="12" y2="12"></line>
                          <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <?php echo safe($errors['phone']); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Street Address -->
                <div class="form-group">
                  <label for="address" class="form-label">
                    Installation / Billing Address <span class="required-mark">*</span>
                  </label>
                  <textarea 
                    id="address" 
                    name="address" 
                    rows="2" 
                    class="form-control <?php echo isset($errors['address']) ? 'has-error' : ''; ?>" 
                    placeholder="Enter street number, city, state, zip code..."
                    required
                    autocomplete="street-address"
                  ><?php echo safe($address); ?></textarea>
                  <?php if (isset($errors['address'])): ?>
                    <div class="field-error-message">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                      </svg>
                      <?php echo safe($errors['address']); ?>
                    </div>
                  <?php endif; ?>
                </div>

              </div>
            </div>

            <!-- Property Type Card (Residential vs Commercial) -->
            <div class="card-panel">
              <div class="card-header">
                <h2 class="card-header-title">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                  </svg>
                  2. Property Type
                </h2>
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
                        <div class="radio-card-icon">🏡</div>
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
                        <div class="radio-card-icon">🏢</div>
                        <div class="radio-card-title">Commercial</div>
                        <div class="radio-card-desc">Office building, warehouse, or enterprise solar grid</div>
                      </div>
                    </label>
                  </div>
                  <?php if (isset($errors['property_type'])): ?>
                    <div class="field-error-message">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                      </svg>
                      <?php echo safe($errors['property_type']); ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Payment Method Card -->
            <div class="card-panel">
              <div class="card-header">
                <h2 class="card-header-title">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                  </svg>
                  3. Payment Method
                </h2>
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
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                      </svg>
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
                <h2 class="card-header-title">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                  </svg>
                  Product Selection
                </h2>
                <span class="card-step-badge">Configurator</span>
              </div>
              <div class="card-body">
                
                <?php if (isset($errors['products'])): ?>
                  <div class="field-error-message" style="margin-bottom:14px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="8" x2="12" y2="12"></line>
                      <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
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
                            <span class="product-unit-price">$<?php echo number_format($price_val, 2); ?></span>
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
                    <strong id="display-subtotal">$<?php echo number_format($subtotal, 2); ?></strong>
                  </div>
                  
                  <div class="summary-line">
                    <span>Clean Energy Tax (8%)</span>
                    <strong id="display-tax">$<?php echo number_format($tax_amount, 2); ?></strong>
                  </div>
                  <div class="tax-badge-note">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="16" x2="12" y2="12"></line>
                      <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <span>Applicable diurnal solar equipment rate</span>
                  </div>

                  <div class="summary-line total-line">
                    <span>Total Cost</span>
                    <span class="grand-value" id="display-grandtotal">$<?php echo number_format($grand_total, 2); ?></span>
                  </div>
                </div>

                <!-- Product Selection Warning -->
                <div id="no-products-warning" class="empty-selection-alert" style="<?php echo empty($selected_items) ? 'display:flex;' : 'display:none;'; ?>">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                  </svg>
                  <span>At least one product must be selected to complete checkout.</span>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit-order <?php echo empty($selected_items) ? 'disabled' : ''; ?>" id="btn-submit" aria-disabled="<?php echo empty($selected_items) ? 'true' : 'false'; ?>" title="<?php echo empty($selected_items) ? 'Please select at least one product to checkout' : 'Confirm and place order'; ?>">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                  </svg>
                  Confirm &amp; Place Order
                </button>

                <!-- Back to Homepage Option -->
                <a href="index.php" class="btn-cancel-return" title="Cancel Order">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                  </svg>
                  <span>Cancel Order</span>
                </a>

                <div class="security-guarantee-note">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                  </svg>
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

  <!-- Dynamic Total Recalculation & Micro-interactions Script -->
  <script>
    const TAX_RATE = 0.08;

    function recalculateTotals() {
      const checkboxes = document.querySelectorAll('.product-checkbox');
      let subtotal = 0;
      let checkedCount = 0;

      checkboxes.forEach(function(chk) {
        const cardId = chk.getAttribute('data-card-id');
        const qtyId = chk.getAttribute('data-qty-id');
        const cardElem = document.getElementById(cardId);
        const qtyElem = document.getElementById(qtyId);

        if (chk.checked) {
          checkedCount++;
          if (cardElem) cardElem.classList.add('is-selected');
          const unitPrice = parseFloat(chk.getAttribute('data-price')) || 0;
          const qty = parseInt(qtyElem.value, 10) || 1;
          subtotal += unitPrice * Math.max(1, qty);
        } else {
          if (cardElem) cardElem.classList.remove('is-selected');
        }
      });

      const tax = subtotal * TAX_RATE;
      const grandTotal = subtotal + tax;

      const subtotalElem = document.getElementById('display-subtotal');
      const taxElem = document.getElementById('display-tax');
      const grandElem = document.getElementById('display-grandtotal');

      if (subtotalElem) subtotalElem.textContent = '$' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      if (taxElem) taxElem.textContent = '$' + tax.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      if (grandElem) grandElem.textContent = '$' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      // Dynamically disable or enable the place order button & warning notice
      const submitBtn = document.getElementById('btn-submit');
      const warningElem = document.getElementById('no-products-warning');

      if (submitBtn) {
        submitBtn.removeAttribute('disabled');
        if (checkedCount === 0 || subtotal <= 0) {
          submitBtn.classList.add('disabled');
          submitBtn.setAttribute('aria-disabled', 'true');
          submitBtn.setAttribute('title', 'Please select at least one product to checkout');
          if (warningElem) warningElem.style.display = 'flex';
        } else {
          submitBtn.classList.remove('disabled');
          submitBtn.removeAttribute('aria-disabled');
          submitBtn.removeAttribute('title');
          if (warningElem) warningElem.style.display = 'none';
        }
      }
    }

    function updatePaymentHighlight(radio) {
      document.querySelectorAll('.payment-option-item').forEach(function(item) {
        item.classList.remove('selected');
      });
      if (radio && radio.checked) {
        const parent = radio.closest('.payment-option-item');
        if (parent) parent.classList.add('selected');
      }
    }

    function showCheckoutNotif(msg) {
      const t = document.getElementById('checkout-toast');
      if (!t) return;
      t.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg><span>' + msg + '</span>';
      t.classList.add('show');
      clearTimeout(window.checkoutToastTimer);
      window.checkoutToastTimer = setTimeout(function() {
        t.classList.remove('show');
      }, 3500);
    }

    function blockEmptyCheckout(e) {
      const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
      if (checkedBoxes.length === 0) {
        if (e) {
          e.preventDefault();
          e.stopPropagation();
        }
        const warningElem = document.getElementById('no-products-warning');
        if (warningElem) {
          warningElem.style.display = 'flex';
          warningElem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        showCheckoutNotif('⚠️ You cannot checkout without an order! Please select at least one product first.');
        return false;
      }
      return true;
    }

    // Intercept click on submit button
    const submitBtn = document.getElementById('btn-submit');
    if (submitBtn) {
      submitBtn.addEventListener('click', function(e) {
        if (!blockEmptyCheckout(e)) {
          return false;
        }
      });
    }

    // Guard form submission against zero products
    const checkoutForm = document.getElementById('apex-checkout-form');
    if (checkoutForm) {
      checkoutForm.addEventListener('submit', function(e) {
        if (!blockEmptyCheckout(e)) {
          return false;
        }
      });
    }

    // Initialize totals on page load
    document.addEventListener('DOMContentLoaded', function() {
      recalculateTotals();

      <?php if (!$is_post && empty($selected_items)): ?>
      // Alert user on empty cart page
      showCheckoutNotif('Notice: You must order first before proceeding to checkout.');
      <?php endif; ?>
    });
  </script>
</body>
</html>
