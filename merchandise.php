<?php
require 'config.php';
requireLogin();
requireSubscription(); // Subscriber only feature to redeem merchandise

$user_id = $_SESSION['user_id'];
$points = getUserPoints();

$success = '';
$error = '';

$merchandise = [
    [
        'id' => 'tumbler',
        'name' => 'Zoonexa Exclusive Tumbler',
        'description' => 'Stay hydrated in style with our premium Zoonexa tumbler.',
        'image' => 'images/tumbler.png',
        'price' => 2000
    ],
    [
        'id' => 'totebag',
        'name' => 'Zoonexa Eco Totebag',
        'description' => 'Carry your essentials while saving the planet with our sturdy eco totebag.',
        'image' => 'images/totebag.png',
        'price' => 1500
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'redeem') {
    $item_id = $_POST['item_id'] ?? '';
    $selected_item = null;
    
    foreach ($merchandise as $item) {
        if ($item['id'] === $item_id) {
            $selected_item = $item;
            break;
        }
    }
    
    if ($selected_item) {
        if ($points >= $selected_item['price']) {
            // Deduct points (add negative points)
            if (addPoints(-$selected_item['price'])) {
                $points -= $selected_item['price']; // Update current display points
                $success = "Successfully redeemed " . e($selected_item['name']) . "! Our team will contact you for shipping details.";
            } else {
                $error = "Failed to redeem item. Please try again later.";
            }
        } else {
            $error = "You don't have enough points to redeem this item.";
        }
    } else {
        $error = "Invalid item selected.";
    }
}

$page_title = 'Merchandise';
include 'header.php';
?>

<section class="page-section">
  <div class="page-header">
    <div>
      <h1>🛍️ Merchandise</h1>
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
              ⭐ <?php echo number_format($item['price']); ?>
            </div>
            <form method="POST" style="margin: 0;">
              <input type="hidden" name="action" value="redeem">
              <input type="hidden" name="item_id" value="<?php echo e($item['id']); ?>">
              <button type="submit" class="hero-btn primary" style="padding: 8px 16px; font-size: 14px;" <?php echo ($points < $item['price']) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?> onclick="return confirm('Redeem <?php echo e($item['name']); ?> for <?php echo number_format($item['price']); ?> points?');">
                Redeem
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="info-card" style="margin-top: 24px;">
    <h3>💡 How it works</h3>
    <p>
      You must have an active <strong>Zoonexa Pro</strong> subscription to redeem merchandise. Once you redeem an item, your points will be deducted immediately, and our team will reach out to the email associated with your account within 1-2 business days to confirm your shipping address.
    </p>
  </div>
</section>

<?php include 'footer.php'; ?>
