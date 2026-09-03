<?php
// Export Bills & Payments to Excel (CSV)
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../helpers/auth.php';
requireLogin('pemilik');

$user = currentUser();
$pdo = getDBConnection();

if (!$pdo) {
    die("Database tidak terhubung.");
}

// Fetch bills
if (isSuperAdmin()) {
    $stmt = $pdo->query("SELECT b.*, 
                                u.name as tenant_name, u.phone as tenant_phone,
                                r.room_number, p.name as property_name,
                                pay.payment_method, pay.verified_at, pay.payment_date
                         FROM bills b
                         JOIN leases l ON b.lease_id = l.id
                         JOIN users u ON b.tenant_id = u.id
                         JOIN rooms r ON l.room_id = r.id
                         JOIN properties p ON r.property_id = p.id
                         LEFT JOIN payments pay ON b.id = pay.bill_id
                         ORDER BY b.id DESC");
} else {
    $stmt = $pdo->prepare("SELECT b.*, 
                                  u.name as tenant_name, u.phone as tenant_phone,
                                  r.room_number, p.name as property_name,
                                  pay.payment_method, pay.verified_at, pay.payment_date
                           FROM bills b
                           JOIN leases l ON b.lease_id = l.id
                           JOIN users u ON b.tenant_id = u.id
                           JOIN rooms r ON l.room_id = r.id
                           JOIN properties p ON r.property_id = p.id
                           LEFT JOIN payments pay ON b.id = pay.bill_id
                           WHERE p.owner_id = ?
                           ORDER BY b.id DESC");
    $stmt->execute([$user['id']]);
}

$bills = $stmt->fetchAll();

$filename = "Laporan_Tagihan_Kos_" . date('Ymd_His') . ".csv";

// Clean output buffer to ensure clean file download
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header Row
fputcsv($output, [
    'No. Invoice',
    'Tanggal Terbit',
    'Nama Properti Kos',
    'Nomor Kamar',
    'Nama Penyewa',
    'No. HP Penyewa',
    'Deskripsi Tagihan',
    'Nominal (Rp)',
    'Jatuh Tempo',
    'Status Pembayaran',
    'Metode Bayar',
    'Tanggal Lunas'
]);

// Data Rows
foreach ($bills as $b) {
    $statusLabel = match($b['status']) {
        'lunas' => 'LUNAS',
        'menunggu_verifikasi' => 'MENUNGGU VERIFIKASI',
        'ditolak' => 'DITOLAK',
        default => 'BELUM BAYAR'
    };

    $paidDate = !empty($b['verified_at']) ? date('d/m/Y H:i', strtotime($b['verified_at'])) : (!empty($b['payment_date']) ? date('d/m/Y H:i', strtotime($b['payment_date'])) : '-');

    fputcsv($output, [
        $b['bill_code'],
        date('d/m/Y', strtotime($b['created_at'])),
        $b['property_name'],
        'Kamar ' . $b['room_number'],
        $b['tenant_name'],
        $b['tenant_phone'] ?: '-',
        $b['title'],
        (int)$b['amount'],
        date('d/m/Y', strtotime($b['due_date'])),
        $statusLabel,
        $b['payment_method'] ?: '-',
        $paidDate
    ]);
}

fclose($output);
exit;
