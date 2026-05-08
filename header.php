<?php
// header.php - Site Header & Navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Zoonexa - Track your daily health habits simply and effectively">
  <title><?php echo isset($page_title) ? e($page_title) . ' · Zoonexa' : 'Zoonexa · Healthy Habits'; ?></title>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Google Fonts: Inter (SF Pro substitute) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

  <!-- Styles -->
  <link rel="stylesheet" href="style.css">

  <!-- PWA Manifest & Meta -->
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0d1117">
  <link rel="apple-touch-icon" href="zoonexa-logo.png">

  <!-- Scripts -->
  <script defer src="script.js"></script>
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/zoonexa/sw.js')
          .catch(err => console.error('Service Worker registration failed: ', err));
      });
    }
  </script>
</head>
<body>

<header class="site-header mac-window-header">
  <!-- macOS Traffic Lights -->
  <div class="mac-traffic-lights">
    <div class="mac-btn close"></div>
    <div class="mac-btn minimize"></div>
    <div class="mac-btn maximize"></div>
  </div>
  <div class="header-inner">

    <!-- Logo -->
    <a href="index.php" class="logo">
      <div class="logo-large">
        <img src="zoonexa-logo.png" alt="Zoonexa Logo" class="logo-image" style="height: 40px; width: auto;">
      </div>
    </a>

    <!-- Desktop Navigation -->
    <nav class="main-nav" id="mainNav">
      <?php if (isLoggedIn()): ?>
        <a href="index.php"><i class="fas fa-home"></i> <span class="nav-label">Home</span></a>
        <a href="health_log.php"><i class="fas fa-clipboard-list"></i> <span class="nav-label">Daily Log</span></a>
        <a href="health_modes.php"><i class="fas fa-bullseye"></i> <span class="nav-label">Modes</span></a>
        <a href="tips.php"><i class="fas fa-lightbulb"></i> <span class="nav-label">Tips</span></a>
        <a href="merchandise.php"><i class="fas fa-shopping-bag"></i> <span class="nav-label">Merch</span></a>
        <a href="milestone.php" class="nav-milestone"><i class="fas fa-trophy"></i> <span class="nav-label">Milestones</span></a>
        <a href="chatbot.php" class="nav-chatbot">
          <i class="fas fa-robot"></i> <span class="nav-label">AI Assistant</span>
        </a>
        <div class="nav-user">
          <button class="nav-username" type="button" aria-haspopup="true" aria-expanded="false" id="userMenuBtn">
            <i class="fas fa-user"></i>
            <span><?php echo e($_SESSION['username']); ?></span>
            <i class="fas fa-chevron-down nav-chevron"></i>
          </button>
          <div class="nav-dropdown" id="userDropdown">
            <?php if (isAdmin()): ?>
              <a href="admin.php" style="color: var(--primary);"><i class="fas fa-shield-alt"></i> Admin Panel</a>
              <div class="nav-dropdown-divider" style="height:1px; background:var(--border); margin:4px 0;"></div>
            <?php endif; ?>
            <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="subscription.php"><i class="fas fa-crown"></i> Subscription</a>
            <a href="logout.php" class="nav-logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php"><i class="fas fa-sign-in-alt"></i> <span class="nav-label">Login</span></a>
        <a href="register.php" class="nav-cta"><i class="fas fa-rocket"></i> <span class="nav-label">Get Started</span></a>
      <?php endif; ?>

      <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Toggle theme">
        <i class="fas fa-moon"></i>
      </button>
    </nav>

    <!-- Mobile Controls -->
    <div class="mobile-controls">
      <button type="button" class="theme-toggle theme-toggle-mobile" id="theme-toggle-mobile" aria-label="Toggle theme">
        <i class="fas fa-moon"></i>
      </button>
      <button class="hamburger" id="hamburger" aria-label="Open menu" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>

  </div>
</header>

<!-- Mobile Menu Overlay -->
<div class="mobile-menu-overlay" id="mobileOverlay"></div>

<!-- Mobile Drawer -->
<div class="mobile-drawer" id="mobileDrawer">
  <div class="mobile-drawer-header">
    <div class="logo-large">
      <i class="fas fa-heartbeat logo-icon"></i>
      <span class="logo-text">Zoonexa</span>
    </div>
    <button class="mobile-drawer-close" id="drawerClose" aria-label="Close menu">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <?php if (isLoggedIn()): ?>
  <div class="mobile-user-card">
    <div class="mobile-user-avatar"><i class="fas fa-user"></i></div>
    <div>
      <div class="mobile-user-name"><?php echo e($_SESSION['username']); ?></div>
      <div class="mobile-user-sub muted small">
        <?php echo isSubscribed() ? '✨ Pro Member' : 'Free Account'; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <nav class="mobile-nav">
    <?php if (isLoggedIn()): ?>
      <a href="index.php" class="mobile-nav-item">
        <i class="fas fa-home"></i> Home
      </a>
      <a href="health_log.php" class="mobile-nav-item">
        <i class="fas fa-clipboard-list"></i> Daily Log
      </a>
      <a href="health_modes.php" class="mobile-nav-item">
        <i class="fas fa-bullseye"></i> Health Modes
      </a>
      <a href="tips.php" class="mobile-nav-item">
        <i class="fas fa-lightbulb"></i> Tips
      </a>
      <a href="merchandise.php" class="mobile-nav-item">
        <i class="fas fa-shopping-bag"></i> Merch
      </a>
      <a href="milestone.php" class="mobile-nav-item">
        <i class="fas fa-trophy"></i> Milestones
      </a>
      <a href="chatbot.php" class="mobile-nav-item mobile-nav-ai">
        <i class="fas fa-robot"></i> AI Assistant
      </a>
      <div class="mobile-nav-divider"></div>
      <?php if (isAdmin()): ?>
        <a href="admin.php" class="mobile-nav-item" style="color: var(--primary);">
          <i class="fas fa-shield-alt"></i> Admin Panel
        </a>
      <?php endif; ?>
      <a href="profile.php" class="mobile-nav-item">
        <i class="fas fa-user-circle"></i> Profile
      </a>
      <a href="subscription.php" class="mobile-nav-item">
        <i class="fas fa-crown"></i> Subscription
      </a>
      <a href="logout.php" class="mobile-nav-item mobile-nav-logout">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    <?php else: ?>
      <a href="login.php" class="mobile-nav-item">
        <i class="fas fa-sign-in-alt"></i> Login
      </a>
      <a href="register.php" class="mobile-nav-item mobile-nav-ai">
        <i class="fas fa-rocket"></i> Get Started
      </a>
    <?php endif; ?>
  </nav>
</div>

<main class="page"><?php // Content starts here ?>
