<?php
require_once 'auth.php';

// Log out the user
logoutUser();

// Redirect to homepage
header('Location: index.php');
exit;
?>