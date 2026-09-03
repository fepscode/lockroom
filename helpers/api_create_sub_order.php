<?php
// Create Subscription Order via AJAX for Instant Real-Time QRIS Monitoring
// LOCK & ROOM (L n' R)

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/subscription.php';
requireLogin('pemilik');

$user = currentUser();
$pdo = getDBConnection();
$plans = getSubscriptionPlans();

$planKey = sanitizeInput($_POST['plan_id'] ?? '');

if (!isset($plans[$planKey])) {
    echo json_encode(['status' => 'error', 'message' => 'Paket tidak valid']);
    exit;
}

$plan = $plans[$planKey];
$amount = $plan['price'];
$orderCode = 'SUB-' . date('Ymd') . '-' . rand(1000, 9999);

try {
    $stmt = $pdo->prepare("INSERT INTO subscription_orders 
        (owner_id, order_code, plan_name, duration_days, amount, payment_method, notes, status) 
        VALUES (?, ?, ?, ?, ?, 'QRIS GoPay', 'Menunggu pembayaran otomatis QRIS GoPay', 'menunggu_konfirmasi')");
    $stmt->execute([
        $user['id'],
        $orderCode,
        $plan['name'],
        $plan['duration_days'],
        $amount
    ]);
    $orderId = $pdo->lastInsertId();

    // Notify Super Admin that an order checkout has been initiated
    notifyAdminNewSubscriptionOrder($orderId);

    echo json_encode([
        'status' => 'success',
        'order_id' => $orderId,
        'order_code' => $orderCode,
        'amount' => $amount,
        'amount_formatted' => formatRupiah($amount),
        'plan_name' => $plan['name']
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
