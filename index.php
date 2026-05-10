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

// Fetch last 30 logs for chart
$stmt = $mysqli->prepare("SELECT log_date, weight_kg, bmi FROM health_logs WHERE user_id = ? ORDER BY log_date ASC LIMIT 30");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$logData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch spin status
$stmt = $mysqli->prepare("SELECT last_spin_date FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$canSpin = (!$userRow['last_spin_date'] || $userRow['last_spin_date'] < date('Y-m-d'));

// Fetch today's log
$todayLog = null;
$stmt = $mysqli->prepare("SELECT steps, sleep_hours, weight_kg FROM health_logs WHERE user_id = ? AND log_date = CURDATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $todayLog = $res->fetch_assoc();
}
$stmt->close();
$targets = getDailyTargets($healthMode);

// Fetch Social Pulse (Latest 5 activities)
$stmt = $mysqli->prepare("
    (SELECT u.username, u.avatar_border, 'logged their health data! 🔥' as action, hl.created_at as time
     FROM health_logs hl JOIN users u ON hl.user_id = u.id)
    UNION
    (SELECT u.username, u.avatar_border, CONCAT('reached the ', m.title, ' milestone! 🏆') as action, um.achieved_at as time
     FROM user_milestones um JOIN users u ON um.user_id = u.id JOIN milestones m ON um.milestone_id = m.id)
    ORDER BY time DESC LIMIT 5
");
$stmt->execute();
$pulseData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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

<!-- =============================================
     HERO SECTION
     ============================================= -->
<section class="home-hero">
  <div class="home-hero-inner">
    <div class="home-hero-text">
      <div class="hero-tag">Welcome Back, <?php echo e($username); ?></div>
      <h1>Your Health Dashboard</h1>
      <p class="muted">
        No spam notifications. No social feeds. Just your data, your progress, and your goals all in one clean place.
      </p>
      <div class="hero-actions">
        <a href="health_log.php" class="hero-btn primary"> Log Today</a>
        <a href="profile.php" class="hero-btn ghost"> My Profile</a>
      </div>
    </div>

    <!-- Hero Stats Banner -->
    <div class="home-hero-banner">
      <div class="banner-pill">
        <span class="pill-label">Your Mode</span>
        <span class="pill-value" style="text-transform: capitalize;"><?php echo e($healthMode); ?></span>
      </div>
      <div class="banner-box-row">
        <div class="banner-box">
          <span class="label">Health Points</span>
          <strong><?php echo number_format($points); ?></strong>
          <span class="unit">total earned</span>
        </div>
        <div class="banner-box">
          <span class="label">Mode</span>
          <strong style="text-transform: capitalize;"><?php echo e($healthMode); ?></strong>
          <span class="unit">current goal</span>
        </div>
        <div class="banner-box">
          <span class="label">Status</span>
          <strong style="color: <?php echo $subscribed ? 'var(--success)' : 'var(--warning)'; ?>;">
            <?php echo $subscribed ? 'Pro' : 'Free'; ?>
          </strong>
          <span class="unit"><?php echo $subscribed ? 'all features' : 'upgrade available'; ?></span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- =============================================
     DAILY GAMIFICATION (SPIN BOX)
     ============================================= -->
<?php if ($canSpin): ?>
<section class="home-section" id="daily-spin-section">
  <div class="card" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
    <div>
      <h2 style="color: white; margin-bottom: 8px;"><i class="fas fa-gift" style="margin-right: 8px;"></i> Daily Mystery Box</h2>
      <p style="opacity: 0.9; margin: 0;">You have a free daily box to open! Claim your random Health Points now.</p>
    </div>
    <button onclick="openDailyBox()" id="btn-open-box" style="background: white; color: var(--primary); font-weight: 800; border: none; padding: 12px 24px; border-radius: 50px; cursor: pointer; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
      <i class="fas fa-box-open" style="margin-right: 8px;"></i> OPEN BOX
    </button>
  </div>
</section>
<?php endif; ?>

<!-- =============================================
     SOCIAL PULSE (LIVE FEED)
     ============================================= -->
<section class="home-section">
  <div class="card" style="padding: 16px 24px; overflow: hidden; white-space: nowrap; border-left: 4px solid var(--warning);">
    <div style="display: flex; align-items: center;">
      <strong style="color: var(--warning); margin-right: 16px; display: flex; align-items: center; gap: 6px;">
        <i class="fas fa-bolt"></i> Live Pulse
      </strong>
      <div class="pulse-ticker" style="display: inline-block; animation: ticker 20s linear infinite;">
        <?php foreach ($pulseData as $pulse): ?>
          <span style="margin-right: 30px; color: var(--text-muted);">
            <strong style="color: var(--text-body);"><i class="fas fa-user-circle"></i> <?php echo e($pulse['username']); ?></strong> <?php echo $pulse['action']; ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<style>
@keyframes ticker {
  0% { transform: translateX(100%); }
  100% { transform: translateX(-100%); }
}
.progress-ring {
  width: 100px; height: 100px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  position: relative;
  background: var(--bg-secondary);
}
.progress-ring-inner {
  width: 80px; height: 80px;
  background: var(--bg-card);
  border-radius: 50%;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  z-index: 2;
}
</style>

<!-- =============================================
     TODAY'S PROGRESS RINGS
     ============================================= -->
<section class="home-section">
  <div class="card big-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin:0;"><i class="fas fa-bullseye" style="color: var(--danger); margin-right: 8px;"></i> Today's Activity</h2>
      <a href="health_log.php" class="hero-btn primary" style="padding: 8px 16px; font-size: 14px;">Update</a>
    </div>
    
    <div style="display: flex; gap: 32px; flex-wrap: wrap; justify-content: center;">
      <?php 
        $stepsPct = $todayLog ? min(100, round(($todayLog['steps'] / $targets['steps']['target']) * 100)) : 0;
        $sleepPct = $todayLog ? min(100, round(($todayLog['sleep_hours'] / $targets['sleep']['target']) * 100)) : 0;
      ?>
      
      <!-- Steps Ring -->
      <div style="text-align: center;">
        <div class="progress-ring" style="background: conic-gradient(var(--primary) <?php echo $stepsPct; ?>%, var(--bg-secondary) 0);">
          <div class="progress-ring-inner">
            <i class="fas fa-walking" style="color: var(--primary); font-size: 20px;"></i>
            <strong style="margin-top: 4px;"><?php echo $stepsPct; ?>%</strong>
          </div>
        </div>
        <p class="muted small" style="margin-top: 12px; font-weight: bold;"><?php echo $todayLog ? number_format($todayLog['steps']) : 0; ?> / <?php echo number_format($targets['steps']['target']); ?></p>
      </div>

      <!-- Sleep Ring -->
      <div style="text-align: center;">
        <div class="progress-ring" style="background: conic-gradient(#3498db <?php echo $sleepPct; ?>%, var(--bg-secondary) 0);">
          <div class="progress-ring-inner">
            <i class="fas fa-bed" style="color: #3498db; font-size: 20px;"></i>
            <strong style="margin-top: 4px;"><?php echo $sleepPct; ?>%</strong>
          </div>
        </div>
        <p class="muted small" style="margin-top: 12px; font-weight: bold;"><?php echo $todayLog ? $todayLog['sleep_hours'] : 0; ?> / <?php echo $targets['sleep']['target']; ?> hrs</p>
      </div>
    </div>
  </div>
</section>

<!-- =============================================
     YOUR PROGRESS CHART
     ============================================= -->
<section class="home-section">
  <div class="card big-card">
    <h2>📈 Your Progress (Last 30 Days)</h2>
    <div style="height: 300px; width: 100%; margin-top: 20px; position: relative;">
      <?php if (count($logData) < 2): ?>
        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); z-index: 10; border-radius: 12px; backdrop-filter: blur(3px);">
          <p style="color: white; font-weight: 500; margin-bottom: 12px;">Log your weight at least 2 times to see your real progress!</p>
          <a href="health_log.php" class="hero-btn primary" style="display: inline-flex;">📝 Log Health Now</a>
        </div>
      <?php endif; ?>
      <canvas id="healthChart"></canvas>
    </div>
  </div>
</section>

<!-- =============================================
     WHAT YOU CAN DO
     ============================================= -->
<section class="home-section">
  <div class="home-section-header">
    <h2>What you can do here</h2>
    <p class="muted">Everything is focused on the basics that actually matter for your health.</p>
  </div>

  <div class="service-grid">
    <a class="service-card" href="health_log.php">
      <div class="service-icon">📝</div>
      <h3>Daily Log</h3>
      <p class="muted">Log your steps, sleep, and weight every day. BMI calculates automatically.</p>
    </a>

    <a class="service-card" href="profile.php">
      <div class="service-icon">📊</div>
      <h3>Progress</h3>
      <p class="muted">View your averages for steps, sleep, weight, and BMI. Track your journey over time.</p>
    </a>

    <a class="service-card" href="health_modes.php">
      <div class="service-icon">🎯</div>
      <h3>Health Modes</h3>
      <p class="muted">Choose between Maintain, Bulking, or Cutting. Each mode sets different daily targets.</p>
    </a>

    <a class="service-card" href="tips.php">
      <div class="service-icon">💡</div>
      <h3>Health Tips</h3>
      <p class="muted">Practical, no-nonsense tips for steps, sleep, hydration, and exercise.</p>
    </a>
  </div>
</section>

<!-- =============================================
     PREMIUM FEATURES (SUBSCRIPTION PROMO)
     ============================================= -->
<section class="home-section">
  <div class="home-section-header">
    <h2>Premium Features</h2>
    <p class="muted">
      <?php if ($subscribed): ?>
        You have full access to all premium features. Keep it up!
      <?php else: ?>
        Unlock these features with a simple subscription.
      <?php endif; ?>
    </p>
  </div>

  <div class="service-grid">
    <a class="service-card <?php echo !$subscribed ? 'card-locked' : ''; ?>" href="milestone.php">
      <div class="service-icon"><i class="fas fa-trophy" style="color: var(--warning);"></i></div>
      <h3>Milestones <?php echo !$subscribed ? '<span class="lock-badge"><i class="fas fa-lock"></i> Pro</span>' : ''; ?></h3>
      <p class="muted">Earn achievements based on your health activity. Collect points and unlock rewards.</p>
    </a>

    <a class="service-card" href="chatbot.php">
      <div class="service-icon"><i class="fas fa-robot" style="color: var(--primary);"></i></div>
      <h3>AI Assistant</h3>
      <p class="muted">Ask questions about your health goals, modes, points, and get personalized guidance.</p>
    </a>

    <a class="service-card <?php echo !$subscribed ? 'card-locked' : ''; ?>" href="subscription.php">
      <div class="service-icon"><i class="fas fa-gem" style="color: var(--secondary);"></i></div>
      <h3>Subscription <?php echo !$subscribed ? '<span class="lock-badge"><i class="fas fa-lock"></i></span>' : '<span class="lock-badge" style="background: var(--success); color: white;"><i class="fas fa-check"></i> Active</span>'; ?></h3>
      <p class="muted">
        <?php if ($subscribed): ?>
          Your subscription is active. Enjoy all premium features!
        <?php else: ?>
          Rp 10,000/month — unlock milestones, AI features, and more.
        <?php endif; ?>
      </p>
    </a>

    <a class="service-card" href="profile.php">
      <div class="service-icon"><i class="fas fa-star" style="color: #f1c40f;"></i></div>
      <h3>Health Points</h3>
      <p class="muted">You have <strong><?php echo number_format($points); ?></strong> points. Earn more by logging daily and hitting your targets.</p>
    </a>
  </div>
</section>

<!-- =============================================
     AI ASSISTANT PROMO
     ============================================= -->
<section class="home-section">
  <div class="info-card">
    <h3><i class="fas fa-robot" style="color: var(--primary); margin-right: 8px;"></i> Try the Zoonexa AI Assistant</h3>
    <p>
      Not sure what to do? Ask the AI! It can help with your daily targets, explain health modes, 
      tell you how to earn points, and answer questions about your subscription benefits. Available 24/7.
    </p>
    <a href="chatbot.php" class="hero-btn primary" style="display: inline-flex; margin-top: 14px;">
      <i class="fas fa-comment-dots" style="margin-right: 6px;"></i> Chat Now
    </a>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function() {
    let rawData = <?php echo json_encode($logData); ?>;
    const ctx = document.getElementById('healthChart');
    if (!ctx) return;

    if (window.myHealthChart) {
        window.myHealthChart.destroy();
    }

    // Use dummy flat data if user has less than 2 logs
    if (rawData.length < 2) {
        rawData = [];
        for (let i = 6; i >= 0; i--) {
            let d = new Date();
            d.setDate(d.getDate() - i);
            rawData.push({
                log_date: d.toISOString().split('T')[0],
                weight_kg: 0 // Flat line at 0
            });
        }
    }

    // Convert date string to readable format (e.g. May 08)
    const labels = rawData.map(r => {
        const d = new Date(r.log_date);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    const weightData = rawData.map(r => parseFloat(r.weight_kg));

    // Get CSS variable for color
    const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#3a86ff';

    const healthMode = "<?php echo e($healthMode); ?>";
    const shouldReverse = (healthMode === 'cutting');

    // Create gradient fill
    let gradientFill = ctx.getContext("2d").createLinearGradient(0, 0, 0, 400);
    gradientFill.addColorStop(0, primaryColor + '66'); // 40% opacity
    gradientFill.addColorStop(1, primaryColor + '00'); // 0% opacity

    window.myHealthChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Weight (kg)',
                data: weightData,
                borderColor: primaryColor,
                backgroundColor: gradientFill,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#fff',
                pointBorderColor: primaryColor,
                pointBorderWidth: 3,
                pointRadius: 4,
                pointHoverRadius: 7,
                pointHoverBackgroundColor: primaryColor,
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                y: {
                    duration: 2000,
                    delay: 200,
                    easing: 'easeOutQuart'
                }
            },
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(26, 26, 26, 0.9)',
                    titleFont: { size: 14, family: 'Inter', weight: 'bold' },
                    bodyFont: { size: 14, family: 'Inter' },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' kg';
                        }
                    }
                }
            },

            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: "'Inter', sans-serif" } }
                },
                y: {
                    reverse: shouldReverse,
                    beginAtZero: false,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { font: { family: "'Inter', sans-serif" } }
                }
            }
        }
    });
  })();

  function openDailyBox() {
    const btn = document.getElementById('btn-open-box');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> OPENING...';
    btn.style.pointerEvents = 'none';

    fetch('index.php?action=spin')
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          btn.innerHTML = `<i class="fas fa-check-circle"></i> +${data.reward} POINTS!`;
          btn.style.background = 'var(--success)';
          btn.style.color = 'white';
          
          if (typeof confetti !== 'undefined') {
              confetti({
                  particleCount: 150,
                  spread: 80,
                  origin: { y: 0.6 },
                  colors: ['#ffd700', '#ff8c00', '#16a085']
              });
          }
          
          // Hide section after 3 seconds
          setTimeout(() => {
              document.getElementById('daily-spin-section').style.display = 'none';
              // Reload to update total points
              window.location.reload();
          }, 2500);
        } else {
          btn.innerHTML = 'Already Claimed!';
        }
      });
  }
</script>

<?php include 'footer.php'; ?>
