<?php
require 'config.php';
requireLogin();

if (!isAdmin()) {
    header("Location: index.php");
    exit;
}

// Handle activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate_sub') {
    $order_id = $_POST['order_id'];
    $user_id_to_activate = $_POST['user_id'];
    
    // Call the shared helper
    activateSubscriptionRecord($user_id_to_activate, $order_id, 'qris_manual', 'manual-admin-' . time());
    
    $success = "Subscription for order $order_id successfully activated!";
}

// Fetch pending subscriptions
$stmt = $mysqli->prepare("
    SELECT s.*, u.username 
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    WHERE s.status = 'pending'
    ORDER BY s.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$pendingSubs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Admin Panel';
include 'header.php';
?>

<section class="page-section">
  <div class="page-header">
    <div>
      <h1><i class="fas fa-shield-alt" style="color: var(--primary);"></i> Admin Panel</h1>
      <p class="muted">Manage user subscriptions and manual payments.</p>
    </div>
  </div>

  <?php if (isset($success)): ?>
    <div class="alert success"><?php echo e($success); ?></div>
  <?php endif; ?>

  <div class="card big-card">
    <h2>Pending Subscriptions</h2>
    <p class="muted" style="margin-bottom: 20px;">The following users have clicked "Saya Sudah Transfer". Verify their payment manually before activating.</p>

    <?php if (count($pendingSubs) > 0): ?>
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
          <thead>
            <tr style="border-bottom: 2px solid var(--border);">
              <th style="padding: 12px; color: var(--text-dark);">User</th>
              <th style="padding: 12px; color: var(--text-dark);">Order ID</th>
              <th style="padding: 12px; color: var(--text-dark);">Date</th>
              <th style="padding: 12px; color: var(--text-dark);">Amount</th>
              <th style="padding: 12px; text-align: right; color: var(--text-dark);">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingSubs as $sub): ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 12px; color: var(--text-body);"><strong><?php echo e($sub['username']); ?></strong></td>
              <td style="padding: 12px; font-family: monospace; font-size: 13px; color: var(--text-muted);"><?php echo e($sub['midtrans_order_id']); ?></td>
              <td style="padding: 12px; color: var(--text-body);"><?php echo date('M d, Y H:i', strtotime($sub['created_at'])); ?></td>
              <td style="padding: 12px; color: var(--success);">Rp <?php echo number_format($sub['amount_paid']); ?></td>
              <td style="padding: 12px; text-align: right;">
                <form method="POST" style="margin: 0; display: inline-block;">
                  <input type="hidden" name="action" value="activate_sub">
                  <input type="hidden" name="order_id" value="<?php echo e($sub['midtrans_order_id']); ?>">
                  <input type="hidden" name="user_id" value="<?php echo e($sub['user_id']); ?>">
                  <button type="submit" class="hero-btn primary" style="padding: 8px 16px; font-size: 13px; margin: 0;" onclick="return confirm('Activate membership for <?php echo e($sub['username']); ?>?');">
                    <i class="fas fa-check-square"></i> Activate
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 40px 0;">
        <i class="fas fa-box-open" style="font-size: 48px; color: var(--border); margin-bottom: 16px;"></i>
        <p class="muted">No pending subscriptions right now.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'footer.php'; ?>
