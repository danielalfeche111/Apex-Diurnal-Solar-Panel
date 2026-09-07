<?php
/**
 * admin/includes/footer.php - Apex Diurnal Admin Footer Component
 */

if (!isset($admin_base)) {
    $admin_base = (strpos($_SERVER['PHP_SELF'], '/admin/orders/') !== false ||
                   strpos($_SERVER['PHP_SELF'], '/admin/inventory/') !== false ||
                   strpos($_SERVER['PHP_SELF'], '/admin/schedule/') !== false ||
                   strpos($_SERVER['PHP_SELF'], '/admin/quotes/') !== false) ? '../' : './';
}
?>
    </main>

    <!-- Footer Copyright -->
    <footer style="padding: 1.25rem 2rem; border-top: 1px solid #e2e8f0; font-size: 0.76rem; color: #94a3b8; display: flex; justify-content: space-between; align-items: center; background: #ffffff;">
      <div>&copy; <?php echo date('Y'); ?> Apex Diurnal Solar Panels. All rights reserved.</div>
      <div>Admin Control System v1.2</div>
    </footer>
  </div>
</div>

<!-- Toast notifications container -->
<div id="admin-toast-container"></div>

<!-- Admin JS -->
<script src="<?php echo $admin_base; ?>assets/js/admin.js"></script>
<?php if (!empty($extra_scripts)): echo $extra_scripts; endif; ?>
</body>
</html>
