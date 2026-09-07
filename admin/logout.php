<?php
/**
 * admin/logout.php - Admin Logout Controller
 */

require_once __DIR__ . '/auth.php';

logoutAdmin();
header('Location: login.php?logged_out=1');
exit;
