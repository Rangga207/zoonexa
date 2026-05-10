<?php
require 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$username = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm'] ?? '');

    // Validation
    if ($username === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if username already exists
        $stmt = $mysqli->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Username already taken. Try another one.';
        } else {
            // Create new user
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $mysqli->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $insert->bind_param('ss', $username, $hash);

            if ($insert->execute()) {
                $insert->close();
                header('Location: login.php?registered=1');
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
            $insert->close();
        }
        $stmt->close();
    }
}

$page_title = 'Sign Up';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up · Zoonexa</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
<style>
/* Modern Auth UI Overrides */
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0f1115;
  position: relative;
  overflow: hidden;
  font-family: 'Inter', sans-serif;
  color: white;
}
/* Glowing Background Shapes */
.auth-bg-shapes {
  position: absolute;
  inset: 0;
  z-index: 0;
  overflow: hidden;
}
.shape {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.4;
  animation: floatBG 10s infinite alternate ease-in-out;
}
.shape-1 { width: 400px; height: 400px; background: var(--primary); top: -100px; left: -100px; }
.shape-2 { width: 500px; height: 500px; background: var(--secondary); bottom: -150px; right: -100px; animation-delay: -5s; }

.auth-wrapper {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 420px;
  padding: 20px;
}
.auth-brand {
  text-align: center;
  margin-bottom: 30px;
}
.auth-logo { width: 60px; height: 60px; margin-bottom: 10px; animation: pulseLogo 3s infinite alternate; filter: drop-shadow(0 0 10px rgba(58,134,255,0.5)); }
.auth-brand h1 { font-family: 'Outfit', sans-serif; font-size: 32px; font-weight: 800; margin: 0; letter-spacing: 1px; }
.auth-brand .muted { color: rgba(255,255,255,0.6); font-size: 14px; margin-top: 5px; }

.auth-card {
  background: rgba(20,22,28,0.7);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 24px;
  padding: 40px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1);
}
.auth-card h2 { font-family: 'Outfit', sans-serif; font-size: 26px; margin: 0 0 8px; }
.auth-card > p.muted { margin-bottom: 24px; color: rgba(255,255,255,0.6); }

.auth-input-group {
  margin-bottom: 20px;
  text-align: left;
}
.auth-input-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: rgba(255,255,255,0.8);
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.auth-input {
  width: 100%;
  background: rgba(0,0,0,0.2);
  border: 1px solid rgba(255,255,255,0.1);
  color: white;
  padding: 14px 16px;
  border-radius: 12px;
  font-size: 15px;
  transition: all 0.3s;
  box-sizing: border-box;
}
.auth-input:focus {
  outline: none;
  border-color: var(--primary);
  background: rgba(0,0,0,0.4);
  box-shadow: 0 0 0 4px rgba(58,134,255,0.15);
}

.auth-btn {
  width: 100%;
  padding: 16px;
  border: none;
  border-radius: 12px;
  background: linear-gradient(135deg, var(--primary), #2272cc);
  color: white;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  margin-top: 10px;
  transition: all 0.3s;
  box-shadow: 0 8px 20px rgba(58,134,255,0.3);
}
.auth-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 25px rgba(58,134,255,0.5);
}
.auth-footer-text {
  text-align: center;
  margin-top: 24px;
  font-size: 14px;
  color: rgba(255,255,255,0.6);
}
.auth-footer-text a {
  color: var(--primary);
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s;
}
.auth-footer-text a:hover { color: #5c9eff; }

@keyframes floatBG { 100% { transform: translateY(30px) scale(1.05); } }
@keyframes pulseLogo { 100% { transform: scale(1.05); filter: drop-shadow(0 0 20px rgba(58,134,255,0.8)); } }
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

  <!-- Register Form -->
  <div class="auth-card">
    <h2>Create Account</h2>
    <p class="muted">Sign up for free. No credit card needed.</p>

    <?php if ($error): ?>
      <div class="alert" style="margin-bottom: 20px; background: rgba(231,76,60,0.1); border-color: var(--danger); color: #e74c3c;"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="auth-input-group">
        <label>Username</label>
        <input type="text" name="username" value="<?php echo e($username); ?>" class="auth-input" minlength="3" maxlength="50" placeholder="Choose a username" required autofocus>
      </div>
      <div class="auth-input-group">
        <label>Password</label>
        <input type="password" name="password" class="auth-input" minlength="6" placeholder="Min. 6 characters" required>
      </div>
      <div class="auth-input-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm" class="auth-input" minlength="6" placeholder="Re-enter your password" required>
      </div>
      <button type="submit" class="auth-btn">Create Account</button>
    </form>

    <p class="auth-footer-text">
      Already have an account? <a href="login.php">Sign in here</a>
    </p>
  </div>
</div>

<script defer src="script.js"></script>
</body>
</html>
