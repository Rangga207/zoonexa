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
        $error = 'Please enter username and password.';
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
/* Premium Light Theme Auth UI */
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f0f4f8;
  position: relative;
  overflow: hidden;
  font-family: 'Inter', sans-serif;
  color: var(--text-dark);
}
/* Optimized Soft Glowing Background Shapes */
.auth-bg-shapes {
  position: absolute;
  inset: 0;
  z-index: 0;
  overflow: hidden;
  background: linear-gradient(135deg, #e0f2f1 0%, #f0f4f8 100%);
}
.shape {
  position: absolute;
  border-radius: 50%;
  animation: floatBG 12s infinite alternate ease-in-out;
  will-change: transform;
}
/* Replaced expensive filter: blur() with cheap radial-gradient */
.shape-1 { 
  width: 500px; height: 500px; 
  background: radial-gradient(circle, rgba(22, 160, 133, 0.4) 0%, transparent 70%); 
  top: -150px; left: -100px; 
}
.shape-2 { 
  width: 600px; height: 600px; 
  background: radial-gradient(circle, rgba(52, 152, 219, 0.3) 0%, transparent 70%); 
  bottom: -200px; right: -150px; 
  animation-delay: -5s; 
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
  margin-bottom: 30px;
}
.auth-logo { width: 60px; height: 60px; margin-bottom: 10px; animation: pulseLogo 3s infinite alternate; will-change: transform; }
.auth-brand h1 { font-family: 'Outfit', sans-serif; font-size: 32px; font-weight: 800; margin: 0; letter-spacing: 1px; color: var(--primary-dark); }
.auth-brand .muted { color: var(--text-muted); font-size: 14px; margin-top: 5px; }

.auth-card {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.5);
  border-radius: 24px;
  padding: 40px;
  box-shadow: 0 20px 40px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.05);
}
.auth-card h2 { font-family: 'Outfit', sans-serif; font-size: 26px; margin: 0 0 8px; color: var(--text-dark); }
.auth-card > p.muted { margin-bottom: 24px; color: var(--text-muted); }

.auth-input-group {
  margin-bottom: 20px;
  text-align: left;
}
.auth-input-group label {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: var(--text-body);
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.auth-input {
  width: 100%;
  background: var(--bg-main);
  border: 1px solid var(--border);
  color: var(--text-dark);
  padding: 14px 16px;
  border-radius: 12px;
  font-size: 15px;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
}
.auth-input:focus {
  outline: none;
  border-color: var(--primary);
  background: #fff;
  box-shadow: 0 0 0 4px rgba(22, 160, 133, 0.15);
}

.auth-btn {
  width: 100%;
  padding: 16px;
  border: none;
  border-radius: 12px;
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: white;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  margin-top: 10px;
  transition: transform 0.2s, box-shadow 0.2s;
  box-shadow: 0 8px 20px rgba(22, 160, 133, 0.3);
}
.auth-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 25px rgba(22, 160, 133, 0.4);
}
.auth-btn:active {
  transform: translateY(0);
}
.auth-footer-text {
  text-align: center;
  margin-top: 24px;
  font-size: 14px;
  color: var(--text-muted);
}
.auth-footer-text a {
  color: var(--primary);
  text-decoration: none;
  font-weight: 700;
  transition: color 0.2s;
}
.auth-footer-text a:hover { color: var(--primary-dark); }

/* Mobile Responsiveness */
@media (max-width: 480px) {
  .auth-card {
    padding: 24px;
    border-radius: 20px;
  }
  .auth-card h2 { font-size: 22px; }
  .auth-brand h1 { font-size: 28px; }
  .auth-logo { width: 50px; height: 50px; }
}

@keyframes floatBG { 100% { transform: translateY(30px) scale(1.05); } }
@keyframes pulseLogo { 100% { transform: scale(1.05); filter: drop-shadow(0 4px 10px rgba(22, 160, 133, 0.3)); } }
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
      <div class="alert success" style="margin-bottom: 20px;">Account created successfully! You can now log in.</div>
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
