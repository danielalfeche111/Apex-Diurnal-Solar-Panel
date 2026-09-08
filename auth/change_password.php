<?php
require_once __DIR__ . '/../auth.php';

// Change Password has been moved inside Settings
// Keep this file for backward compatibility – redirect to settings.php
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}
header('Location: ../account/settings.php#change-password');
exit;
?>
