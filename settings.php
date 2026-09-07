<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'User.php';
require_once __DIR__ . '/product_data.php';
require_once __DIR__ . '/cart_functions.php';

// Require login
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle Change Password inside Settings
$email = '';
$current_password = '';
$new_password = '';
$confirm_password = '';
$errors = [];
$success_message = '';

if (isset($_GET['updated']) && empty($_GET)) {
    // placeholder to avoid undefined
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation – same as change_password.php (important)
    if ($email === '') {
        $errors['email'] = 'Email is required';
    }
    if ($current_password === '') {
        $errors['current_password'] = 'Current password is required';
    }
    if ($new_password === '') {
        $errors['new_password'] = 'New password is required';
    } elseif (strlen($new_password) < 6) {
        $errors['new_password'] = 'New password must be at least 6 characters';
    }
    if ($confirm_password === '') {
        $errors['confirm_password'] = 'Please confirm your new password';
    } elseif ($new_password !== $confirm_password) {
        $errors['confirm_password'] = 'New passwords do not match';
    }

    if (empty($errors)) {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);
        $user_id = getCurrentUserId();
        if ($user_id) {
            $user->id = $user_id;
            if ($user->findById($user_id)) {
                if ($user->validatePassword($current_password)) {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    if ($user->updatePassword($new_password_hash)) {
                        $success_message = 'Your password has been successfully updated.';
                        $current_password = '';
                        $new_password = '';
                        $confirm_password = '';
                    } else {
                        $errors['general'] = 'Failed to update password. Please try again.';
                    }
                } else {
                    $errors['current_password'] = 'Current password is incorrect';
                }
            } else {
                $errors['general'] = 'User not found. Please log in again.';
            }
        } else {
            $errors['general'] = 'Please log in to access this page.';
        }
    }
}

// Pre-fill email from session if empty
if ($email === '' && isLoggedIn()) {
    $email = getCurrentUserEmail();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Apex Diurnal Solar Panels</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=5">
    <style>
        :root {
            --color-navy: #1b335f;
            --color-navy-dark: #122444;
            --color-yellow: #fee000;
            --color-yellow-hover: #e5ca00;
            --color-border: rgba(27, 51, 95, 0.12);
            --color-text-muted: #576579;
        }
        body {
            background: radial-gradient(circle at 50% 10%, rgba(254,224,0,0.08), transparent 45%),
                        linear-gradient(145deg, #f3f6fa 0%, #e9edf5 50%, #f7f9fc 100%);
            min-height: 100vh;
        }
        .settings-page {
            padding: 110px 20px 60px;
            min-height: calc(100vh - 200px);
        }
        .settings-wrapper {
            max-width: 640px;
            margin: 0 auto;
        }
        .settings-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .settings-avatar-large {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--color-navy);
            color: var(--color-yellow);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0 auto 16px;
            border: 3px solid var(--color-yellow);
            box-shadow: 0 8px 24px rgba(27,51,95,0.15);
        }
        .settings-title {
            font-size: 1.9rem;
            font-weight: 800;
            color: var(--color-navy);
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }
        .settings-subtitle {
            color: var(--color-text-muted);
            font-size: 0.95rem;
        }
        .settings-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 20px 45px -10px rgba(27,51,95,0.12), 0 0 0 1px rgba(27,51,95,0.06);
            margin-bottom: 24px;
        }
        .settings-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(27,51,95,0.08);
        }
        .settings-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(27,51,95,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-navy);
        }
        .settings-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--color-navy);
        }
        .settings-card-desc {
            font-size: 0.85rem;
            color: var(--color-text-muted);
            margin-top: 2px;
        }
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #22c55e;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.9rem;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .alert-error div + div { margin-top: 4px; }
        .form-group { margin-bottom: 18px; }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--color-navy);
        }
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            color: #788aa3;
            pointer-events: none;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px 12px 42px;
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--color-navy);
            background: #ffffff;
            border: 2px solid rgba(27,51,95,0.12);
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        .form-control:read-only {
            background: #f8fafc;
            color: #64748b;
            cursor: not-allowed;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--color-yellow);
            box-shadow: 0 0 0 4px rgba(254,224,0,0.25);
        }
        .btn-settings {
            width: 100%;
            padding: 14px 24px;
            background: var(--color-yellow);
            color: var(--color-navy);
            border: none;
            border-radius: 50px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(254,224,0,0.4);
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-settings:hover {
            background: var(--color-yellow-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(254,224,0,0.5);
        }
        .account-email-display {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: #f8fafc;
            border: 1px solid rgba(27,51,95,0.06);
            border-radius: 12px;
            font-weight: 600;
            color: var(--color-navy);
        }
        .account-email-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--color-navy);
            color: var(--color-yellow);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        @media (max-width: 640px) {
            .settings-card { padding: 24px 20px; border-radius: 20px; }
            .settings-page { padding-top: 90px; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="site-header" id="site-header">
        <div class="header-container">
            <a href="index.php#home" class="brand-logo" aria-label="Apex Diurnal Home">
                <img src="assets/images/logo.png" alt="Apex Diurnal Logo" class="logo-mark">
                <div class="brand-text">
                    <span class="brand-title">APEX</span>
                    <span class="brand-subtitle">DIURNAL</span>
                </div>
            </a>

            <nav class="main-nav" aria-label="Main Navigation">
                <ul class="nav-list">
                    <li>
                        <a href="index.php#home" class="nav-link">Home</a>
                    </li>
                    <li>
                        <a href="index.php#products" class="nav-link">Products</a>
                    </li>
                    <li>
                        <a href="index.php#services" class="nav-link">Services</a>
                    </li>
                    <li>
                        <a href="index.php#about" class="nav-link">About Us</a>
                    </li>
                    <li>
                        <a href="index.php#contact" class="nav-link">Contact</a>
                    </li>
                </ul>
            </nav>

            <div class="header-actions">
                <div class="search-container" id="search-container">
                    <button type="button" class="icon-btn" aria-label="Search" id="search-toggle" aria-expanded="false"
                        aria-controls="search-bar">
                        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                    <div class="search-bar" id="search-bar" role="search">
                        <input type="search" class="search-input" id="search-input" placeholder="Search"
                            aria-label="Search products and navigation" autocomplete="off">
                        <button type="button" class="search-close" id="search-close" aria-label="Close search">×</button>
                    </div>
                    <div class="search-results" id="search-results" aria-live="polite"></div>
                </div>
                <button type="button" class="icon-btn cart-btn" id="cart-trigger" aria-label="Shopping Cart">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2-1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <span class="cart-badge" id="cart-count" data-count="<?php echo cart_item_count(); ?>" <?php if (cart_item_count() === 0)
                         echo 'style="display:none"'; ?>><?php echo cart_item_count(); ?></span>
                </button>
                <?php if (isLoggedIn()): ?>
                    <div class="user-dropdown" id="user-dropdown">
                        <button type="button" class="user-avatar-btn" aria-label="User Account" id="user-account-btn" title="<?php echo htmlspecialchars(getCurrentUserEmail()); ?>">
                            <span class="user-avatar"><?php echo htmlspecialchars(strtoupper(substr(getCurrentUserEmail() ?? 'U', 0, 1))); ?></span>
                        </button>
                        <div class="user-dropdown-menu" id="user-dropdown-menu">
                            <a href="settings.php" class="user-dropdown-item">Settings</a>
                            <a href="logout.php" class="user-dropdown-item">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="header-login-btn" aria-label="Log In">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" aria-hidden="true">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                        Log In
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="settings-page">
        <div class="settings-wrapper">
            <!-- Header with avatar -->
            <div class="settings-header">
                <div class="settings-avatar-large"><?php echo htmlspecialchars(strtoupper(substr($email ?? 'U', 0, 1))); ?></div>
                <h1 class="settings-title">Settings</h1>
                <p class="settings-subtitle">Manage your account and security</p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <div><?php echo htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Account Card -->
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </div>
                    <div>
                        <div class="settings-card-title">Account</div>
                        <div class="settings-card-desc">Your personal information</div>
                    </div>
                </div>
                <div class="account-email-display">
                    <div class="account-email-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    </div>
                    <div>
                        <div style="font-size:0.75rem; color:var(--color-text-muted); font-weight:600; letter-spacing:0.5px; text-transform:uppercase;">Email Address</div>
                        <div style="font-size:1rem;"><?php echo htmlspecialchars($email); ?></div>
                    </div>
                    <span style="margin-left:auto; font-size:0.75rem; background:rgba(34,197,94,0.12); color:#166534; padding:4px 10px; border-radius:50px; font-weight:700;">Verified</span>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="settings-card" id="change-password">
                <div class="settings-card-header">
                    <div class="settings-card-icon" style="background:rgba(254,224,0,0.18); color:var(--color-navy);">
                        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </div>
                    <div>
                        <div class="settings-card-title">Change Password</div>
                        <div class="settings-card-desc">Ensure your account is using a strong password</div>
                    </div>
                </div>

                <form method="POST" action="settings.php#change-password">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password</label>
                        <div class="input-group">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Enter current password" value="<?php echo htmlspecialchars($current_password); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password <span style="font-weight:400; color:var(--color-text-muted);">(min 6 characters)</span></label>
                        <div class="input-group">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Enter new password" value="<?php echo htmlspecialchars($new_password); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat new password" value="<?php echo htmlspecialchars($confirm_password); ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-settings">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        Update Password
                    </button>
                </form>
            </div>

            <div style="text-align:center; margin-top:16px;">
                <a href="index.php" style="color:var(--color-navy); font-weight:600; text-decoration:none; font-size:0.9rem;">← Return to Homepage</a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer" id="contact">
        <div class="container">
            <div class="footer-grid">
                <!-- Brand Column -->
                <div class="footer-brand-col">
                    <div class="footer-logo">
                        <img src="assets/images/FOOTER.jpg" alt="Apex Diurnal Logo" class="footer-logo-mark">
                    </div>
                    <div class="social-links">
                        <a href="javascript:void(0)" onclick="return false;" class="social-btn" aria-label="Facebook">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="var(--color-yellow)">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                            </svg>
                        </a>
                        <a href="javascript:void(0)" onclick="return false;" class="social-btn" aria-label="Instagram">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="var(--color-yellow)" stroke-width="2">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                            </svg>
                        </a>
                        <a href="javascript:void(0)" onclick="return false;" class="social-btn" aria-label="X (Twitter)">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="var(--color-yellow)">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99
                                     21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161
                                     17.52h1.833L7.084 4.126H5.117z" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Menu Links Column -->
                <div class="footer-col">
                    <h4 class="footer-heading">MENU</h4>
                    <ul class="footer-links">
                        <li><a href="index.php#home">Home</a></li>
                        <li><a href="index.php#products">Products</a></li>
                        <li><a href="index.php#services">Services</a></li>
                        <li><a href="index.php#about">About us</a></li>
                        <li><a href="index.php#contact">Contact us</a></li>
                    </ul>
                </div>

                <!-- Legalities Column -->
                <div class="footer-col">
                    <h4 class="footer-heading">LEGALITIES</h4>
                    <ul class="footer-links">
                        <li><a href="javascript:void(0)" onclick="return false;">Copyright Notice</a></li>
                        <li><a href="javascript:void(0)" onclick="return false;">Privacy Policy</a></li>
                        <li><a href="javascript:void(0)" onclick="return false;">Terms of Service / Conditions</a></li>
                        <li><a href="javascript:void(0)" onclick="return false;">Disclaimers</a></li>
                        <li><a href="javascript:void(0)" onclick="return false;">Accessibility Statement</a></li>
                    </ul>
                </div>

                <!-- Contact Column -->
                <div class="footer-col">
                    <h4 class="footer-heading">CONTACT</h4>
                    <div class="contact-info">
                        <p><strong>Phone:</strong> 09561973910</p>
                        <p><strong>Email:</strong> danielalfeche2006@gmail.com</p>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy;<?php echo date('Y'); ?> Apex Diurnal. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Cart Drawer -->
    <div class="cart-overlay" id="cart-overlay"></div>
    <aside class="cart-drawer" id="cart-drawer" aria-label="Shopping Cart" aria-hidden="true">
        <div class="cart-drawer-header">
            <h3>Your Cart <span class="cart-drawer-count" id="cart-drawer-count"><?php echo cart_item_count(); ?>
                item(s)</span></h3>
            <button type="button" class="cart-close" id="cart-close" aria-label="Close cart">&times;</button>
        </div>
        <div class="cart-drawer-body" id="cart-items">
            <!-- JS renders cart items here -->
        </div>
        <div class="cart-drawer-footer">
            <div class="cart-total-row">
                <span>Total</span>
                <strong id="cart-total">&#8369;<?php echo number_format(cart_total($products), 2); ?></strong>
            </div>
            <?php $has_cart_items = (cart_item_count() > 0); ?>
            <div class="cart-footer-actions">
                <button type="button" class="btn btn-outline btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>" id="cart-clear" aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>">Clear Cart</button>
                <button type="button" class="btn btn-yellow btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>" id="cart-checkout" aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>" title="<?php echo !$has_cart_items ? 'Your cart is empty. Please add items before checking out.' : 'Proceed to Checkout'; ?>">Checkout</button>
            </div>
            <p class="cart-empty-hint" id="cart-empty-hint"
                style="display:none; text-align:center; margin-top:12px; font-size:0.85rem; color:var(--color-text-muted);">Your
                cart is empty.</p>
        </div>
    </aside>

    <!-- Toast -->
    <div id="cart-toast" class="cart-toast" role="status" aria-live="polite"></div>

    <script>
        // --- Cart state from PHP ---
        const CART_INITIAL = <?php echo json_encode([
          'itemCount' => cart_item_count(),
          'grandTotal' => number_format(cart_total($products), 2),
          'items' => array_values(array_map(function ($item, $id) {
          return [
            'id' => $id,
            'title' => $item['title'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'line_total' => number_format($item['line_total'], 2),
            'image' => $item['image'],
            'alt' => $item['alt']
          ];
        }, get_cart_items($products), array_keys(get_cart_items($products))))
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
  <script>
    // Inline fallback for user dropdown - ensures logout is clickable even if main.js fails
    (function(){
      function initFallbackDropdown(){
        var btn = document.getElementById('user-account-btn');
        var menu = document.getElementById('user-dropdown-menu');
        var dropdown = document.getElementById('user-dropdown');
        if(!btn || !menu) return;
        if(btn.dataset.fallbackBound) return;
        btn.dataset.fallbackBound = '1';
        btn.addEventListener('click', function(e){
          e.stopPropagation();
          e.preventDefault();
          menu.classList.toggle('show');
        });
        document.addEventListener('click', function(e){
          if(dropdown && !dropdown.contains(e.target)){
            menu.classList.remove('show');
          }
        });
      }
      if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', initFallbackDropdown);
      } else {
        initFallbackDropdown();
      }
      window.toggleUserDropdown = window.toggleUserDropdown || function(){
        var m = document.getElementById('user-dropdown-menu');
        if(m) m.classList.toggle('show');
      };
    })();
  </script>

    <script src="js/main.js?v=3" defer></script>
</body>
</html>