<?php
// subscription-success.php
require 'config.php';
requireLogin();

$orderId = $_GET['order_id'] ?? '';

// Verify transaksi dari Midtrans API
$verified = false;
if ($orderId !== '') {
    $ch = curl_init();
    $url = (MIDTRANS_IS_SANDBOX)
        ? 'https://api.sandbox.midtrans.com/v1/payment/transactions/' . $orderId
        : 'https://api.midtrans.com/v1/payment/transactions/' . $orderId;

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':'),
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    $transactionData = json_decode($response, true);

    // Kalau payment_status = settlement atau capture, aktivasi langsung
    if ($transactionData && in_array($transactionData['payment_status'], ['settlement', 'capture'])) {
        $startDate     = date('Y-m-d');
        $endDate       = date('Y-m-d', strtotime('+30 days'));
        $paymentMethod = $transactionData['payment_type'] ?? 'unknown';
        $transactionId = $transactionData['transaction_id'] ?? '';

        // Update subscription
        $stmt = $mysqli->prepare("
            UPDATE subscriptions 
            SET status = 'active', start_date = ?, end_date = ?, payment_method = ?, midtrans_transaction_id = ?
            WHERE midtrans_order_id = ?
        ");
        $stmt->bind_param("sssss", $startDate, $endDate, $paymentMethod, $transactionId, $orderId);
        $stmt->execute();
        $stmt->close();

        // Update user
        $stmt = $mysqli->prepare("UPDATE users SET subscription_status = 1 WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();

        // Award milestones
        checkAndAwardMilestones();

        $verified = true;
    }
}

$page_title = 'Payment Successful';
include 'header.php';
?>

<section class="page-section">
  <div class="card big-card" style="max-width: 600px; margin: 0 auto; text-align: center;">
    <?php if ($verified): ?>
      <div class="success-icon">🎉</div>
      <h1 style="color: var(--success);">Payment Successful!</h1>
      <p class="muted">Welcome to Zoonexa Pro! You now have access to all premium features.</p>

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
          <span class="sub-label">Expires</span>
          <span class="sub-value"><?php echo date('F j, Y', strtotime('+30 days')); ?></span>
        </div>
      </div>

      <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <a href="milestone.php" class="hero-btn primary">🏆 View Milestones</a>
        <a href="index.php" class="hero-btn ghost">Go to Home</a>
      </div>
    <?php else: ?>
      <div class="success-icon">⚠️</div>
      <h1 style="color: var(--warning);">Verification Pending</h1>
      <p class="muted">Your payment is being processed. Please wait a moment and check back.</p>
      <a href="subscription.php" class="hero-btn primary" style="margin-top: 20px; display: inline-block;">Back to Subscription</a>
    <?php endif; ?>
  </div>
</section>

<?php include 'footer.php'; ?>
