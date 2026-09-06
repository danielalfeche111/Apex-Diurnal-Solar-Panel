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
                    } elseif ($upper === 'CONTACT SALES' || $upper === 'REQUEST QUOTE') {
                      $onclick = "openConsultationModal('rfq');";
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
