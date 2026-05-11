<?php
require 'config.php';
requireLogin();
requireSubscription(); // Subscriber only

$user_id = $_SESSION['user_id'];

// Refresh milestone checks sebelum tampilkan
checkAndAwardMilestones();

// Ambil semua milestones
$stmt = $mysqli->prepare("SELECT * FROM milestones ORDER BY reward_points ASC");
$stmt->execute();
$result = $stmt->get_result();
$allMilestones = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ambil milestones yang sudah dicapai user
$stmt = $mysqli->prepare("
    SELECT milestone_id, achieved_at FROM user_milestones WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$achievedMap = [];
while ($row = $result->fetch_assoc()) {
    $achievedMap[$row['milestone_id']] = $row['achieved_at'];
}
$stmt->close();

// Hitung total points dari milestones
$totalMilestonePoints = 0;
$achievedCount = 0;
foreach ($allMilestones as $m) {
    if (isset($achievedMap[$m['id']])) {
        $totalMilestonePoints += $m['reward_points'];
        $achievedCount++;
    }
}

$page_title = 'Milestones';
include 'header.php';
?>

<section class="page-section">
  <div class="page-header">
    <div>
      <h1><i class="fas fa-trophy" style="color: var(--warning); margin-right: 8px;"></i> Milestones</h1>
      <p class="muted">Earn achievements by hitting your health goals. Each milestone rewards you with Health Points.</p>
    </div>
  </div>

  <!-- Progress Summary -->
  <div class="card big-card">
    <div class="milestone-summary">
      <div class="milestone-stat">
        <span class="milestone-stat-value" style="color: var(--primary);"><?php echo $achievedCount; ?></span>
        <span class="milestone-stat-label">Achieved</span>
      </div>
      <div class="milestone-stat">
        <span class="milestone-stat-value" style="color: var(--secondary);"><?php echo count($allMilestones) - $achievedCount; ?></span>
        <span class="milestone-stat-label">Remaining</span>
      </div>
      <div class="milestone-stat">
        <span class="milestone-stat-value" style="color: var(--success);"><?php echo number_format($totalMilestonePoints); ?></span>
        <span class="milestone-stat-label">Points Earned</span>
      </div>
      <div class="milestone-stat">
        <span class="milestone-stat-value" style="color: var(--warning);"><?php echo count($allMilestones); ?></span>
        <span class="milestone-stat-label">Total</span>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="milestone-progress-bar-wrapper">
      <div class="milestone-progress-bar">
        <div class="milestone-progress-fill" style="width: <?php echo ($achievedCount / count($allMilestones)) * 100; ?>%;"></div>
      </div>
      <span class="milestone-progress-text"><?php echo round(($achievedCount / count($allMilestones)) * 100); ?>% Complete</span>
    </div>
  </div>

  <!-- Milestone Cards -->
  <div class="milestone-grid">
    <?php foreach ($allMilestones as $m):
        $achieved = isset($achievedMap[$m['id']]);
    ?>
    <div class="milestone-card <?php echo $achieved ? 'milestone-achieved' : 'milestone-locked'; ?>">
      <div class="milestone-card-icon"><?php echo $m['icon']; ?></div>
      <div class="milestone-card-body">
        <h3><?php echo e($m['title']); ?></h3>
        <p class="muted"><?php echo e($m['description']); ?></p>
        <div class="milestone-card-footer">
          <span class="milestone-reward"><i class="fas fa-star" style="color: #f1c40f;"></i> <?php echo $m['reward_points']; ?> pts</span>
          <?php if ($achieved): ?>
            <span class="milestone-badge achieved"><i class="fas fa-check-circle"></i> Achieved</span>
            <span class="milestone-date small muted">
              <?php echo date('M j, Y', strtotime($achievedMap[$m['id']])); ?>
            </span>
          <?php else: ?>
            <span class="milestone-badge locked"><i class="fas fa-lock"></i> Locked</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Info Card -->
  <div class="info-card" style="margin-top: 24px;">
    <h3><i class="fas fa-lightbulb" style="color: #f1c40f; margin-right: 8px;"></i> How to Earn Milestones</h3>
    <p>
      Milestones are earned automatically based on your health activity. Log your data daily in the
      <a href="health_log.php">Daily Log</a>, hit your step and sleep targets, and keep your streak going.
      The system checks your progress automatically, no manual claiming needed. Points are added directly to your Health Points balance.
    </p>
  </div>
</section>

<?php include 'footer.php'; ?>
