<?php
// payment-callback.php
// Midtrans Notification Webhook Handler
// URL ini diset di Midtrans Dashboard -> Settings -> Payment Notification URL
// Contoh: https://yourdomain.com/payment-callback.php

require 'config.php';

// Disable session untuk webhook (tidak butuh login)
// session_start() sudah dipanggil di config.php, itu oke

// =============================================
// Cek metode request — harus POST
// =============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

// =============================================
// Ambil notification payload dari Midtrans
// =============================================
$payload = file_get_contents('php://input');
$notification = json_decode($payload, true);

if (!$notification || !isset($notification['order_id'])) {
    http_response_code(400);
    echo 'Invalid payload.';
    exit;
}

// =============================================
// Verifikasi signature dari Midtrans
// Signature = SHA512(order_id + gross_amount + status_code + server_key)
// =============================================
$orderId     = $notification['order_id'];
$grossAmount = $notification['gross_amount'];
$statusCode  = $notification['status_code'];
$signatureOn = $notification['signature_key'] ?? '';

$expectedSignature = hash('sha512', $orderId . $grossAmount . $statusCode . MIDTRANS_SERVER_KEY);

if ($signatureOn !== $expectedSignature) {
    error_log('Midtrans signature mismatch for order: ' . $orderId);
    http_response_code(403);
    echo 'Signature mismatch.';
    exit;
}

// =============================================
// Ambil status transaksi dari Midtrans API
// untuk double-check
// =============================================
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

if (!$transactionData) {
    error_log('Failed to fetch transaction data from Midtrans for order: ' . $orderId);
    http_response_code(500);
    echo 'Failed to verify transaction.';
    exit;
}

// =============================================
// Ambil subscription record dari DB
// =============================================
$stmt = $mysqli->prepare("SELECT id, user_id, status FROM subscriptions WHERE midtrans_order_id = ?");
$stmt->bind_param("s", $orderId);
$stmt->execute();
$result = $stmt->get_result();
$subscription = $result->fetch_assoc();
$stmt->close();

if (!$subscription) {
    error_log('No subscription record found for order: ' . $orderId);
    http_response_code(404);
    echo 'Subscription not found.';
    exit;
}

$userId = $subscription['user_id'];
$transactionStatus = $transactionData['transaction_status'];
$paymentStatus     = $transactionData['payment_status'];
$paymentType       = $transactionData['payment_type'] ?? '';

// =============================================
// Handle berbagai status transaksi
// =============================================

if ($transactionStatus === 'capture') {
    // --- Credit card capture ---
    if ($paymentStatus === 'capture') {
        // Payment success
        activateSubscription($userId, $orderId, $transactionData);
    } elseif ($paymentStatus === 'deny') {
        // Payment denied
        updateSubscriptionStatus($orderId, 'failed');
    }
} elseif ($transactionStatus === 'settlement') {
    // --- Payment settled (e-wallet, bank transfer settled) ---
    activateSubscription($userId, $orderId, $transactionData);
} elseif ($transactionStatus === 'pending') {
    // --- Pending (bank transfer — menunggu pembayaran) ---
    // Status tetap pending di DB, tidak perlu ubah
    updateSubscriptionStatus($orderId, 'pending');
} elseif ($transactionStatus === 'cancel' || $transactionStatus === 'expire') {
    // --- Dibatalkan atau expired ---
    if ($paymentStatus === 'deny') {
        updateSubscriptionStatus($orderId, 'failed');
    } else {
        updateSubscriptionStatus($orderId, 'cancelled');
    }
}

// =============================================
// Respond 200 ke Midtrans
// =============================================
http_response_code(200);
echo 'OK';

// =============================================
// FUNGSI HELPER
// =============================================

function activateSubscription($userId, $orderId, $transactionData) {
    global $mysqli;

    $startDate = date('Y-m-d');
    $endDate   = date('Y-m-d', strtotime('+30 days'));
    $paymentMethod = $transactionData['payment_type'] ?? 'unknown';
    $transactionId = $transactionData['transaction_id'] ?? '';

    // Update subscription record
    $stmt = $mysqli->prepare("
        UPDATE subscriptions 
        SET status = 'active', 
            start_date = ?, 
            end_date = ?, 
            payment_method = ?,
            midtrans_transaction_id = ?
        WHERE midtrans_order_id = ?
    ");
    $stmt->bind_param("sssss", $startDate, $endDate, $paymentMethod, $transactionId, $orderId);
    $stmt->execute();
    $stmt->close();

    // Update user subscription_status = 1
    $stmt = $mysqli->prepare("UPDATE users SET subscription_status = 1 WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    // Award 'first_subscribe' milestone jika belum
    checkAndAwardMilestones($userId);

    error_log("Subscription activated for user $userId, order: $orderId");
}

function updateSubscriptionStatus($orderId, $status) {
    global $mysqli;

    $stmt = $mysqli->prepare("UPDATE subscriptions SET status = ? WHERE midtrans_order_id = ?");
    $stmt->bind_param("ss", $status, $orderId);
    $stmt->execute();
    $stmt->close();

    error_log("Subscription status updated to '$status' for order: $orderId");
}
?>
