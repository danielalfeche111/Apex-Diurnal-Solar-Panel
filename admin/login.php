<?php
/**
 * admin/login.php - Apex Diurnal Admin Authentication
 * 
 * Unified Authentication:
 * System now uses a single centralized login page at auth/login.php with automatic
 * account role detection (Admin -> Admin Dashboard, User -> Homepage).
 */

require_once __DIR__ . '/auth.php';

// If already authenticated as admin, redirect to admin dashboard
if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Preserve any redirect parameter and route to unified login page
$redirect = $_GET['redirect'] ?? $_SESSION['admin_redirect_after_login'] ?? 'index.php';
$_SESSION['admin_redirect_after_login'] = $redirect;

header('Location: ../auth/login.php?redirect=' . urlencode($redirect));
exit;
