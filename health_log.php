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

                // Handle Bonus Missions
                // 1. Jogging
                if (isset($_POST['jogging_mission']) && $_POST['jogging_mission'] === 'on') {
                    $bm_stmt = $mysqli->prepare("INSERT INTO bonus_missions (user_id, log_date, mission_type, points_awarded, status) VALUES (?, ?, 'jogging', 3, 'approved')");
                    $bm_stmt->bind_param("is", $user_id, $log_date);
                    $bm_stmt->execute();
                    $bm_stmt->close();
                    addPoints(3); // Auto approve jogging
                    $success .= ' +3 points for Jogging!';
                }

                // 2. Strava Proof Upload
                if (isset($_FILES['strava_proof']) && $_FILES['strava_proof']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/';
                    $file_ext = strtolower(pathinfo($_FILES['strava_proof']['name'], PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
                    
                    if (in_array($file_ext, $allowed_exts)) {
                        $new_filename = 'strava_' . $user_id . '_' . time() . '.' . $file_ext;
                        if (move_uploaded_file($_FILES['strava_proof']['tmp_name'], $upload_dir . $new_filename)) {
                            $bm_stmt = $mysqli->prepare("INSERT INTO bonus_missions (user_id, log_date, mission_type, proof_path, points_awarded, status) VALUES (?, ?, 'strava', ?, 5, 'pending')");
                            $path = $upload_dir . $new_filename;
                            $bm_stmt->bind_param("iss", $user_id, $log_date, $path);
                            $bm_stmt->execute();
                            $bm_stmt->close();
                            $success .= ' Strava proof uploaded and pending admin approval!';
                        }
                    } else {
                        $error = 'Invalid file type for Strava proof. Please upload JPG, PNG, or PDF.';
                    }
                }

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
      <div class="bonus-missions-wrap">
        <div class="bonus-missions-header">
          <i class="fas fa-bolt" style="color: var(--warning);"></i>
          <span>Bonus Missions</span>
          <span class="bonus-badge">Earn Extra Points</span>
        </div>

        <div class="bonus-grid">
          <!-- Jogging Toggle Card -->
          <label class="mission-card" for="jogging_mission" id="jogging-label">
            <input type="checkbox" name="jogging_mission" id="jogging_mission"
                   onchange="toggleMissionCard(this, 'jogging-label')" hidden>
            <div class="mission-icon-wrap" style="background: rgba(231,76,60,0.12);">
              <i class="fas fa-running" style="color: #e74c3c;"></i>
            </div>
            <div class="mission-info">
              <strong>Morning Jog</strong>
              <span>Complete a jogging session today</span>
            </div>
            <div class="mission-footer">
              <span class="pts-badge">+3 pts</span>
              <div class="mission-check"><i class="fas fa-check"></i></div>
            </div>
          </label>

          <!-- Strava Upload Card -->
          <div class="mission-card strava-card" id="strava-drop-zone">
            <div class="mission-icon-wrap" style="background: rgba(52,152,219,0.12);">
              <i class="fas fa-route" style="color: #3498db;"></i>
            </div>
            <div class="mission-info">
              <strong>Strava Proof</strong>
              <span id="strava-label-text">Tap to upload your route screenshot</span>
            </div>
            <div class="mission-footer">
              <span class="pts-badge" style="background: rgba(52,152,219,0.15); color: #3498db;">+5 pts</span>
              <label for="strava_proof" class="strava-upload-btn">
                <i class="fas fa-cloud-upload-alt"></i>
              </label>
            </div>
            <input type="file" name="strava_proof" id="strava_proof" accept="image/*,application/pdf"
                   style="display:none;"
                   onchange="handleStravaFile(this)">
          </div>
        </div>
      </div>

      <button type="submit" class="btn-save-log">
        <i class="fas fa-save"></i> Save Log
      </button>
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

<style>
/* ── Bonus Missions Modern UI ── */
.bonus-missions-wrap {
  margin-top: 24px;
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px;
}
.bonus-missions-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 16px;
  color: var(--text-body);
}
.bonus-badge {
  margin-left: auto;
  font-size: 11px;
  font-weight: 600;
  background: rgba(241,196,15,0.15);
  color: var(--warning);
  padding: 3px 10px;
  border-radius: 20px;
  letter-spacing: 0.3px;
}
.bonus-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  align-items: stretch;
}
@media (max-width: 600px) {
  .bonus-grid { grid-template-columns: 1fr; }
}

/* Mission Card — vertical fill */
.mission-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0;
  background: var(--bg-card);
  border: 2px solid var(--border);
  border-radius: 14px;
  padding: 18px;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s, transform 0.15s;
  user-select: none;
  min-height: 140px;
}
.mission-card:hover {
  border-color: var(--primary);
  background: rgba(58,134,255,0.04);
  transform: translateY(-1px);
}
.mission-card.active {
  border-color: var(--success);
  background: rgba(22,160,133,0.08);
}
.mission-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 22px;
  margin-bottom: 12px;
}
.mission-info {
  flex: 1;
  width: 100%;
}
.mission-info strong {
  display: block;
  font-size: 15px;
  font-weight: 700;
  color: var(--text-body);
  margin-bottom: 4px;
}
.mission-info span {
  font-size: 12px;
  color: var(--text-muted);
  display: block;
  line-height: 1.4;
}
.mission-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  margin-top: 16px;
}
.mission-pts {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.pts-badge {
  font-size: 12px;
  font-weight: 700;
  background: rgba(22,160,133,0.15);
  color: var(--success);
  padding: 3px 10px;
  border-radius: 20px;
  white-space: nowrap;
}
.mission-check {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 2px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: transparent;
  font-size: 11px;
  transition: all 0.25s;
}
.mission-card.active .mission-check {
  background: var(--success);
  border-color: var(--success);
  color: white;
}

/* Strava Upload Button */
.strava-upload-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(52,152,219,0.15);
  color: #3498db;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 15px;
  transition: background 0.2s;
}
.strava-upload-btn:hover {
  background: rgba(52,152,219,0.3);
}
.strava-card.uploaded {
  border-color: #3498db;
  background: rgba(52,152,219,0.06);
}

/* Save Log Button */
.btn-save-log {
  margin-top: 24px;
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, var(--primary), #2272cc);
  color: white;
  font-size: 15px;
  font-weight: 700;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  letter-spacing: 0.3px;
  box-shadow: 0 4px 16px rgba(58,134,255,0.3);
  transition: transform 0.2s, box-shadow 0.2s;
}
.btn-save-log:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(58,134,255,0.45);
}
.btn-save-log:active {
  transform: translateY(0);
}
</style>

<script>
function toggleMissionCard(checkbox, labelId) {
  const label = document.getElementById(labelId);
  if (checkbox.checked) {
    label.classList.add('active');
  } else {
    label.classList.remove('active');
  }
}

function handleStravaFile(input) {
  const zone = document.getElementById('strava-drop-zone');
  const labelText = document.getElementById('strava-label-text');
  if (input.files && input.files[0]) {
    const name = input.files[0].name;
    zone.classList.add('uploaded');
    labelText.textContent = '✓ ' + name;
  }
}
</script>

<?php include 'footer.php'; ?>

