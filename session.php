<?php
// Session management

class SessionManager {
    public const DEFAULT_IDLE_TIMEOUT = 1800;
    public const DEFAULT_REGENERATE_INTERVAL = 900;

    private static bool $started = false;
    private static int $idleTimeout = self::DEFAULT_IDLE_TIMEOUT;
    private static int $regenerateInterval = self::DEFAULT_REGENERATE_INTERVAL;

    // Start session and configure settings
    public static function start(?int $idleTimeout = null, ?int $regenerateInterval = null): void {
        if ($idleTimeout !== null) {
            self::$idleTimeout = max(60, $idleTimeout);
        }
        if ($regenerateInterval !== null) {
            self::$regenerateInterval = max(60, $regenerateInterval);
        }

        if (session_status() === PHP_SESSION_NONE) {
            // Enforce secure session settings
            if (!headers_sent()) {
                ini_set('session.use_only_cookies', '1');
                ini_set('session.use_trans_sid', '0');
                ini_set('session.use_strict_mode', '1');

                $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

                session_set_cookie_params([
                    'lifetime' => 0,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            @session_start();
        }

        self::$started = true;

        // Run security checks
        self::validateFingerprint();
        self::checkInactivity();
        self::checkPeriodicRegeneration();
    }

    // Check if session is active
    public static function isStarted(): bool {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    // Validate browser fingerprint
    public static function validateFingerprint(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown_agent';
        $currentFingerprint = hash('sha256', $userAgent);

        if (!isset($_SESSION['_session_fingerprint'])) {
            $_SESSION['_session_fingerprint'] = $currentFingerprint;
            return true;
        }

        if (!hash_equals($_SESSION['_session_fingerprint'], $currentFingerprint)) {
            // Fingerprint mismatch detected
            error_log("[SessionManager] Session fingerprint mismatch detected. Invalidator IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            unset(
                $_SESSION['user_id'],
                $_SESSION['email'],
                $_SESSION['admin_id'],
                $_SESSION['admin_username'],
                $_SESSION['admin_email'],
                $_SESSION['admin_role']
            );
            $_SESSION['_session_fingerprint'] = $currentFingerprint;
            self::setFlash('error', 'Your session was reset due to a security verification check. Please log in again.');
            return false;
        }

        return true;
    }

    // Check inactivity timeout
    public static function checkInactivity(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $currentTime = time();
        $isLoggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']);

        // Check timeout for logged in user
        if ($isLoggedIn && isset($_SESSION['_last_activity'])) {
            $elapsed = $currentTime - (int)$_SESSION['_last_activity'];
            if ($elapsed > self::$idleTimeout) {
                // Session expired
                self::expire();
                return;
            }
        }

        // Update last activity timestamp
        $_SESSION['_last_activity'] = $currentTime;
    }

    // Periodically regenerate session id
    public static function checkPeriodicRegeneration(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $currentTime = time();
        if (!isset($_SESSION['_session_created_at'])) {
            $_SESSION['_session_created_at'] = $currentTime;
            return;
        }

        if (($currentTime - (int)$_SESSION['_session_created_at']) > self::$regenerateInterval) {
            self::regenerate(true);
        }
    }

    // Regenerate session id
    public static function regenerate(bool $deleteOld = true): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        if (!headers_sent()) {
            $result = session_regenerate_id($deleteOld);
            if ($result) {
                $_SESSION['_session_created_at'] = time();
            }
            return $result;
        }

        return false;
    }

    // Expire session for inactivity
    public static function expire(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // Clear user and admin credentials
        unset(
            $_SESSION['user_id'],
            $_SESSION['email'],
            $_SESSION['admin_id'],
            $_SESSION['admin_username'],
            $_SESSION['admin_email'],
            $_SESSION['admin_role'],
            $_SESSION['_last_activity']
        );

        $_SESSION['_session_expired'] = true;
        self::setFlash('warning', 'Your session has expired due to inactivity. Please log in again.');
        self::regenerate(true);
    }

    // Check if session expired
    public static function isExpired(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $expired = !empty($_SESSION['_session_expired']);
        if ($expired) {
            unset($_SESSION['_session_expired']);
        }
        return $expired;
    }

    // Get remaining idle time
    public static function getTimeRemaining(): int {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['_last_activity'])) {
            return self::$idleTimeout;
        }

        $elapsed = time() - (int)$_SESSION['_last_activity'];
        $remaining = self::$idleTimeout - $elapsed;
        return max(0, $remaining);
    }

    // Keep session alive
    public static function keepAlive(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_last_activity'] = time();
        }
    }

    // Destroy session and cookies
    public static function destroy(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Preserve flash messages
        $flashes = $_SESSION['_flash'] ?? null;

        $_SESSION = [];

        if ($flashes !== null) {
            $_SESSION['_flash'] = $flashes;
        }

        if (ini_get("session.use_cookies") && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$started = false;
    }

    // Session helpers

    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void {
        unset($_SESSION[$key]);
    }

    // Flash messages

    // Add flash message
    public static function setFlash(string $type, string $message): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }
        if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        if (!isset($_SESSION['_flash'][$type])) {
            $_SESSION['_flash'][$type] = [];
        }
        $_SESSION['_flash'][$type][] = $message;
    }

    // Get and clear flash messages
    public static function getFlash(?string $type = null): array {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['_flash'])) {
            return [];
        }

        if ($type !== null) {
            $messages = $_SESSION['_flash'][$type] ?? [];
            unset($_SESSION['_flash'][$type]);
            if (empty($_SESSION['_flash'])) {
                unset($_SESSION['_flash']);
            }
            return $messages;
        }

        $all = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $all;
    }

    // Check if flash message exists
    public static function hasFlash(string $type): bool {
        return !empty($_SESSION['_flash'][$type]);
    }
}

// Helper functions
if (!function_exists('session_get')) {
    function session_get(string $key, $default = null) {
        return SessionManager::get($key, $default);
    }
}

if (!function_exists('session_set')) {
    function session_set(string $key, $value): void {
        SessionManager::set($key, $value);
    }
}

if (!function_exists('session_has')) {
    function session_has(string $key): bool {
        return SessionManager::has($key);
    }
}

if (!function_exists('session_remove')) {
    function session_remove(string $key): void {
        SessionManager::remove($key);
    }
}

if (!function_exists('session_flash')) {
    function session_flash(string $type, ?string $message = null) {
        if ($message === null) {
            return SessionManager::getFlash($type);
        }
        SessionManager::setFlash($type, $message);
    }
}

// Ajax ping endpoint
if (isset($_GET['action']) && $_GET['action'] === 'ping') {
    SessionManager::start();
    SessionManager::keepAlive();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'authenticated' => !empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']),
        'remaining' => SessionManager::getTimeRemaining()
    ]);
    exit;
}
