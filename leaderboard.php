<?php
require 'config.php';
requireLogin();

// Fetch Top 10 Users by Points
$stmt = $mysqli->prepare("
    SELECT username, points, health_mode, subscription_status, avatar_border, role
    FROM users 
    ORDER BY points DESC 
    LIMIT 10
");
$leaderboard = [];
if ($stmt) {
    $stmt->execute();
    $leaderboard = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // Fallback if avatar_border column doesn't exist
    $stmt = $mysqli->prepare("SELECT username, points, health_mode, subscription_status, 'default' as avatar_border, role FROM users ORDER BY points DESC LIMIT 10");
    if ($stmt) {
        $stmt->execute();
        $leaderboard = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$page_title = 'Leaderboard';
include 'header.php';
?>

<section class="page-section">
  <div class="page-header" style="text-align: center; justify-content: center; margin-bottom: 40px;">
    <div>
      <h1><i class="fas fa-trophy" style="color: var(--warning); margin-right: 8px;"></i> Global Leaderboard</h1>
      <p class="muted">The most consistent and dedicated Zoonexa users.</p>
    </div>
  </div>

  <div class="card big-card" style="max-width: 800px; margin: 0 auto;">
    <?php if (count($leaderboard) > 0): ?>
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
          <thead>
            <tr style="border-bottom: 2px solid var(--border);">
              <th style="padding: 16px; color: var(--text-dark); width: 60px; text-align: center;">Rank</th>
              <th style="padding: 16px; color: var(--text-dark);">User</th>
              <th style="padding: 16px; color: var(--text-dark);">Mode</th>
              <th style="padding: 16px; text-align: right; color: var(--text-dark);">Points</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($leaderboard as $index => $user): ?>
            <tr style="border-bottom: 1px solid var(--border); <?php echo ($user['username'] === $_SESSION['username']) ? 'background: rgba(58, 134, 255, 0.05);' : ''; ?>">
              <td style="padding: 16px; text-align: center; font-weight: bold; font-size: 18px; color: <?php 
                if ($index == 0) echo 'gold';
                elseif ($index == 1) echo 'silver';
                elseif ($index == 2) echo '#cd7f32'; // bronze
                else echo 'var(--text-muted)';
              ?>;">
                <?php 
                  if ($index == 0) echo '<i class="fas fa-medal" style="color: #ffd700;"></i>';
                  elseif ($index == 1) echo '<i class="fas fa-medal" style="color: #c0c0c0;"></i>';
                  elseif ($index == 2) echo '<i class="fas fa-medal" style="color: #cd7f32;"></i>';
                  else echo '#' . ($index + 1);
                ?>
              </td>
              <td style="padding: 16px; color: var(--text-body); display: flex; align-items: center; gap: 8px;">
                <div class="<?php echo e($user['avatar_border'] !== 'default' ? $user['avatar_border'] : ''); ?>" style="width: 32px; height: 32px; border-radius: 50%; background: var(--border); display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 12px; transition: 0.3s ease;">
                    <i class="fas fa-user"></i>
                </div>
                <strong><?php echo e($user['username']); ?></strong>
                <?php if ($user['role'] === 'admin'): ?>
                  <span style="background: var(--primary); color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700;" title="Administrator">ADMIN</span>
                <?php elseif ($user['subscription_status']): ?>
                  <span style="background: var(--warning); color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700;" title="Pro Subscriber">PRO</span>
                <?php endif; ?>
                <?php if ($user['username'] === $_SESSION['username']): ?>
                  <span style="font-size: 12px; color: var(--primary);">(You)</span>
                <?php endif; ?>
              </td>
              <td style="padding: 16px; color: var(--text-muted); text-transform: capitalize;">
                <?php echo e($user['health_mode']); ?>
              </td>
              <td style="padding: 16px; text-align: right; font-weight: bold; color: var(--primary); font-size: 16px;">
                <?php echo ($user['role'] === 'admin') ? '&infin;' : number_format($user['points']); ?> <span style="font-size: 12px; color: var(--text-muted); font-weight: normal;">pts</span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 40px 0;">
        <p class="muted">No users found yet. Start logging to be the first!</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'footer.php'; ?>
