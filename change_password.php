<?php
require_once 'auth.php';

// Change Password has been moved inside Settings
// Keep this file for backward compatibility – redirect to settings.php
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}
header('Location: settings.php#change-password');
exit;
?>
