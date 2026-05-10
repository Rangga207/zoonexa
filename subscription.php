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
        if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please upload a screenshot of your payment receipt.';
        } else {
            $uploadDir = __DIR__ . '/uploads/receipts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileInfo = pathinfo($_FILES['payment_proof']['name']);
            $ext = isset($fileInfo['extension']) ? strtolower($fileInfo['extension']) : '';
            $blocked = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'sh', 'bat', 'js', 'html', 'htm'];
            
            if (in_array($ext, $blocked) || empty($ext)) {
                $error = 'Invalid file type for security reasons. Please upload an image or document.';
            } else {
                // Generate unique order ID
                $orderId = 'zoonexa-' . $user_id . '-' . time();
                $newFilename = $orderId . '.' . $ext;
                $targetPath = $uploadDir . $newFilename;
                
                if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetPath)) {
                    $paymentMethod = 'qris_manual: uploads/receipts/' . $newFilename;
                    
                    // Simpan pending subscription ke DB
                    $stmt = $mysqli->prepare("
                        INSERT INTO subscriptions (user_id, midtrans_order_id, status, amount_paid, payment_method)
                        VALUES (?, ?, 'pending', 10000, ?)
                    ");
                    $stmt->bind_param('iss', $user_id, $orderId, $paymentMethod);
                    $stmt->execute();
                    $stmt->close();

                    // Redirect ke pending page
                    header('Location: subscription-pending.php?order_id=' . $orderId);
                    exit;
                } else {
                    $error = 'Failed to upload receipt. Please try again.';
                }
            }
        }
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

<style>
/* =============================================
   PREMIUM SUBSCRIPTION UI
   ============================================= */
.sub-pay-section {
  text-align: center;
  border-top: 1px solid var(--border);
  padding-top: 32px;
  margin-top: 32px;
}

/* Animated QR Code Scanner Container */
.qris-container {
  display: flex;
  justify-content: center;
  margin: 30px 0;
}
.qris-box {
  position: relative;
  width: 240px;
  height: 240px;
  background: white;
  padding: 15px;
  border-radius: 20px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.4), 0 0 0 2px rgba(255,255,255,0.05);
  overflow: hidden;
  cursor: zoom-in;
  transition: transform 0.2s;
}
.qris-box:hover { transform: scale(1.02); }
.qris-box::after {
  content: '\f00e'; /* fa-search-plus */
  font-family: 'Font Awesome 5 Free';
  font-weight: 900;
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.6);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 40px;
  opacity: 0;
  transition: opacity 0.2s;
  z-index: 5;
  border-radius: 20px;
}
.qris-box:hover::after { opacity: 1; }

.qris-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 10px;
  position: relative;
  z-index: 2;
}
.qris-glow {
  position: absolute;
  top: -50%; left: -50%; right: -50%; bottom: -50%;
  background: conic-gradient(from 0deg, transparent, var(--primary), transparent 30%);
  animation: spin-glow 4s linear infinite;
  z-index: 0;
  opacity: 0.15;
}
.qris-scan-line {
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: var(--primary);
  box-shadow: 0 0 15px 4px var(--primary);
  z-index: 3;
  animation: scan 2.5s ease-in-out infinite;
  opacity: 0.8;
}

/* Premium Form Elements */
.payment-form {
  max-width: 400px;
  margin: 0 auto;
  text-align: left;
}
.input-group label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--text-body);
  margin-bottom: 8px;
}
.payment-input {
  width: 100%;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.1);
  color: white;
  padding: 16px;
  border-radius: 12px;
  font-size: 16px;
  outline: none;
  transition: all 0.3s;
  box-sizing: border-box;
}

/* File Upload Zone */
.upload-zone {
  width: 100%;
  border: 2px dashed rgba(255,255,255,0.2);
  border-radius: 16px;
  padding: 30px 20px;
  text-align: center;
  background: rgba(255,255,255,0.02);
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  box-sizing: border-box;
}
.upload-zone:hover, .upload-zone.dragover {
  border-color: var(--primary);
  background: rgba(58,134,255,0.05);
}
.upload-icon {
  font-size: 32px;
  color: var(--primary);
  margin-bottom: 12px;
}
.upload-text {
  font-size: 15px;
  font-weight: 600;
  color: white;
  margin-bottom: 6px;
}
.upload-sub {
  font-size: 13px;
  color: var(--text-muted);
}
.upload-zone input[type="file"] {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}
.preview-text {
  display: none;
  font-size: 14px;
  color: var(--success);
  font-weight: 600;
  margin-top: 10px;
}

/* Modal for QR Zoom */
.qr-modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.85);
  backdrop-filter: blur(8px);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.qr-modal.show { display: flex; animation: fadeIn 0.3s; }
.qr-modal-content {
  background: white;
  padding: 20px;
  border-radius: 24px;
  max-width: 500px;
  width: 100%;
  position: relative;
  animation: scaleUp 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.qr-modal-img {
  width: 100%;
  height: auto;
  border-radius: 12px;
  display: block;
}
.qr-modal-close {
  position: absolute;
  top: -40px;
  right: 0;
  background: none;
  border: none;
  color: white;
  font-size: 30px;
  cursor: pointer;
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes scaleUp { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
@keyframes spin-glow { 100% { transform: rotate(360deg); } }
.btn-confirm-payment {
  width: 100%;
  margin-top: 24px;
  padding: 18px;
  border-radius: 14px;
  border: none;
  background: linear-gradient(135deg, var(--primary), #2272cc);
  color: white;
  font-size: 16px;
  font-weight: 800;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(58,134,255,0.3);
  transition: transform 0.2s, box-shadow 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-confirm-payment:hover {
  transform: translateY(-3px);
  box-shadow: 0 15px 40px rgba(58,134,255,0.5);
}
.btn-content {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 10px;
}
.btn-flare {
  position: absolute;
  top: 0; left: -100%; width: 50%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
  transform: skewX(-20deg);
  z-index: 1;
  animation: flare 3s infinite;
}

@keyframes spin-glow { 100% { transform: rotate(360deg); } }
@keyframes scan {
  0% { top: 0; }
  50% { top: 100%; }
  100% { top: 0; }
}
@keyframes flare {
  0% { left: -100%; }
  20% { left: 200%; }
  100% { left: 200%; }
}
</style>

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
      <div class="sub-active-icon"><i class="fas fa-check-circle" style="color: var(--success);"></i></div>
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
        <i class="fas fa-lock" style="margin-right: 6px;"></i> This feature requires a subscription. Upgrade below to unlock all premium features.
      </div>
    <?php endif; ?>

    <div class="sub-hero">
      <div class="sub-hero-icon"><i class="fas fa-gem" style="color: var(--secondary);"></i></div>
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
        <div class="feature-item"><span><i class="fas fa-trophy" style="color: var(--warning);"></i></span> Milestone Achievements</div>
        <div class="feature-item"><span><i class="fas fa-robot" style="color: var(--primary);"></i></span> AI Health Assistant (Advanced)</div>
        <div class="feature-item"><span><i class="fas fa-star" style="color: #f1c40f;"></i></span> Health Points & Rewards</div>
        <div class="feature-item"><span><i class="fas fa-chart-bar" style="color: #9b59b6;"></i></span> Detailed Progress Analysis</div>
        <div class="feature-item"><span><i class="fas fa-bullseye" style="color: var(--danger);"></i></span> All Health Modes & Targets</div>
        <div class="feature-item"><span><i class="fas fa-comments" style="color: #1abc9c;"></i></span> Priority Support</div>
      </div>
    </div>

    <!-- Pay Button Section -->
    <div class="sub-pay-section">
      <h3 style="font-size: 20px; margin-bottom: 8px;">Complete Your Payment</h3>
      <p class="muted">Scan the QRIS code below using your preferred E-Wallet or Mobile Banking app.</p>
      
      <div class="qris-container">
        <div class="qris-box" onclick="document.getElementById('qrModal').classList.add('show')">
          <div class="qris-glow"></div>
          <img src="qris.png" alt="QRIS Zoonexa" class="qris-image">
          <div class="qris-scan-line"></div>
        </div>
      </div>

      <?php if (isset($error)): ?>
        <div class="alert" style="background: rgba(231,76,60,0.1); border-color: var(--danger); color: #e74c3c; max-width: 400px; margin: 0 auto 20px; text-align: left;">
          <i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i> <?php echo e($error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="payment-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="submit_payment">
        
        <div class="input-group">
          <label>Upload Payment Receipt</label>
          <div class="upload-zone" id="dropZone">
            <input type="file" name="payment_proof" id="payment_proof" accept="*/*" required onchange="handleFileSelect(this)">
            <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
            <div class="upload-text">Tap to Upload Screenshot</div>
            <div class="upload-sub">Supports all image & document types</div>
            <div class="preview-text" id="filePreview"><i class="fas fa-check-circle"></i> <span></span> attached</div>
          </div>
        </div>

        <button type="submit" class="btn-confirm-payment">
          <span class="btn-content">
            <i class="fas fa-check-circle"></i> Confirm Payment
          </span>
          <div class="btn-flare"></div>
        </button>
      </form>
      
      <p class="muted small" style="margin-top: 24px; text-align: center;">
        <i class="fas fa-shield-alt" style="color: var(--primary);"></i> Secure manual verification. The admin will process your request within 24 hours.
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

<!-- QR Zoom Modal -->
<div class="qr-modal" id="qrModal" onclick="this.classList.remove('show')">
  <div class="qr-modal-content" onclick="event.stopPropagation()">
    <button class="qr-modal-close" onclick="document.getElementById('qrModal').classList.remove('show')">&times;</button>
    <img src="qris.png" alt="QRIS Zoom" class="qr-modal-img">
    <p style="text-align: center; color: var(--text-muted); font-weight: 600; margin-top: 15px;">Scan this QR Code to Pay</p>
  </div>
</div>

<script>
function handleFileSelect(input) {
  const preview = document.getElementById('filePreview');
  const previewSpan = preview.querySelector('span');
  
  if (input.files && input.files[0]) {
    previewSpan.textContent = input.files[0].name;
    preview.style.display = 'block';
    
    // Hide default texts
    document.querySelector('.upload-icon').style.display = 'none';
    document.querySelector('.upload-text').style.display = 'none';
    document.querySelector('.upload-sub').style.display = 'none';
  } else {
    preview.style.display = 'none';
    document.querySelector('.upload-icon').style.display = 'block';
    document.querySelector('.upload-text').style.display = 'block';
    document.querySelector('.upload-sub').style.display = 'block';
  }
}
</script>

<!-- End Subscription Page -->

<?php include 'footer.php'; ?>
