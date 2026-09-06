<?php
/**
 * Apex Diurnal - Main Landing Page Orchestrator
 *
 * Each functional section and modal is modularized in the includes/ directory:
 * - includes/header.php             : Configurations, session, auth, <head>, stylesheets
 * - includes/navbar.php             : Header bar, logo, nav links, cart trigger, user menu
 * - includes/hero.php               : Hero banner & primary CTA buttons
 * - includes/products.php           : Solar product catalog grid loop
 * - includes/services.php           : Services and energy transition features
 * - includes/about.php              : Rare technology and expert craftsmanship sections
 * - includes/contact.php            : Contact details and footer menu navigation
 * - includes/consultation_modal.php : Dual-mode Consultation & Corporate RFQ modal
 * - includes/cart_drawer.php        : Shopping cart slide-out drawer & overlay
 * - includes/toast.php              : Floating notification alert
 * - includes/footer.php             : Data exports, modular JS bundle, closing tags
 */

// 1. Layout & Header
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main>
  <?php
  // 2. Main Content Sections
  require_once __DIR__ . '/includes/hero.php';
  require_once __DIR__ . '/includes/products.php';
  require_once __DIR__ . '/includes/services.php';
  require_once __DIR__ . '/includes/about.php';
  ?>
</main>

<?php
// 3. Contact & Footer Navigation
require_once __DIR__ . '/includes/contact.php';

// 4. Modals, Drawers & Notifications
require_once __DIR__ . '/includes/consultation_modal.php';
require_once __DIR__ . '/includes/cart_drawer.php';
require_once __DIR__ . '/includes/toast.php';

// 5. Global Scripts & Document Termination
require_once __DIR__ . '/includes/footer.php';