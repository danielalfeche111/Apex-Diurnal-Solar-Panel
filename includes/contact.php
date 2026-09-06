  <!-- FOOTER & CONTACT SECTION -->
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
