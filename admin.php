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

// Handle merchandise order shipping
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ship_order') {
    $order_id = (int)$_POST['order_id'];
    
    $stmt = $mysqli->prepare("UPDATE merchandise_orders SET status = 'shipped' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();
    
    $success = "Merchandise Order #$order_id marked as shipped.";
}

// Handle Bonus Mission approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_mission') {
    $mission_id = (int)$_POST['mission_id'];
    $user_id_mission = (int)$_POST['user_id'];
    $points_to_award = (int)$_POST['points'];
    
    $stmt = $mysqli->prepare("UPDATE bonus_missions SET status = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $mission_id);
    if ($stmt->execute()) {
        addPoints($points_to_award, $user_id_mission);
        $success = "Bonus mission approved! $points_to_award points awarded.";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_mission') {
    $mission_id = (int)$_POST['mission_id'];
    
    $stmt = $mysqli->prepare("UPDATE bonus_missions SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $mission_id);
    $stmt->execute();
    $stmt->close();
    
    $success = "Bonus mission rejected.";
}

// Handle User Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id_to_delete = (int)$_POST['user_id'];
    
    if ($user_id_to_delete === $_SESSION['user_id']) {
        $error = "You cannot delete your own admin account.";
    } else {
        // Cascade delete all related user data
        $mysqli->query("DELETE FROM health_logs WHERE user_id = $user_id_to_delete");
        $mysqli->query("DELETE FROM bonus_missions WHERE user_id = $user_id_to_delete");
        $mysqli->query("DELETE FROM merchandise_orders WHERE user_id = $user_id_to_delete");
        $mysqli->query("DELETE FROM subscriptions WHERE user_id = $user_id_to_delete");
        
        $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id_to_delete);
        if ($stmt->execute()) {
            $success = "Member successfully removed from the system.";
        }
        $stmt->close();
    }
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

// Fetch Merchandise Orders
$stmt = $mysqli->prepare("
    SELECT m.*, u.username 
    FROM merchandise_orders m
    JOIN users u ON m.user_id = u.id
    ORDER BY FIELD(m.status, 'pending', 'processed', 'shipped'), m.created_at DESC
");
$stmt->execute();
$merchOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch Pending Bonus Missions
$stmt = $mysqli->prepare("
    SELECT b.*, u.username 
    FROM bonus_missions b
    JOIN users u ON b.user_id = u.id
    WHERE b.status = 'pending'
    ORDER BY b.created_at ASC
");
$stmt->execute();
$pendingMissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch All Users for Member Management
$stmt = $mysqli->prepare("
    SELECT id, username, role, points, created_at 
    FROM users 
    ORDER BY role ASC, created_at DESC
");
$stmt->execute();
$allUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
  <?php if (isset($error)): ?>
    <div class="alert" style="background: rgba(231,76,60,0.1); border-color: var(--danger); color: #e74c3c;"><?php echo e($error); ?></div>
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

  <!-- Pending Bonus Missions -->
  <div class="card big-card" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <h2><i class="fas fa-camera" style="color: var(--primary);"></i> Pending Bonus Missions</h2>
        <span style="background: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700;"><?php echo count($pendingMissions); ?></span>
    </div>

    <?php if (count($pendingMissions) > 0): ?>
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
          <thead>
            <tr style="border-bottom: 2px solid var(--border);">
              <th style="padding: 12px; color: var(--text-dark);">User</th>
              <th style="padding: 12px; color: var(--text-dark);">Date Logged</th>
              <th style="padding: 12px; color: var(--text-dark);">Proof</th>
              <th style="padding: 12px; text-align: right; color: var(--text-dark);">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingMissions as $mission): ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 12px; color: var(--text-body);"><strong><?php echo e($mission['username']); ?></strong><br><span class="muted small"><?php echo ucfirst($mission['mission_type']); ?> (+<?php echo $mission['points_awarded']; ?> pts)</span></td>
              <td style="padding: 12px; color: var(--text-body);"><?php echo date('M d, Y', strtotime($mission['log_date'])); ?></td>
              <td style="padding: 12px; color: var(--text-body);">
                <?php if ($mission['proof_path']): ?>
                    <a href="<?php echo e($mission['proof_path']); ?>" target="_blank" style="color: var(--primary); font-size: 13px; text-decoration: underline;">View Proof</a>
                <?php else: ?>
                    <span class="muted small">No file</span>
                <?php endif; ?>
              </td>
              <td style="padding: 12px; text-align: right;">
                <form method="POST" style="margin: 0; display: inline-block; margin-right: 4px;">
                  <input type="hidden" name="action" value="approve_mission">
                  <input type="hidden" name="mission_id" value="<?php echo e($mission['id']); ?>">
                  <input type="hidden" name="user_id" value="<?php echo e($mission['user_id']); ?>">
                  <input type="hidden" name="points" value="<?php echo e($mission['points_awarded']); ?>">
                  <button type="submit" class="hero-btn primary" style="padding: 6px 12px; font-size: 12px; margin: 0;" onclick="return confirm('Approve this mission for <?php echo e($mission['username']); ?>?');">
                    <i class="fas fa-check"></i>
                  </button>
                </form>
                <form method="POST" style="margin: 0; display: inline-block;">
                  <input type="hidden" name="action" value="reject_mission">
                  <input type="hidden" name="mission_id" value="<?php echo e($mission['id']); ?>">
                  <button type="submit" class="hero-btn danger" style="padding: 6px 12px; font-size: 12px; margin: 0; background: var(--danger); border-color: var(--danger); color: white;" onclick="return confirm('Reject this mission?');">
                    <i class="fas fa-times"></i>
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
        <p class="muted">No pending missions to verify.</p>
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

  <!-- Merchandise Orders -->
  <div class="card big-card" style="margin-top: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <h2><i class="fas fa-shopping-bag" style="color: var(--primary);"></i> Merchandise Orders</h2>
    </div>

    <?php if (count($merchOrders) > 0): ?>
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
          <thead>
            <tr style="border-bottom: 2px solid var(--border);">
              <th style="padding: 12px; color: var(--text-dark);">User</th>
              <th style="padding: 12px; color: var(--text-dark);">Item</th>
              <th style="padding: 12px; color: var(--text-dark);">Shipping Details</th>
              <th style="padding: 12px; color: var(--text-dark);">Status</th>
              <th style="padding: 12px; text-align: right; color: var(--text-dark);">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($merchOrders as $order): ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 12px; color: var(--text-body);"><strong><?php echo e($order['username']); ?></strong><br><span class="muted small"><?php echo e($order['points_used']); ?> pts</span></td>
              <td style="padding: 12px; color: var(--text-body);"><?php echo e($order['item_name']); ?></td>
              <td style="padding: 12px; color: var(--text-body); font-size: 13px;">
                <strong><?php echo e($order['full_name']); ?></strong> (<?php echo e($order['phone']); ?>)<br>
                <span class="muted"><?php echo e($order['address']); ?></span>
              </td>
              <td style="padding: 12px;">
                <?php if ($order['status'] === 'pending'): ?>
                    <span style="background: var(--warning); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700;">Pending</span>
                <?php else: ?>
                    <span style="background: var(--success); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700;">Shipped</span>
                <?php endif; ?>
              </td>
              <td style="padding: 12px; text-align: right;">
                <?php if ($order['status'] === 'pending'): ?>
                    <form method="POST" style="margin: 0; display: inline-block;">
                      <input type="hidden" name="action" value="ship_order">
                      <input type="hidden" name="order_id" value="<?php echo e($order['id']); ?>">
                      <button type="submit" class="hero-btn primary" style="padding: 6px 12px; font-size: 12px; margin: 0;" onclick="return confirm('Mark order for <?php echo e($order['full_name']); ?> as shipped?');">
                        <i class="fas fa-truck"></i> Mark Shipped
                      </button>
                    </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 20px 0;">
        <p class="muted">No merchandise orders yet.</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Member Management -->
  <div class="card big-card" style="margin-top: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <h2><i class="fas fa-users-cog" style="color: var(--primary);"></i> Member Management</h2>
        <span style="background: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700;"><?php echo count($allUsers); ?> Users</span>
    </div>

    <?php if (count($allUsers) > 0): ?>
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
          <thead>
            <tr style="border-bottom: 2px solid var(--border);">
              <th style="padding: 12px; color: var(--text-dark);">ID</th>
              <th style="padding: 12px; color: var(--text-dark);">Username</th>
              <th style="padding: 12px; color: var(--text-dark);">Role</th>
              <th style="padding: 12px; color: var(--text-dark);">Points</th>
              <th style="padding: 12px; color: var(--text-dark);">Joined</th>
              <th style="padding: 12px; text-align: right; color: var(--text-dark);">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allUsers as $u): ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 12px; color: var(--text-muted); font-size: 13px;">#<?php echo e($u['id']); ?></td>
              <td style="padding: 12px; color: var(--text-body);"><strong><?php echo e($u['username']); ?></strong></td>
              <td style="padding: 12px;">
                <?php if ($u['role'] === 'admin'): ?>
                    <span style="background: var(--warning); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700;">Admin</span>
                <?php else: ?>
                    <span style="background: var(--border); color: var(--text-dark); padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">Member</span>
                <?php endif; ?>
              </td>
              <td style="padding: 12px; color: var(--primary); font-weight: 600; font-size: 16px;">
                  <?php echo ($u['role'] === 'admin') ? '&infin;' : number_format($u['points']); ?>
              </td>
              <td style="padding: 12px; color: var(--text-body); font-size: 14px;"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
              <td style="padding: 12px; text-align: right;">
                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                    <form method="POST" style="margin: 0; display: inline-block;">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?php echo e($u['id']); ?>">
                      <button type="submit" class="hero-btn danger" style="padding: 6px 12px; font-size: 12px; margin: 0; background: rgba(231,76,60,0.1); border-color: var(--danger); color: #e74c3c;" onclick="return confirm('⚠️ DANGER: Are you sure you want to completely delete the member <?php echo e($u['username']); ?> and all their data? This action cannot be undone.');">
                        <i class="fas fa-trash-alt"></i> Delete
                      </button>
                    </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 20px 0;">
        <p class="muted">No users found.</p>
      </div>
    <?php endif; ?>
  </div>

</section>

<?php include 'footer.php'; ?>
