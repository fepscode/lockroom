<?php
// Midtrans Official Payment Gateway Engine
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../config/database.php';

/**
 * Check if Midtrans Keys are configured with real or sandbox keys
 */
function isMidtransConfigured() {
    return defined('MIDTRANS_SERVER_KEY') && 
           !empty(MIDTRANS_SERVER_KEY) && 
           strpos(MIDTRANS_SERVER_KEY, 'YOUR_SERVER_KEY') === false;
}

/**
 * Get Midtrans Snap API Endpoint URL
 */
function getMidtransSnapUrl() {
    return (defined('MIDTRANS_IS_PRODUCTION') && MIDTRANS_IS_PRODUCTION)
        ? 'https://app.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
}

/**
 * Get Midtrans Snap JS URL for Frontend Script
 */
function getMidtransSnapJsUrl() {
    return (defined('MIDTRANS_IS_PRODUCTION') && MIDTRANS_IS_PRODUCTION)
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js';
}

/**
 * Create Midtrans Snap Token & Redirect URL
 */
function createMidtransSnapTransaction($orderCode, $amount, $planName, $customer) {
    if (!isMidtransConfigured()) {
        // Return simulated response for development/testing if API key not yet entered
        return [
            'success' => true,
            'simulated' => true,
            'token' => 'simulated_snap_token_' . md5($orderCode),
            'redirect_url' => '#'
        ];
    }

    $payload = [
        'transaction_details' => [
            'order_id' => $orderCode,
            'gross_amount' => (int)$amount
        ],
        'item_details' => [
            [
                'id' => 'SUB-' . substr(md5($planName), 0, 8),
                'price' => (int)$amount,
                'quantity' => 1,
                'name' => substr($planName, 0, 50)
            ]
        ],
        'customer_details' => [
            'first_name' => substr($customer['name'] ?? 'Pelanggan', 0, 40),
            'email' => $customer['email'] ?? 'pelanggan@lockroom.com',
            'phone' => $customer['phone'] ?? '08123456789'
        ],
        'enabled_payments' => ['gopay', 'qris', 'bca_va', 'bni_va', 'bri_va', 'mandiri_bill', 'permata_va', 'shopeepay'],
        'qris' => [
            'acquirer' => 'gopay'
        ]
    ];

    $authHeader = base64_encode(MIDTRANS_SERVER_KEY . ':');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, getMidtransSnapUrl());
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . $authHeader
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode === 201 && !empty($data['token'])) {
        return [
            'success' => true,
            'simulated' => false,
            'token' => $data['token'],
            'redirect_url' => $data['redirect_url'] ?? '#'
        ];
    } else {
        return [
            'success' => false,
            'error' => $data['error_messages'][0] ?? 'Gagal membuat transaksi Midtrans. Periksa konfigurasi Server Key.'
        ];
    }
}

/**
 * Verify Midtrans Webhook Signature
 */
function verifyMidtransWebhookSignature($orderId, $statusCode, $grossAmount, $signatureKey) {
    if (!defined('MIDTRANS_SERVER_KEY')) return false;

    // Signature formula: SHA512(order_id + status_code + gross_amount + ServerKey)
    $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . MIDTRANS_SERVER_KEY);
    return hash_equals($expectedSignature, $signatureKey);
}
