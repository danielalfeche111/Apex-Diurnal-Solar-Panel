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
require_once __DIR__ . '/cart_functions.php';
require_once __DIR__ . '/auth.php';

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
  <link rel="stylesheet" href="styles.css?v=5">
</head>

<body>

  <!-- NAVIGATION HEADER -->
  <header class="site-header" id="site-header">
    <div class="header-container">

      <a href="#home" class="brand-logo" aria-label="Apex Diurnal Home">
        <img src="assets/images/logo.png" alt="Apex Diurnal Logo" class="logo-mark">
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
            <button type="button" class="user-avatar-btn" aria-label="User Account" id="user-account-btn" title="<?php echo htmlspecialchars(getCurrentUserEmail()); ?>">
              <span class="user-avatar"><?php echo htmlspecialchars(strtoupper(substr(getCurrentUserEmail() ?? 'U', 0, 1))); ?></span>
            </button>
            <div class="user-dropdown-menu" id="user-dropdown-menu">
              <a href="settings.php" class="user-dropdown-item">Settings</a>
              <a href="logout.php" class="user-dropdown-item">Logout</a>
            </div>
          </div>
        <?php else: ?>
          <a href="login.php" class="header-login-btn" aria-label="Log In">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" aria-hidden="true">
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
          <a href="#contact" class="btn btn-yellow hero-btn">BOOK A CONSULTATION</a>
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
                <img src="assets/images/logo.png" alt="Apex Diurnal Logo" class="badge-logo">
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
                      $onclick = "addToCart('" . htmlspecialchars($product['id'], ENT_QUOTES) . "'); openCart();";
                    } elseif ($upper === 'LEARN MORE') {
                      $onclick = "document.getElementById('about')?.scrollIntoView({behavior:'smooth',block:'start'})";
                    } elseif ($upper === 'CONTACT SALES') {
                      $onclick = "document.getElementById('contact')?.scrollIntoView({behavior:'smooth',block:'start'})";
                    } else {
                      $onclick = "addToCart('" . htmlspecialchars($product['id'], ENT_QUOTES) . "')";
                    }
                    ?>
                    <button type="button" data-id="<?php echo htmlspecialchars($product['id']); ?>"
                      onclick="<?php echo $onclick; ?>" class="<?php echo htmlspecialchars($action['class']); ?>">
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
        <strong id="cart-total">$<?php echo number_format(cart_total($products), 2); ?></strong>
      </div>
      <?php $has_cart_items = (cart_item_count() > 0); ?>
      <div class="cart-footer-actions">
        <button type="button" class="btn btn-outline btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>" id="cart-clear" aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>">Clear Cart</button>
        <button type="button" class="btn btn-yellow btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>" id="cart-checkout" aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>" title="<?php echo !$has_cart_items ? 'Your cart is empty. Please add items before checking out.' : 'Proceed to Checkout'; ?>">Checkout</button>
      </div>
      <p class="cart-empty-hint" id="cart-empty-hint"
        style="display:none; text-align:center; margin-top:12px; font-size:0.85rem; color:var(--color-text-muted);">Your
        cart is empty.</p>
    </div>
  </aside>

  <!-- TOAST -->
  <div id="cart-toast" class="cart-toast" role="status" aria-live="polite"></div>

  <script>
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
    }, get_cart_items($products), array_keys(get_cart_items($products))))
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  </script>
  <script>
    // Inline fallback for user dropdown - ensures logout is clickable even if main.js fails or is cached
    (function(){
      function initFallbackDropdown(){
        var btn = document.getElementById('user-account-btn');
        var menu = document.getElementById('user-dropdown-menu');
        var dropdown = document.getElementById('user-dropdown');
        if(!btn || !menu) return;
        // Avoid double binding
        if(btn.dataset.fallbackBound) return;
        btn.dataset.fallbackBound = '1';
        btn.addEventListener('click', function(e){
          e.stopPropagation();
          e.preventDefault();
          menu.classList.toggle('show');
          console.log('Fallback toggle, show:', menu.classList.contains('show'));
        });
        document.addEventListener('click', function(e){
          if(dropdown && !dropdown.contains(e.target)){
            menu.classList.remove('show');
          }
        });
      }
      if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', initFallbackDropdown);
      } else {
        initFallbackDropdown();
      }
      // Also expose global for inline onclick
      window.toggleUserDropdown = window.toggleUserDropdown || function(){
        var m = document.getElementById('user-dropdown-menu');
        if(m) m.classList.toggle('show');
      };
    })();
  </script>

    <script src="js/main.js?v=3" defer></script>

</body>

</html>