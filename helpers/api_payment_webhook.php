<?php
// Midtrans Automatic Webhook Callback Handler
// LOCK & ROOM (L n' R)

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/midtrans.php';
require_once __DIR__ . '/subscription.php';
require_once __DIR__ . '/onesignal.php';

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$notif = json_decode($rawInput, true);

if (!$notif || empty($notif['order_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid notification payload']);
    exit;
}

$orderCode = $notif['order_id'];
$statusCode = $notif['status_code'] ?? '';
$grossAmount = $notif['gross_amount'] ?? '';
$signatureKey = $notif['signature_key'] ?? '';
$transactionStatus = $notif['transaction_status'] ?? '';
$paymentType = $notif['payment_type'] ?? 'qris';

// Verify Midtrans Signature if in live/configured mode
if (isMidtransConfigured()) {
    if (!verifyMidtransWebhookSignature($orderCode, $statusCode, $grossAmount, $signatureKey)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
        exit;
    }
}

// Find the subscription order in database
$stmt = $pdo->prepare("SELECT * FROM subscription_orders WHERE order_code = ? LIMIT 1");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    // If not found in subscription_orders, it might be a tenant room bill (future expansion)
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'message' => 'Order code not found in subscription_orders']);
    exit;
}

// Check if transaction is paid successfully (settlement / capture)
if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && ($notif['fraud_status'] ?? '') === 'accept')) {
    
    // Only process if not already approved
    if ($order['status'] !== 'disetujui') {
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

        // Update order status to approved
        $stmtUpdateOrder = $pdo->prepare("UPDATE subscription_orders SET 
            status = 'disetujui', 
            payment_method = ?, 
            verified_at = NOW(), 
            notes = 'Otomatis lunas diverifikasi via Midtrans Webhook (QRIS GoPay)' 
            WHERE id = ?");
        $stmtUpdateOrder->execute([strtoupper($paymentType), $order['id']]);

        // Send Push Notifications via OneSignal
        notifyOwnerSubscriptionApproved($order['id']);

        // Notify Super Admin
        $stmtAdmin = $pdo->query("SELECT id FROM users WHERE role = 'superadmin' ORDER BY id ASC LIMIT 1");
        $adminId = $stmtAdmin->fetchColumn() ?: 1;
        sendOneSignalPush($adminId, "✅ Pembayaran QRIS Berhasil Masuk!", "Pemilik akun telah membayar {$order['plan_name']} sebesar " . formatRupiah($order['amount']) . " dan akun telah aktif otomatis.", BASE_URL . '/owner/admin_subscriptions.php');
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Payment settled and subscription activated']);
    exit;

} elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
    
    if ($order['status'] === 'menunggu_konfirmasi') {
        $stmtUpdate = $pdo->prepare("UPDATE subscription_orders SET status = 'ditolak', admin_notes = ? WHERE id = ?");
        $stmtUpdate->execute(['Transaksi ' . $transactionStatus . ' oleh Midtrans', $order['id']]);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Payment status updated to rejected']);
    exit;

} else {
    // Other pending statuses
    http_response_code(200);
    echo json_encode(['status' => 'pending', 'message' => 'Payment is still pending']);
    exit;
}
