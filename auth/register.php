<?php
require_once __DIR__ . '/../db.php';
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
$confirm_password = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Single centralized validation & sanitization
    $validator = new Validator($_POST);
    $validator->required([
        'email'            => 'Email is required.',
        'password'         => 'Password is required.',
        'confirm_password' => 'Please confirm your password.'
    ]);
    $validator->email('email', 'Please enter a valid email address.');
    $validator->minLength('password', 8, 'Password must be at least 8 characters.');
    $validator->matches('password', 'confirm_password', 'Passwords do not match.');

    $errors = $validator->errors();
    $clean = $validator->sanitized();
    $email = $clean['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // If initial validation passes, check uniqueness and proceed
    if (empty($errors)) {
        $database = new Database();
        $db = $database->getConnection();
        if (!$db) {
            $errors['general'] = 'Database connection error. Please try again later.';
        } else {
            $user = new User($db);

            // Check if email already exists
            if ($user->findByEmail($email)) {
                $errors['email'] = 'Email already registered. Please sign in or use another email.';
            } else {
                // Securely hash password
                $user->email = $email;
                $user->password_hash = password_hash($password, PASSWORD_DEFAULT);

                if ($user->create()) {
                    // Auto-login user
                    loginUser($user->id, $user->email);

                    // Redirect to intended page or homepage
                    $redirect = getRedirectAfterLogin();
                    if ($redirect) {
                        header('Location: ' . $redirect);
                    } else {
                        header('Location: ../index.php');
                    }
                    exit;
                } else {
                    $errors['general'] = 'Failed to create your account. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | Apex Diurnal Solar Panels</title>
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
                <h1 class="login-title">Create an Account</h1>
                <p class="login-subtitle">Sign up to get started with Apex Diurnal Solar Panels</p>
            </div>

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

            <form action="register.php" method="POST" autocomplete="on">
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
                            placeholder="e.g. yourname@example.com"
                            value="<?php echo htmlspecialchars($email); ?>" 
                            required 
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-label-row">
                        <label for="password" class="form-label">Password</label>
                        <span class="form-hint">Min. 8 characters</span>
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
                            placeholder="Create a password"
                            style="padding-right: 44px;"
                            required
                            minlength="8"
                        >
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Show or hide password">
                            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-label-row">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                    </div>
                    <div class="input-group">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-control" 
                            placeholder="Confirm your password"
                            style="padding-right: 44px;"
                            required
                            minlength="8"
                        >
                        <button type="button" class="password-toggle" id="toggleConfirmPassword" aria-label="Show or hide password confirmation">
                            <svg id="eyeIconConfirm" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-signin">
                    <span>Sign Up</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>

            <div class="card-footer">
                <p>Already have an account? <a href="login.php">Sign In</a></p>
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
