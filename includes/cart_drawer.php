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
