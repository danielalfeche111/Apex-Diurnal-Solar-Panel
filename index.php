<?php
// --- Page Configuration ---
$page_title = "Apex Diurnal | Clean Energy, Intelligent Future";
$page_description = "Apex Diurnal - Top-tier solar technology, custom system design, "
  . "professional installation, and long-term solar maintenance for a sustainable future.";
$current_year = date('Y');

// --- Navigation Links ---
$nav_links = [
  ['label' => 'Home', 'href' => '#home', 'active' => true],
  ['label' => 'Products', 'href' => '#products', 'active' => false],
  ['label' => 'Services', 'href' => '#services', 'active' => false],
  ['label' => 'About Us', 'href' => '#about', 'active' => false],
  ['label' => 'Contact', 'href' => '#contact', 'active' => false],
];

// --- Load product cart, session helpers, and authentication ---
require_once __DIR__ . '/product_data.php';
require_once __DIR__ . '/cart/cart_functions.php';
require_once __DIR__ . '/auth.php';

// Cart hydration & notification gathering for active session
$cart_notices = [];
if (!empty($_SESSION['cart_notifications']) && is_array($_SESSION['cart_notifications'])) {
    $cart_notices = array_merge($cart_notices, $_SESSION['cart_notifications']);
    unset($_SESSION['cart_notifications']);
}
if (isLoggedIn()) {
    $cartService = get_cart_service();
    $userId = getCurrentUserId();
    if ($cartService && $userId) {
        try {
            $hydration = $cartService->validateAndHydrate($userId);
            if (!empty($hydration['notices'])) {
                $cart_notices = array_merge($cart_notices, $hydration['notices']);
            }
        } catch (Exception $e) {
            error_log('[index] Cart hydration error: ' . $e->getMessage());
        }
    }
}

// --- Service / Feature Cards ---
$features = [
  [
    'title' => 'CUSTOM SYSTEM DESIGN',
    'image' => 'assets/images/custom design.png',
    'icon' => 'assets/images/custom.png',
    'alt_img' => 'Custom System Design',
    'alt_icon' => 'Custom System Design Icon',
    'text' => 'End to End Service. Custom solutions for homes and businesses. '
      . 'Complete Site Analysis and 3D Modeling.',
  ],
  [
    'title' => 'PROFESSIONAL INSTALLATION',
    'image' => 'assets/images/professional installation.png',
    'icon' => 'assets/images/PROF INSTALLATION.png',
    'alt_img' => 'Professional Installation',
    'alt_icon' => 'Professional Installation Icon',
    'text' => 'Seamless Execution. Flawless installation of unique system designs. '
      . 'Maximizing Solar Capture.',
  ],
  [
    'title' => 'LONG-TERM MAINTENANCE',
    'image' => 'assets/images/maintenance.png',
    'icon' => 'assets/images/longterm maintenance.png',
    'alt_img' => 'Long-Term Maintenance',
    'alt_icon' => 'Long-Term Maintenance Icon',
    'text' => 'Ensuring Performance. System monitoring, cleaning, and preventative care. '
      . 'Prolonging Life and Rewarding Investment.',
  ],
];

// --- Footer Columns ---
$footer_menu = [
  ['label' => 'Home', 'href' => '#home'],
  ['label' => 'Products', 'href' => '#products'],
  ['label' => 'Services', 'href' => '#services'],
  ['label' => 'About us', 'href' => '#about'],
  ['label' => 'Contact us', 'href' => '#contact'],
];
$footer_legalities = [
  'Copyright Notice',
  'Privacy Policy',
  'Terms of Service / Conditions',
  'Disclaimers',
  'Accessibility Statement',
];

// --- Consultation Address Dropdown Data - Province -> Cities & Municipalities (full Philippines PSGC) ---
require_once __DIR__ . '/philippine_locations.php';

function void_link(string $inner, string $class = '', string $extra_attr = ''): string
{
  $cls = $class ? ' class="' . $class . '"' : '';
  return '<a href="javascript:void(0)" onclick="return false;"' . $cls . $extra_attr . '>' . $inner . '</a>';
}

function nav_link(string $label, string $href, string $class = '', string $extra_attr = ''): string
{
  $cls = $class ? ' class="' . $class . '"' : '';
  return '<a href="' . htmlspecialchars($href) . '"' . $cls . $extra_attr . '>' . htmlspecialchars($label) . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
  <title><?php echo htmlspecialchars($page_title); ?></title>

  <!-- Font Preconnect & Main CSS -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=<?php echo filemtime(__DIR__ . '/styles.css'); ?>">
  <link rel="stylesheet" href="cart/cart.css?v=<?php echo filemtime(__DIR__ . '/cart/cart.css'); ?>">
  <link rel="stylesheet" href="services/services.css?v=<?php echo filemtime(__DIR__ . '/services/services.css'); ?>">
  <link rel="stylesheet" href="products/product-modal.css?v=<?php echo filemtime(__DIR__ . '/products/product-modal.css'); ?>">
  <link rel="stylesheet" href="css/auth-modal.css?v=<?php echo filemtime(__DIR__ . '/css/auth-modal.css'); ?>">
</head>

<body>

  <!-- NAVIGATION HEADER -->
  <header class="site-header" id="site-header">
    <div class="header-container">

      <a href="#home" class="brand-logo" aria-label="Apex Diurnal Home">
        <img src="assets/images/logo-clean.png?v=<?php echo filemtime(__DIR__ . '/assets/images/logo-clean.png'); ?>" alt="Apex Diurnal Logo" class="logo-mark">
        <div class="brand-text">
          <span class="brand-title">APEX</span>
          <span class="brand-subtitle">DIURNAL</span>
        </div>
      </a>

      <nav class="main-nav" aria-label="Main Navigation">
        <ul class="nav-list">
          <?php foreach ($nav_links as $link): ?>
            <li>
              <?php
              $cls = $link['active'] ? 'nav-link active' : 'nav-link';
              echo nav_link($link['label'], $link['href'], $cls);
              ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>

      <div class="header-actions">
        <div class="search-container" id="search-container">
          <button type="button" class="icon-btn" aria-label="Search" id="search-toggle" aria-expanded="false"
            aria-controls="search-bar">
            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </button>
          <div class="search-bar" id="search-bar" role="search">
            <input type="search" class="search-input" id="search-input" placeholder="Search"
              aria-label="Search products and navigation" autocomplete="off">
            <button type="button" class="search-close" id="search-close" aria-label="Close search">×</button>
          </div>
          <div class="search-results" id="search-results" aria-live="polite"></div>
        </div>
        <button type="button" class="icon-btn cart-btn" id="cart-trigger" aria-label="Shopping Cart">
          <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
          <span class="cart-badge" id="cart-count" data-count="<?php echo cart_item_count(); ?>" <?php if (cart_item_count() === 0)
               echo 'style="display:none"'; ?>><?php echo cart_item_count(); ?></span>
        </button>
        <?php if (isLoggedIn()): ?>
          <div class="user-dropdown" id="user-dropdown">
            <button type="button" class="user-avatar-btn" aria-label="User Account" id="user-account-btn"
              title="<?php echo htmlspecialchars(getCurrentUserEmail()); ?>">
              <span
                class="user-avatar"><?php echo htmlspecialchars(strtoupper(substr(getCurrentUserEmail() ?? 'U', 0, 1))); ?></span>
            </button>
            <div class="user-dropdown-menu" id="user-dropdown-menu">
              <a href="account/settings.php" class="user-dropdown-item">Settings</a>
              <a href="account/orders/index.php" class="user-dropdown-item">My Orders</a>
              <a href="auth/logout.php" class="user-dropdown-item">Logout</a>
            </div>
          </div>
        <?php else: ?>
          <a href="auth/login.php" class="header-login-btn" aria-label="Log In">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"
              aria-hidden="true">
              <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
              <polyline points="10 17 15 12 10 7"></polyline>
              <line x1="15" y1="12" x2="3" y2="12"></line>
            </svg>
            Log In
          </a>
        <?php endif; ?>
      </div>

    </div>
  </header>

  <main>

    <!-- HERO SECTION -->
    <section class="hero-section" id="home">
      <div class="hero-background">
        <img src="assets/images/homebackground.png" alt="Modern house with solar panel rooftop installation"
          class="hero-img">
        <div class="hero-overlay"></div>
      </div>
      <div class="container hero-container">
        <div class="hero-content">
          <h1 class="hero-title">
            Clean Energy,<br>
            <span class="text-yellow">Intelligent Future.</span>
          </h1>
          <p class="hero-description">
            Equip your property with top-tier solar technology for a sustainable future.
          </p>
          <button type="button" id="consultation-trigger" class="btn btn-yellow hero-btn consultation-trigger-btn"
            onclick="openConsultationModal()">BOOK A CONSULTATION</button>
        </div>
      </div>
    </section>

    <!-- PRODUCTS GRID SECTION -->
    <section class="products-section" id="products">
      <div class="container">
        <div class="products-grid">

          <?php foreach ($products as $idx => $product): ?>
            <article class="product-card" id="product-<?php echo $idx; ?>">
              <div class="card-badge">
                <img src="assets/images/logo-clean.png?v=<?php echo filemtime(__DIR__ . '/assets/images/logo-clean.png'); ?>" alt="Apex Diurnal Logo" class="badge-logo">
              </div>
              <div class="card-image-wrap">
                <img src="<?php echo htmlspecialchars($product['image']); ?>"
                  alt="<?php echo htmlspecialchars($product['alt']); ?>" class="card-img">
              </div>
              <div class="card-body">
                <h3 class="product-title"><?php echo htmlspecialchars($product['title']); ?></h3>
                <div class="product-price"><?php echo htmlspecialchars($product['price']); ?></div>
                <div class="card-actions">
                  <?php foreach ($product['actions'] as $action):
                    $label = $action['label'];
                    $upper = strtoupper(trim($label));
                    $onclick = '';
                    if ($upper === 'BUY NOW' || $upper === 'BOOK NOW') {
                      $onclick = "if (typeof isUserAuthenticated === 'function' && isUserAuthenticated()) { addToCart('" . htmlspecialchars($product['id'], ENT_QUOTES) . "'); openCart(); } else { showAuthRequiredModal('buy'); }";
                    } elseif ($upper === 'LEARN MORE') {
                      $onclick = "openProductModal('" . htmlspecialchars($product['id'], ENT_QUOTES) . "');";
                    } elseif ($upper === 'CONTACT SALES' || $upper === 'REQUEST QUOTE') {
                      $onclick = "openConsultationModal('rfq');";
                    } else {
                      $onclick = "addToCart('" . htmlspecialchars($product['id'], ENT_QUOTES) . "')";
                    }
                    ?>
                    <button type="button" data-id="<?php echo htmlspecialchars($product['id']); ?>"
                      data-product-id="<?php echo htmlspecialchars($product['id']); ?>"
                      <?php if ($upper === 'LEARN MORE'): ?>data-action="learn-more"<?php endif; ?>
                      onclick="<?php echo $onclick; ?>" class="<?php echo htmlspecialchars($action['class']); ?>"
                      style="cursor: pointer;">
                      <?php echo htmlspecialchars($label); ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>

        </div>
      </div>
    </section>

    <!-- SEAMLESS TRANSITION SECTION -->
    <section class="seamless-section" id="services">
      <div class="container">
        <h2 class="seamless-title">A SEAMLESS TRANSITION TO RENEWABLE ENERGY</h2>

        <div class="features-grid">
          <?php foreach ($features as $feature): ?>
            <div class="feature-card">
              <div class="feature-img-wrap">
                <img src="<?php echo htmlspecialchars($feature['image']); ?>"
                  alt="<?php echo htmlspecialchars($feature['alt_img']); ?>" class="feature-img">
              </div>
              <div class="feature-header">
                <div class="feature-icon">
                  <img src="<?php echo htmlspecialchars($feature['icon']); ?>"
                    alt="<?php echo htmlspecialchars($feature['alt_icon']); ?>" style="width:28px;height:28px;object-fit:contain;
                              filter:invert(82%) sepia(87%) saturate(1915%)
                                     hue-rotate(345deg) brightness(103%) contrast(105%);">
                </div>
                <h3 class="feature-name"><?php echo htmlspecialchars($feature['title']); ?></h3>
              </div>
              <p class="feature-text"><?php echo htmlspecialchars($feature['text']); ?></p>
            </div>
          <?php endforeach; ?>
        </div>


      </div>
    </section>

    <!-- RARE TECHNOLOGY SECTION -->
    <section class="tech-section" id="about">
      <div class="container">
        <div class="tech-grid">
          <div class="tech-content">
            <h2 class="tech-title">RARE TECHNOLOGY<br>
              <span class="tech-subtitle">Advancement Components</span>
            </h2>
            <p class="tech-desc">
              We equip homes and businesses with top-tier technology. By optimizing the thermodynamics of energy
              conversion, our rare components maximize sunlight absorption and minimize thermal loss, ensuring reliable
              power and promoting a sustainable future.
            </p>
          </div>
          <div class="tech-image-wrap">
            <img src="assets/images/rare advancement technology component.png"
              alt="Solar panel thermodynamic cell detail" class="tech-img">
          </div>
        </div>
      </div>
    </section>

    <!-- MASTERFUL CRAFTSMANSHIP & EXPERT INSTALLATION SECTION -->
    <section class="craftsmanship-section">
      <div class="container">
        <div class="craftsmanship-grid">
          <div class="craftsmanship-image-wrap">
            <div class="image-frame-yellow">
              <img src="assets/images/craftsmanship.png" alt="Solar installer mounting panel precision"
                class="craftsmanship-img">
            </div>
          </div>
          <div class="craftsmanship-content">
            <div class="section-tag-row">
              <span class="section-tag">MASTERFUL CRAFTSMANSHIP</span>
              <div class="tag-line"></div>
            </div>
            <h2 class="craftsmanship-title">EXPERT<br>INSTALLATION</h2>
            <div class="trust-badge-row">
              <div class="trust-icon-box">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="var(--color-navy)" stroke-width="2">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="8.5" cy="7" r="4"></circle>
                  <polyline points="17 11 19 13 23 9"></polyline>
                </svg>
              </div>
              <div class="trust-text-box">
                <h4 class="trust-heading">EXPERTISE YOU CAN TRUST</h4>
                <p class="trust-sub">Certified Technicians, Seamless Execution.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- FOOTER SECTION -->
  <footer class="site-footer" id="contact">
    <div class="container">
      <div class="footer-grid">

        <!-- Brand Column -->
        <div class="footer-brand-col">
          <div class="footer-logo">
            <img src="assets/images/FOOTER.jpg" alt="Apex Diurnal Logo" class="footer-logo-mark">
          </div>
          <div class="social-links">
            <a href="javascript:void(0)" onclick="return false;" class="social-btn" aria-label="Facebook">
              <svg viewBox="0 0 24 24" width="22" height="22" fill="var(--color-yellow)">
                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
              </svg>
            </a>
            <a href="javascript:void(0)" onclick="return false;" class="social-btn" aria-label="Instagram">
              <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="var(--color-yellow)" stroke-width="2">
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
              </svg>
            </a>
            <a href="javascript:void(0)" onclick="return false;" class="social-btn" aria-label="X (Twitter)">
              <svg viewBox="0 0 24 24" width="22" height="22" fill="var(--color-yellow)">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99
                         21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161
                         17.52h1.833L7.084 4.126H5.117z" />
              </svg>
            </a>
          </div>
        </div>

        <!-- Menu Links Column -->
        <div class="footer-col">
          <h4 class="footer-heading">MENU</h4>
          <ul class="footer-links">
            <?php foreach ($footer_menu as $item): ?>
              <li><?php echo nav_link($item['label'], $item['href']); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <!-- Legalities Column -->
        <div class="footer-col">
          <h4 class="footer-heading">LEGALITIES</h4>
          <ul class="footer-links">
            <?php foreach ($footer_legalities as $item): ?>
              <li><?php echo void_link(htmlspecialchars($item)); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <!-- Contact Column -->
        <div class="footer-col">
          <h4 class="footer-heading">CONTACT</h4>
          <div class="contact-info">
            <p><strong>Phone:</strong> 09561973910</p>
            <p><strong>Email:</strong> danielalfeche2006@gmail.com</p>
          </div>
        </div>

      </div>

      <div class="footer-bottom">
        <p>&copy;<?php echo $current_year; ?> Apex Diurnal. All rights reserved.</p>
      </div>
    </div>
  </footer>

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
                placeholder="e.g. 09171234567" maxlength="11" inputmode="numeric" pattern="[0-9]{11}"
                oninput="this.value=this.value.replace(/\D/g,'').slice(0,11);" required>
              <div class="consultation-field-error" id="err-phone_number"></div>
            </div>
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
              <label for="preferred_time_slot" class="consultation-label">Inspection Time <span
                  class="text-danger">*</span></label>
              <select id="preferred_time_slot" name="preferred_time_slot" class="consultation-input consultation-select"
                required>
                <option value="">Select Inspection Time</option>
                <option value="8:00 AM - 10:00 AM">8:00 AM - 10:00 AM (Morning)</option>
                <option value="10:00 AM - 12:00 PM">10:00 AM - 12:00 PM (Morning)</option>
                <option value="1:00 PM - 3:00 PM">1:00 PM - 3:00 PM (Afternoon)</option>
                <option value="3:00 PM - 5:00 PM">3:00 PM - 5:00 PM (Afternoon)</option>
              </select>
              <div class="consultation-field-error" id="err-preferred_time_slot"></div>
              <small id="slot-availability-hint" style="display:block; margin-top:4px; font-size:0.75rem; color:#64748b;">
                Select a date above to check available inspection slots.
              </small>
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

          <div class="form-row-2">
            <div class="form-group">
              <label for="rfq_facility_size" class="consultation-label">Facility Rooftop / Land Area (sqm) <span
                  class="text-danger">*</span></label>
              <input type="number" id="rfq_facility_size" name="facility_size" class="consultation-input"
                placeholder="e.g. 500" min="1" step="any" required disabled>
              <div class="consultation-field-error" id="err-rfq_facility_size"></div>
            </div>
            <div class="form-group">
              <label for="rfq_current_monthly_bill" class="consultation-label">Current Monthly Electricity Bill (₱) <span
                  class="text-danger">*</span></label>
              <input type="number" id="rfq_current_monthly_bill" name="current_monthly_bill" class="consultation-input"
                placeholder="e.g. 75000" min="1" step="any" required disabled>
              <div class="consultation-field-error" id="err-rfq_current_monthly_bill"></div>
            </div>
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
                placeholder="e.g. 09171234567" maxlength="11" inputmode="numeric" pattern="[0-9]{11}"
                oninput="this.value=this.value.replace(/\D/g,'').slice(0,11);" required disabled>
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

  <!-- CART DRAWER -->
  <div class="cart-overlay" id="cart-overlay"></div>
  <aside class="cart-drawer" id="cart-drawer" aria-label="Shopping Cart" aria-hidden="true">
    <div class="cart-drawer-header">
      <h3>Your Cart <span class="cart-drawer-count" id="cart-drawer-count"><?php echo cart_item_count(); ?>
          item(s)</span></h3>
      <button type="button" class="cart-close" id="cart-close" aria-label="Close cart">&times;</button>
    </div>
    <div class="cart-drawer-body" id="cart-items">
      <!-- JS renders cart items here -->
    </div>
    <div class="cart-drawer-footer">
      <div class="cart-total-row">
        <span>Total</span>
        <strong id="cart-total">&#8369;<?php echo number_format(cart_total($products), 2); ?></strong>
      </div>
      <?php $has_cart_items = (cart_item_count() > 0); ?>
      <div class="cart-footer-actions">
        <button type="button" class="btn btn-outline btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>"
          id="cart-clear" aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>">Clear Cart</button>
        <button type="button" class="btn btn-yellow btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>"
          id="cart-checkout" aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>"
          title="<?php echo !$has_cart_items ? 'Your cart is empty. Please add items before checking out.' : 'Proceed to Checkout'; ?>">Checkout</button>
      </div>
      <p class="cart-empty-hint" id="cart-empty-hint"
        style="display:none; text-align:center; margin-top:12px; font-size:0.85rem; color:var(--color-text-muted);">Your
        cart is empty.</p>
    </div>
  </aside>

  <!-- TOAST -->
  <div id="cart-toast" class="cart-toast" role="status" aria-live="polite"></div>

  <!-- PRODUCT "LEARN MORE" DETAIL MODAL -->
  <div id="product-modal-overlay" class="product-modal-overlay" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="product-modal-card">
      <button type="button" class="product-modal-close" id="product-modal-close" aria-label="Close product details">&times;</button>
      <div class="product-modal-body">
        <!-- Media / Photo Stage -->
        <div class="product-modal-media">
          <span class="product-modal-badge" id="modal-product-badge">Premium Solar</span>
          <div class="product-modal-img-wrap">
            <img src="" alt="" id="modal-product-img" class="product-modal-img">
          </div>
          <div class="product-modal-media-caption">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <span>Apex Diurnal Tier-1 Quality Assured</span>
          </div>
        </div>

        <!-- Information & Specifications -->
        <div class="product-modal-info">
          <div class="product-modal-header">
            <h2 class="product-modal-title" id="modal-product-title"></h2>
            <div class="product-modal-price-wrap">
              <span class="product-modal-price" id="modal-product-price"></span>
              <span class="product-modal-vat" id="modal-product-vat">(VAT Inc.)</span>
            </div>
          </div>

          <div class="product-modal-desc-wrap">
            <div class="product-modal-desc-heading">Overview & Technology</div>
            <p class="product-modal-desc" id="modal-product-desc"></p>
          </div>

          <div class="product-modal-specs" id="modal-product-specs-wrap">
            <div class="product-modal-specs-heading">Key Specifications</div>
            <ul class="product-modal-specs-list" id="modal-product-specs-list"></ul>
          </div>

          <div class="product-modal-actions">
            <button type="button" class="btn-modal-primary" id="modal-primary-action">
              Add to Cart
            </button>
            <button type="button" class="btn-modal-secondary" id="modal-secondary-close">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- AUTHENTICATION REQUIRED ERROR HANDLING MODAL -->
  <div class="auth-modal-overlay" id="auth-modal-overlay" style="display:none;" aria-hidden="true"></div>
  <div class="auth-modal" id="auth-modal" role="dialog" aria-modal="true" aria-labelledby="auth-modal-title" aria-hidden="true" style="display:none;">
    <button type="button" class="auth-modal-close" id="auth-modal-close" aria-label="Close dialog">&times;</button>
    <div class="auth-modal-body">
      <h3 class="auth-modal-title" id="auth-modal-title">Sign In Required</h3>
      <p class="auth-modal-desc" id="auth-modal-desc">
        You need to be signed in to perform this action. Please sign in to your existing account or create a new one to continue.
      </p>
      <div class="auth-modal-actions">
        <a href="auth/login.php" class="auth-btn-primary" id="auth-modal-btn-login">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
            <polyline points="10 17 15 12 10 7"></polyline>
            <line x1="15" y1="12" x2="3" y2="12"></line>
          </svg>
          <span>Sign In to Account</span>
        </a>
        <a href="auth/register.php" class="auth-btn-secondary" id="auth-modal-btn-register">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="8.5" cy="7" r="4"></circle>
            <line x1="20" y1="8" x2="20" y2="14"></line>
            <line x1="23" y1="11" x2="17" y2="11"></line>
          </svg>
          <span>Create an Account</span>
        </a>
        <button type="button" class="auth-btn-dismiss" id="auth-modal-btn-dismiss">
          Continue Browsing
        </button>
      </div>
    </div>
  </div>

  <script>
    // --- Authentication & Session state from PHP ---
    window.USER_LOGGED_IN = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;

    // --- Cart state from PHP ---
    const CART_INITIAL = <?php echo json_encode([
      'itemCount' => cart_item_count(),
      'grandTotal' => number_format(cart_total($products), 2),
      'items' => array_values(array_map(function ($item, $id) {
        return [
          'id' => $id,
          'title' => $item['title'],
          'price' => $item['price'],
          'quantity' => $item['quantity'],
          'line_total' => number_format($item['line_total'], 2),
          'image' => $item['image'],
          'alt' => $item['alt']
        ];
      }, get_cart_items($products), array_keys(get_cart_items($products)))),
      'notices' => $cart_notices
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    // --- Product Catalog Lookup for Learn More Modal ---
    window.PRODUCT_CATALOG = <?php echo json_encode(catalog_lookup($products), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var PRODUCT_CATALOG = window.PRODUCT_CATALOG;
  </script>
  <!-- Province -> City filtering data -->
  <script>
    const consultProvinceCityMap = <?php echo json_encode($consultProvinceCityMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const consultCityProvinceMap = <?php echo json_encode($consultCityProvinceMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const consultAllCities = <?php echo json_encode($consult_cities, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  </script>

  <!-- Feature Client Scripts -->
  <script src="js/auth-modal.js?v=<?php echo filemtime(__DIR__ . '/js/auth-modal.js'); ?>"></script>
  <script src="cart/cart.js?v=<?php echo filemtime(__DIR__ . '/cart/cart.js'); ?>" defer></script>
  <script src="services/services.js?v=<?php echo filemtime(__DIR__ . '/services/services.js'); ?>" defer></script>
  <script src="products/product-modal.js?v=<?php echo filemtime(__DIR__ . '/products/product-modal.js'); ?>" defer></script>
  <script src="js/session-timer.js?v=<?php echo filemtime(__DIR__ . '/js/session-timer.js'); ?>" defer></script>
  <script src="js/main.js?v=<?php echo filemtime(__DIR__ . '/js/main.js'); ?>" defer></script>

</body>

</html>