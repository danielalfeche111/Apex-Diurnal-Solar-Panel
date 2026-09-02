<?php
// --- Page Configuration ---
$page_title       = "Apex Diurnal | Clean Energy, Intelligent Future";
$page_description = "Apex Diurnal - Top-tier solar technology, custom system design, "
                  . "professional installation, and long-term solar maintenance for a sustainable future.";
$current_year     = date('Y');

// --- Navigation Links ---
$nav_links = [
    ['label' => 'Home',     'href' => '#home',     'active' => true],
    ['label' => 'Products', 'href' => '#products', 'active' => false],
    ['label' => 'Services', 'href' => '#services', 'active' => false],
    ['label' => 'About Us', 'href' => '#about',    'active' => false],
    ['label' => 'Contact',  'href' => '#contact',  'active' => false],
];

// --- Product Cards ---
$products = [
    [
        'title'   => 'Residential Arrays',
        'image'   => 'assets/images/residential arrays.png',
        'alt'     => 'Residential Solar Panel Arrays',
        'price'   => '$145.00',
        'actions' => [
            ['label' => 'BUY NOW',    'class' => 'btn btn-yellow'],
            ['label' => 'LEARN MORE', 'class' => 'btn btn-outline'],
        ],
    ],
    [
        'title'   => 'Commercial Grids',
        'image'   => 'assets/images/commercial grids.png',
        'alt'     => 'Commercial Solar Grids',
        'price'   => 'Call for Quote',
        'actions' => [
            ['label' => 'CONTACT SALES', 'class' => 'btn btn-yellow btn-block'],
        ],
    ],
    [
        'title'   => 'Advanced Solar Inverter',
        'image'   => 'assets/images/advance power inverter.png',
        'alt'     => 'Advanced Solar Inverter',
        'price'   => '$450.00',
        'actions' => [
            ['label' => 'BUY NOW',    'class' => 'btn btn-yellow'],
            ['label' => 'LEARN MORE', 'class' => 'btn btn-outline'],
        ],
    ],
    [
        'title'   => 'Professional Installation Booking',
        'image'   => 'assets/images/product-booking.png',
        'alt'     => 'Professional Installation Booking',
        'price'   => '$150.00',
        'actions' => [
            ['label' => 'BOOK NOW', 'class' => 'btn btn-yellow btn-block'],
        ],
    ],
];

// --- Service / Feature Cards ---
$features = [
    [
        'title'    => 'CUSTOM SYSTEM DESIGN',
        'image'    => 'assets/images/custom design.png',
        'icon'     => 'assets/images/custom.png',
        'alt_img'  => 'Custom System Design',
        'alt_icon' => 'Custom System Design Icon',
        'text'     => 'End to End Service. Custom solutions for homes and businesses. '
                    . 'Complete Site Analysis and 3D Modeling.',
    ],
    [
        'title'    => 'PROFESSIONAL INSTALLATION',
        'image'    => 'assets/images/professional installation.png',
        'icon'     => 'assets/images/PROF INSTALLATION.png',
        'alt_img'  => 'Professional Installation',
        'alt_icon' => 'Professional Installation Icon',
        'text'     => 'Seamless Execution. Flawless installation of unique system designs. '
                    . 'Maximizing Solar Capture.',
    ],
    [
        'title'    => 'LONG-TERM MAINTENANCE',
        'image'    => 'assets/images/maintenance.png',
        'icon'     => 'assets/images/longterm maintenance.png',
        'alt_img'  => 'Long-Term Maintenance',
        'alt_icon' => 'Long-Term Maintenance Icon',
        'text'     => 'Ensuring Performance. System monitoring, cleaning, and preventative care. '
                    . 'Prolonging Life and Rewarding Investment.',
    ],
];

// --- Footer Columns ---
$footer_menu = [
    ['label' => 'Home',       'href' => '#home'],
    ['label' => 'Products',   'href' => '#products'],
    ['label' => 'Services',   'href' => '#services'],
    ['label' => 'About us',   'href' => '#about'],
    ['label' => 'Contact us', 'href' => '#contact'],
];
$footer_legalities = [
    'Copyright Notice',
    'Privacy Policy',
    'Terms of Service / Conditions',
    'Disclaimers',
    'Accessibility Statement',
];

function void_link(string $inner, string $class = '', string $extra_attr = ''): string {
    $cls = $class ? ' class="' . $class . '"' : '';
    return '<a href="javascript:void(0)" onclick="return false;"' . $cls . $extra_attr . '>' . $inner . '</a>';
}

function nav_link(string $label, string $href, string $class = '', string $extra_attr = ''): string {
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
  <link rel="stylesheet" href="styles.css">
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
          <button type="button" class="icon-btn" aria-label="Search" id="search-toggle" aria-expanded="false" aria-controls="search-bar">
            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </button>
          <div class="search-bar" id="search-bar" role="search">
            <input type="search" class="search-input" id="search-input" placeholder="Search" aria-label="Search products and navigation" autocomplete="off">
            <button type="button" class="search-close" id="search-close" aria-label="Close search">×</button>
          </div>
          <div class="search-results" id="search-results" aria-live="polite"></div>
        </div>
        <button type="button" onclick="return false;" class="icon-btn cart-btn" aria-label="Shopping Cart">
          <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2F" fill="none">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
        </button>
        <button type="button" onclick="return false;" class="icon-btn" aria-label="User Account">
          <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
        </button>
      </div>

    </div>
  </header>

  <main>

    <!-- HERO SECTION -->
    <section class="hero-section" id="home">
      <div class="hero-background">
        <img src="assets/images/homebackground.png"
             alt="Modern house with solar panel rooftop installation"
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
                     alt="<?php echo htmlspecialchars($product['alt']); ?>"
                     class="card-img">
              </div>
              <div class="card-body">
                <h3 class="product-title"><?php echo htmlspecialchars($product['title']); ?></h3>
                <div class="product-price"><?php echo htmlspecialchars($product['price']); ?></div>
                <div class="card-actions">
                  <?php foreach ($product['actions'] as $action): ?>
                    <button type="button" onclick="return false;"
                            class="<?php echo htmlspecialchars($action['class']); ?>">
                      <?php echo htmlspecialchars($action['label']); ?>
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
                     alt="<?php echo htmlspecialchars($feature['alt_img']); ?>"
                     class="feature-img">
              </div>
              <div class="feature-header">
                <div class="feature-icon">
                  <img src="<?php echo htmlspecialchars($feature['icon']); ?>"
                       alt="<?php echo htmlspecialchars($feature['alt_icon']); ?>"
                       style="width:28px;height:28px;object-fit:contain;
                              filter:invert(82%) sepia(87%) saturate(1915%)
                                     hue-rotate(345deg) brightness(103%) contrast(105%);">
                </div>
                <h3 class="feature-name"><?php echo htmlspecialchars($feature['title']); ?></h3>
              </div>
              <p class="feature-text"><?php echo htmlspecialchars($feature['text']); ?></p>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="seamless-cta-wrap">
          <button type="button" onclick="return false;" class="btn btn-yellow btn-large">BOOK NOW</button>
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
                 alt="Solar panel thermodynamic cell detail"
                 class="tech-img">
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
              <img src="assets/images/craftsmanship.png"
                   alt="Solar installer mounting panel precision"
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
                <svg viewBox="0 0 24 24" width="32" height="32" fill="none"
                     stroke="var(--color-navy)" stroke-width="2">
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
              <svg viewBox="0 0 24 24" width="22" height="22" fill="none"
                   stroke="var(--color-yellow)" stroke-width="2">
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

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toggle = document.getElementById('search-toggle');
      const bar = document.getElementById('search-bar');
      const input = document.getElementById('search-input');
      const closeBtn = document.getElementById('search-close');
      const container = document.getElementById('search-container');
      const results = document.getElementById('search-results');
      const cards = document.querySelectorAll('.product-card');
      const navLinks = document.querySelectorAll('.nav-link');
      const footerLinks = document.querySelectorAll('.footer-links a');

      function openSearch() {
        bar.classList.add('active');
        toggle.setAttribute('aria-expanded', 'true');
        setTimeout(function() { input.focus(); }, 100);
      }

      function closeSearch() {
        bar.classList.remove('active');
        results.classList.remove('active');
        results.innerHTML = '';
        toggle.setAttribute('aria-expanded', 'false');
        input.value = '';
        document.body.classList.remove('is-searching');
      }

      function navigateTo(href) {
        closeSearch();
        const target = document.querySelector(href);
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        else window.location.hash = href;
      }

      function buildResultItem(label, href, type) {
        const div = document.createElement('div');
        div.className = 'search-result-item';
        div.innerHTML = '<span class="result-type">' + type + '</span> ' + label;
        div.addEventListener('click', function() { navigateTo(href); });
        return div;
      }

      toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        if (bar.classList.contains('active')) closeSearch(); else openSearch();
      });

      closeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        closeSearch();
      });

      document.addEventListener('click', function(e) {
        if (!container.contains(e.target) && bar.classList.contains('active')) closeSearch();
      });

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && bar.classList.contains('active')) closeSearch();
      });

      input.addEventListener('input', function() {
        const q = input.value.toLowerCase().trim();
        results.innerHTML = '';
        results.classList.remove('active');

        if (!q) {
          document.body.classList.remove('is-searching');
          return;
        }
        document.body.classList.add('is-searching');

        // --- Product search with direct card direction ---
        let productMatches = [];
        cards.forEach(function(card) {
          const titleEl = card.querySelector('.product-title');
          const title = (titleEl?.textContent || '').toLowerCase();
          const price = (card.querySelector('.product-price')?.textContent || '').toLowerCase();
          // handle common typo / partial matches for the 4 products
          const match = title.includes(q) || price.includes(q) ||
                        (q.includes('professional') && title.includes('professional')) ||
                        (q.includes('proffesional') && title.includes('professional'));
          if (match) productMatches.push({ card: card, title: titleEl?.textContent.trim() || 'Product', href: '#' + card.id });
        });

        // --- Navigation search (no highlight) ---
        let navMatches = [];
        navLinks.forEach(function(link) {
          const text = (link.textContent || '').toLowerCase();
          const href = (link.getAttribute('href') || '').toLowerCase();
          const match = text.includes(q) || href.includes(q);
          if (match) {
            navMatches.push({ label: link.textContent.trim(), href: link.getAttribute('href'), type: 'NAV' });
          }
        });
        footerLinks.forEach(function(link) {
          const text = (link.textContent || '').toLowerCase();
          if (text.includes(q)) {
            // avoid duplicate if already in header
            if (!navMatches.some(function(m){ return m.href === link.getAttribute('href'); })) {
              navMatches.push({ label: link.textContent.trim(), href: link.getAttribute('href'), type: 'NAV' });
            }
          }
        });

        // If searching for "product"/"products" show all products with direction to each card
        const isProductSearch = q.includes('product') || navMatches.some(function(m){ return m.href === '#products'; });
        if (isProductSearch && productMatches.length === 0) {
          productMatches = Array.from(cards).map(function(card){
            return { card: card, title: card.querySelector('.product-title')?.textContent.trim() || 'Product', href: '#' + card.id };
          });
        }

        // --- Build dropdown results ---
        let hasResults = false;
        navMatches.forEach(function(m) {
          const div = document.createElement('div');
          div.className = 'search-result-item';
          div.textContent = m.label;
          div.addEventListener('click', function() { navigateTo(m.href); });
          results.appendChild(div);
          hasResults = true;
        });
        productMatches.forEach(function(m) {
          results.appendChild(buildResultItem(m.title, m.href, 'PRODUCT'));
          hasResults = true;
        });

        if (hasResults) {
          results.classList.add('active');
        } else {
          const empty = document.createElement('div');
          empty.className = 'search-result-item';
          empty.style.opacity = '0.6';
          empty.style.cursor = 'default';
          empty.textContent = 'No results for "' + input.value + '"';
          results.appendChild(empty);
          results.classList.add('active');
        }
      });

      input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          const firstResult = results.querySelector('.search-result-item');
          if (firstResult && firstResult.textContent.indexOf('No results') === -1) {
            firstResult.click();
            return;
          }
          const qNav = input.value.toLowerCase().trim();
          let firstNav = null;
          if (qNav) {
            firstNav = Array.from(navLinks).find(function(l) {
              return (l.textContent || '').toLowerCase().includes(qNav) || (l.getAttribute('href') || '').toLowerCase().includes(qNav);
            });
          }
          if (firstNav) {
            const href = firstNav.getAttribute('href');
            const target = href ? document.querySelector(href) : null;
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
          }
          const firstCard = document.querySelector('.product-card:not([style*="display: none"])');
          if (firstCard) firstCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    });
  </script>

</body>

</html>