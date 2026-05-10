<?php
require 'config.php';
requireLogin();
requireSubscription(); // Subscriber only feature to redeem merchandise

$user_id = $_SESSION['user_id'];
$points = getUserPoints();

$success = '';
$error = '';
$show_shipping_form = false;
$redeem_item = null;

$merchandise = [
    [
        'id' => 'tumbler',
        'name' => 'Zoonexa Exclusive Tumbler',
        'description' => 'Stay hydrated in style with our premium Zoonexa tumbler.',
        'image' => 'images/tumbler.png',
        'price' => 3000
    ],
    [
        'id' => 'totebag',
        'name' => 'Zoonexa Eco Totebag',
        'description' => 'Carry your essentials while saving the planet with our sturdy eco totebag.',
        'image' => 'images/totebag.png',
        'price' => 2500
    ]
];

$digital_merchandise = [
    [
        'id' => 'border_gold',
        'name' => 'Golden Aura Border',
        'description' => 'A glowing golden border for your profile picture on the Leaderboard.',
        'css_class' => 'border-gold',
        'price' => 500
    ],
    [
        'id' => 'border_neon',
        'name' => 'Cyber Neon Border',
        'description' => 'A futuristic neon blue border to stand out.',
        'css_class' => 'border-neon',
        'price' => 750
    ],
    [
        'id' => 'border_fire',
        'name' => 'Inferno Border',
        'description' => 'A blazing red border for top performers.',
        'css_class' => 'border-fire',
        'price' => 1000
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'redeem') {
        $item_id = $_POST['item_id'] ?? '';
        
        foreach ($merchandise as $item) {
            if ($item['id'] === $item_id) {
                $redeem_item = $item;
                break;
            }
        }
        
        if ($redeem_item) {
            if ($points >= $redeem_item['price']) {
                $show_shipping_form = true;
            } else {
                $error = "You don't have enough points to redeem this item.";
            }
        } else {
            // Check digital merch
            $redeem_digital = null;
            foreach ($digital_merchandise as $d_item) {
                if ($d_item['id'] === $item_id) {
                    $redeem_digital = $d_item;
                    break;
                }
            }
            if ($redeem_digital) {
                if ($points >= $redeem_digital['price']) {
                    if (addPoints(-$redeem_digital['price'])) {
                        $points -= $redeem_digital['price'];
                        $stmt = $mysqli->prepare("UPDATE users SET avatar_border = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("si", $redeem_digital['id'], $user_id);
                            $stmt->execute();
                            $stmt->close();
                            $success = "Successfully purchased " . e($redeem_digital['name']) . "! It is now equipped on the leaderboard.";
                        } else {
                            $error = "Database error. Missing avatar_border column.";
                            // Refund points
                            addPoints($redeem_digital['price']);
                        }
                    } else {
                        $error = "Failed to process purchase.";
                    }
                } else {
                    $error = "You don't have enough points.";
                }
            } else {
                $error = "Invalid item selected.";
            }
        }
    } elseif ($action === 'confirm_redeem') {
        $item_id = $_POST['item_id'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        foreach ($merchandise as $item) {
            if ($item['id'] === $item_id) {
                $redeem_item = $item;
                break;
            }
        }
        
        if ($redeem_item && $full_name && $phone && $address) {
            if ($points >= $redeem_item['price']) {
                // Deduct points
                if (addPoints(-$redeem_item['price'])) {
                    $points -= $redeem_item['price'];
                    
                    // Insert into orders
                    $stmt = $mysqli->prepare("INSERT INTO merchandise_orders (user_id, item_id, item_name, points_used, full_name, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ississs", $user_id, $redeem_item['id'], $redeem_item['name'], $redeem_item['price'], $full_name, $phone, $address);
                    $stmt->execute();
                    $stmt->close();
                    
                    $success = "Successfully redeemed " . e($redeem_item['name']) . "! We will process your shipment soon.";
                    $show_shipping_form = false;
                } else {
                    $error = "Failed to redeem item. Please try again later.";
                }
            } else {
                $error = "You don't have enough points.";
            }
        } else {
            $error = "Please fill in all shipping details.";
            if ($redeem_item) {
                $show_shipping_form = true;
            }
        }
    }
}

$page_title = 'Merchandise';
include 'header.php';
?>

<section class="page-section">
  <div class="page-header">
    <div>
      <h1><i class="fas fa-shopping-bag" style="color: var(--primary); margin-right: 8px;"></i> Merchandise</h1>
      <p class="muted">Redeem your hard-earned Health Points for exclusive Zoonexa merchandise.</p>
    </div>
  </div>

  <div class="card" style="margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
    <div>
      <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-star" style="color: var(--warning);"></i> Your Health Points
      </h3>
      <p class="muted" style="margin-top: 4px;">Earn more points by logging your daily activities.</p>
    </div>
    <div style="font-size: 32px; font-weight: 800; color: var(--primary);">
      <?php echo number_format($points); ?> <span style="font-size: 16px; color: var(--text-muted); font-weight: normal;">pts</span>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert danger"><?php echo e($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert success"><?php echo e($success); ?></div>
  <?php endif; ?>

  <?php if ($show_shipping_form && $redeem_item): ?>
    <div class="card" style="max-width: 600px; margin: 0 auto 24px;">
      <h2>Shipping Details</h2>
      <p class="muted" style="margin-bottom: 20px;">Please provide your delivery information for <strong><?php echo e($redeem_item['name']); ?></strong> (Cost: <?php echo number_format($redeem_item['price']); ?> pts).</p>
      
      <form method="POST" class="form">
        <input type="hidden" name="action" value="confirm_redeem">
        <input type="hidden" name="item_id" value="<?php echo e($redeem_item['id']); ?>">
        
        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display:block; margin-bottom: 8px;">Full Name</label>
          <input type="text" name="full_name" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary); color: var(--text-body);">
        </div>
        
        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display:block; margin-bottom: 8px;">Phone Number</label>
          <input type="text" name="phone" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary); color: var(--text-body);">
        </div>
        
        <div class="form-group" style="margin-bottom: 24px;">
          <label style="display:block; margin-bottom: 8px;">Complete Address</label>
          <textarea name="address" required rows="4" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary); color: var(--text-body); resize: vertical;"></textarea>
        </div>
        
        <div style="display: flex; gap: 12px;">
          <a href="merchandise.php" class="hero-btn ghost" style="text-decoration: none; flex: 1; text-align: center; justify-content: center;">Cancel</a>
          <button type="submit" class="hero-btn primary" style="flex: 1; justify-content: center;">Confirm & Redeem</button>
        </div>
      </form>
    </div>
  <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
      <?php foreach ($merchandise as $item): ?>
        <div class="card" style="display: flex; flex-direction: column; overflow: hidden; padding: 0;">
          <div style="height: 250px; background: white; display: flex; align-items: center; justify-content: center; padding: 20px;">
            <img src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
          </div>
          <div style="padding: 24px; flex: 1; display: flex; flex-direction: column;">
            <h3 style="margin-bottom: 8px;"><?php echo e($item['name']); ?></h3>
            <p class="muted" style="margin-bottom: 20px; flex: 1; font-size: 14px;"><?php echo e($item['description']); ?></p>
            
            <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--border); padding-top: 20px; margin-top: auto;">
              <div style="font-size: 20px; font-weight: 700; color: var(--warning);">
                <i class="fas fa-star" style="color: var(--warning);"></i> <?php echo number_format($item['price']); ?>
              </div>
              <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="redeem">
                <input type="hidden" name="item_id" value="<?php echo e($item['id']); ?>">
                <button type="submit" class="hero-btn primary" style="padding: 8px 16px; font-size: 14px;" <?php echo ($points < $item['price']) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                  Redeem
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$show_shipping_form || !$redeem_item): ?>
    <!-- DIGITAL MERCHANDISE (BORDERS) -->
    <h2 style="margin-top: 40px; margin-bottom: 20px;"><i class="fas fa-id-badge" style="color: var(--secondary); margin-right: 8px;"></i> Digital Borders</h2>
    <p class="muted" style="margin-bottom: 24px;">Equip these borders to stand out on the Global Leaderboard!</p>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px;">
      <?php foreach ($digital_merchandise as $item): ?>
        <div class="card" style="display: flex; flex-direction: column; overflow: hidden; position: relative;">
          <div style="padding: 24px; text-align: center; flex: 1;">
            <div style="width: 80px; height: 80px; margin: 0 auto 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; color: var(--text-muted); background: var(--bg-secondary);" class="<?php echo $item['css_class']; ?>">
              <i class="fas fa-user"></i>
            </div>
            <h3 style="margin-bottom: 8px;"><?php echo e($item['name']); ?></h3>
            <p class="muted" style="font-size: 14px; margin-bottom: 20px;"><?php echo e($item['description']); ?></p>
            
            <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--border); padding-top: 20px;">
              <div style="font-size: 18px; font-weight: 700; color: var(--warning);">
                <i class="fas fa-star"></i> <?php echo number_format($item['price']); ?>
              </div>
              <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="redeem">
                <input type="hidden" name="item_id" value="<?php echo e($item['id']); ?>">
                <button type="submit" class="hero-btn primary" style="padding: 6px 12px; font-size: 13px;" <?php echo ($points < $item['price']) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                  Buy
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="info-card" style="margin-top: 24px;">
    <h3><i class="fas fa-lightbulb" style="color: #f1c40f; margin-right: 8px;"></i> How it works</h3>
    <p>
      You must have an active <strong>Zoonexa Pro</strong> subscription to redeem merchandise. Once you redeem an item, your points will be deducted immediately, and our team will reach out to the email associated with your account within 1-2 business days to confirm your shipping address.
    </p>
  </div>
</section>

<?php include 'footer.php'; ?>
