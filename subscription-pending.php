<?php
// subscription-pending.php
require 'config.php';
requireLogin();

$orderId = $_GET['order_id'] ?? '';

$page_title = 'Payment Pending';
include 'header.php';
?>

<section class="page-section">
  <div class="card big-card" style="max-width: 600px; margin: 0 auto; text-align: center;">
    <div class="success-icon">⏳</div>
    <h1 style="color: var(--warning);">Payment Pending</h1>
    <p class="muted">
      Thank you for confirming your payment! Our admin team will verify your transaction 
      within a maximum of 24 hours.
    </p>

    <div class="sub-details" style="margin: 24px 0; text-align: left;">
      <div class="sub-detail-item">
        <span class="sub-label">Order ID</span>
        <span class="sub-value"><?php echo e($orderId); ?></span>
      </div>
      <div class="sub-detail-item">
        <span class="sub-label">Amount</span>
        <span class="sub-value">Rp 10,000</span>
      </div>
      <div class="sub-detail-item">
        <span class="sub-label">Status</span>
        <span class="sub-value" style="color: var(--warning); font-weight: 700;">Pending</span>
      </div>
    </div>

    <div class="info-card" style="text-align: left; margin: 20px 0;">
      <h3>💡 Next Steps</h3>
      <p>
        You just need to wait. Once the admin confirms your transaction in the system,
        your account will automatically upgrade to Zoonexa Pro and all premium features will be unlocked.
      </p>
    </div>

    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
      <a href="subscription.php" class="hero-btn primary">Back to Subscription</a>
      <a href="index.php" class="hero-btn ghost">Go to Home</a>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>
