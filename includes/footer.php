  <!-- GLOBAL SCRIPTS AND DATA -->
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

  <!-- Province -> City filtering data -->
  <script>
    const consultProvinceCityMap = <?php echo json_encode($consultProvinceCityMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const consultCityProvinceMap = <?php echo json_encode($consultCityProvinceMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const consultAllCities = <?php echo json_encode($consult_cities, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  </script>

  <!-- Modular JavaScript Bundle -->
  <script src="js/toast.js?v=<?php echo filemtime(__DIR__ . '/../js/toast.js'); ?>"></script>
  <script src="js/navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/navigation.js'); ?>"></script>
  <script src="js/cart.js?v=<?php echo filemtime(__DIR__ . '/../js/cart.js'); ?>"></script>
  <script src="js/consultation.js?v=<?php echo filemtime(__DIR__ . '/../js/consultation.js'); ?>"></script>
  <script src="js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>" defer></script>

</body>

</html>
