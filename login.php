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

<div class="auth-wrapper">
  <!-- Branding -->
  <div class="auth-brand">
    <img src="zoonexa-logo.png" alt="Zoonexa" class="auth-logo">
    <h1>Zoonexa</h1>
    <p class="muted">Track your health. Simple. Clean. Effective.</p>
  </div>

  <!-- Login Form -->
  <div class="card auth-card">
    <h2>Welcome Back</h2>
    <p class="muted">Sign in to continue your health journey.</p>

    <?php if ($registered): ?>
      <div class="alert success">Account created successfully! You can now log in.</div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <label>
        Username
        <input type="text" name="username" value="<?php echo e($username); ?>"
               placeholder="Enter your username" required autofocus>
      </label>
      <label>
        Password
        <input type="password" name="password" placeholder="Enter your password" required>
      </label>
      <button type="submit">Sign In</button>
    </form>

    <p class="muted auth-footer-text">
      Don't have an account? <a href="register.php">Sign up here</a>
    </p>
  </div>
</div>

<script defer src="script.js"></script>
</body>
</html>
