<?php
require 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user account info
$stmt = $mysqli->prepare('SELECT created_at, health_mode, points, subscription_status, avatar_border FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get health summary statistics
$stmt = $mysqli->prepare('
    SELECT
        COUNT(*) as total_logs,
        AVG(steps) as avg_steps,
        AVG(sleep_hours) as avg_sleep,
        AVG(weight_kg) as avg_weight,
        AVG(bmi) as avg_bmi,
        MAX(log_date) as last_log_date
    FROM health_logs
    WHERE user_id = ?
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$summary = $result->fetch_assoc();
$stmt->close();

// Get recent activity (last 7 entries)
$stmt = $mysqli->prepare('
    SELECT log_date, steps, sleep_hours, weight_kg, bmi
    FROM health_logs
    WHERE user_id = ?
    ORDER BY log_date DESC
    LIMIT 7
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_logs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get active subscription info
$subscription = getActiveSubscription($user_id);

// Count achieved milestones
$stmt = $mysqli->prepare('SELECT COUNT(*) as cnt FROM user_milestones WHERE user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$milestonesAchieved = $result->fetch_assoc()['cnt'];
$stmt->close();

$page_title = 'Profile';
include 'header.php';
?>

<section class="page-section">

  <!-- Profile Header -->
  <div class="card big-card">
    <div class="page-header" style="display: flex; align-items: center; gap: 24px;">
      <!-- User Avatar with Border Feature -->
      <?php
        $borderColorMap = [
            'border_gold' => '#FFD700',
            'border_neon' => '#00ffff',
            'border_fire' => '#ff4500'
        ];
        $avatarColor = isset($user['avatar_border']) ? ($borderColorMap[$user['avatar_border']] ?? null) : null;
      ?>
      <div style="
        width: 80px; 
        height: 80px; 
        border-radius: 50%; 
        background: var(--bg-main); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 36px; 
        font-family: 'Outfit', sans-serif;
        font-weight: 800; 
        color: var(--primary);
        <?php if ($avatarColor): ?>
            border: 4px solid <?php echo $avatarColor; ?>;
            box-shadow: 0 0 20px <?php echo $avatarColor; ?>;
        <?php else: ?>
            border: 2px solid var(--border);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        <?php endif; ?>
      ">
        <?php echo strtoupper(substr($username, 0, 1)); ?>
      </div>

      <div>
        <h1>Your Profile</h1>
        <p class="muted">A quick overview of your health journey.</p>
        <div class="muted small" style="margin-top: 4px;">
          Logged in as <span class="accent-text"><?php echo e($username); ?></span>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <!-- Account Info -->
      <div>
        <h2>Account Info</h2>
        <p><strong>Username:</strong> <span class="accent-text"><?php echo e($username); ?></span></p>
        <p><strong>Member since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
        <p><strong>Health Mode:</strong> <span style="text-transform: capitalize;"><?php echo e($user['health_mode']); ?></span></p>
        <p><strong>Health Points:</strong> <span class="accent-text"><?php echo isAdmin() ? '&infin;' : number_format($user['points']); ?></span></p>
        <p>
          <strong>Subscription:</strong>
          <?php if (isAdmin()): ?>
            <span style="color: var(--primary); font-weight: 600;">👑 Admin Privileges</span>
          <?php elseif ($user['subscription_status']): ?>
            <span style="color: var(--success); font-weight: 600;">✓ Active</span>
            <?php if ($subscription): ?>
              <span class="small muted">(expires <?php echo date('M j, Y', strtotime($subscription['end_date'])); ?>)</span>
            <?php endif; ?>
          <?php else: ?>
            <span style="color: var(--warning); font-weight: 600;">Free</span>
            — <a href="subscription.php">Upgrade</a>
          <?php endif; ?>
        </p>
        <p><strong>Milestones:</strong> <span class="accent-text"><?php echo $milestonesAchieved; ?></span> achieved</p>
        <?php if ($summary['total_logs'] > 0): ?>
          <p><strong>Last logged:</strong> <?php echo date('F j, Y', strtotime($summary['last_log_date'])); ?></p>
        <?php endif; ?>
      </div>

      <!-- Health Summary -->
      <div>
        <h2>Health Summary</h2>
        <?php if ($summary['total_logs'] == 0): ?>
          <p class="muted">No logs yet. Start tracking from the <a href="health_log.php">Daily Log</a> page.</p>
        <?php else: ?>
          <ul class="summary-list">
            <li>
              <span>Total Logs</span>
              <strong><?php echo (int)$summary['total_logs']; ?></strong>
            </li>
            <li>
              <span>Avg Steps</span>
              <strong><?php echo number_format($summary['avg_steps']); ?></strong>
            </li>
            <li>
              <span>Avg Sleep</span>
              <strong><?php echo number_format($summary['avg_sleep'], 1); ?> hrs</strong>
            </li>
            <li>
              <span>Avg Weight</span>
              <strong><?php echo number_format($summary['avg_weight'], 1); ?> kg</strong>
            </li>
            <li>
              <span>Avg BMI</span>
              <strong><?php echo number_format($summary['avg_bmi'], 1); ?></strong>
            </li>
            <li>
              <span>Health Points</span>
              <strong><?php echo isAdmin() ? '&infin;' : number_format($user['points']); ?></strong>
            </li>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Activity -->
  <?php if (count($recent_logs) > 0): ?>
  <div class="card" style="margin-top: 24px;">
    <h2>Recent Activity <span class="muted small">(Last 7 entries)</span></h2>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Steps</th>
            <th>Sleep (hrs)</th>
            <th>Weight (kg)</th>
            <th>BMI</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($recent_logs as $log): ?>
          <tr>
            <td><?php echo e(date('D, M j, Y', strtotime($log['log_date']))); ?></td>
            <td><?php echo number_format($log['steps']); ?></td>
            <td><?php echo number_format($log['sleep_hours'], 1); ?></td>
            <td><?php echo number_format($log['weight_kg'], 1); ?></td>
            <td><?php echo number_format($log['bmi'], 1); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</section>

<?php include 'footer.php'; ?>
