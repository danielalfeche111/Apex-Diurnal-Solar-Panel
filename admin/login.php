<?php
/**
 * admin/login.php - Apex Diurnal Admin Authentication Page
 */

require_once __DIR__ . '/auth.php';

// If already logged in as admin, redirect directly to dashboard
if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
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
  <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo file_exists(__DIR__ . '/assets/css/admin.css') ? filemtime(__DIR__ . '/assets/css/admin.css') : time(); ?>">
  <style>
    body {
      background: linear-gradient(135deg, #102140 0%, #1b335f 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 1.5rem;
    }
    .login-card {
      background: transparent;
      border-radius: var(--radius-lg);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
      width: 100%;
      max-width: 420px;
      overflow: hidden;
      border: none;
      outline: none;
      -webkit-mask-image: -webkit-radial-gradient(white, black);
    }
    .login-header {
      background: var(--navy-dark);
      padding: 2.25rem 2rem;
      text-align: center;
      color: #ffffff;
      border-bottom: 2px solid var(--yellow-accent);
      border-top-left-radius: var(--radius-lg);
      border-top-right-radius: var(--radius-lg);
      border: none;
      outline: none;
    }
    .login-title {
      font-size: 1.35rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      color: var(--yellow-accent);
    }
    .login-subtitle {
      font-size: 0.76rem;
      color: rgba(255, 255, 255, 0.85);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-top: 0.25rem;
      font-weight: 600;
    }
    .login-body {
      background: #ffffff;
      padding: 2rem;
      border-bottom-left-radius: var(--radius-lg);
      border-bottom-right-radius: var(--radius-lg);
      border: none;
      outline: none;
    }
    .login-alert {
      background: var(--danger-bg);
      border: 1px solid #fecaca;
      color: #991b1b;
      padding: 0.75rem 1rem;
      border-radius: var(--radius-sm);
      font-size: 0.8rem;
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
  </style>
</head>
<body>

<div class="login-card">
  <div class="login-header">
    <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 0.85rem;">
      <?php $login_logo_v = file_exists(__DIR__ . '/../assets/images/logo.png') ? filemtime(__DIR__ . '/../assets/images/logo.png') : time(); ?>
      <img src="../assets/images/logo.png?v=<?php echo $login_logo_v; ?>" alt="Apex Diurnal Logo" style="height: 52px; width: auto; object-fit: contain; filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.35)); border: none; outline: none; background: transparent;">
    </div>
    <div class="login-title">Apex Diurnal</div>
    <div class="login-subtitle">Operations & Booking Admin</div>
  </div>

  <div class="login-body">
    <?php if (!empty($error)): ?>
      <div class="login-alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

      <div class="form-group">
        <label class="form-label" for="username">Username or Email</label>
        <input type="text" id="username" name="username" class="form-control" required autofocus value="<?php echo htmlspecialchars($username); ?>" placeholder="admin">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; margin-top: 0.5rem; font-size: 0.9rem;">
        Sign In to Dashboard &rarr;
      </button>
    </form>
  </div>
</div>

</body>
</html>
