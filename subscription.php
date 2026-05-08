<?php
require 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$subscribed = isSubscribed();
$activeSubscription = getActiveSubscription();
$locked = isset($_GET['locked']) && $_GET['locked'] == '1';

// =============================================
// HANDLE: Submit Manual Payment
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_payment') {
    if ($subscribed) {
        $error = 'You already have an active subscription.';
    } else {
        // Generate unique order ID
        $orderId = 'zoonexa-' . $user_id . '-' . time();
        
        // Simpan pending subscription ke DB
        $stmt = $mysqli->prepare("
            INSERT INTO subscriptions (user_id, midtrans_order_id, status, amount_paid, payment_method)
            VALUES (?, ?, 'pending', 10000, 'qris_manual')
        ");
        $stmt->bind_param('is', $user_id, $orderId);
        $stmt->execute();
        $stmt->close();

        // Redirect ke pending page
        header('Location: subscription-pending.php?order_id=' . $orderId);
        exit;
    }
}

// =============================================
// HANDLE: Midtrans notification callback (webhook)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'notification') {
    // This is handled in payment-callback.php instead
    // See payment-callback.php for the webhook handler
    exit;
}

$page_title = 'Subscription';
include 'header.php';
?>

<section class="page-section">
  <div class="page-header">
    <div>
      <h1>Subscription</h1>
      <p class="muted">Unlock premium features with a simple monthly subscription.</p>
    </div>
  </div>

  <!-- Already Subscribed -->
  <?php if ($subscribed): ?>
  <div class="card big-card">
    <div class="sub-active-banner">
      <div class="sub-active-icon">✅</div>
      <div>
        <h2 style="color: var(--success);">Your Subscription is Active</h2>
        <p class="muted">You have full access to all Zoonexa Pro features.</p>
      </div>
    </div>

    <div class="sub-details">
      <div class="sub-detail-item">
        <span class="sub-label">Status</span>
        <span class="sub-value" style="color: var(--success); font-weight: 700;">Active</span>
      </div>
      <?php if ($activeSubscription): ?>
      <div class="sub-detail-item">
        <span class="sub-label">Started</span>
        <span class="sub-value"><?php echo date('F j, Y', strtotime($activeSubscription['start_date'])); ?></span>
      </div>
      <div class="sub-detail-item">
        <span class="sub-label">Expires</span>
        <span class="sub-value"><?php echo date('F j, Y', strtotime($activeSubscription['end_date'])); ?></span>
      </div>
      <div class="sub-detail-item">
        <span class="sub-label">Amount Paid</span>
        <span class="sub-value">Rp <?php echo number_format($activeSubscription['amount_paid']); ?></span>
      </div>
      <?php endif; ?>
    </div>

    <div class="sub-features">
      <h3>Your Pro Benefits</h3>
      <div class="features-grid">
        <div class="feature-item active"><span>✓</span> Milestone Achievements</div>
        <div class="feature-item active"><span>✓</span> AI Health Assistant</div>
        <div class="feature-item active"><span>✓</span> Health Points Rewards</div>
        <div class="feature-item active"><span>✓</span> Detailed Progress Tracking</div>
        <div class="feature-item active"><span>✓</span> All Health Modes</div>
        <div class="feature-item active"><span>✓</span> Priority Support</div>
      </div>
    </div>
  </div>

  <!-- Not Subscribed -->
  <?php else: ?>
  <div class="card big-card">
    <?php if ($locked): ?>
      <div class="alert info">
        🔒 This feature requires a subscription. Upgrade below to unlock all premium features.
      </div>
    <?php endif; ?>

    <div class="sub-hero">
      <div class="sub-hero-icon">💎</div>
      <h2>Zoonexa Pro</h2>
      <p class="muted">Get the most out of your health tracking experience.</p>
      <div class="sub-price">
        <span class="price-amount">Rp 10,000</span>
        <span class="price-period">/ month</span>
      </div>
    </div>

    <!-- What's included -->
    <div class="sub-features">
      <h3>What's Included</h3>
      <div class="features-grid">
        <div class="feature-item"><span>🏆</span> Milestone Achievements</div>
        <div class="feature-item"><span>🤖</span> AI Health Assistant (Advanced)</div>
        <div class="feature-item"><span>⭐</span> Health Points & Rewards</div>
        <div class="feature-item"><span>📊</span> Detailed Progress Analysis</div>
        <div class="feature-item"><span>🎯</span> All Health Modes & Targets</div>
        <div class="feature-item"><span>💬</span> Priority Support</div>
      </div>
    </div>

    <!-- Pay Button -->
    <div class="sub-pay-section" style="text-align: center; border-top: 1px solid var(--border); padding-top: 20px; margin-top: 20px;">
      <h3 style="margin-bottom: 15px;">Scan QRIS to Pay</h3>
      
      <div style="background: white; padding: 15px; border-radius: 12px; display: inline-block; margin-bottom: 20px;">
        <!-- QRIS Image -->
        <img src="qris.png" alt="QRIS Zoonexa" style="width: 200px; height: 200px; display: block; border-radius: 8px; object-fit: contain; background: white;">
      </div>
      <p class="muted" style="margin-bottom: 20px;">Please scan the QR above via Mobile Banking or your preferred E-Wallet (GoPay, OVO, Dana, etc).</p>

      <?php if (isset($error)): ?>
        <div class="alert"><?php echo e($error); ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="action" value="submit_payment">
        <button type="submit" class="hero-btn primary" style="width: 100%; justify-content: center;">
          ✅ I Have Transferred
        </button>
      </form>
      <p class="muted small" style="margin-top: 12px; text-align: center;">
        The admin will verify your payment within a maximum of 24 hours.
      </p>
    </div>
  </div>
  <?php endif; ?>

  <!-- FAQ -->
  <div class="card" style="margin-top: 24px;">
    <h2>Frequently Asked Questions</h2>
    <div class="faq-list">
      <div class="faq-item">
        <h4>What payment methods are supported?</h4>
        <p class="muted">We accept GoPay, OVO, ShopeePay, Dana, and most bank transfers (BCA, Mandiri, BNI, BRI) via Midtrans.</p>
      </div>
      <div class="faq-item">
        <h4>Can I cancel my subscription?</h4>
        <p class="muted">Yes. Your access will continue until the end of your current billing period. You won't be charged again after that.</p>
      </div>
      <div class="faq-item">
        <h4>What happens when my subscription expires?</h4>
        <p class="muted">You'll still be able to use free features like Daily Log, Health Modes, and Tips. Premium features like Milestones will be locked until you renew.</p>
      </div>
      <div class="faq-item">
        <h4>Is my payment secure?</h4>
        <p class="muted">Yes. All payments are processed through Midtrans, which is PCI-DSS compliant. We never store your payment details.</p>
      </div>
    </div>
  </div>
</section>

<!-- End Subscription Page -->

<?php include 'footer.php'; ?>
