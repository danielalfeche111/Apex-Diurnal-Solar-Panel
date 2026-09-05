<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if the user is logged in
 * @return boolean
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Log in the user
 * @param int $user_id
 * @param string $email
 */
function loginUser($user_id, $email) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
}

/**
 * Log out the user
 */
function logoutUser() {
    // Ensure session is started so we can clear it
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Get the current user's ID
 * @return int|null
 */
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

/**
 * Get the current user's email
 * @return string|null
 */
function getCurrentUserEmail() {
    return isLoggedIn() ? $_SESSION['email'] : null;
}

/**
 * Redirect to login page if not logged in
 * @param string $redirect_url (optional) the URL to redirect back to after login
 */
function requireLogin($redirect_url = null) {
    if (!isLoggedIn()) {
        // Store the intended destination in the session
        if ($redirect_url !== null) {
            $_SESSION['redirect_after_login'] = $redirect_url;
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }
        header('Location: login.php');
        exit;
    }
}

/**
 * Get the redirect URL after login (if set) and clear it from session
 * @return string|null
 */
function getRedirectAfterLogin() {
    $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : null;
    unset($_SESSION['redirect_after_login']);
    return $redirect;
}
?>