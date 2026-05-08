<?php
require 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle POST (add new log)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_date    = $_POST['log_date'] ?? '';
    $steps       = (int)($_POST['steps'] ?? 0);
    $sleep_hours = (float)($_POST['sleep_hours'] ?? 0);
    $weight_kg   = (float)($_POST['weight_kg'] ?? 0);
    $bmi         = (float)($_POST['bmi'] ?? 0);

    // Validation
    if ($log_date === '') {
        $error = 'Please select a date.';
    } elseif ($steps <= 0 || $steps > 100000) {
        $error = 'Steps must be between 1 and 100,000.';
    } elseif ($sleep_hours <= 0 || $sleep_hours > 24) {
        $error = 'Sleep hours must be between 0.1 and 24.';
    } elseif ($weight_kg <= 0 || $weight_kg > 500) {
        $error = 'Weight must be between 1 and 500 kg.';
    } elseif ($bmi <= 0 || $bmi > 100) {
        $error = 'BMI must be between 1 and 100.';
    } else {
        // Check if log already exists for this date
        $check = $mysqli->prepare('SELECT id FROM health_logs WHERE user_id = ? AND log_date = ?');
        $check->bind_param('is', $user_id, $log_date);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = 'An entry for this date already exists. Please delete it first to update.';
        } else {
            // Insert new log
            $stmt = $mysqli->prepare('
                INSERT INTO health_logs (user_id, log_date, steps, sleep_hours, weight_kg, bmi)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->bind_param('isiddd', $user_id, $log_date, $steps, $sleep_hours, $weight_kg, $bmi);

            if ($stmt->execute()) {
                $success = 'Health log saved successfully!';

                // Award points for logging (10 points per log)
                addPoints(10);

                // Check and award milestones after new log
                checkAndAwardMilestones();
            } else {
                $error = 'Failed to save log. Please try again.';
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Handle DELETE (delete a log by id)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $del = $mysqli->prepare('DELETE FROM health_logs WHERE id = ? AND user_id = ?');
    $del->bind_param('ii', $del_id, $user_id);
    if ($del->execute()) {
        $success = 'Log entry deleted successfully.';
    }
    $del->close();
}

// Fetch user's logs (last 30)
$stmt = $mysqli->prepare('
    SELECT id, log_date, steps, sleep_hours, weight_kg, bmi
    FROM health_logs
    WHERE user_id = ?
    ORDER BY log_date DESC
    LIMIT 30
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Daily Log';
include 'header.php';
?>

<section class="page-section">
  <div class="page-header">
    <div>
      <h1>Daily Log</h1>
      <p class="muted">Log your health data. BMI calculates automatically as you type.</p>
    </div>
    <div class="muted small">
      Logging as <strong><?php echo e($_SESSION['username']); ?></strong>
    </div>
  </div>

  <!-- Log Form -->
  <div class="card big-card">
    <?php if ($error): ?>
      <div class="alert"><?php echo e($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <form method="post" class="form" id="health-form" enctype="multipart/form-data">
      <div class="form-grid">
        <label>
          Date
          <input type="date" name="log_date" id="log_date"
                 max="<?php echo date('Y-m-d'); ?>" required>
        </label>
        <label>
          Steps
          <input type="number" name="steps" id="steps"
                 min="1" max="100000" placeholder="e.g. 8,420" required>
        </label>
        <label>
          Sleep (hours)
          <input type="number" name="sleep_hours" id="sleep_hours"
                 step="0.1" min="0.1" max="24" placeholder="e.g. 7.5" required>
        </label>
        <label>
          Weight (kg)
          <input type="number" name="weight_kg" id="weight_kg"
                 step="0.1" min="1" max="500" placeholder="e.g. 70.5" required>
        </label>
        <label>
          Height (m)
          <input type="number" id="height_m"
                 step="0.01" min="0.5" max="3" placeholder="e.g. 1.75" required>
        </label>
        <label>
          BMI (auto)
          <input type="number" name="bmi" id="bmi"
                 step="0.01" readonly placeholder="Auto-calculated" required>
        </label>
      </div>

      <!-- Bonus Missions -->
      <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px; margin-top: 20px; border: 1px solid var(--border);">
        <h3 style="margin-bottom: 16px; font-size: 16px;">🎯 Bonus Missions</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">
          <!-- Jogging -->
          <div>
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0; padding-top: 25px;">
              <input type="checkbox" name="jogging_mission" style="width: 20px; height: 20px; border-radius: 4px; border: 1px solid var(--border); accent-color: var(--primary);">
              <span style="font-weight: normal; color: var(--text-body);">🏃‍♂️ Jogging (Medium Task, +3 pts)</span>
            </label>
          </div>

          <!-- Strava Proof -->
          <div>
            <span style="display: block; margin-bottom: 8px; color: var(--text-body); font-size: 14px; font-weight: 600;">Upload Strava Proof (Hardest Task, +5 pts)</span>
            <div style="border: 2px dashed var(--border); padding: 15px; border-radius: 10px; background: var(--bg-card); display: flex; align-items: center;">
              <input type="file" name="strava_proof" accept="image/*" style="width: 100%; border: none; padding: 0; background: transparent; font-size: 14px;">
            </div>
          </div>
        </div>
      </div>

      <button type="submit" style="margin-top: 20px;">Save Log</button>
    </form>
  </div>

  <!-- Logs Table -->
  <div class="card" style="margin-top: 24px;">
    <h2>Your Logs <span class="muted small">(Last 30 entries)</span></h2>
    <div class="table-wrapper">
      <?php if (count($logs) === 0): ?>
        <p class="muted" style="padding: 20px 0;">No logs yet. Fill in the form above to start tracking!</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Steps</th>
              <th>Sleep (hrs)</th>
              <th>Weight (kg)</th>
              <th>BMI</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?php echo e(date('D, M j, Y', strtotime($log['log_date']))); ?></td>
              <td><?php echo number_format($log['steps']); ?></td>
              <td><?php echo number_format($log['sleep_hours'], 1); ?></td>
              <td><?php echo number_format($log['weight_kg'], 1); ?></td>
              <td><?php echo number_format($log['bmi'], 1); ?></td>
              <td>
                <a href="health_log.php?delete=<?php echo (int)$log['id']; ?>"
                   class="btn-delete"
                   onclick="return confirm('Are you sure you want to delete this log?');">
                  🗑️ Delete
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>
