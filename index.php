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
      <div class="service-icon">🏆</div>
      <h3>Milestones <?php echo !$subscribed ? '<span class="lock-badge">🔒 Pro</span>' : ''; ?></h3>
      <p class="muted">Earn achievements based on your health activity. Collect points and unlock rewards.</p>
    </a>

    <a class="service-card" href="chatbot.php">
      <div class="service-icon">🤖</div>
      <h3>AI Assistant</h3>
      <p class="muted">Ask questions about your health goals, modes, points, and get personalized guidance.</p>
    </a>

    <a class="service-card <?php echo !$subscribed ? 'card-locked' : ''; ?>" href="subscription.php">
      <div class="service-icon">💎</div>
      <h3>Subscription <?php echo !$subscribed ? '<span class="lock-badge">🔒</span>' : '<span class="lock-badge" style="background: var(--success); color: white;">✓ Active</span>'; ?></h3>
      <p class="muted">
        <?php if ($subscribed): ?>
          Your subscription is active. Enjoy all premium features!
        <?php else: ?>
          Rp 10,000/month — unlock milestones, AI features, and more.
        <?php endif; ?>
      </p>
    </a>

    <a class="service-card" href="profile.php">
      <div class="service-icon">⭐</div>
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
    <h3>🤖 Try the Zoonexa AI Assistant</h3>
    <p>
      Not sure what to do? Ask the AI! It can help with your daily targets, explain health modes, 
      tell you how to earn points, and answer questions about your subscription benefits. Available 24/7.
    </p>
    <a href="chatbot.php" class="hero-btn primary" style="display: inline-flex; margin-top: 14px;">
      🤖 Chat Now
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

    window.myHealthChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Weight (kg)',
                data: weightData,
                borderColor: primaryColor,
                backgroundColor: 'rgba(58, 134, 255, 0.15)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: primaryColor,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    padding: 12,
                    titleFont: { size: 14, family: "'Inter', sans-serif" },
                    bodyFont: { size: 14, family: "'Inter', sans-serif" },
                    displayColors: false
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
</script>

<?php include 'footer.php'; ?>
