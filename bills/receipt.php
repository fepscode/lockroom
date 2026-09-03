<?php
// Official Payment Receipt Generator (Cetak Kwitansi Pembayaran)
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../helpers/auth.php';

if (!isLoggedIn()) {
    setFlash('error', 'Silakan login terlebih dahulu untuk melihat kwitansi.');
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

$currentUser = currentUser();
$pdo = getDBConnection();
$billId = (int)($_GET['bill_id'] ?? 0);

if (!$billId || !$pdo) {
    die("Tagihan tidak ditemukan.");
}

// Fetch complete bill, lease, room, property, tenant, and payment info
$stmt = $pdo->prepare("SELECT b.*, 
                              u.name as tenant_name, u.email as tenant_email, u.phone as tenant_phone,
                              r.room_number, r.type as room_type,
                              p.name as property_name, p.address as property_address, p.city as property_city,
                              owner.name as owner_name, owner.phone as owner_phone,
                              pay.payment_method, pay.payment_date, pay.verified_at, pay.notes as pay_notes
                       FROM bills b
                       JOIN leases l ON b.lease_id = l.id
                       JOIN users u ON b.tenant_id = u.id
                       JOIN rooms r ON l.room_id = r.id
                       JOIN properties p ON r.property_id = p.id
                       JOIN users owner ON p.owner_id = owner.id
                       LEFT JOIN payments pay ON b.id = pay.bill_id
                       WHERE b.id = ? LIMIT 1");
$stmt->execute([$billId]);
$bill = $stmt->fetch();

if (!$bill) {
    die("Data tagihan tidak valid.");
}

// Authorization check: User must be either superadmin, owner of this property, or the tenant themselves
if (!isSuperAdmin() && $currentUser['id'] != $bill['tenant_id'] && $currentUser['role'] !== 'pemilik') {
    die("Anda tidak memiliki akses untuk melihat kwitansi ini.");
}

/**
 * Helper to convert numbers to Indonesian words (Terbilang)
 */
function terbilangRupiah($angka) {
    $angka = abs((float)$angka);
    $bilangan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    $temp = '';
    
    if ($angka < 12) {
        $temp = ' ' . $bilangan[(int)$angka];
    } elseif ($angka < 20) {
        $temp = terbilangRupiah($angka - 10) . ' Belas';
    } elseif ($angka < 100) {
        $temp = terbilangRupiah($angka / 10) . ' Puluh' . terbilangRupiah($angka % 10);
    } elseif ($angka < 200) {
        $temp = ' Seratus' . terbilangRupiah($angka - 100);
    } elseif ($angka < 1000) {
        $temp = terbilangRupiah($angka / 100) . ' Ratus' . terbilangRupiah($angka % 100);
    } elseif ($angka < 2000) {
        $temp = ' Seribu' . terbilangRupiah($angka - 1000);
    } elseif ($angka < 1000000) {
        $temp = terbilangRupiah($angka / 1000) . ' Ribu' . terbilangRupiah($angka % 1000);
    } elseif ($angka < 1000000000) {
        $temp = terbilangRupiah($angka / 1000000) . ' Juta' . terbilangRupiah($angka % 1000000);
    } elseif ($angka < 1000000000000) {
        $temp = terbilangRupiah($angka / 1000000000) . ' Milyar' . terbilangRupiah(fmod($angka, 1000000000));
    }
    return trim($temp);
}

$receiptNo = 'KW/' . date('Y/m', strtotime($bill['created_at'])) . '/' . str_pad($bill['id'], 5, '0', STR_PAD_LEFT);
$paidDate = !empty($bill['verified_at']) ? formatDateIndo($bill['verified_at']) : (!empty($bill['payment_date']) ? formatDateIndo($bill['payment_date']) : formatDateIndo($bill['updated_at']));
$isPaid = ($bill['status'] === 'lunas');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kwitansi Pembayaran - <?= htmlspecialchars($bill['bill_code']) ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; }
        .font-serif-title { font-family: 'Playfair Display', serif; }
        @media print {
            body { background: #ffffff !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .print-shadow-none { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
        }
    </style>
</head>
<body class="p-4 sm:p-8 flex flex-col items-center justify-center min-h-screen text-slate-800">

    <!-- Action Bar (Hide on Print) -->
    <div class="no-print max-w-3xl w-full mb-6 flex items-center justify-between gap-4">
        <button onclick="window.history.back()" class="px-4 py-2.5 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-sm transition-all flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </button>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-600/30 transition-all flex items-center gap-2">
                <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <!-- Official Kwitansi Card -->
    <div class="max-w-3xl w-full bg-white rounded-3xl p-8 sm:p-12 border border-slate-200 shadow-xl print-shadow-none relative overflow-hidden">
        
        <!-- Subtle Top Deco -->
        <div class="absolute top-0 left-0 right-0 h-3 bg-gradient-to-r from-indigo-600 via-purple-600 to-emerald-500"></div>

        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-8 border-b-2 border-slate-100 gap-6">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-extrabold text-sm shadow-md">
                        L&R
                    </div>
                    <span class="font-extrabold text-lg text-slate-900 tracking-tight font-heading">LOCK &amp; ROOM</span>
                </div>
                <h2 class="text-base font-extrabold text-indigo-700"><?= htmlspecialchars($bill['property_name']) ?></h2>
                <p class="text-xs text-slate-500 max-w-sm mt-0.5"><?= htmlspecialchars($bill['property_address']) ?>, <?= htmlspecialchars($bill['property_city']) ?></p>
                <p class="text-xs text-slate-400 mt-1">Telp / WA: <?= htmlspecialchars($bill['owner_phone'] ?: '-') ?></p>
            </div>

            <div class="text-left sm:text-right">
                <div class="text-2xl font-black text-slate-900 tracking-widest font-serif-title uppercase">KWITANSI RESMI</div>
                <div class="text-xs font-bold text-slate-400 mt-1">NO: <span class="font-mono text-slate-800"><?= $receiptNo ?></span></div>
                <div class="text-xs text-slate-500 mt-0.5">Invoice: <span class="font-mono text-indigo-600 font-bold"><?= htmlspecialchars($bill['bill_code']) ?></span></div>
                <div class="text-xs text-slate-500 mt-0.5">Tanggal: <span class="font-bold text-slate-700"><?= $paidDate ?></span></div>
            </div>
        </div>

        <!-- Kwitansi Body -->
        <div class="py-8 space-y-6">
            
            <!-- Row: Telah Diterima Dari -->
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 sm:gap-4 items-baseline border-b border-slate-100 pb-4">
                <div class="sm:col-span-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Telah Diterima Dari</div>
                <div class="sm:col-span-8">
                    <span class="text-base font-extrabold text-slate-900"><?= htmlspecialchars($bill['tenant_name']) ?></span>
                    <span class="text-xs text-slate-500 ml-2">(<?= htmlspecialchars($bill['tenant_phone'] ?: $bill['tenant_email']) ?>)</span>
                </div>
            </div>

            <!-- Row: Uang Sejumlah (Terbilang) -->
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 sm:gap-4 items-baseline border-b border-slate-100 pb-4">
                <div class="sm:col-span-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Uang Sejumlah</div>
                <div class="sm:col-span-8 p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                    <span class="text-sm font-bold text-indigo-900 italic capitalize"># <?= terbilangRupiah($bill['amount']) ?> Rupiah #</span>
                </div>
            </div>

            <!-- Row: Untuk Pembayaran -->
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 sm:gap-4 items-baseline border-b border-slate-100 pb-4">
                <div class="sm:col-span-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Untuk Pembayaran</div>
                <div class="sm:col-span-8 space-y-1">
                    <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($bill['title']) ?></div>
                    <div class="text-xs text-slate-600">Unit: <strong>Kamar <?= htmlspecialchars($bill['room_number']) ?></strong> (Tipe <?= ucfirst($bill['room_type']) ?>)</div>
                    <div class="text-xs text-slate-500">Metode Bayar: <strong><?= htmlspecialchars($bill['payment_method'] ?: 'Transfer Bank / QRIS') ?></strong></div>
                </div>
            </div>

        </div>

        <!-- Total Box & Digital Stamp -->
        <div class="pt-4 pb-8 flex flex-col sm:flex-row items-center justify-between gap-6 border-b-2 border-slate-100">
            
            <!-- Nominal Box -->
            <div class="p-4 px-6 rounded-2xl bg-indigo-50 border-2 border-indigo-200/80 w-full sm:w-auto">
                <div class="text-[10px] font-extrabold uppercase text-indigo-500 tracking-wider">Jumlah Terbayar</div>
                <div class="text-2xl font-black text-indigo-700 font-heading mt-0.5">
                    <?= formatRupiah($bill['amount']) ?>
                </div>
            </div>

            <!-- Digital Stamp LUNAS -->
            <?php if ($isPaid): ?>
                <div class="border-4 border-emerald-600 rounded-2xl p-3 px-6 text-center transform -rotate-3 select-none bg-emerald-50/50 shadow-inner">
                    <div class="text-xl font-black tracking-widest text-emerald-700 font-serif-title uppercase leading-none">L U N A S</div>
                    <div class="text-[9px] font-extrabold text-emerald-600 tracking-wider mt-1 uppercase">VERIFIED &amp; OFFICIAL</div>
                    <div class="text-[8px] font-mono text-emerald-500 mt-0.5"><?= $paidDate ?></div>
                </div>
            <?php else: ?>
                <div class="border-4 border-amber-500 rounded-2xl p-3 px-6 text-center transform -rotate-2 select-none bg-amber-50/50">
                    <div class="text-lg font-black tracking-widest text-amber-600 font-serif-title uppercase leading-none">MENUNGGU VERIFIKASI</div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Footer Signatures -->
        <div class="pt-8 flex flex-col sm:flex-row sm:items-end justify-between gap-6 text-xs text-slate-600">
            <div>
                <p class="text-[11px] text-slate-400">Catatan:</p>
                <p class="text-[11px] text-slate-500 mt-0.5 max-w-xs">
                    Kwitansi ini adalah bukti pembayaran yang sah dan diterbitkan secara digital oleh sistem LOCK &amp; ROOM.
                </p>
            </div>

            <div class="text-center sm:text-right">
                <p class="text-slate-400 text-[11px] mb-1"><?= htmlspecialchars($bill['property_city']) ?>, <?= $paidDate ?></p>
                <p class="font-bold text-slate-700">Pengelola / Pemilik Kos</p>
                <div class="h-16 flex items-center justify-center sm:justify-end">
                    <span class="font-serif-title italic text-slate-300 text-2xl select-none">[ Tanda Tangan Digital ]</span>
                </div>
                <p class="font-extrabold text-slate-900 underline"><?= htmlspecialchars($bill['owner_name']) ?></p>
            </div>
        </div>

    </div>

</body>
</html>
