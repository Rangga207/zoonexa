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

<div class="auth-wrapper">
  <!-- Branding -->
  <div class="auth-brand">
    <img src="zoonexa-logo.png" alt="Zoonexa" class="auth-logo">
    <h1>Zoonexa</h1>
    <p class="muted">Track your health. Simple. Clean. Effective.</p>
  </div>

  <!-- Register Form -->
  <div class="card auth-card">
    <h2>Create Account</h2>
    <p class="muted">Sign up for free. No credit card needed to get started.</p>

    <?php if ($error): ?>
      <div class="alert"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <label>
        Username
        <input type="text" name="username" value="<?php echo e($username); ?>"
               minlength="3" maxlength="50" placeholder="Choose a username" required autofocus>
      </label>
      <label>
        Password
        <input type="password" name="password" minlength="6" placeholder="Min. 6 characters" required>
      </label>
      <label>
        Confirm Password
        <input type="password" name="confirm" minlength="6" placeholder="Re-enter your password" required>
      </label>
      <button type="submit">Create Account</button>
    </form>

    <p class="muted auth-footer-text">
      Already have an account? <a href="login.php">Sign in here</a>
    </p>
  </div>
</div>

<script defer src="script.js"></script>
</body>
</html>
