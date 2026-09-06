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
