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

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_sub') {
    $order_id = $_POST['order_id'];
    $user_id_to_cancel = $_POST['user_id'];
    
    $stmt = $mysqli->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE midtrans_order_id = ?");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $mysqli->prepare("UPDATE users SET subscription_status = 0 WHERE id = ?");
    $stmt->bind_param("i", $user_id_to_cancel);
    $stmt->execute();
    $stmt->close();
    
    $success = "Subscription for order $order_id has been cancelled.";
}

// Fetch Stats
$res = $mysqli->query("SELECT COUNT(id) AS total FROM users");
$totalUsers = $res->fetch_assoc()['total'];

$res = $mysqli->query("SELECT COUNT(id) AS total FROM subscriptions WHERE status = 'active'");
$activeSubs = $res->fetch_assoc()['total'];

$res = $mysqli->query("SELECT SUM(amount_paid) AS total FROM subscriptions WHERE status = 'active'");
$totalRevenue = $res->fetch_assoc()['total'] ?? 0;

// Fetch pending subscriptions
$stmt = $mysqli->prepare("
    SELECT s.*, u.username 
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    WHERE s.status = 'pending'
    ORDER BY s.created_at DESC
");
$stmt->execute();
$pendingSubs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch active subscriptions
$stmt = $mysqli->prepare("
    SELECT s.*, u.username 
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    WHERE s.status = 'active'
    ORDER BY s.updated_at DESC
");
$stmt->execute();
$activeList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Admin Panel';
include 'header.php';
?>

<section class="page-section">
  <div class="page-header">
    <div>
      <h1><i class="fas fa-shield-alt" style="color: var(--primary);"></i> Admin Dashboard</h1>
      <p class="muted">Platform overview and subscription management.</p>
    </div>
  </div>

  <?php if (isset($success)): ?>
    <div class="alert success"><?php echo e($success); ?></div>
  <?php endif; ?>

  <!-- Stats Grid -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="text-align: center; padding: 24px;">
      <i class="fas fa-users" style="font-size: 32px; color: var(--primary); margin-bottom: 12px;"></i>
      <h3 style="margin-bottom: 4px;"><?php echo number_format($totalUsers); ?></h3>
      <p class="muted small">Total Users</p>
    </div>
    <div class="card" style="text-align: center; padding: 24px;">
      <i class="fas fa-crown" style="font-size: 32px; color: var(--warning); margin-bottom: 12px;"></i>
      <h3 style="margin-bottom: 4px;"><?php echo number_format($activeSubs); ?></h3>
      <p class="muted small">Active PRO</p>
    </div>
    <div class="card" style="text-align: center; padding: 24px;">
      <i class="fas fa-wallet" style="font-size: 32px; color: var(--success); margin-bottom: 12px;"></i>
      <h3 style="margin-bottom: 4px;">Rp <?php echo number_format($totalRevenue); ?></h3>
      <p class="muted small">Total Revenue</p>
    </div>
  </div>

  <!-- Pending Approvals -->
  <div class="card big-card" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <h2><i class="fas fa-clock" style="color: var(--warning);"></i> Pending Approvals</h2>
        <span style="background: var(--warning); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700;"><?php echo count($pendingSubs); ?></span>
    </div>

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
              <td style="padding: 12px; color: var(--success); font-weight: 600;">Rp <?php echo number_format($sub['amount_paid']); ?></td>
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
      <div style="text-align: center; padding: 20px 0;">
        <p class="muted">No pending subscriptions right now.</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Active Subscribers -->
  <div class="card big-card">
    <h2 style="margin-bottom: 20px;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Active Subscribers</h2>
    <?php if (count($activeList) > 0): ?>
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
          <thead>
            <tr style="border-bottom: 2px solid var(--border);">
              <th style="padding: 12px; color: var(--text-dark);">User</th>
              <th style="padding: 12px; color: var(--text-dark);">Order ID</th>
              <th style="padding: 12px; color: var(--text-dark);">Expires</th>
              <th style="padding: 12px; text-align: right; color: var(--text-dark);">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($activeList as $sub): ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 12px; color: var(--text-body);"><strong><?php echo e($sub['username']); ?></strong></td>
              <td style="padding: 12px; font-family: monospace; font-size: 13px; color: var(--text-muted);"><?php echo e($sub['midtrans_order_id']); ?></td>
              <td style="padding: 12px; color: var(--text-body);"><?php echo date('M d, Y', strtotime($sub['end_date'])); ?></td>
              <td style="padding: 12px; text-align: right;">
                <form method="POST" style="margin: 0; display: inline-block;">
                  <input type="hidden" name="action" value="cancel_sub">
                  <input type="hidden" name="order_id" value="<?php echo e($sub['midtrans_order_id']); ?>">
                  <input type="hidden" name="user_id" value="<?php echo e($sub['user_id']); ?>">
                  <button type="submit" class="hero-btn danger" style="padding: 6px 12px; font-size: 12px; margin: 0; background: var(--danger); border-color: var(--danger); color: white;" onclick="return confirm('Revoke membership for <?php echo e($sub['username']); ?>?');">
                    <i class="fas fa-times"></i> Revoke
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 20px 0;">
        <p class="muted">No active subscribers found.</p>
      </div>
    <?php endif; ?>
  </div>

</section>

<?php include 'footer.php'; ?>
