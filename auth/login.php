<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../User.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../admin/auth.php';
require_once __DIR__ . '/../validation.php';

// If admin is already logged in, redirect directly to admin dashboard
if (isAdminLoggedIn()) {
    header('Location: ../admin/index.php');
    exit;
}

// If user is already logged in, redirect to homepage
if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$email = '';
$password = '';
$errors = [];

if (isset($_GET['logged_out'])) {
    SessionManager::setFlash('success', 'You have been signed out successfully.');
}

if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $r = $_GET['redirect'];
    $_SESSION['redirect_after_login'] = $r;
    if (strpos($r, 'admin') !== false) {
        $_SESSION['admin_redirect_after_login'] = $r;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Single centralized validation & sanitization
    $validator = new Validator($_POST);
    $validator->required([
        'email'    => 'Email or username is required',
        'password' => 'Password is required'
    ]);

    $errors = $validator->errors();
    $clean = $validator->sanitized();
    $identity = trim($clean['email'] ?? $_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $email = $identity;

    // If no validation errors, identify and authenticate the account
    if (empty($errors)) {
        $db = getConnection();
        $accountMatched = false;

        // --- STEP 1: Check admin_users table (matches by username OR email) ---
        try {
            $stmtAdmin = $db->prepare("SELECT * FROM admin_users WHERE username = :u OR email = :e LIMIT 1");
            $stmtAdmin->execute([':u' => $identity, ':e' => $identity]);
            $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $accountMatched = true;
                loginAdmin($admin);

                // Determine redirect target for admin
                $adminRedirect = $_SESSION['admin_redirect_after_login'] ?? $_GET['redirect'] ?? '';
                unset($_SESSION['admin_redirect_after_login'], $_SESSION['redirect_after_login']);

                if (!empty($adminRedirect) && strpos($adminRedirect, 'admin') !== false) {
                    header('Location: ' . $adminRedirect);
                } else {
                    header('Location: ../admin/index.php');
                }
                exit;
            }
        } catch (PDOException $e) {
            error_log('[login] admin_users query error: ' . $e->getMessage());
        }

        // --- STEP 2: Check users table (Customer / Storefront User) ---
        try {
            $stmtUser = $db->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
            $stmtUser->execute([':e' => $identity]);
            $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($userRow && password_verify($password, $userRow['password_hash'])) {
                $accountMatched = true;

                // Identify if this user account has admin privileges
                $userRole = strtolower($userRow['role'] ?? '');
                $isAdmin = in_array($userRole, ['admin', 'superadmin', 'manager', 'staff'], true);

                if (!$isAdmin) {
                    // Check if this email exists in admin_users or matches test admin
                    $stmtAdminCheck = $db->prepare("SELECT * FROM admin_users WHERE email = :e LIMIT 1");
                    $stmtAdminCheck->execute([':e' => $userRow['email']]);
                    $matchedAdmin = $stmtAdminCheck->fetch(PDO::FETCH_ASSOC);
                    if ($matchedAdmin) {
                        $isAdmin = true;
                        $adminData = $matchedAdmin;
                    } elseif ($userRow['email'] === 'admin@gmail.com') {
                        $isAdmin = true;
                        $adminData = [
                            'id' => (int)$userRow['id'],
                            'username' => 'admin',
                            'email' => $userRow['email'],
                            'role' => 'superadmin'
                        ];
                    }
                } else {
                    $adminData = [
                        'id' => (int)$userRow['id'],
                        'username' => $userRow['full_name'] ?? 'Admin',
                        'email' => $userRow['email'],
                        'role' => !empty($userRole) ? $userRole : 'superadmin'
                    ];
                }

                if ($isAdmin) {
                    // Account identified as ADMIN -> Redirect to Admin Dashboard
                    loginAdmin($adminData);
                    $adminRedirect = $_SESSION['admin_redirect_after_login'] ?? $_GET['redirect'] ?? '';
                    unset($_SESSION['admin_redirect_after_login'], $_SESSION['redirect_after_login']);

                    if (!empty($adminRedirect) && strpos($adminRedirect, 'admin') !== false) {
                        header('Location: ' . $adminRedirect);
                    } else {
                        header('Location: ../admin/index.php');
                    }
                    exit;
                }

                // Account identified as USER -> Redirect to Homepage (or customer redirect)
                $guestCart = !empty($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

                loginUser($userRow['id'], $userRow['email']);

                // Rehydrate and merge cart into database
                try {
                    require_once __DIR__ . '/../Cart.php';
                    $cartModel = new Cart($db);
                    $cartResult = $cartModel->mergeSessionCart((int)$userRow['id'], $guestCart);
                    if (!empty($cartResult['notices'])) {
                        $_SESSION['cart_notifications'] = $cartResult['notices'];
                        foreach ($cartResult['notices'] as $notice) {
                            if (!empty($notice['message'])) {
                                SessionManager::setFlash('warning', $notice['message']);
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log('[login] Error merging cart on login: ' . $e->getMessage());
                }

                // Redirect to customer destination or Homepage
                $redirect = getRedirectAfterLogin();
                if (!empty($redirect) && strpos($redirect, 'admin') === false) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: ../index.php');
                }
                exit;
            }
        } catch (PDOException $e) {
            error_log('[login] users query error: ' . $e->getMessage());
        }

        if (!$accountMatched) {
            $errors['password'] = 'Invalid email/username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Apex Diurnal Solar Panels</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <?php $auth_css_v = file_exists(__DIR__ . '/auth.css') ? filemtime(__DIR__ . '/auth.css') : time(); ?>
    <link rel="stylesheet" href="auth.css?v=<?php echo $auth_css_v; ?>">
</head>
<body>
    <!-- Top Navigation with return link on right -->
    <div class="top-nav">
        <a href="../index.php" class="back-home-link" title="Return to Homepage">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Back to Home</span>
        </a>
    </div>

    <!-- Main Auth Center Wrapper -->
    <div class="auth-wrapper">
        <div class="login-card">
            <div class="login-header">
                <img src="../assets/images/logo.png" alt="Apex Diurnal Logo" class="logo-mark">
                <h1 class="login-title">Sign In to Your Account</h1>
                <p class="login-subtitle">Enter your email or username to access your account</p>
            </div>

            <?php 
            $flashWarnings = SessionManager::getFlash('warning');
            $flashErrors = SessionManager::getFlash('error');
            $flashSuccesses = SessionManager::getFlash('success');
            $flashInfos = SessionManager::getFlash('info');
            ?>

            <?php if (!empty($flashWarnings)): ?>
                <div class="warning-alert" role="alert" style="display:flex; align-items:flex-start; gap:12px; background-color:#fffbeb; border:1px solid #fde68a; border-left:4px solid #f59e0b; border-radius:10px; padding:12px 16px; margin-bottom:20px; color:#92400e; font-size:0.88rem; line-height:1.4;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>
                        <?php foreach ($flashWarnings as $msg): ?>
                            <div><?php echo htmlspecialchars($msg); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashErrors)): ?>
                <div class="error-alert" role="alert" style="margin-bottom:20px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>
                        <?php foreach ($flashErrors as $msg): ?>
                            <div><?php echo htmlspecialchars($msg); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashSuccesses)): ?>
                <div class="success-alert" role="alert" style="display:flex; align-items:flex-start; gap:12px; background-color:#ecfdf5; border:1px solid #a7f3d0; border-left:4px solid #10b981; border-radius:10px; padding:12px 16px; margin-bottom:20px; color:#065f46; font-size:0.88rem; line-height:1.4;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <div>
                        <?php foreach ($flashSuccesses as $msg): ?>
                            <div><?php echo htmlspecialchars($msg); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="error-alert" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="on">
                <div class="form-group">
                    <label for="email" class="form-label">Email or Username</label>
                    <div class="input-group">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <input 
                            type="text" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="Enter your email or username"
                            value="<?php echo htmlspecialchars($email); ?>" 
                            required 
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-label-row">
                        <label for="password" class="form-label">Password</label>
                        <a href="change_password.php" class="forgot-link">Change Password?</a>
                    </div>
                    <div class="input-group">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Enter your password"
                            style="padding-right: 44px;"
                            required
                        >
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Show or hide password">
                            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-signin">
                    <span>Sign In</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>

            <div class="card-footer">
                <p>Don't have an account? <a href="register.php">Sign Up</a></p>
                <p style="margin-top: 8px;"><a href="../index.php">← Return to Solar Homepage</a></p>
            </div>
        </div>
    </div>

    <!-- Clean Brand Footer -->
    <footer class="page-footer">
        &copy; <?php echo date('Y'); ?> Apex Diurnal Solar Panels. Clean Energy &bull; Intelligent Future.
    </footer>

    <script src="auth.js" defer></script>
</body>
</html>