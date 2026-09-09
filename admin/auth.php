<?php
/**
 * admin/auth.php - Authentication & Authorization for Apex Diurnal Admin Dashboard
 */

require_once __DIR__ . '/../session.php';

// Initialize session with enterprise security & inactivity tracking
SessionManager::start();

require_once __DIR__ . '/../config.php';

/**
 * Check if an admin is currently logged in
 * @return bool
 */
function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

/**
 * Get the currently logged-in admin user information
 * @return array|null
 */
function getAdminUser(): ?array
{
    if (!isAdminLoggedIn()) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'] ?? 'Admin',
        'email' => $_SESSION['admin_email'] ?? '',
        'role' => $_SESSION['admin_role'] ?? 'staff',
    ];
}

/**
 * Enforce admin authentication. Redirects to login page if unauthenticated.
 * @param array|string|null $allowed_roles
 * @param string|null $redirect_url
 */
function requireAdminLogin($allowed_roles = null, ?string $redirect_url = null): void
{
    SessionManager::start();
    if (!isAdminLoggedIn()) {
        if (SessionManager::isExpired()) {
            SessionManager::setFlash('warning', 'Your admin session has expired due to inactivity. Please sign in again.');
        }

        $target = $redirect_url ?? $_SERVER['REQUEST_URI'] ?? 'index.php';
        $_SESSION['admin_redirect_after_login'] = $target;

        // Calculate relative path to unified login page at auth/login.php
        $login_path = (strpos($_SERVER['PHP_SELF'], '/admin/orders/') !== false ||
            strpos($_SERVER['PHP_SELF'], '/admin/inventory/') !== false ||
            strpos($_SERVER['PHP_SELF'], '/admin/schedule/') !== false ||
            strpos($_SERVER['PHP_SELF'], '/admin/quotes/') !== false)
            ? '../../auth/login.php' : '../auth/login.php';

        header("Location: $login_path");
        exit;
    }

    if ($allowed_roles !== null) {
        $roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
        $current_role = $_SESSION['admin_role'] ?? 'staff';
        if (!in_array($current_role, $roles, true) && $current_role !== 'superadmin') {
            http_response_code(403);
            die("Access Denied: You do not have permission to view this section.");
        }
    }
}

/**
 * Log in an admin user and initialize secure session
 * @param array $user
 */
function loginAdmin(array $user): void
{
    SessionManager::start();
    $_SESSION['admin_id'] = (int) $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['admin_email'] = $user['email'];
    $_SESSION['admin_role'] = $user['role'] ?? 'staff';
    $_SESSION['_last_activity'] = time();

    // Regenerate session id to protect against session fixation
    SessionManager::regenerate(true);
}

/**
 * Log out admin user (clears only admin-related session variables)
 */
function logoutAdmin(): void
{
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_username'],
        $_SESSION['admin_email'],
        $_SESSION['admin_role'],
        $_SESSION['admin_redirect_after_login']
    );

    SessionManager::regenerate(true);
}

/**
 * Generate or get existing CSRF token
 * @return string
 */
function csrfToken(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

/**
 * Verify CSRF token from request
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['admin_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['admin_csrf_token'], $token);
}

/**
 * Quick helper to get live badge counts for sidebar
 * @return array
 */
function getAdminBadgeCounts(): array
{
    static $counts = null;
    if ($counts !== null) {
        return $counts;
    }

    $counts = [
        'pending_orders' => 0,
        'low_stock' => 0,
        'pending_bookings' => 0,
        'new_quotes' => 0
    ];

    try {
        $db = getConnection();
        if ($db) {
            $counts['pending_orders'] = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
            $counts['low_stock'] = (int) $db->query("SELECT COUNT(*) FROM inventory WHERE current_stock <= reorder_point")->fetchColumn();
            $counts['pending_bookings'] = (int) $db->query("SELECT COUNT(*) FROM service_bookings WHERE status = 'pending'")->fetchColumn();
            $counts['new_quotes'] = (int) $db->query("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'")->fetchColumn();
        }
    } catch (\Exception $e) {
        // Fallback silently if tables are being migrated
    }

    return $counts;
}
