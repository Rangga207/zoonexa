<?php
require 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$username = '';
$error = '';
$registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $mysqli->prepare('SELECT id, password_hash, role FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                // Login successful
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $row['role'];
                $_SESSION['show_welcome_anim'] = true;

                // Check and award milestones on login
                checkAndAwardMilestones($row['id']);

                // Redirect to intended page or homepage
                $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
        $stmt->close();
    }
}

$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login · Zoonexa</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
<style>
/* Auth Page Shell */
.auth-page {
  position: relative;
  overflow: hidden;
}
/* Floating aurora blobs */
.auth-bg-shapes {
  position: fixed;
  inset: 0;
  z-index: 0;
  pointer-events: none;
}
.shape {
  position: absolute;
  border-radius: 50%;
  animation: floatBG 14s infinite alternate ease-in-out;
  will-change: transform;
}
.shape-1 {
  width: 520px; height: 520px;
  background: radial-gradient(circle, rgba(22, 160, 133, 0.25) 0%, transparent 70%);
  top: -160px; left: -120px;
}
.shape-2 {
  width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(52, 152, 219, 0.2) 0%, transparent 70%);
  bottom: -200px; right: -150px;
  animation-delay: -6s;
}

.auth-wrapper {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 420px;
  padding: 20px;
  box-sizing: border-box;
}
.auth-brand {
  text-align: center;
  margin-bottom: 28px;
}
.auth-logo {
  width: 60px; height: 60px;
  margin-bottom: 10px;
  animation: pulseLogo 3s infinite alternate;
  will-change: transform;
}
.auth-brand h1 {
  font-family: 'Outfit', 'Inter', sans-serif;
  font-size: 30px;
  font-weight: 800;
  margin: 0;
  letter-spacing: 1px;
  color: var(--primary);
}
.auth-brand .muted {
  color: var(--text-muted);
  font-size: 14px;
  margin-top: 5px;
}

/* Card — distinct from background in both themes */
.auth-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 22px;
  padding: 38px;
  box-shadow: 0 20px 48px rgba(0,0,0,0.09), 0 1px 3px rgba(0,0,0,0.05);
}
:root[data-theme="dark"] .auth-card {
  background: rgba(26, 30, 28, 0.92);
  border: 1px solid rgba(255,255,255,0.08);
  box-shadow: 0 20px 48px rgba(0,0,0,0.55), 0 1px 3px rgba(0,0,0,0.3);
}
.auth-card h2 {
  font-family: 'Outfit', 'Inter', sans-serif;
  font-size: 24px;
  margin: 0 0 6px;
  color: var(--text-dark);
}
.auth-card > p.muted {
  margin-bottom: 22px;
  color: var(--text-muted);
}

/* Input group */
.auth-input-group {
  margin-bottom: 18px;
  text-align: left;
}
.auth-input-group label {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: var(--text-body);
  margin-bottom: 7px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.auth-input {
  width: 100%;
  background: var(--bg-main);
  border: 1px solid var(--border);
  color: var(--text-dark);
  padding: 13px 15px;
  border-radius: 11px;
  font-size: 15px;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
  font-family: inherit;
}
.auth-input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(22, 160, 133, 0.15);
}
.auth-input:-webkit-autofill,
.auth-input:-webkit-autofill:hover,
.auth-input:-webkit-autofill:focus {
  -webkit-box-shadow: 0 0 0 30px var(--bg-main) inset !important;
  -webkit-text-fill-color: var(--text-dark) !important;
}

/* Submit button */
.auth-btn {
  width: 100%;
  padding: 14px;
  border: none;
  border-radius: 11px;
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: white;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  margin-top: 8px;
  transition: transform 0.2s, box-shadow 0.2s;
  box-shadow: 0 6px 18px rgba(22, 160, 133, 0.28);
  font-family: inherit;
}
.auth-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 24px rgba(22, 160, 133, 0.38);
}
.auth-btn:active { transform: translateY(0); }

.auth-footer-text {
  text-align: center;
  margin-top: 20px;
  font-size: 14px;
  color: var(--text-muted);
}
.auth-footer-text a {
  color: var(--primary);
  text-decoration: none;
  font-weight: 700;
}
.auth-footer-text a:hover { color: var(--primary-dark); }

/* Mobile */
@media (max-width: 480px) {
  .auth-card { padding: 24px 20px; border-radius: 18px; }
  .auth-brand h1 { font-size: 26px; }
}

@keyframes floatBG { 100% { transform: translateY(30px) scale(1.04); } }
@keyframes pulseLogo { 100% { transform: scale(1.05); filter: drop-shadow(0 4px 10px rgba(22, 160, 133, 0.28)); } }
</style>

<div class="auth-bg-shapes">
  <div class="shape shape-1"></div>
  <div class="shape shape-2"></div>
</div>

<div class="auth-wrapper">
  <!-- Branding -->
  <div class="auth-brand">
    <img src="zoonexa-logo.png" alt="Zoonexa" class="auth-logo">
    <h1>Zoonexa</h1>
    <p class="muted">Track your health. Simple. Clean. Effective.</p>
  </div>

  <!-- Login Form -->
  <div class="auth-card">
    <h2>Welcome Back</h2>
    <p class="muted">Sign in to continue your health journey.</p>

    <?php if ($registered): ?>
      <div class="alert success" style="margin-bottom: 20px;">Account created. You can now sign in.</div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert" style="margin-bottom: 20px; background: rgba(231,76,60,0.1); border-color: var(--danger); color: #e74c3c;"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="auth-input-group">
        <label>Username</label>
        <input type="text" name="username" value="<?php echo e($username); ?>" class="auth-input" placeholder="Enter your username" required autofocus>
      </div>
      <div class="auth-input-group">
        <label>Password</label>
        <input type="password" name="password" class="auth-input" placeholder="Enter your password" required>
      </div>
      <button type="submit" class="auth-btn">Sign In</button>
    </form>

    <p class="auth-footer-text">
      Don't have an account? <a href="register.php">Sign up here</a>
    </p>
  </div>
</div>

<script defer src="script.js"></script>
</body>
</html>
