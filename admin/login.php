<?php
/**
 * admin/login.php - Apex Diurnal Admin Authentication Page
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../validation.php';

// If already logged in as admin, redirect directly to dashboard
if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validator($_POST);
    $validator->required([
        'username' => 'Please enter both username and password.',
        'password' => 'Please enter both username and password.'
    ]);

    $clean = $validator->sanitized();
    $username = $clean['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif ($validator->fails()) {
        $error = $validator->firstError();
    } else {
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = :u OR email = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                loginAdmin($admin);
                
                $redirect = $_SESSION['admin_redirect_after_login'] ?? 'index.php';
                unset($_SESSION['admin_redirect_after_login']);
                header("Location: $redirect");
                exit;
            } else {
                $error = 'Invalid administrative credentials.';
            }
        } catch (PDOException $e) {
            $error = 'Database connection error. Please verify server status.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Sign In | Apex Diurnal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

    /* Top floating nav */
    .top-nav {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      padding: 24px 24px 0;
      display: flex;
      justify-content: flex-end;
      align-items: center;
    }

    .logo-mark {
      height: 40px;
      width: auto;
      object-fit: contain;
      filter: drop-shadow(0 2px 6px rgba(27, 51, 95, 0.15));
      transition: transform 0.2s ease;
    }

    .logo-mark:hover {
      transform: scale(1.05);
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

    .form-label {
      display: block;
      font-size: 0.88rem;
      font-weight: 600;
      color: var(--color-navy);
      margin-bottom: 8px;
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
        <div class="brand-badge">
          <span class="brand-badge-title">ADMIN</span>
          <span class="brand-badge-sub">PORTAL</span>
        </div>
        <h1 class="login-title">Operations & Booking</h1>
        <p class="login-subtitle">Enter your credentials to access the admin dashboard</p>
      </div>

      <?php 
      $adminFlashWarnings = SessionManager::getFlash('warning');
      $adminFlashErrors = SessionManager::getFlash('error');
      $adminFlashSuccesses = SessionManager::getFlash('success');
      ?>

      <?php if (!empty($adminFlashWarnings)): ?>
        <div class="error-alert" role="alert" style="background-color: #fffbeb; border-color: #fde68a; border-left-color: #f59e0b; color: #92400e;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <div>
            <?php foreach ($adminFlashWarnings as $w): ?>
              <div><?php echo htmlspecialchars($w); ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($adminFlashErrors)): ?>
        <div class="error-alert" role="alert">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <div>
            <?php foreach ($adminFlashErrors as $e): ?>
              <div><?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($adminFlashSuccesses)): ?>
        <div class="error-alert" role="alert" style="background-color: #ecfdf5; border-color: #a7f3d0; border-left-color: #10b981; color: #065f46;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
          <div>
            <?php foreach ($adminFlashSuccesses as $s): ?>
              <div><?php echo htmlspecialchars($s); ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="error-alert" role="alert">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <div><?php echo htmlspecialchars($error); ?></div>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <div class="form-group">
          <label for="username" class="form-label">Username or Email</label>
          <div class="input-group">
            <input 
              type="text" 
              id="username" 
              name="username" 
              class="form-control" 
              placeholder="Enter your username or email"
              value="<?php echo htmlspecialchars($username); ?>" 
              required 
              autofocus
            >
            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </div>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <div class="input-group">
            <input 
              type="password" 
              id="password" 
              name="password" 
              class="form-control" 
              placeholder="Enter your password"
              style="padding-right: 44px;"
              required
            >
            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <button type="button" class="password-toggle" id="togglePassword" aria-label="Show or hide password">
              <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-signin">
          <span>Sign In to Dashboard</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </button>
      </form>

      <div class="card-footer">
        <p><a href="../index.php">← Return to Solar Homepage</a></p>
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
