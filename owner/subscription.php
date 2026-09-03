<?php
// Subscription & Billing Management for Pemilik Kos
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/subscription.php';
requireLogin('pemilik');

$user = currentUser();
$pdo = getDBConnection();
$plans = getSubscriptionPlans();
$subInfo = getOwnerSubscriptionInfo($user['id']);

$qrisImage = getSystemSetting('qris_image', 'assets/images/qris_merchant.svg');
$merchantName = getSystemSetting('merchant_name', 'LOCK & ROOM (L n\' R)');
$merchantNmid = getSystemSetting('merchant_nmid', 'ID1020030040050');

// Handle Order Creation / Upload Proof
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit_payment') {
        $planKey = sanitizeInput($_POST['plan_id'] ?? '');

        if (!isset($plans[$planKey])) {
            setFlash('error', 'Paket langganan tidak valid!');
        } else {
            $selectedPlan = $plans[$planKey];
            $amount = $selectedPlan['price'];
            $orderCode = 'SUB-' . date('Ymd') . '-' . rand(1000, 9999);

            $stmt = $pdo->prepare("INSERT INTO subscription_orders 
                (owner_id, order_code, plan_name, duration_days, amount, payment_method, notes, status) 
                VALUES (?, ?, ?, ?, ?, 'QRIS GoPay', 'Konfirmasi bayar via QRIS GoPay Merchant', 'menunggu_konfirmasi')");
            $stmt->execute([
                $user['id'],
                $orderCode,
                $selectedPlan['name'],
                $selectedPlan['duration_days'],
                $amount
            ]);
            $orderId = $pdo->lastInsertId();

            // Trigger notification to Super Admin
            notifyAdminNewSubscriptionOrder($orderId);

            setFlash('success', 'Konfirmasi pembayaran berhasil dikirim! Notifikasi telah masuk ke Super Admin untuk persetujuan aktivasi paket Anda.');
            header("Location: subscription.php");
            exit;
        }
    }
}

$pageTitle = 'Paket Langganan & Billing';
require_once __DIR__ . '/header.php';

// Fetch order history for this owner
$stmtOrders = $pdo->prepare("SELECT * FROM subscription_orders WHERE owner_id = ? ORDER BY id DESC");
$stmtOrders->execute([$user['id']]);
$orders = $stmtOrders->fetchAll();
?>

<!-- Header Status Banner -->
<div class="glass-card rounded-3xl p-6 sm:p-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
    <div class="absolute -right-12 -top-12 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr <?= $subInfo['status'] === 'active' ? 'from-emerald-500 to-teal-600' : ($subInfo['status'] === 'trial' ? 'from-amber-500 to-indigo-600' : 'from-rose-500 to-red-600') ?> text-white flex items-center justify-center text-2xl shadow-lg flex-shrink-0">
                <i class="fa-solid <?= $subInfo['status'] === 'active' ? 'fa-circle-check' : ($subInfo['status'] === 'trial' ? 'fa-clock-rotate-left animate-spin-slow' : 'fa-triangle-exclamation') ?>"></i>
            </div>
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider border 
                        <?= $subInfo['status'] === 'active' ? 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30' : ($subInfo['status'] === 'trial' ? 'bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30' : 'bg-rose-50 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30') ?>">
                        <?= $subInfo['status'] === 'active' ? 'LANGGANAN AKTIF' : ($subInfo['status'] === 'trial' ? 'MASA UJI COBA (TRIAL)' : 'KEDALUWARSA (EXPIRED)') ?>
                    </span>
                    <span class="text-xs text-slate-500"><?= htmlspecialchars($subInfo['plan_name']) ?></span>
                </div>
                
                <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">
                    <?= $subInfo['status'] === 'active' ? 'Paket Kos Anda Sedang Aktif' : ($subInfo['status'] === 'trial' ? 'Masa Trial Gratis 14 Hari' : 'Masa Trial Anda Telah Berakhir') ?>
                </h2>

                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    <?php if ($subInfo['status'] === 'active'): ?>
                        Masa aktif berlaku hingga <strong class="text-slate-900 dark:text-white"><?= formatDateIndo($subInfo['ends_at']) ?></strong> (tersisa <span class="text-emerald-600 font-bold"><?= $subInfo['days_remaining'] ?> hari lagi</span>).
                    <?php elseif ($subInfo['status'] === 'trial'): ?>
                        Sisa masa uji coba gratis Anda: <strong class="text-indigo-600 dark:text-amber-400 font-bold"><?= $subInfo['days_remaining'] ?> hari lagi</strong> (berakhir tanggal <?= formatDateIndo($subInfo['ends_at']) ?>).
                    <?php else: ?>
                        Masa trial Anda telah habis. Silakan pilih paket langganan di bawah untuk melanjutkan pengelolaan kos Anda.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div>
            <a href="#pilih-paket" class="px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-qrcode"></i> <?= $subInfo['status'] === 'active' ? 'Perpanjang Paket' : 'Upgrade Langganan Sekarang' ?>
            </a>
        </div>
    </div>
</div>

<!-- Section: Pilihan Paket Langganan -->
<div id="pilih-paket" class="space-y-6 pt-4">
    <div class="text-center max-w-xl mx-auto">
        <h3 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Pilih Paket Langganan Kos</h3>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">Semua paket mencakup pengelolaan kamar tanpa batas, tagihan otomatis, broadcast, dan notifikasi push smartphone.</p>
    </div>

    <!-- Pricing Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($plans as $key => $p): ?>
            <div class="glass-card rounded-3xl p-6 sm:p-7 border <?= $p['popular'] ? 'border-indigo-500 dark:border-indigo-500 ring-2 ring-indigo-500/20 shadow-xl' : 'border-slate-200 dark:border-slate-800 shadow-sm' ?> bg-white dark:bg-slate-900 flex flex-col justify-between relative overflow-hidden">
                
                <?php if ($p['popular']): ?>
                    <div class="absolute -right-12 top-6 bg-gradient-to-r from-amber-500 to-indigo-600 text-white text-[10px] font-extrabold py-1 px-12 rotate-45 shadow-md uppercase tracking-wider">
                        Paling Hemat
                    </div>
                <?php endif; ?>

                <div>
                    <div class="inline-block px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider mb-3 <?= $p['popular'] ? 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' ?>">
                        <?= $p['badge'] ?>
                    </div>
                    
                    <h4 class="text-lg font-extrabold text-slate-900 dark:text-white font-heading"><?= htmlspecialchars($p['name']) ?></h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 min-h-[36px]"><?= htmlspecialchars($p['description']) ?></p>

                    <div class="my-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <div class="text-3xl font-extrabold text-slate-900 dark:text-white font-heading">
                            <?= $p['price_label'] ?>
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Durasi aktif <?= $p['duration_days'] ?> hari penuh</div>
                    </div>

                    <!-- Features List -->
                    <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300 mb-6">
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-emerald-500"></i> Kelola Kamar & Properti Kos
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-emerald-500"></i> Terbitkan Tagihan & Bukti Bayar
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-emerald-500"></i> Broadcast Notifikasi HP ke Penghuni
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-emerald-500"></i> Penanganan Pengaduan Fasilitas
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-emerald-500"></i> Fitur App Lock Sandi Keamanan
                        </li>
                    </ul>
                </div>

                <button onclick="openQrisModal('<?= $key ?>', '<?= addslashes($p['name']) ?>', <?= $p['price'] ?>, '<?= $p['price_label'] ?>')" type="button" class="w-full py-3.5 px-4 rounded-xl font-bold text-xs transition-all flex items-center justify-center gap-2 <?= $p['popular'] ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-white' ?>">
                    <i class="fa-solid fa-qrcode text-sm"></i> Bayar dengan QRIS
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Riwayat Pesanan Langganan -->
<div class="space-y-4 pt-6">
    <h3 class="text-lg font-extrabold text-slate-900 dark:text-white font-heading flex items-center gap-2">
        <i class="fa-solid fa-clock-rotate-left text-indigo-600"></i> Riwayat Pembayaran Langganan
    </h3>

    <?php if (empty($orders)): ?>
        <div class="p-8 rounded-3xl border border-dashed border-slate-300 dark:border-slate-800 text-center bg-white dark:bg-slate-900/60">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto text-xl mb-3">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div class="text-sm font-bold text-slate-900 dark:text-white">Belum Ada Riwayat Transaksi</div>
            <p class="text-xs text-slate-500 mt-1">Transaksi langganan Anda akan tercatat di sini setelah Anda melakukan pembayaran.</p>
        </div>
    <?php else: ?>
        <div class="glass-card rounded-3xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-bold uppercase tracking-wider">Kode Order</th>
                            <th class="py-3.5 px-4 font-bold uppercase tracking-wider">Paket</th>
                            <th class="py-3.5 px-4 font-bold uppercase tracking-wider">Nominal</th>
                            <th class="py-3.5 px-4 font-bold uppercase tracking-wider">Metode</th>
                            <th class="py-3.5 px-4 font-bold uppercase tracking-wider">Status</th>
                            <th class="py-3.5 px-4 font-bold uppercase tracking-wider">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                        <?php foreach ($orders as $ord): ?>
                            <tr>
                                <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($ord['order_code']) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($ord['plan_name']) ?></td>
                                <td class="py-3 px-4 font-bold text-slate-900 dark:text-white"><?= formatRupiah($ord['amount']) ?></td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 font-bold text-[10px]">
                                        <?= htmlspecialchars($ord['payment_method']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($ord['status'] === 'disetujui'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-extrabold text-[10px]">LUNAS &amp; AKTIF</span>
                                    <?php elseif ($ord['status'] === 'ditolak'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full bg-rose-50 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 font-extrabold text-[10px]">DITOLAK</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 font-extrabold text-[10px] animate-pulse">MENUNGGU VERIFIKASI</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-slate-400"><?= date('d M Y, H:i', strtotime($ord['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ================= MODAL CHECKOUT QRIS GOPAY ================= -->
<div id="qrisModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="relative max-w-md w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-2xl animate-in zoom-in-95 duration-200">
        
        <!-- Modal Header -->
        <div class="p-4 px-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50 dark:bg-slate-950">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-qrcode text-indigo-600"></i>
                <div class="text-sm font-extrabold text-slate-900 dark:text-white font-heading">Pembayaran QRIS Universal</div>
            </div>
            <button onclick="closeQrisModal()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- 1. WAITING FOR PAYMENT STATE -->
        <div id="paymentWaitingState" class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
            
            <!-- Ringkasan Paket & Nominal Pas -->
            <div class="p-4 rounded-2xl bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-500/30 flex items-center justify-between">
                <div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold" id="modalPlanNameText">Paket Langganan</div>
                    <div class="text-xl font-extrabold text-indigo-700 dark:text-cyan-400 font-heading mt-0.5" id="modalAmountText">Rp 0</div>
                </div>
                <div class="text-right">
                    <div class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-[10px] font-bold uppercase tracking-wider inline-block">
                        Nominal Pas
                    </div>
                    <div class="text-[10px] font-mono text-slate-400 mt-1" id="modalOrderCodeText">SUB-WAITING</div>
                </div>
            </div>

            <!-- Real-time Live Status Pulse -->
            <div class="flex items-center justify-center gap-2 py-1 px-3 rounded-full bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-[11px] font-bold">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                <span>Mendeteksi Pembayaran Otomatis...</span>
            </div>

            <!-- Barcode QRIS Display -->
            <div class="text-center space-y-2.5">
                <div class="text-xs text-slate-600 dark:text-slate-300 font-semibold">
                    Scan barcode di bawah menggunakan aplikasi e-wallet atau m-Banking Anda:
                </div>
                
                <div class="w-64 h-80 mx-auto rounded-2xl overflow-hidden border-2 border-slate-200 dark:border-slate-700 shadow-md bg-white p-2">
                    <img id="qrisDisplayImage" src="../<?= htmlspecialchars($qrisImage) ?>" alt="QRIS Merchant GoPay" class="w-full h-full object-contain">
                </div>

                <!-- Tombol Download QRIS untuk Bayar dari Galeri HP -->
                <div class="flex items-center justify-center gap-2 pt-0.5">
                    <a href="../<?= htmlspecialchars($qrisImage) ?>" download="QRIS_Pembayaran_LockRoom" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold border border-slate-200 dark:border-slate-700 transition-all shadow-sm">
                        <i class="fa-solid fa-download text-indigo-500"></i>
                        <span>Download QRIS (Bayar dari Galeri)</span>
                    </a>
                </div>

                <div class="text-[11px] text-slate-400 flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-shield-check text-emerald-500"></i>
                    <span>Dapat discan via: GoPay, DANA, OVO, BCA, Mandiri, BRImo, dll.</span>
                </div>
            </div>

            <!-- Tombol Simulasi Pengujian (Developer / Testing Otomatis) -->
            <div class="pt-2">
                <button onclick="simulatePaymentNow()" type="button" class="w-full py-3 px-4 rounded-xl font-bold text-xs text-emerald-700 dark:text-emerald-300 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-500/30 transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-bolt text-amber-500"></i> Coba Simulasi Bayar Lunas (Testing Otomatis)
                </button>
            </div>

        </div>

        <!-- 2. PAYMENT SUCCESS CELEBRATION STATE (HIDDEN BY DEFAULT) -->
        <div id="paymentSuccessState" class="p-8 text-center space-y-4 hidden">
            <div class="w-20 h-20 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-4xl mx-auto shadow-xl animate-bounce">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            
            <h3 class="text-xl font-extrabold text-slate-900 dark:text-white font-heading">Pembayaran Berhasil!</h3>
            
            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                Terima kasih! Pembayaran QRIS Anda telah <strong class="text-emerald-600">terverifikasi otomatis</strong>. Masa aktif akun Anda telah diperpanjang.
            </p>

            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs font-mono text-slate-500" id="successOrderDetails">
                Status: LUNAS &amp; AKTIF
            </div>

            <div class="text-[11px] text-slate-400">
                Halaman akan memuat ulang otomatis dalam 2 detik...
            </div>
        </div>

    </div>
</div>

<script>
let activeOrderCode = null;
let orderCheckTimer = null;

function openQrisModal(planId, planName, amount, priceLabel) {
    document.getElementById('modalPlanNameText').innerText = planName;
    document.getElementById('modalAmountText').innerText = priceLabel;
    document.getElementById('modalOrderCodeText').innerText = 'Membuat Order...';
    
    // Reset States
    document.getElementById('paymentWaitingState').classList.remove('hidden');
    document.getElementById('paymentSuccessState').classList.add('hidden');
    document.getElementById('qrisModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // Initialize Order in Database via AJAX
    const formData = new FormData();
    formData.append('plan_id', planId);

    fetch('../helpers/api_create_sub_order.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            activeOrderCode = data.order_code;
            document.getElementById('modalOrderCodeText').innerText = data.order_code;
            
            // Start Auto Polling Payment Status
            startPaymentPolling(activeOrderCode);
        }
    })
    .catch(err => {
        console.error("Order init error", err);
    });
}

function startPaymentPolling(orderCode) {
    if (orderCheckTimer) clearInterval(orderCheckTimer);

    orderCheckTimer = setInterval(() => {
        if (!orderCode) return;

        fetch(`../helpers/api_check_order.php?order_code=${orderCode}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'paid') {
                clearInterval(orderCheckTimer);
                showPaymentSuccessScreen(data);
            }
        })
        .catch(err => console.log('Polling check...', err));
    }, 2500);
}

function simulatePaymentNow() {
    if (!activeOrderCode) return;

    fetch(`../helpers/api_check_order.php?order_code=${activeOrderCode}&simulate_pay=1`)
    .then(res => res.json())
    .then(data => {
        if (data.status === 'paid') {
            clearInterval(orderCheckTimer);
            showPaymentSuccessScreen(data);
        }
    });
}

function showPaymentSuccessScreen(data) {
    document.getElementById('paymentWaitingState').classList.add('hidden');
    document.getElementById('paymentSuccessState').classList.remove('hidden');
    
    setTimeout(() => {
        window.location.reload();
    }, 2500);
}

function closeQrisModal() {
    if (orderCheckTimer) clearInterval(orderCheckTimer);
    document.getElementById('qrisModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

