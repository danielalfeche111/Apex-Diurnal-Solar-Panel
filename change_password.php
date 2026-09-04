<?php
require_once 'db.php';
require_once 'User.php';
require_once 'auth.php';

// Require login
requireLogin();

$email = '';
$current_password = '';
$new_password = '';
$confirm_password = '';
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation
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

    // If no validation errors, verify current password and update
    if (empty($errors)) {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);

        // Get current user ID from session
        $user_id = getCurrentUserId();
        if ($user_id) {
            $user->id = $user_id;
            if ($user->findById($user_id)) {
                // Verify current password
                if ($user->validatePassword($current_password)) {
                    // Update password
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    if ($user->updatePassword($new_password_hash)) {
                        $success_message = 'Your password has been successfully updated.';
                        // Clear form fields for security
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

// Get user email for display (from session or form)
if ($email === '' && isLoggedIn()) {
    $email = getCurrentUserEmail();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | Apex Diurnal Solar Panels</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --color-navy: #1b335f;
            --color-navy-dark: #122444;
            --color-navy-light: #25447c;
            --color-yellow: #fee000;
            --color-yellow-hover: #e5ca00;
            --color-border: rgba(27, 51, 95, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: radial-gradient(circle at 50% 10%, rgba(254, 224, 0, 0.08), transparent 45%),
                        linear-gradient(145deg, #f3f6fa 0%, #e9edf5 50%, #f7f9fc 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: var(--color-navy);
        }

        .top-nav {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 24px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-home-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--color-navy);
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            border: 1px solid var(--color-border);
            box-shadow: 0 2px 8px rgba(27, 51, 95, 0.05);
            transition: all 0.2s ease;
        }

        .back-home-link:hover {
            background: #ffffff;
            transform: translateX(-3px);
            box-shadow: 0 4px 12px rgba(27, 51, 95, 0.1);
        }

        .auth-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }

        .reset-card {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 24px;
            padding: 40px 36px;
            box-shadow: 0 20px 45px -10px rgba(27, 51, 95, 0.12),
                        0 0 0 1px rgba(27, 51, 95, 0.06);
            animation: cardAppear 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(16px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .card-header {
            text-align: center;
            margin-bottom: 26px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--color-navy);
            border-radius: 30px;
            margin-bottom: 16px;
            box-shadow: 0 4px 12px rgba(27, 51, 95, 0.15);
        }

        .brand-badge-title {
            font-size: 0.85rem;
            font-weight: 800;
            letter-spacing: 2px;
            color: var(--color-yellow);
        }

        .brand-badge-sub {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 2px;
            color: #ffffff;
            opacity: 0.9;
        }

        .card-title {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--color-navy);
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .card-subtitle {
            font-size: 0.9rem;
            color: #576579;
            line-height: 1.4;
        }

        .error-alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 22px;
            color: #991b1b;
            font-size: 0.88rem;
            line-height: 1.4;
        }

        .error-alert svg {
            flex-shrink: 0;
            margin-top: 2px;
            color: #ef4444;
        }

        .success-alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #22c55e;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 22px;
            color: #166534;
            font-size: 0.88rem;
            line-height: 1.4;
        }

        .success-alert svg {
            flex-shrink: 0;
            margin-top: 2px;
            color: #22c55e;
        }

        .form-group {
            margin-bottom: 18px;
        }

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
            background-color: #ffffff;
            border: 2px solid rgba(27, 51, 95, 0.12);
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .form-control:read-only {
            background-color: #f8fafc;
            color: #64748b;
            border-color: rgba(27, 51, 95, 0.08);
            cursor: not-allowed;
        }

        .form-control:focus:not(:read-only) {
            outline: none;
            border-color: var(--color-yellow);
            box-shadow: 0 0 0 4px rgba(254, 224, 0, 0.25);
        }

        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-yellow {
            flex: 2;
            padding: 14px 20px;
            background-color: var(--color-yellow);
            color: var(--color-navy);
            border: none;
            border-radius: 50px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(254, 224, 0, 0.45);
            transition: all 0.2s ease;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-yellow:hover {
            background-color: var(--color-yellow-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(254, 224, 0, 0.55);
        }

        .btn-outline {
            flex: 1;
            padding: 14px 18px;
            background: #f1f5f9;
            color: #475569;
            border-radius: 50px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-outline:hover {
            background: #e2e8f0;
            color: var(--color-navy);
        }

        .card-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 0.85rem;
            color: #64748b;
            border-top: 1px solid rgba(27, 51, 95, 0.06);
            padding-top: 20px;
        }

        .card-footer a {
            color: var(--color-navy);
            font-weight: 600;
            text-decoration: none;
        }

        .card-footer a:hover {
            text-decoration: underline;
        }

        .page-footer {
            text-align: center;
            padding: 18px 24px;
            font-size: 0.8rem;
            color: #7b8a9e;
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <a href="index.php" class="back-home-link" title="Return to Homepage">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Back to Home</span>
        </a>
    </div>

    <div class="auth-wrapper">
        <div class="reset-card">
            <div class="card-header">
                <img src="assets/images/logo.png" alt="Apex Diurnal Logo" class="logo-mark">
                <h1 class="card-title">Change Password</h1>
                <p class="card-subtitle">Update your credentials to keep your account secure</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-alert" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="success-alert" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <div><?php echo htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <form action="change_password.php" method="POST">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password</label>
                    <div class="input-group">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Enter current password" value="<?php echo htmlspecialchars($current_password); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password" class="form-label">New Password (min 6 chars)</label>
                    <div class="input-group">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Enter new password" value="<?php echo htmlspecialchars($new_password); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat new password" value="<?php echo htmlspecialchars($confirm_password); ?>" required>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-yellow">Update Password</button>
                    <a href="index.php" class="btn-outline">Cancel</a>
                </div>
            </form>

            <div class="card-footer">
                <p><a href="index.php">← Return to Homepage</a></p>
            </div>
        </div>
    </div>

    <footer class="page-footer">
        &copy; <?php echo date('Y'); ?> Apex Diurnal Solar Panels. Clean Energy &bull; Intelligent Future.
    </footer>
</body>
</html>