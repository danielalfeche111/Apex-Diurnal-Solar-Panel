<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../User.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../validation.php';

// If user is already logged in, redirect to homepage
if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$email = '';
$password = '';
$errors = [];

if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Single centralized validation & sanitization
    $validator = new Validator($_POST);
    $validator->required([
        'email'    => 'Email is required',
        'password' => 'Password is required'
    ]);

    $errors = $validator->errors();
    $clean = $validator->sanitized();
    $email = $clean['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // If no validation errors, try to log in
    if (empty($errors)) {
        $db = getConnection();
        $user = new User($db);

        $user->email = $email;
        if ($user->findByEmail($email)) {
            // Validate password
            if ($user->validatePassword($password)) {
                // Capture guest cart before login session regeneration
                $guestCart = !empty($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

                // Login successful
                loginUser($user->id, $user->email);

                // Rehydrate and merge cart into database
                try {
                    require_once __DIR__ . '/../Cart.php';
                    $cartModel = new Cart($db);
                    $cartResult = $cartModel->mergeSessionCart((int)$user->id, $guestCart);
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

                // Redirect to intended page or homepage
                $redirect = getRedirectAfterLogin();
                if ($redirect) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: ../index.php');
                }
                exit;
            } else {
                $errors['password'] = 'Invalid email or password';
            }
        } else {
            $errors['password'] = 'Invalid email or password';
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
                <p class="login-subtitle">Enter your email and password to access your account</p>
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
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="Enter your email address"
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