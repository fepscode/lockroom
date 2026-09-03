<?php
// Subscription & SaaS Trial Management Helper
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/onesignal.php';

/**
 * Get Available Subscription Plans
 */
function getSubscriptionPlans() {
    return [
        'paket_1_bulan' => [
            'id' => 'paket_1_bulan',
            'name' => 'Paket Basic (1 Bulan)',
            'duration_days' => 30,
            'price' => 39900,
            'price_label' => 'Rp 39.900',
            'badge' => 'BULANAN',
            'description' => 'Akses penuh seluruh fitur manajemen kos & kontrakan selama 30 hari.',
            'popular' => false
        ],
        'paket_6_bulan' => [
            'id' => 'paket_6_bulan',
            'name' => 'Paket Hemat (6 Bulan)',
            'duration_days' => 180,
            'price' => 219000,
            'price_label' => 'Rp 219.000',
            'badge' => 'HEMAT 10%',
            'description' => 'Akses operasional kos stabil selama setengah tahun (180 hari). Hemat 10%.',
            'popular' => false
        ],
        'paket_1_tahun' => [
            'id' => 'paket_1_tahun',
            'name' => 'Paket Pro (1 Tahun)',
            'duration_days' => 365,
            'price' => 399000,
            'price_label' => 'Rp 399.000',
            'badge' => 'TERBAIK & HEMAT 2 BULAN',
            'description' => 'Pilihan paling hemat untuk pengusaha kos. Bebas cemas 365 hari (cukup bayar 10 bulan).',
            'popular' => true
        ]
    ];
}

/**
 * Get System Setting with fallback
 */
function getSystemSetting($key, $default = '') {
    $pdo = getDBConnection();
    if (!$pdo) return $default;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Update System Setting
 */
function updateSystemSetting($key, $value) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get Comprehensive Subscription & Trial Status for an Owner
 */
function getOwnerSubscriptionInfo($ownerId) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return [
            'status' => 'trial',
            'is_active' => true,
            'days_remaining' => 14,
            'ends_at' => date('Y-m-d H:i:s', strtotime('+14 days')),
            'plan_name' => 'Free Trial 14 Hari'
        ];
    }

    try {
        $stmt = $pdo->prepare("SELECT subscription_status, trial_ends_at, subscription_ends_at, subscription_plan, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$ownerId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['status' => 'expired', 'is_active' => false, 'days_remaining' => 0];
        }

        $now = time();
        $trialEnds = !empty($user['trial_ends_at']) ? strtotime($user['trial_ends_at']) : strtotime($user['created_at'] . ' +14 days');
        $subEnds = !empty($user['subscription_ends_at']) ? strtotime($user['subscription_ends_at']) : null;

        // Check if active subscription
        if ($subEnds && $subEnds > $now) {
            $daysLeft = ceil(($subEnds - $now) / 86400);
            return [
                'status' => 'active',
                'is_active' => true,
                'days_remaining' => max(0, (int)$daysLeft),
                'ends_at' => date('Y-m-d H:i:s', $subEnds),
                'plan_name' => $user['subscription_plan'] ?: 'Paket Berlangganan'
            ];
        }

        // Check if in 14-day trial
        if ($trialEnds > $now) {
            $daysLeft = ceil(($trialEnds - $now) / 86400);
            return [
                'status' => 'trial',
                'is_active' => true,
                'days_remaining' => max(1, (int)$daysLeft),
                'ends_at' => date('Y-m-d H:i:s', $trialEnds),
                'plan_name' => 'Free Trial 14 Hari'
            ];
        }

        // Otherwise expired
        return [
            'status' => 'expired',
            'is_active' => false,
            'days_remaining' => 0,
            'ends_at' => date('Y-m-d H:i:s', $subEnds ?: $trialEnds),
            'plan_name' => $user['subscription_plan'] ?: 'Expired'
        ];

    } catch (Exception $e) {
        return ['status' => 'trial', 'is_active' => true, 'days_remaining' => 14];
    }
}

/**
 * Check if Owner is Allowed to Use Full App Features
 */
function isOwnerSubscriptionActive($ownerId) {
    $info = getOwnerSubscriptionInfo($ownerId);
    return $info['is_active'];
}

/**
 * Notify Admin when Owner submits subscription payment
 */
function notifyAdminNewSubscriptionOrder($orderId) {
    $pdo = getDBConnection();
    if (!$pdo) return;

    try {
        $stmt = $pdo->prepare("SELECT o.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone 
                                FROM subscription_orders o 
                                JOIN users u ON o.owner_id = u.id 
                                WHERE o.id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order) {
            // Find Super Admin user
            $stmtAdmin = $pdo->query("SELECT id FROM users WHERE role = 'superadmin' ORDER BY id ASC LIMIT 1");
            $adminId = $stmtAdmin->fetchColumn() ?: 1;

            $title = "💳 Konfirmasi Pembayaran QRIS GoPay Masuk";
            $message = "Pemilik {$order['owner_name']} telah membayar {$order['plan_name']} (" . formatRupiah($order['amount']) . ") via QRIS. Klik untuk approve!";
            $url = BASE_URL . '/owner/admin_subscriptions.php';

            sendOneSignalPush($adminId, $title, $message, $url, [
                'type' => 'subscription_order',
                'order_id' => $orderId
            ]);
        }
    } catch (Exception $e) {}
}

/**
 * Notify Owner when Subscription is Approved by Admin
 */
function notifyOwnerSubscriptionApproved($orderId) {
    notifyPaymentSettledBoth($orderId);
}

/**
 * Notify BOTH Customer (Owner) and Super Admin when Payment is LUNAS
 */
function notifyPaymentSettledBoth($orderId) {
    $pdo = getDBConnection();
    if (!$pdo) return;

    try {
        $stmt = $pdo->prepare("SELECT o.*, u.name as owner_name, u.subscription_ends_at 
                                FROM subscription_orders o 
                                JOIN users u ON o.owner_id = u.id 
                                WHERE o.id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order) {
            $expiryDate = formatDateIndo($order['subscription_ends_at']);

            // 1. Notifikasi Pop-Up ke Pelanggan (Pemilik Kos yang membayar)
            if (!empty($order['owner_id'])) {
                $customerTitle = "🎉 Pembayaran Lunas & Akun Aktif!";
                $customerMsg = "Pembayaran untuk {$order['plan_name']} telah berhasil diterima via QRIS GoPay. Masa aktif kos Anda diperpanjang hingga {$expiryDate}.";
                $customerUrl = BASE_URL . '/owner/subscription.php';

                sendOneSignalPush($order['owner_id'], $customerTitle, $customerMsg, $customerUrl, [
                    'type' => 'subscription_approved',
                    'order_id' => $orderId
                ]);
            }

            // 2. Notifikasi Pop-Up ke Super Admin (Pemilik Aplikasi)
            $stmtAdmin = $pdo->query("SELECT id FROM users WHERE role = 'superadmin' ORDER BY id ASC LIMIT 1");
            $adminId = $stmtAdmin->fetchColumn() ?: 1;

            $adminTitle = "💰 Uang Masuk: Pembayaran QRIS Lunas!";
            $adminMsg = "Pembayaran dari {$order['owner_name']} ({$order['plan_name']}) sebesar " . formatRupiah($order['amount']) . " telah LUNAS terverifikasi.";
            $adminUrl = BASE_URL . '/owner/admin_subscriptions.php';

            sendOneSignalPush($adminId, $adminTitle, $adminMsg, $adminUrl, [
                'type' => 'subscription_settled',
                'order_id' => $orderId
            ]);
        }
    } catch (Exception $e) {}
}

