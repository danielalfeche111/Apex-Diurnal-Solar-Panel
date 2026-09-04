<?php
require_once 'db.php';
require_once 'User.php';
require_once 'auth.php';

// If user is already logged in, redirect to homepage
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$email = '';
$password = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if ($email === '') {
        $errors['email'] = 'Email is required';
    }
    if ($password === '') {
        $errors['password'] = 'Password is required';
    }

    // If no validation errors, try to log in
    if (empty($errors)) {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);

        $user->email = $email;
        if ($user->findByEmail($email)) {
            // Validate password
            if ($user->validatePassword($password)) {
                // Login successful
                loginUser($user->id, $user->email);

                // Redirect to intended page or homepage
                $redirect = getRedirectAfterLogin();
                if ($redirect) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: index.php');
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
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --color-navy: #1b335f;
            --color-navy-dark: #122444;
            --color-navy-light: #25447c;
            --color-yellow: #fee000;
            --color-yellow-hover: #e5ca00;
            --color-grey-light: #f5f6f8;
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
            position: relative;
        }

        /* Top floating back link */
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
            color: var(--color-navy-dark);
            transform: translateX(-3px);
            box-shadow: 0 4px 12px rgba(27, 51, 95, 0.1);
        }

        /* Main center container */
        .auth-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
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

        .login-header {
            text-align: center;
            margin-bottom: 28px;
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

        .login-title {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--color-navy);
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .login-subtitle {
            font-size: 0.9rem;
            color: #576579;
            line-height: 1.4;
        }

        /* Error Alert Box */
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

        /* Form Controls */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .form-label {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--color-navy);
        }

        .forgot-link {
            font-size: 0.82rem;
            font-weight: 500;
            color: #4b6389;
            text-decoration: none;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: var(--color-navy);
            text-decoration: underline;
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
            transition: color 0.2s;
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

        .form-control:focus {
            outline: none;
            border-color: var(--color-yellow);
            box-shadow: 0 0 0 4px rgba(254, 224, 0, 0.25);
        }

        .form-control:focus + .input-icon,
        .input-group:focus-within .input-icon {
            color: var(--color-navy);
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            padding: 6px;
            cursor: pointer;
            color: #788aa3;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: var(--color-navy);
        }

        .btn-signin {
            width: 100%;
            padding: 14px 24px;
            margin-top: 8px;
            background-color: var(--color-yellow);
            color: var(--color-navy);
            border: none;
            border-radius: 50px;
            font-family: inherit;
            font-size: 0.98rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(254, 224, 0, 0.45);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-signin:hover {
            background-color: var(--color-yellow-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(254, 224, 0, 0.55);
        }

        .btn-signin:active {
            transform: translateY(0);
        }

        /* Demo helper callout */
        .demo-callout {
            margin-top: 24px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px dashed rgba(27, 51, 95, 0.15);
            border-radius: 12px;
            font-size: 0.8rem;
            color: #576579;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .demo-callout-title {
            font-weight: 600;
            color: var(--color-navy);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .demo-badge {
            background: rgba(254, 224, 0, 0.25);
            color: var(--color-navy-dark);
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.72rem;
        }

        .demo-code {
            font-family: monospace;
            background: #eef2f6;
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--color-navy-dark);
            font-weight: 600;
        }

        /* Footer inside card */
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

        /* Minimal Page Footer */
        .page-footer {
            text-align: center;
            padding: 18px 24px;
            font-size: 0.8rem;
            color: #7b8a9e;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 32px 24px;
                border-radius: 20px;
            }
            .login-title {
                font-size: 1.45rem;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation with logo on left and return link on right -->
    <div class="top-nav">
        <a href="index.php" class="brand-logo">
            <img src="assets/images/logo.png" alt="Apex Diurnal Logo" class="logo-mark">
        </a>
        <a href="index.php" class="back-home-link" title="Return to Homepage">
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
                <img src="assets/images/logo.png" alt="Apex Diurnal Logo" class="logo-mark">
                <h1 class="login-title">Sign In to Your Account</h1>
                <p class="login-subtitle">Enter your email and password to access your account</p>
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
                            placeholder="e.g. admin@apexdiurnal.com"
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

            <div class="demo-callout">
                <div class="demo-callout-title">
                    <span class="demo-badge">DEMO ACCESS</span>
                    <span>Admin Credentials</span>
                </div>
                <div>Email: <span class="demo-code">admin@apexdiurnal.com</span></div>
                <div>Password: <span class="demo-code">admin123</span></div>
            </div>

            <div class="card-footer">
                <p>Need access or help? Please contact your administrator.</p>
                <p style="margin-top: 8px;"><a href="index.php">← Return to Solar Homepage</a></p>
            </div>
        </div>
    </div>

    <!-- Clean Brand Footer -->
    <footer class="page-footer">
        &copy; <?php echo date('Y'); ?> Apex Diurnal Solar Panels. Clean Energy &bull; Intelligent Future.
    </footer>

    <script>
        // Interactive Show / Hide Password toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                
                if (isPassword) {
                    // Show eye-off icon
                    eyeIcon.innerHTML = `
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    `;
                } else {
                    // Show standard eye icon
                    eyeIcon.innerHTML = `
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    `;
                }
            });
        }
    </script>
</body>
</html>