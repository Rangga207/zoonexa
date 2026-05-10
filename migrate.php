<?php
/**
 * Zoonexa One-Time Database Migration
 * Run this ONCE by visiting: zoonexa.biz.id/migrate.php
 * Delete this file after running.
 */
require 'config.php';
requireLogin(); // Only logged-in users can run this

// Only admin can run this
if ($_SESSION['username'] !== 'Rangga') {
    die("Access denied.");
}

$results = [];

function tryQuery($mysqli, $label, $sql) {
    global $results;
    try {
        $ok = @$mysqli->query($sql);
        if ($ok) {
            $results[] = ['status' => 'ok', 'label' => $label, 'msg' => 'Success'];
        } else {
            $results[] = ['status' => 'warn', 'label' => $label, 'msg' => $mysqli->error ?: 'Already exists / skipped'];
        }
    } catch (\Throwable $e) {
        $results[] = ['status' => 'warn', 'label' => $label, 'msg' => $e->getMessage()];
    }
}

// --- Run Migrations ---
// Check and add last_spin_date
$res = @$mysqli->query("SHOW COLUMNS FROM users LIKE 'last_spin_date'");
if ($res && $res->num_rows === 0) {
    tryQuery($mysqli, "Add users.last_spin_date", "ALTER TABLE users ADD COLUMN last_spin_date DATE DEFAULT NULL");
} else {
    $results[] = ['status' => 'ok', 'label' => "users.last_spin_date", 'msg' => 'Already exists ✓'];
}

// Check and add avatar_border
$res = @$mysqli->query("SHOW COLUMNS FROM users LIKE 'avatar_border'");
if ($res && $res->num_rows === 0) {
    tryQuery($mysqli, "Add users.avatar_border", "ALTER TABLE users ADD COLUMN avatar_border VARCHAR(50) DEFAULT 'default'");
} else {
    $results[] = ['status' => 'ok', 'label' => "users.avatar_border", 'msg' => 'Already exists ✓'];
}

// Expand milestones.icon column
tryQuery($mysqli, "Expand milestones.icon to VARCHAR(255)", "ALTER TABLE milestones MODIFY icon VARCHAR(255)");

// Update milestone icons to FontAwesome
$iconUpdates = [
    [1, '<i class="fas fa-seedling" style="color: var(--success);"></i>'],
    [2, '<i class="fas fa-fire" style="color: var(--danger);"></i>'],
    [3, '<i class="fas fa-gem" style="color: var(--secondary);"></i>'],
    [4, '<i class="fas fa-running" style="color: var(--primary);"></i>'],
    [5, '<i class="fas fa-bolt" style="color: #f1c40f;"></i>'],
    [6, '<i class="fas fa-bed" style="color: #3498db;"></i>'],
    [7, '<i class="fas fa-moon" style="color: #9b59b6;"></i>'],
    [8, '<i class="fas fa-clipboard-list" style="color: var(--info);"></i>'],
    [9, '<i class="fas fa-chart-line" style="color: var(--primary);"></i>'],
    [10, '<i class="fas fa-trophy" style="color: var(--warning);"></i>'],
    [11, '<i class="fas fa-dumbbell" style="color: var(--danger);"></i>'],
    [12, '<i class="fas fa-star" style="color: #f1c40f;"></i>'],
    [13, '<i class="fas fa-coins" style="color: #e67e22;"></i>'],
    [14, '<i class="fas fa-crown" style="color: #f1c40f;"></i>'],
];
foreach ($iconUpdates as [$id, $icon]) {
    $stmt = @$mysqli->prepare("UPDATE milestones SET icon = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $icon, $id);
        $stmt->execute();
        $stmt->close();
    }
}
$results[] = ['status' => 'ok', 'label' => 'Update milestone icons', 'msg' => 'FontAwesome icons applied ✓'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Zoonexa Migration</title>
<style>
  body { font-family: 'Inter', sans-serif; background: #0d1117; color: #e6edf3; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
  .box { background: #161b22; border: 1px solid #30363d; border-radius: 16px; padding: 40px; max-width: 600px; width: 100%; }
  h1 { color: #58a6ff; margin-top: 0; }
  .item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid #21262d; }
  .item:last-child { border: none; }
  .badge { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
  .ok { background: #1a3a1a; color: #3fb950; }
  .warn { background: #3a2a1a; color: #d29922; }
  .label { font-weight: 600; }
  .msg { color: #8b949e; font-size: 13px; margin-top: 2px; }
  .warning-box { background: #3a1a1a; border: 1px solid #f85149; border-radius: 8px; padding: 16px; margin-top: 24px; color: #f85149; font-size: 14px; }
  a.btn { display: inline-block; margin-top: 20px; background: #238636; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; }
</style>
</head>
<body>
<div class="box">
  <h1>⚡ Zoonexa Migration</h1>
  <p style="color: #8b949e;">Running database schema updates for new gamification features...</p>

  <?php foreach ($results as $r): ?>
  <div class="item">
    <span class="badge <?php echo $r['status']; ?>"><?php echo strtoupper($r['status']); ?></span>
    <div>
      <div class="label"><?php echo htmlspecialchars($r['label']); ?></div>
      <div class="msg"><?php echo htmlspecialchars($r['msg']); ?></div>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="warning-box">
    ⚠️ <strong>IMPORTANT:</strong> Delete the file <code>migrate.php</code> from your server after this runs to prevent unauthorized access!
  </div>
  <a href="index.php" class="btn">✓ Done — Go to Homepage</a>
</div>
</body>
</html>
