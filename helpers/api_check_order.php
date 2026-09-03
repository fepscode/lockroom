<?php
// API Endpoint to Check Real-Time Order Status & Simulation
// LOCK & ROOM (L n' R)

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/subscription.php';

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'DB offline']);
    exit;
}

$orderCode = sanitizeInput($_GET['order_code'] ?? '');
$simulatePay = isset($_GET['simulate_pay']) && $_GET['simulate_pay'] == '1';

if (empty($orderCode)) {
    echo json_encode(['status' => 'error', 'message' => 'Order code is required']);
    exit;
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM subscription_orders WHERE order_code = ? LIMIT 1");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

// Developer Testing: Simulate automatic webhook payment if requested
if ($simulatePay && $order['status'] !== 'disetujui') {
    $ownerId = $order['owner_id'];
    $durationDays = (int)$order['duration_days'];

    // Calculate new expiration date
    $stmtUser = $pdo->prepare("SELECT subscription_ends_at FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$ownerId]);
    $currentSub = $stmtUser->fetchColumn();

    $baseTime = (strtotime($currentSub) > time()) ? strtotime($currentSub) : time();
    $newEndsAt = date('Y-m-d H:i:s', strtotime("+{$durationDays} days", $baseTime));

    // Update user subscription
    $stmtUpdateUser = $pdo->prepare("UPDATE users SET 
        subscription_status = 'active', 
        subscription_ends_at = ?, 
        subscription_plan = ? 
        WHERE id = ?");
    $stmtUpdateUser->execute([$newEndsAt, $order['plan_name'], $ownerId]);

    // Update order status
    $stmtUpdateOrder = $pdo->prepare("UPDATE subscription_orders SET 
        status = 'disetujui', 
        verified_at = NOW(), 
        notes = 'Simulasi Pembayaran Otomatis QRIS GoPay Sukses' 
        WHERE id = ?");
    $stmtUpdateOrder->execute([$order['id']]);

    // Send OneSignal push
    notifyOwnerSubscriptionApproved($order['id']);

    echo json_encode([
        'status' => 'paid',
        'simulated' => true,
        'message' => 'Pembayaran berhasil disimulasikan sebagai lunas!',
        'new_ends_at' => formatDateIndo($newEndsAt)
    ]);
    exit;
}

if ($order['status'] === 'disetujui') {
    echo json_encode([
        'status' => 'paid',
        'order_code' => $order['order_code'],
        'plan_name' => $order['plan_name'],
        'verified_at' => $order['verified_at']
    ]);
} elseif ($order['status'] === 'ditolak') {
    echo json_encode(['status' => 'rejected']);
} else {
    echo json_encode(['status' => 'pending']);
}
