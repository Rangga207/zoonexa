<?php
require 'config.php';
requireLogin();

// Check milestones setiap visit homepage
checkAndAwardMilestones();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$points = getUserPoints();
$healthMode = getUserHealthMode();
$subscribed = isSubscribed();

// Fetch last 30 logs for chart and recent list
$stmt = $mysqli->prepare("SELECT id, log_date, steps, sleep_hours, weight_kg, bmi FROM health_logs WHERE user_id = ? ORDER BY log_date ASC LIMIT 30");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$logData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch spin status
$stmt = $mysqli->prepare("SELECT last_spin_date FROM users WHERE id = ?");
$userRow = null;
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $userRow = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}
$canSpin = ($userRow && (empty($userRow['last_spin_date']) || $userRow['last_spin_date'] < date('Y-m-d')));

// Fetch today's log
$todayLog = null;
$stmt = $mysqli->prepare("SELECT steps, sleep_hours, weight_kg FROM health_logs WHERE user_id = ? AND log_date = CURDATE()");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $todayLog = $res->fetch_assoc();
    }
    $stmt->close();
}
$targets = getDailyTargets($healthMode);

// Dynamic Greeting Logic
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $timeGreeting = 'Good morning';
    $timeEmoji = '🌅';
} elseif ($hour >= 12 && $hour < 18) {
    $timeGreeting = 'Good afternoon';
    $timeEmoji = '☀️';
} else {
    $timeGreeting = 'Good evening';
    $timeEmoji = '🌙';
}

$modeMotivations = [
    'bulking' => "Ready to crush your calorie goals and build muscle today?",
    'cutting' => "Stay sharp! Let's burn some fat and hit those steps today.",
    'maintain' => "Keep up the great balance. Consistency is key!"
];
$motivationText = $modeMotivations[$healthMode] ?? "Ready to conquer your health goals today?";

// Fetch Social Pulse (Latest 5 activities)
$stmt = $mysqli->prepare("
    (SELECT u.username, u.avatar_border, 'logged their health data! 🔥' as action, hl.created_at as time
     FROM health_logs hl JOIN users u ON hl.user_id = u.id)
    UNION
    (SELECT u.username, u.avatar_border, CONCAT('reached the ', m.title, ' milestone! 🏆') as action, um.achieved_at as time
     FROM user_milestones um JOIN users u ON um.user_id = u.id JOIN milestones m ON um.milestone_id = m.id)
    ORDER BY time DESC LIMIT 5
");
$pulseData = [];
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    $pulseData = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// Handle Spin Action via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'spin') {
    if ($canSpin) {
        $reward = [5, 10, 15, 20, 50][array_rand([0, 1, 2, 3, 4])]; // Jackpot 50 is rare? No just random for now
        addPoints($reward);
        $mysqli->query("UPDATE users SET last_spin_date = CURDATE() WHERE id = $user_id");
        echo json_encode(['success' => true, 'reward' => $reward]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Already spun today!']);
    }
    exit;
}

$page_title = 'Home';
include 'header.php';
?>

<?php
// Display Welcome Animation if just logged in
if (isset($_SESSION['show_welcome_anim']) && $_SESSION['show_welcome_anim'] === true):
    unset($_SESSION['show_welcome_anim']); // Show only once
?>
<div id="welcome-overlay">
  <div class="welcome-content">
    <div class="welcome-icon">✨</div>
    <h1 class="welcome-text">Welcome to the most health tracking web in indonesia</h1>
  </div>
</div>
<style>
#welcome-overlay {
  position: fixed;
  inset: 0;
  background: #111;
  z-index: 999999;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeOutOverlay 0.8s ease forwards 2.5s;
}
.welcome-content {
  text-align: center;
  animation: slideUpFade 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
.welcome-icon {
  font-size: 60px;
  margin-bottom: 20px;
  animation: floatIcon 2s ease-in-out infinite;
}
.welcome-text {
  font-size: 40px;
  font-weight: 800;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  max-width: 800px;
  line-height: 1.3;
  margin: 0;
  text-transform: capitalize;
}
@media (max-width: 600px) {
  .welcome-text { font-size: 28px; padding: 0 20px; }
}
@keyframes slideUpFade {
  0% { opacity: 0; transform: translateY(40px) scale(0.9); }
  100% { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes floatIcon {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-15px); }
}
@keyframes fadeOutOverlay {
  to { opacity: 0; visibility: hidden; }
}
</style>
<?php endif; ?>

<style>
/* =============================================
   CLEAN DASHBOARD LAYOUT — Full Screen + Responsive
   ============================================= */
/* Override global .page wrapper to allow full width */
main.page {
  max-width: 100% !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}

.dash-wrap {
  width: 100%;
  max-width: 1800px;
  margin: 0 auto;
  padding: 32px 40px;
  min-height: calc(100vh - 80px);
  box-sizing: border-box;
}

/* Top greeting bar */
.dash-greeting {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 12px;
}
.dash-greeting h1 {
  font-size: 26px;
  font-weight: 800;
  margin: 0;
  letter-spacing: -0.5px;
}
.dash-greeting h1 span { color: var(--primary); }
.dash-greeting .sub { color: var(--text-muted); font-size: 14px; margin: 3px 0 0; }

/* Mystery box pill */
.spin-pill {
  display: flex;
  align-items: center;
  gap: 10px;
  background: linear-gradient(135deg, var(--primary), #2272cc);
  color: white;
  padding: 11px 22px;
  border-radius: 50px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  border: none;
  transition: transform 0.2s, box-shadow 0.2s;
  box-shadow: 0 4px 20px rgba(58,134,255,0.35);
  white-space: nowrap;
}
.spin-pill:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(58,134,255,0.5); }

/* Main 2-column grid — fills available width */
.dash-grid {
  display: grid;
  grid-template-columns: 10fr 11fr; /* Nearly 50/50 but chart slightly wider */
  gap: 30px;
  align-items: start;
  width: 100%;
}

/* Stat cards row */
.stat-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 16px;
}
.stat-item {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 22px 16px;
  text-align: center;
  transition: transform 0.2s, box-shadow 0.2s;
}
.stat-item:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}
.stat-item .stat-val {
  font-size: 28px;
  font-weight: 800;
  color: var(--primary);
  display: block;
  line-height: 1.1;
}
.stat-item .stat-lbl {
  font-size: 11px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.6px;
  margin-top: 5px;
  display: block;
}

/* Today activity card */
.activity-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 32px;
  flex: 1;
}
.activity-card .card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}
.activity-card .card-title {
  font-size: 15px;
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.activity-card .card-action {
  font-size: 13px;
  color: var(--primary);
  text-decoration: none;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 6px 14px;
  border: 1px solid var(--primary);
  border-radius: 20px;
  transition: all 0.2s;
}
.activity-card .card-action:hover {
  background: var(--primary);
  color: white;
}

/* Progress rings — bigger but safe */
.rings-row {
  display: flex;
  flex-wrap: wrap;
  gap: 24px;
  justify-content: center;
  padding: 10px 0;
}
.ring-wrap { text-align: center; flex: 1; min-width: 110px; }
.ring-circle {
  width: 124px;
  height: 124px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  transition: transform 0.3s;
}
.ring-circle:hover { transform: scale(1.05); }
.ring-inner {
  width: 100px;
  height: 100px;
  background: var(--bg-card);
  border-radius: 50%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  box-shadow: inset 0 2px 6px rgba(0,0,0,0.15);
}
.ring-inner i { font-size: 24px; }
.ring-inner strong { font-size: 18px; font-weight: 800; }
.ring-label { font-size: 14px; color: var(--text-body); margin-top: 14px; font-weight: 700; }
.ring-sub { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

/* Chart card */
.chart-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 32px;
  height: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
}
.chart-card .card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  flex-shrink: 0;
}
.chart-card .card-title {
  font-size: 15px;
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.chart-canvas-wrap {
  flex: 1;
  min-height: 480px;
  position: relative;
  margin-top: 10px;
}

/* Recent Logs List */
.recent-logs-list {
  margin-top: 24px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 24px 32px;
}
.log-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 0;
  border-bottom: 1px solid var(--border);
}
.log-item:last-child { border-bottom: none; padding-bottom: 0; }
.log-date { font-weight: 600; color: var(--text-body); font-size: 14px; }
.log-metrics { display: flex; gap: 16px; font-size: 13px; color: var(--text-muted); }
.log-metrics span { display: flex; align-items: center; gap: 6px; }

/* Social pulse ticker */
.pulse-bar {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 10px 18px;
  display: flex;
  align-items: center;
  gap: 12px;
  overflow: hidden;
  margin-top: 20px;
  white-space: nowrap;
}
.pulse-label {
  color: var(--warning);
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 5px;
  flex-shrink: 0;
}
.pulse-ticker {
  display: inline-block;
  animation: ticker 25s linear infinite;
  font-size: 13px;
  color: var(--text-muted);
}
@keyframes ticker {
  0% { transform: translateX(60vw); }
  100% { transform: translateX(-100%); }
}
@keyframes glow-gold {
  from { box-shadow: 0 0 10px 2px rgba(255,215,0,0.4); }
  to { box-shadow: 0 0 20px 8px rgba(255,215,0,0.7); }
}
@keyframes glow-neon {
  from { box-shadow: 0 0 10px 2px rgba(0,255,255,0.4); }
  to { box-shadow: 0 0 20px 8px rgba(0,255,255,0.8); }
}
@keyframes glow-fire {
  from { box-shadow: 0 0 10px 2px rgba(255,69,0,0.5); border-color: #ff4500; }
  to { box-shadow: 0 0 25px 10px rgba(255,0,0,0.8); border-color: #ff0000; }
}

/* ── Responsive Breakpoints ── */
@media (max-width: 900px) {
  .dash-wrap { padding: 20px 16px; }
  .dash-grid { grid-template-columns: 1fr; }
  .chart-card { height: auto; }
  .chart-canvas-wrap { min-height: 260px; }
}
@media (max-width: 600px) {
  .dash-greeting h1 { font-size: 20px; }
  .stat-item .stat-val { font-size: 20px; }
  .stat-row { gap: 8px; }
  .stat-item { padding: 14px 8px; border-radius: 12px; }
  .rings-row { gap: 8px; padding: 0; }
  .ring-circle { width: 90px; height: 90px; }
  .ring-inner { width: 70px; height: 70px; }
  .ring-inner i { font-size: 15px; }
  .ring-inner strong { font-size: 12px; }
  .activity-card { padding: 16px; }
  .chart-card { padding: 16px; }
  .chart-canvas-wrap { min-height: 220px; }
  .pulse-bar { padding: 8px 12px; }
}
@media (max-width: 400px) {
  .rings-row { gap: 4px; }
  .ring-circle { width: 78px; height: 78px; }
  .ring-inner { width: 60px; height: 60px; }
  .ring-label { font-size: 10px; }
  .stat-item .stat-val { font-size: 18px; }
}
</style>

<div class="dash-wrap">

  <!-- ── Greeting Bar ── -->
  <div class="dash-greeting">
    <div>
      <h1><?php echo $timeGreeting; ?>, <span><?php echo e($username); ?></span> <?php echo $timeEmoji; ?></h1>
      <p class="sub"><?php echo $motivationText; ?> • <span style="opacity:0.7; font-size: 13px;"><?php echo date('l, F j'); ?></span></p>
    </div>
    <?php if ($canSpin): ?>
    <button class="spin-pill" onclick="openDailyBox()" id="btn-open-box">
      <i class="fas fa-gift"></i> Daily Box Ready!
    </button>
    <?php endif; ?>
  </div>

  <!-- ── Main Grid ── -->
  <div class="dash-grid">

    <!-- LEFT COLUMN -->
    <div>
      <!-- Stat Row -->
      <div class="stat-row">
        <div class="stat-item">
          <span class="stat-val"><?php echo number_format($points); ?></span>
          <span class="stat-lbl">Points</span>
        </div>
        <div class="stat-item">
          <span class="stat-val" style="text-transform:capitalize; font-size:16px;"><?php echo e($healthMode); ?></span>
          <span class="stat-lbl">Mode</span>
        </div>
        <div class="stat-item">
          <span class="stat-val" style="color:<?php echo $subscribed ? 'var(--success)' : 'var(--warning)'; ?>; font-size:16px;">
            <?php echo $subscribed ? 'Pro' : 'Free'; ?>
          </span>
          <span class="stat-lbl">Status</span>
        </div>
      </div>

      <!-- Today's Activity -->
      <div class="activity-card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-bullseye" style="color:var(--danger);"></i> Today's Activity</h3>
          <a href="health_log.php" class="card-action"><i class="fas fa-plus"></i> Log</a>
        </div>

        <?php
          $stepsPct = $todayLog ? min(100, round(($todayLog['steps'] / $targets['steps']['target']) * 100)) : 0;
          $sleepPct = $todayLog ? min(100, round(($todayLog['sleep_hours'] / $targets['sleep']['target']) * 100)) : 0;
        ?>

        <?php if (!$todayLog): ?>
          <div style="text-align:center; padding: 16px 0;">
            <i class="fas fa-clipboard-list" style="font-size:32px; color:var(--text-muted); opacity:0.4;"></i>
            <p class="muted" style="margin:12px 0 0; font-size:14px;">No log yet today. <a href="health_log.php" style="color:var(--primary);">Add one now →</a></p>
          </div>
        <?php else: ?>
          <div class="rings-row">
            <!-- Steps -->
            <div class="ring-wrap">
              <div class="ring-circle" style="background: conic-gradient(var(--primary) <?php echo $stepsPct; ?>%, var(--bg-secondary) 0);">
                <div class="ring-inner">
                  <i class="fas fa-walking" style="color:var(--primary);"></i>
                  <strong><?php echo $stepsPct; ?>%</strong>
                </div>
              </div>
              <div class="ring-label">Steps</div>
              <div class="ring-sub"><?php echo number_format($todayLog['steps']); ?> / <?php echo number_format($targets['steps']['target']); ?></div>
            </div>
            <!-- Sleep -->
            <div class="ring-wrap">
              <div class="ring-circle" style="background: conic-gradient(#3498db <?php echo $sleepPct; ?>%, var(--bg-secondary) 0);">
                <div class="ring-inner">
                  <i class="fas fa-bed" style="color:#3498db;"></i>
                  <strong><?php echo $sleepPct; ?>%</strong>
                </div>
              </div>
              <div class="ring-label">Sleep</div>
              <div class="ring-sub"><?php echo $todayLog['sleep_hours']; ?> / <?php echo $targets['sleep']['target']; ?> hrs</div>
            </div>
            <!-- Weight -->
            <div class="ring-wrap">
              <div class="ring-circle" style="background: conic-gradient(#9b59b6 100%, var(--bg-secondary) 0);">
                <div class="ring-inner">
                  <i class="fas fa-weight" style="color:#9b59b6;"></i>
                  <strong style="font-size:12px;"><?php echo $todayLog['weight_kg']; ?></strong>
                </div>
              </div>
              <div class="ring-label">Weight</div>
              <div class="ring-sub"><?php echo $todayLog['weight_kg']; ?> kg</div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Recent Logs -->
      <?php if (!empty($logData)): ?>
      <div class="recent-logs-list">
        <div class="card-header" style="margin-bottom: 16px;">
          <h3 class="card-title" style="margin:0; font-size:15px; font-weight:700;"><i class="fas fa-history" style="color:var(--text-muted);"></i> Recent Entries</h3>
        </div>
        <div>
          <?php 
            $recent = array_slice(array_reverse($logData), 0, 4); // Get last 4
            foreach ($recent as $l):
          ?>
            <div class="log-item">
              <div class="log-date"><?php echo date('M j', strtotime($l['log_date'])); ?></div>
              <div class="log-metrics">
                <span><i class="fas fa-walking" style="color:var(--primary);"></i> <?php echo number_format($l['steps']); ?></span>
                <span><i class="fas fa-bed" style="color:#3498db;"></i> <?php echo $l['sleep_hours']; ?>h</span>
                <span><i class="fas fa-weight" style="color:#9b59b6;"></i> <?php echo $l['weight_kg']; ?>kg</span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- RIGHT COLUMN: Weight Chart -->
    <div class="chart-card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-line" style="color:var(--primary);"></i> Weight Progress</h3>
        <span class="muted" style="font-size:12px;">Last 30 days</span>
      </div>
      <div class="chart-canvas-wrap">
        <?php if (count($logData) < 2): ?>
          <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background:rgba(0,0,0,0.45); border-radius:10px; backdrop-filter:blur(4px); z-index:5;">
            <i class="fas fa-chart-bar" style="font-size:28px; color:rgba(255,255,255,0.3); margin-bottom:10px;"></i>
            <p style="color:white; font-size:13px; margin:0;">Log at least 2 times to see your trend</p>
            <a href="health_log.php" class="hero-btn primary" style="margin-top:12px; padding:8px 16px; font-size:13px;">Log Now</a>
          </div>
        <?php endif; ?>
        <canvas id="healthChart"></canvas>
      </div>
    </div>

  </div><!-- /dash-grid -->

  <!-- ── Social Pulse Ticker ── -->
  <?php if (!empty($pulseData)): ?>
  <div class="pulse-bar">
    <span class="pulse-label"><i class="fas fa-bolt"></i> Live</span>
    <div class="pulse-ticker">
      <?php foreach ($pulseData as $p): ?>
        <span style="margin-right:40px;">
          <strong style="color:var(--text-body);"><?php echo e($p['username']); ?></strong>
          <span><?php echo $p['action']; ?></span>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /dash-wrap -->

<!-- Hidden spin section for AJAX target -->
<div id="daily-spin-section" style="display:none;"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
  let rawData = <?php echo json_encode($logData); ?>;
  const ctx = document.getElementById('healthChart');
  if (!ctx) return;

  if (rawData.length < 2) {
    rawData = [];
    for (let i = 6; i >= 0; i--) {
      let d = new Date(); d.setDate(d.getDate() - i);
      rawData.push({ log_date: d.toISOString().split('T')[0], weight_kg: 0 });
    }
  }

  const labels     = rawData.map(r => { const d = new Date(r.log_date); return d.toLocaleDateString('en-US', { month:'short', day:'numeric' }); });
  const weightData = rawData.map(r => parseFloat(r.weight_kg));
  const primary    = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#3a86ff';
  const grad       = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
  grad.addColorStop(0, primary + '55');
  grad.addColorStop(1, primary + '00');

  window.myHealthChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Weight (kg)',
        data: weightData,
        borderColor: primary,
        backgroundColor: grad,
        borderWidth: 2.5,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#fff',
        pointBorderColor: primary,
        pointBorderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { y: { duration: 1800, easing: 'easeOutQuart' } },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(20,20,30,0.92)',
          titleFont: { size: 13, family: 'Inter' },
          bodyFont: { size: 13, family: 'Inter' },
          padding: 10,
          cornerRadius: 8,
          displayColors: false,
          callbacks: { label: c => c.parsed.y + ' kg' }
        }
      },
      scales: {
        x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 11 } } },
        y: {
          reverse: "<?php echo e($healthMode); ?>" === 'cutting',
          beginAtZero: false,
          grid: { color: 'rgba(128,128,128,0.1)' },
          ticks: { font: { family: 'Inter', size: 11 } }
        }
      }
    }
  });
})();

function openDailyBox() {
  const btn = document.getElementById('btn-open-box');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Opening...';
  btn.style.pointerEvents = 'none';

  fetch('index.php?action=spin')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        btn.innerHTML = `<i class="fas fa-check-circle"></i> +${data.reward} pts!`;
        btn.style.background = 'var(--success)';
        if (typeof confetti !== 'undefined') {
          confetti({ particleCount: 120, spread: 70, origin: { y: 0.5 }, colors: ['#ffd700','#ff8c00','#16a085'] });
        }
        setTimeout(() => window.location.reload(), 2200);
      } else {
        btn.innerHTML = '<i class="fas fa-times-circle"></i> Already claimed!';
      }
    });
}
</script>

<?php include 'footer.php'; ?>
