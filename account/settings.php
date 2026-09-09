<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../User.php';
require_once __DIR__ . '/../product_data.php';
require_once __DIR__ . '/../cart/cart_functions.php';
require_once __DIR__ . '/../validation.php';
require_once __DIR__ . '/../philippine_locations.php';

// Require login
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = getConnection();
$user_id = getCurrentUserId();
$currentUser = new User($db);
$currentUser->findById($user_id);

// Cart hydration & notification gathering for active session
$cart_notices = [];
if (!empty($_SESSION['cart_notifications']) && is_array($_SESSION['cart_notifications'])) {
    $cart_notices = array_merge($cart_notices, $_SESSION['cart_notifications']);
    unset($_SESSION['cart_notifications']);
}
try {
    require_once __DIR__ . '/../Cart.php';
    $cartModel = new Cart($db);
    $hydration = $cartModel->validateAndHydrate((int) $user_id);
    if (!empty($hydration['notices'])) {
        $cart_notices = array_merge($cart_notices, $hydration['notices']);
    }
} catch (Exception $e) {
    error_log('[settings] Cart hydration error: ' . $e->getMessage());
}

$email = $currentUser->email;
$current_password = '';
$new_password = '';
$confirm_password = '';

$profile_errors = [];
$profile_success_message = '';
$password_errors = [];
$password_success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $validator = new Validator($_POST);
        $validator->required([
            'full_name' => 'Full Name is required.',
            'phone' => 'Phone number is required.',
            'street_address' => 'Street address is required.',
            'province' => 'Please select a province.',
            'city' => 'Please select a city or municipality.',
            'postal_code' => 'Postal code is required.'
        ]);
        $validator->minLength('full_name', 3, 'Full Name must be at least 3 characters.');
        $validator->pattern('full_name', "/^[a-zA-Z\s\.\'\-]+$/", 'Full Name contains invalid characters.');
        $validator->pattern('phone', '/^\d{11}$/', 'Please enter a valid 11-digit mobile number (e.g., 09171234567).');
        $validator->minLength('street_address', 5, 'Street address must be at least 5 characters.');
        $validator->in('province', $consult_provinces, 'Invalid province selected.');

        $clean = $validator->sanitized();
        if (!empty($clean['province']) && isset($consultProvinceCityMap[$clean['province']])) {
            if (!checkCityMatchesProvince($clean['city'], $clean['province'], $consultProvinceCityMap)) {
                $validator->addError('city', ($clean['city'] !== '' ? $clean['city'] : 'City') . ' does not belong to ' . $clean['province'] . '.');
            }
        }
        $validator->pattern('postal_code', '/^\d{4}$/', 'Please enter a valid 4-digit postal code.');

        $profile_errors = $validator->errors();
        if (empty($profile_errors)) {
            if ($currentUser->updateProfile($clean)) {
                $profile_success_message = 'Your profile has been successfully saved!';
                $currentUser->findById($user_id); // Refresh user data
            } else {
                $profile_errors['general'] = 'Failed to update profile. Please try again.';
            }
        }
    } elseif ($action === 'change_password' || isset($_POST['current_password'])) {
        $validator = new Validator($_POST);
        $validator->required([
            'current_password' => 'Current password is required',
            'new_password' => 'New password is required',
            'confirm_password' => 'Please confirm your new password'
        ]);
        $validator->minLength('new_password', 6, 'New password must be at least 6 characters');
        $validator->matches('new_password', 'confirm_password', 'New passwords do not match');

        $password_errors = $validator->errors();
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($password_errors)) {
            if ($currentUser->validatePassword($current_password)) {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                if ($currentUser->updatePassword($new_password_hash)) {
                    $password_success_message = 'Your password has been successfully updated.';
                    $current_password = '';
                    $new_password = '';
                    $confirm_password = '';
                } else {
                    $password_errors['general'] = 'Failed to update password. Please try again.';
                }
            } else {
                $password_errors['current_password'] = 'Current password is incorrect';
            }
        }
    }
}

$profile = $currentUser->getProfile();
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
    <link rel="stylesheet" href="../styles.css?v=5">
    <link rel="stylesheet" href="account.css?v=<?php echo filemtime(__DIR__ . '/account.css'); ?>">
    <link rel="stylesheet"
        href="../assets/css/order-history.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/order-history.css'); ?>">
    <link rel="stylesheet" href="../cart/cart.css">
</head>

<body>
    <!-- Header -->
    <header class="site-header" id="site-header">
        <div class="header-container">
            <a href="../index.php#home" class="brand-logo" aria-label="Apex Diurnal Home">
                <img src="../assets/images/logo-clean.png?v=<?php echo filemtime(__DIR__ . '/../assets/images/logo-clean.png'); ?>"
                    alt="Apex Diurnal Logo" class="logo-mark">
                <div class="brand-text">
                    <span class="brand-title">APEX</span>
                    <span class="brand-subtitle">DIURNAL</span>
                </div>
            </a>

            <nav class="main-nav" aria-label="Main Navigation">
                <ul class="nav-list">
                    <li>
                        <a href="../index.php#home" class="nav-link">Home</a>
                    </li>
                    <li>
                        <a href="../index.php#products" class="nav-link">Products</a>
                    </li>
                    <li>
                        <a href="../index.php#services" class="nav-link">Services</a>
                    </li>
                    <li>
                        <a href="../index.php#about" class="nav-link">About Us</a>
                    </li>
                    <li>
                        <a href="../index.php#contact" class="nav-link">Contact</a>
                    </li>
                </ul>
            </nav>

            <div class="header-actions">
                <div class="search-container" id="search-container">
                    <button type="button" class="icon-btn" aria-label="Search" id="search-toggle" aria-expanded="false"
                        aria-controls="search-bar">
                        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2"
                            fill="none">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                    <div class="search-bar" id="search-bar" role="search">
                        <input type="search" class="search-input" id="search-input" placeholder="Search"
                            aria-label="Search products and navigation" autocomplete="off">
                        <button type="button" class="search-close" id="search-close"
                            aria-label="Close search">×</button>
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
                        <button type="button" class="user-avatar-btn" aria-label="User Account" id="user-account-btn"
                            title="<?php echo htmlspecialchars(getCurrentUserEmail()); ?>">
                            <span
                                class="user-avatar"><?php echo htmlspecialchars(strtoupper(substr(getCurrentUserEmail() ?? 'U', 0, 1))); ?></span>
                        </button>
                        <div class="user-dropdown-menu" id="user-dropdown-menu">
                            <a href="settings.php" class="user-dropdown-item">Settings</a>
                            <a href="orders/index.php" class="user-dropdown-item">My Orders</a>
                            <a href="../auth/logout.php" class="user-dropdown-item">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="../auth/login.php" class="header-login-btn" aria-label="Log In">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"
                            aria-hidden="true">
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
            <!-- Account Navigation Tabs -->
            <div style="text-align: center; margin-bottom: 24px;">
                <div class="account-nav-tabs" role="tablist" aria-label="Account Tabs">
                    <a href="settings.php" class="account-nav-tab active">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Settings & Security
                    </a>
                    <a href="orders/index.php" class="account-nav-tab">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-2z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                        Order History
                    </a>
                </div>
            </div>

            <!-- Header -->
            <div class="settings-header">
                <h1 class="settings-title">Settings</h1>
                <p class="settings-subtitle">Manage your account and security</p>
            </div>

            <?php if (!empty($profile_success_message)): ?>
                <div class="alert-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <div><?php echo htmlspecialchars($profile_success_message); ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($profile_errors)): ?>
                <div class="alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>
                        <?php foreach ($profile_errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Account Card -->
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2"
                            fill="none">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div>
                        <div class="settings-card-title">Account</div>
                    </div>
                </div>
                <div class="account-email-display">
                    <div class="account-email-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2"
                            fill="none">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                            </path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div
                            style="font-size:0.75rem; color:var(--color-text-muted); font-weight:600; letter-spacing:0.5px; text-transform:uppercase;">
                            Email Address</div>
                        <div style="font-size:1rem;"><?php echo htmlspecialchars($email); ?></div>
                    </div>
                    <span
                        style="margin-left:auto; font-size:0.75rem; background:rgba(34,197,94,0.12); color:#166534; padding:4px 10px; border-radius:50px; font-weight:700;">Verified</span>
                </div>
            </div>

            <!-- Profile Card -->
            <div class="settings-card" id="profile-details">
                <div class="settings-card-header">
                    <div class="settings-card-icon" style="background:rgba(27,51,95,0.08); color:var(--color-navy);">
                        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2"
                            fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"></rect>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                            <circle cx="5.5" cy="18.5" r="2.5"></circle>
                            <circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                    </div>
                    <div>
                        <div class="settings-card-title">Profile</div>
                    </div>
                </div>

                <form method="POST" action="settings.php#profile-details">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="profile_full_name" class="form-label">Full Name <span
                                    style="color:#dc2626;">*</span></label>
                            <div class="input-group">
                                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <input type="text" id="profile_full_name" name="full_name" class="form-control"
                                    placeholder="e.g. Juan dela Cruz"
                                    value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="profile_phone" class="form-label">Phone Number <span
                                    style="color:#dc2626;">*</span></label>
                            <div class="input-group">
                                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path
                                        d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z">
                                    </path>
                                </svg>
                                <input type="tel" id="profile_phone" name="phone" class="form-control"
                                    placeholder="e.g. 09171234567"
                                    value="<?php echo htmlspecialchars(substr(preg_replace('/\D/', '', $profile['phone'] ?? ''), 0, 11)); ?>"
                                    maxlength="11" inputmode="numeric" pattern="[0-9]{11}"
                                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,11);" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="profile_province" class="form-label">Province <span
                                    style="color:#dc2626;">*</span></label>
                            <div class="input-group">
                                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon>
                                    <line x1="8" y1="2" x2="8" y2="18"></line>
                                    <line x1="16" y1="6" x2="16" y2="22"></line>
                                </svg>
                                <select id="profile_province" name="province" class="form-control form-select" required>
                                    <option value="">Select Province</option>
                                    <?php foreach ($consult_provinces as $p): ?>
                                        <option value="<?php echo htmlspecialchars($p); ?>" <?php echo (($profile['province'] ?? '') === $p) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="profile_city" class="form-label">City / Municipality <span
                                    style="color:#dc2626;">*</span></label>
                            <div class="input-group">
                                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path>
                                </svg>
                                <select id="profile_city" name="city" class="form-control form-select" required>
                                    <option value="">
                                        <?php echo !empty($profile['province']) ? 'Select City / Municipality' : 'Select Province First'; ?>
                                    </option>
                                    <?php
                                    $curr_prov = $profile['province'] ?? '';
                                    $available_cities = (!empty($curr_prov) && isset($consultProvinceCityMap[$curr_prov])) ? $consultProvinceCityMap[$curr_prov] : [];
                                    foreach ($available_cities as $c):
                                        ?>
                                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo (($profile['city'] ?? '') === $c) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="profile_street_address" class="form-label">Street Address / House No. / Barangay
                            <span style="color:#dc2626;">*</span></label>
                        <div class="input-group">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <input type="text" id="profile_street_address" name="street_address" class="form-control"
                                placeholder="e.g. Unit 4B, 123 Solar Avenue, Brgy. San Isidro"
                                value="<?php echo htmlspecialchars($profile['street_address'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="profile_postal_code" class="form-label">Postal Code <span
                                style="color:#dc2626;">*</span></label>
                        <div class="input-group" style="max-width: 280px;">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <input type="text" id="profile_postal_code" name="postal_code" class="form-control"
                                placeholder="e.g. 1000" maxlength="4" pattern="\d{4}" inputmode="numeric"
                                value="<?php echo htmlspecialchars($profile['postal_code'] ?? ''); ?>" required
                                oninput="this.value=this.value.replace(/\D/g,'').slice(0,4)">
                        </div>
                    </div>

                    <button type="submit" class="btn-settings" style="margin-top:12px;">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        Save Profile
                    </button>
                </form>
            </div>

            <!-- Change Password Card -->
            <div class="settings-card" id="change-password">
                <div class="settings-card-header">
                    <div class="settings-card-icon" style="background:rgba(254,224,0,0.18); color:var(--color-navy);">
                        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2"
                            fill="none">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="settings-card-title">Change Password</div>
                    </div>
                </div>

                <form method="POST" action="settings.php#change-password">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password <span
                                style="color:#dc2626;">*</span></label>
                        <div class="input-group">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <input type="password" id="current_password" name="current_password"
                                class="form-control <?php echo isset($password_errors['current_password']) ? 'has-error' : ''; ?>"
                                placeholder="Enter current password"
                                value="<?php echo htmlspecialchars($current_password); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password <span
                                style="font-weight:400; color:var(--color-text-muted);">(min 6 characters)</span> <span
                                style="color:#dc2626;">*</span></label>
                        <div class="input-group">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <input type="password" id="new_password" name="new_password"
                                class="form-control <?php echo isset($password_errors['new_password']) ? 'has-error' : ''; ?>"
                                placeholder="Enter new password" value="<?php echo htmlspecialchars($new_password); ?>"
                                required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password <span
                                style="color:#dc2626;">*</span></label>
                        <div class="input-group">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <input type="password" id="confirm_password" name="confirm_password"
                                class="form-control <?php echo isset($password_errors['confirm_password']) ? 'has-error' : ''; ?>"
                                placeholder="Repeat new password"
                                value="<?php echo htmlspecialchars($confirm_password); ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-settings" id="btn-update-password">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        Update Password
                    </button>

                    <!-- Error and feedback handling at the bottom of the Change Password feature -->
                    <?php if (!empty($password_errors)): ?>
                        <div class="alert-error password-feedback" id="password-error-box" role="alert">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <div>
                                <?php foreach ($password_errors as $error): ?>
                                    <div><?php echo htmlspecialchars($error); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($password_success_message)): ?>
                        <div class="alert-success password-feedback" id="password-success-box" role="alert">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <div><?php echo htmlspecialchars($password_success_message); ?></div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <div style="text-align:center; margin-top:20px;">
                <a href="../index.php" class="return-home-link">
                    <span class="link-arrow">&larr;</span> Return to Homepage
                </a>
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
                        <img src="../assets/images/FOOTER.jpg" alt="Apex Diurnal Logo" class="footer-logo-mark">
                    </div>
                    <div class="social-links">
                        <a href="javascript:void(0)" onclick="return false;" class="social-btn" aria-label="Facebook">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="var(--color-yellow)">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                            </svg>
                        </a>
                        <a href="javascript:void(0)" onclick="return false;" class="social-btn" aria-label="Instagram">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="var(--color-yellow)"
                                stroke-width="2">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                            </svg>
                        </a>
                        <a href="javascript:void(0)" onclick="return false;" class="social-btn"
                            aria-label="X (Twitter)">
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
                        <li><a href="../index.php#home">Home</a></li>
                        <li><a href="../index.php#products">Products</a></li>
                        <li><a href="../index.php#services">Services</a></li>
                        <li><a href="../index.php#about">About us</a></li>
                        <li><a href="../index.php#contact">Contact us</a></li>
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
                <button type="button"
                    class="btn btn-outline btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>" id="cart-clear"
                    aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>">Clear Cart</button>
                <button type="button" class="btn btn-yellow btn-block <?php echo !$has_cart_items ? 'disabled' : ''; ?>"
                    id="cart-checkout" aria-disabled="<?php echo !$has_cart_items ? 'true' : 'false'; ?>"
                    title="<?php echo !$has_cart_items ? 'Your cart is empty. Please add items before checking out.' : 'Proceed to Checkout'; ?>">Checkout</button>
            </div>
            <p class="cart-empty-hint" id="cart-empty-hint"
                style="display:none; text-align:center; margin-top:12px; font-size:0.85rem; color:var(--color-text-muted);">
                Your
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
            $img = $item['image'] ?? 'assets/images/logo-clean.png';
            if (!empty($img) && strpos($img, 'http') !== 0 && strpos($img, '/') !== 0 && strpos($img, '../') !== 0) {
                $img = '../' . $img;
            }
            return [
                'id' => $id,
                'title' => $item['title'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'line_total' => number_format($item['line_total'], 2),
                'image' => $img,
                'alt' => $item['alt']
            ];
        }, get_cart_items($products), array_keys(get_cart_items($products)))),
            'notices' => $cart_notices
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <script>
        window.provinceCityMap = <?php echo json_encode($consultProvinceCityMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <script src="../cart/cart.js?v=<?php echo filemtime(__DIR__ . '/../cart/cart.js'); ?>" defer></script>
    <script src="account.js?v=<?php echo filemtime(__DIR__ . '/account.js'); ?>" defer></script>
    <script src="../js/main.js" defer></script>
</body>

</html>