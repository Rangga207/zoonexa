<?php
require 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';

// Handle mode switch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['health_mode'])) {
    $newMode = $_POST['health_mode'];
    if (setUserHealthMode($newMode)) {
        $success = 'Your health mode has been updated to <strong>' . ucfirst(e($newMode)) . '</strong>.';
    }
}

$currentMode = getUserHealthMode();
$targets = getDailyTargets($currentMode);

$page_title = 'Health Modes';
include 'header.php';
?>

<section class="page-section">
  <div class="page-header">
    <div>
      <h1>Health Modes</h1>
      <p class="muted">Choose a mode that fits your current fitness goal. Each mode sets different daily targets.</p>
    </div>
    <div class="muted small">
      Current mode: <strong style="text-transform: capitalize; color: var(--primary);"><?php echo e($currentMode); ?></strong>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $success; ?></div>
  <?php endif; ?>

  <!-- Mode Cards -->
  <div class="service-grid" style="grid-template-columns: repeat(3, 1fr);">

    <!-- Maintain -->
    <div class="service-card mode-card <?php echo $currentMode === 'maintain' ? 'mode-active' : ''; ?>">
      <div class="service-icon">⚖️</div>
      <h3>Maintain</h3>
      <p class="muted">Keep your current body composition. Balanced approach for steady health.</p>
      <div class="mode-targets">
        <span>🚶 8,000 steps</span>
        <span>😴 7 hrs sleep</span>
        <span>💧 8 glasses water</span>
        <span>🏋️ 30 min exercise</span>
      </div>
      <form method="post">
        <input type="hidden" name="health_mode" value="maintain">
        <button type="submit" <?php echo $currentMode === 'maintain' ? 'disabled' : ''; ?> class="btn-mode">
          <?php echo $currentMode === 'maintain' ? '✓ Active' : 'Select Maintain'; ?>
        </button>
      </form>
    </div>

    <!-- Bulking -->
    <div class="service-card mode-card <?php echo $currentMode === 'bulking' ? 'mode-active' : ''; ?>">
      <div class="service-icon">💪</div>
      <h3>Bulking</h3>
      <p class="muted">Focus on muscle gain. Higher calorie intake and more intense training.</p>
      <div class="mode-targets">
        <span>🚶 10,000 steps</span>
        <span>😴 8 hrs sleep</span>
        <span>💧 10 glasses water</span>
        <span>🏋️ 45 min exercise</span>
        <span>🍽️ 2,500 kcal/day</span>
      </div>
      <form method="post">
        <input type="hidden" name="health_mode" value="bulking">
        <button type="submit" <?php echo $currentMode === 'bulking' ? 'disabled' : ''; ?> class="btn-mode">
          <?php echo $currentMode === 'bulking' ? '✓ Active' : 'Select Bulking'; ?>
        </button>
      </form>
    </div>

    <!-- Cutting -->
    <div class="service-card mode-card <?php echo $currentMode === 'cutting' ? 'mode-active' : ''; ?>">
      <div class="service-icon">🔥</div>
      <h3>Cutting</h3>
      <p class="muted">Focus on fat loss. Lower calorie intake with higher activity levels.</p>
      <div class="mode-targets">
        <span>🚶 12,000 steps</span>
        <span>😴 7 hrs sleep</span>
        <span>💧 10 glasses water</span>
        <span>🏋️ 60 min exercise</span>
        <span>🍽️ 1,800 kcal/day</span>
      </div>
      <form method="post">
        <input type="hidden" name="health_mode" value="cutting">
        <button type="submit" <?php echo $currentMode === 'cutting' ? 'disabled' : ''; ?> class="btn-mode">
          <?php echo $currentMode === 'cutting' ? '✓ Active' : 'Select Cutting'; ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Your Current Targets -->
  <div class="card" style="margin-top: 30px;">
    <h2>Your Current Daily Targets</h2>
    <p class="muted" style="margin-bottom: 20px;">
      Based on your <strong style="text-transform: capitalize;"><?php echo e($currentMode); ?></strong> mode, here are your daily goals:
    </p>
    <div class="targets-grid">
      <?php foreach ($targets as $key => $target): ?>
        <div class="target-card">
          <span class="target-label" style="text-transform: capitalize;"><?php echo e($key); ?></span>
          <span class="target-value"><?php echo $target['target']; ?> <span class="target-unit"><?php echo e($target['unit']); ?></span></span>
          <span class="target-points">+<?php echo $target['points']; ?> pts</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Info Card -->
  <div class="info-card" style="margin-top: 24px;">
    <h3>💡 How do modes work?</h3>
    <p>
      Modes set your daily health targets. When you log your data each day, the system tracks how well you're hitting 
      your targets. You earn <strong>Health Points</strong> for meeting or exceeding your goals. You can switch modes 
      at any time there's no penalty or cooldown.
    </p>
  </div>
</section>

<?php include 'footer.php'; ?>
