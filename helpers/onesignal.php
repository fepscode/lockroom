<?php
// OneSignal REST API & Web Push Notification Helper
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../config/database.php';

/**
 * Send Push Notification via OneSignal REST API
 * 
 * @param array|string $targetUserIds Array of User IDs or string User ID
 * @param string $title Notification Heading
 * @param string $message Notification Content/Body
 * @param string $url Target redirect URL when notification clicked
 * @param array $additionalData Custom payload data
 * @return array Result status and response
 */
function sendOneSignalPush($targetUserIds, $title, $message, $url = '', $additionalData = []) {
    if (empty(ONESIGNAL_APP_ID) || ONESIGNAL_APP_ID === 'YOUR_ONESIGNAL_APP_ID' || empty(ONESIGNAL_REST_API_KEY) || ONESIGNAL_REST_API_KEY === 'YOUR_ONESIGNAL_REST_API_KEY') {
        // App ID or API Key not configured yet
        return ['status' => false, 'message' => 'OneSignal credentials are not configured yet in config/database.php'];
    }

    if (!is_array($targetUserIds)) {
        $targetUserIds = [$targetUserIds];
    }

    // Format all user IDs as strings (OneSignal external_id)
    $externalUserIds = array_map('strval', $targetUserIds);

    $fields = [
        'app_id' => ONESIGNAL_APP_ID,
        'include_aliases' => [
            'external_id' => $externalUserIds
        ],
        'target_channel' => 'push',
        'headings' => [
            'en' => $title,
            'id' => $title
        ],
        'contents' => [
            'en' => $message,
            'id' => $message
        ],
        'data' => $additionalData
    ];

    if (!empty($url)) {
        $fields['url'] = $url;
    }

    $authHeader = str_starts_with(ONESIGNAL_REST_API_KEY, 'os_v2_') 
        ? 'Key ' . ONESIGNAL_REST_API_KEY 
        : 'Basic ' . ONESIGNAL_REST_API_KEY;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: ' . $authHeader
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['status' => false, 'error' => $curlErr];
    }

    $resDecoded = json_decode($response, true);
    return [
        'status' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response' => $resDecoded
    ];
}

/**
 * Trigger: Notify Owner when Tenant submits payment proof
 */
function notifyOwnerNewPayment($billId) {
    $pdo = getDBConnection();
    if (!$pdo) return;

    try {
        $stmt = $pdo->prepare("SELECT b.bill_code, b.amount, b.title, r.room_number, p.name as property_name, p.owner_id, u.name as tenant_name
                                FROM bills b
                                JOIN leases l ON b.lease_id = l.id
                                JOIN rooms r ON l.room_id = r.id
                                JOIN properties p ON r.property_id = p.id
                                JOIN users u ON b.tenant_id = u.id
                                WHERE b.id = ? LIMIT 1");
        $stmt->execute([$billId]);
        $data = $stmt->fetch();

        if ($data && !empty($data['owner_id'])) {
            $title = "💳 Bukti Pembayaran Baru Masuk!";
            $msg = "Penyewa {$data['tenant_name']} (Kamar {$data['room_number']}) mengunggah bukti pembayaran " . formatRupiah($data['amount']) . " untuk {$data['title']}.";
            $redirectUrl = BASE_URL . '/owner/bills.php';
            
            sendOneSignalPush($data['owner_id'], $title, $msg, $redirectUrl, ['bill_id' => $billId, 'type' => 'payment_submitted']);
        }
    } catch (Exception $e) {}
}

/**
 * Trigger: Notify Tenant when Owner verifies/approves/rejects payment
 */
function notifyTenantPaymentDecision($billId, $decision) {
    $pdo = getDBConnection();
    if (!$pdo) return;

    try {
        $stmt = $pdo->prepare("SELECT b.tenant_id, b.title, b.amount, r.room_number
                                FROM bills b
                                JOIN leases l ON b.lease_id = l.id
                                JOIN rooms r ON l.room_id = r.id
                                WHERE b.id = ? LIMIT 1");
        $stmt->execute([$billId]);
        $data = $stmt->fetch();

        if ($data && !empty($data['tenant_id'])) {
            if ($decision === 'lunas') {
                $title = "✅ Pembayaran Diterima (LUNAS)!";
                $msg = "Pembayaran untuk {$data['title']} (" . formatRupiah($data['amount']) . ") telah diverifikasi oleh pemilik kos.";
            } else {
                $title = "❌ Bukti Pembayaran Ditolak";
                $msg = "Bukti transfer untuk {$data['title']} belum dapat diverifikasi. Silakan unggah bukti pembayaran yang valid.";
            }
            $redirectUrl = BASE_URL . '/tenant/bills.php';
            
            sendOneSignalPush($data['tenant_id'], $title, $msg, $redirectUrl, ['bill_id' => $billId, 'status' => $decision]);
        }
    } catch (Exception $e) {}
}

/**
 * Trigger: Notify Owner when Tenant creates a new complaint
 */
function notifyOwnerNewComplaint($complaintId) {
    $pdo = getDBConnection();
    if (!$pdo) return;

    try {
        $stmt = $pdo->prepare("SELECT c.title, c.priority, r.room_number, p.owner_id, u.name as tenant_name
                                FROM complaints c
                                JOIN rooms r ON c.room_id = r.id
                                JOIN properties p ON r.property_id = p.id
                                JOIN users u ON c.tenant_id = u.id
                                WHERE c.id = ? LIMIT 1");
        $stmt->execute([$complaintId]);
        $data = $stmt->fetch();

        if ($data && !empty($data['owner_id'])) {
            $title = "⚠️ Laporan Kerusakan/Fasilitas Baru";
            $msg = "Kamar {$data['room_number']} ({$data['tenant_name']}) melaporkan kendala: \"{$data['title']}\" (Prioritas: " . ucfirst($data['priority']) . ").";
            $redirectUrl = BASE_URL . '/owner/complaints.php';
            
            sendOneSignalPush($data['owner_id'], $title, $msg, $redirectUrl, ['complaint_id' => $complaintId, 'type' => 'complaint_created']);
        }
    } catch (Exception $e) {}
}

/**
 * Trigger: Notify Tenant when Owner updates/responds to complaint
 */
function notifyTenantComplaintResponse($complaintId, $status, $adminResponse = '') {
    $pdo = getDBConnection();
    if (!$pdo) return;

    try {
        $stmt = $pdo->prepare("SELECT c.tenant_id, c.title, r.room_number
                                FROM complaints c
                                JOIN rooms r ON c.room_id = r.id
                                WHERE c.id = ? LIMIT 1");
        $stmt->execute([$complaintId]);
        $data = $stmt->fetch();

        if ($data && !empty($data['tenant_id'])) {
            $statusLabel = ($status === 'selesai') ? 'Selesai Diperbaiki' : (($status === 'diproses') ? 'Sedang Diproses' : ucfirst($status));
            $title = "🔧 Update Laporan Fasilitas: $statusLabel";
            $msg = "Laporan \"{$data['title']}\" statusnya telah diubah menjadi: $statusLabel.";
            if (!empty($adminResponse)) {
                $msg .= " Catatan Pemilik: \"$adminResponse\"";
            }
            $redirectUrl = BASE_URL . '/tenant/complaints.php';
            
            sendOneSignalPush($data['tenant_id'], $title, $msg, $redirectUrl, ['complaint_id' => $complaintId, 'status' => $status]);
        }
    } catch (Exception $e) {}
}

/**
 * Trigger: Broadcast announcement from Owner to all active tenants
 */
function sendOneSignalBroadcastToTenants($ownerId, $title, $message, $type = 'info', $propertyId = null) {
    $pdo = getDBConnection();
    if (!$pdo) return;

    try {
        $sql = "SELECT DISTINCT l.tenant_id 
                FROM leases l
                JOIN rooms r ON l.room_id = r.id
                JOIN properties p ON r.property_id = p.id
                WHERE p.owner_id = ? AND l.status = 'aktif'";
        $params = [$ownerId];

        if (!empty($propertyId)) {
            $sql .= " AND p.id = ?";
            $params[] = $propertyId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tenantIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($tenantIds)) {
            $typeIcons = [
                'penting' => '🚨 PENGUMUMAN PENTING: ',
                'peringatan' => '⚠️ PERINGATAN KOS: ',
                'kegiatan' => '📅 INFORMASI KEGIATAN: ',
                'info' => '📢 PENGUMUMAN KOS: '
            ];
            $prefix = $typeIcons[$type] ?? '📢 ';
            $fullTitle = $prefix . $title;
            $redirectUrl = BASE_URL . '/tenant/index.php';

            sendOneSignalPush($tenantIds, $fullTitle, $message, $redirectUrl, [
                'type' => 'broadcast',
                'category' => $type
            ]);
        }
    } catch (Exception $e) {}
}

