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
            <button type="button" class="user-avatar-btn" aria-label="User Account" id="user-account-btn"
              title="<?php echo htmlspecialchars(getCurrentUserEmail()); ?>">
              <span
                class="user-avatar"><?php echo htmlspecialchars(strtoupper(substr(getCurrentUserEmail() ?? 'U', 0, 1))); ?></span>
            </button>
            <div class="user-dropdown-menu" id="user-dropdown-menu">
              <a href="settings.php" class="user-dropdown-item">Settings</a>
              <a href="logout.php" class="user-dropdown-item">Logout</a>
            </div>
          </div>
        <?php else: ?>
          <a href="login.php" class="header-login-btn" aria-label="Log In">
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
