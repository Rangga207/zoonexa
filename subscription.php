<?php
require 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$subscribed = isSubscribed();
$activeSubscription = getActiveSubscription();
$locked = isset($_GET['locked']) && $_GET['locked'] == '1';

// =============================================
// HANDLE: Create Midtrans payment (AJAX endpoint)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_payment') {
    header('Content-Type: application/json');

    if ($subscribed) {
        echo json_encode(['error' => 'You already have an active subscription.']);
        exit;
    }

    // Generate unique order ID
    $orderId = 'zoonexa-' . $user_id . '-' . time();

    // Midtrans Snap configuration
    $payload = [
        'transaction_details' => [
            'order_id'  => $orderId,
            'amount'    => 10000,
        ],
        'customer_details' => [
            'name'  => $_SESSION['username'],
            'email' => $_SESSION['username'] . '@zoonexa.local', // placeholder email
        ],
        'item_details' => [
            [
                'id'       => 'zoonexa-sub-monthly',
                'price'    => 10000,
                'quantity' => 1,
                'name'     => 'Zoonexa Pro — 1 Month',
            ]
        ],
    ];

    // Call Midtrans Snap API
    $ch = curl_init();
    $url = (MIDTRANS_IS_SANDBOX)
        ? 'https://api.sandbox.midtrans.com/v1/payment/transactions'
        : 'https://api.midtrans.com/v1/payment/transactions';

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':'),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201) {
        $snapData = json_decode($response, true);

        // Simpan pending subscription ke DB
        $stmt = $mysqli->prepare("
            INSERT INTO subscriptions (user_id, midtrans_order_id, status, amount_paid)
            VALUES (?, ?, 'pending', 10000)
        ");
        $stmt->bind_param('is', $user_id, $orderId);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success'  => true,
            'snap_token' => $snapData['token'],
            'order_id'   => $orderId,
        ]);
    } else {
        error_log('Midtrans error: ' . $response);
        echo json_encode(['error' => 'Payment initialization failed. Please try again.']);
    }
    exit;
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
    <div class="sub-pay-section">
      <button type="button" id="pay-btn" class="btn-pay">
        💳 Subscribe Now — Rp 10,000
      </button>
      <p class="muted small" style="margin-top: 12px; text-align: center;">
        Secure payment via Midtrans. Cancel anytime.
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

<!-- Midtrans Snap JS (only load if not subscribed) -->
<?php if (!$subscribed): ?>
<script src="https://app.sandbox.midtrans.com/snap/v2/snap.js"
        data-client-key="<?php echo MIDTRANS_CLIENT_KEY; ?>"></script>
<script>
document.getElementById('pay-btn').addEventListener('click', function () {
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Processing...';

    // Step 1: Call our backend to create a Midtrans transaction
    fetch('subscription.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=create_payment',
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            btn.disabled = false;
            btn.textContent = '💳 Subscribe Now — Rp 10,000';
            return;
        }

        // Step 2: Open Midtrans Snap popup with the snap_token
        snap.open(data.snap_token, {
            onSuccess: function (result) {
                // Payment success — redirect to success page
                window.location.href = 'subscription-success.php?order_id=' + data.order_id;
            },
            onPending: function (result) {
                // Payment pending (e.g. bank transfer) — redirect to pending page
                window.location.href = 'subscription-pending.php?order_id=' + data.order_id;
            },
            onError: function (result) {
                alert('Payment failed. Please try again.');
                btn.disabled = false;
                btn.textContent = '💳 Subscribe Now — Rp 10,000';
            },
            onClose: function () {
                btn.disabled = false;
                btn.textContent = '💳 Subscribe Now — Rp 10,000';
            }
        });
    })
    .catch(err => {
        console.error(err);
        alert('Something went wrong. Please try again.');
        btn.disabled = false;
        btn.textContent = '💳 Subscribe Now — Rp 10,000';
    });
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
