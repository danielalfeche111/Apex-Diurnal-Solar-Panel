<?php
require_once __DIR__ . '/session.php';

// Initialize session with enterprise security & inactivity tracking
SessionManager::start();

/**
 * Check if the user is logged in
 * @return boolean
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Log in the user
 * @param int $user_id
 * @param string $email
 */
function loginUser($user_id, $email): void {
    SessionManager::start();
    $_SESSION['user_id'] = (int)$user_id;
    $_SESSION['email'] = $email;
    $_SESSION['_last_activity'] = time();

    // Regenerate session ID to prevent session fixation
    SessionManager::regenerate(true);

    // Set non-sensitive flag for client-side JavaScript without compromising HttpOnly PHPSESSID
    if (!headers_sent()) {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        setcookie('app_logged_in', '1', [
            'expires'  => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    }
}

/**
 * Log out the user
 */
function logoutUser(): void {
    SessionManager::destroy();

    // Remove client-side flag
    if (!headers_sent()) {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        setcookie('app_logged_in', '', [
            'expires'  => time() - 42000,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    }
}

/**
 * Get the current user's ID
 * @return int|null
 */
function getCurrentUserId(): ?int {
    return isLoggedIn() ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get the current user's email
 * @return string|null
 */
function getCurrentUserEmail(): ?string {
    return isLoggedIn() ? $_SESSION['email'] : null;
}

/**
 * Redirect to login page if not logged in
 * @param string|null $redirect_url (optional) the URL to redirect back to after login
 */
function requireLogin(?string $redirect_url = null): void {
    SessionManager::start();
    if (!isLoggedIn()) {
        if (SessionManager::isExpired()) {
            SessionManager::setFlash('warning', 'Your session has expired due to inactivity. Please sign in again.');
        }

        // Store the intended destination in the session
        if ($redirect_url !== null) {
            $_SESSION['redirect_after_login'] = $redirect_url;
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appDir = str_replace('\\', '/', realpath(__DIR__));
        $baseUri = $docRoot ? rtrim(str_replace($docRoot, '', $appDir), '/') : '';
        $loginUrl = ($baseUri ? $baseUri : '') . '/auth/login.php';
        header('Location: ' . $loginUrl);
        exit;
    }
}

/**
 * Get the redirect URL after login (if set) and clear it from session
 * @return string|null
 */
function getRedirectAfterLogin(): ?string {
    $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : null;
    unset($_SESSION['redirect_after_login']);
    return $redirect;
}
?>