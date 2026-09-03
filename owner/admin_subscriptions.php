<?php
// Admin Subscription Verification & QRIS Management
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/subscription.php';
requireLogin('pemilik');

if (!isSuperAdmin()) {
    setFlash('error', 'Akses ditolak! Halaman ini hanya untuk Pemilik Aplikasi (Super Admin).');
    header("Location: index.php");
    exit;
}

$user = currentUser();
$pdo = getDBConnection();

// Handle Actions: Approve Order, Reject Order, Update QRIS Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. APPROVE SUBSCRIPTION ORDER
    if ($action === 'approve_order') {
        $orderId = (int)$_POST['order_id'];

        $stmt = $pdo->prepare("SELECT * FROM subscription_orders WHERE id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order && $order['status'] === 'menunggu_konfirmasi') {
            $ownerId = $order['owner_id'];
            $durationDays = (int)$order['duration_days'];

            // Fetch current user subscription ends
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
                verified_by = ? 
                WHERE id = ?");
            $stmtUpdateOrder->execute([$user['id'], $orderId]);

            // Fire OneSignal notification to the owner
            notifyOwnerSubscriptionApproved($orderId);

            setFlash('success', "Pembayaran langganan berhasil disetujui! Masa aktif akun pemilik telah diperpanjang hingga " . formatDateIndo($newEndsAt) . ".");
        }
        header("Location: admin_subscriptions.php");
        exit;
    }

    // 2. REJECT SUBSCRIPTION ORDER
    if ($action === 'reject_order') {
        $orderId = (int)$_POST['order_id'];
        $reason = sanitizeInput($_POST['admin_notes'] ?? 'Bukti pembayaran tidak valid atau dana belum masuk.');

        $stmt = $pdo->prepare("UPDATE subscription_orders SET 
            status = 'ditolak', 
            admin_notes = ?, 
            verified_at = NOW(), 
            verified_by = ? 
            WHERE id = ?");
        $stmt->execute([$reason, $user['id'], $orderId]);

        setFlash('error', 'Pembayaran langganan telah ditolak.');
        header("Location: admin_subscriptions.php");
        exit;
    }

    // 3. UPDATE QRIS SETTINGS & REPLACE QRIS IMAGE
    if ($action === 'update_qris_settings') {
        $merchantName = sanitizeInput($_POST['merchant_name'] ?? '');
        $merchantNmid = sanitizeInput($_POST['merchant_nmid'] ?? '');

        updateSystemSetting('merchant_name', $merchantName);
        updateSystemSetting('merchant_nmid', $merchantNmid);

        // Handle QRIS Image Replacement
        if (isset($_FILES['qris_file']) && $_FILES['qris_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../assets/images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['qris_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
                $filename = 'qris_merchant_' . time() . '.' . $ext;
                $target = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['qris_file']['tmp_name'], $target)) {
                    updateSystemSetting('qris_image', 'assets/images/' . $filename);
                }
            }
        }

        setFlash('success', 'Pengaturan QRIS Merchant berhasil diperbarui! Gambar barcode QRIS baru langsung aktif di halaman checkout.');
        header("Location: admin_subscriptions.php");
        exit;
    }
}

$pageTitle = 'Kelola Pembayaran Langganan & QRIS';
require_once __DIR__ . '/header.php';

// Fetch all subscription orders
$stmtOrders = $pdo->query("SELECT o.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone 
                           FROM subscription_orders o 
                           JOIN users u ON o.owner_id = u.id 
                           ORDER BY FIELD(o.status, 'menunggu_konfirmasi', 'disetujui', 'ditolak'), o.id DESC");
$allOrders = $stmtOrders->fetchAll();

$pendingCount = 0;
foreach ($allOrders as $o) {
    if ($o['status'] === 'menunggu_konfirmasi') $pendingCount++;
}

$currentQris = getSystemSetting('qris_image', 'assets/images/qris_merchant.svg');
$currentName = getSystemSetting('merchant_name', 'LOCK & ROOM (L n\' R)');
$currentNmid = getSystemSetting('merchant_nmid', 'ID1020030040050');
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-1.5 bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30">
            <i class="fa-solid fa-shield-halved"></i> Panel Admin Platform
        </div>
        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Verifikasi Langganan &amp; Pengaturan QRIS</h2>
        <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Setujui bukti transfer QRIS pemilik kos dan ganti gambar QRIS GoPay Merchant kapan saja.</p>
    </div>

    <div class="flex items-center gap-3">
        <span class="px-4 py-2 rounded-2xl bg-amber-50 dark:bg-amber-500/20 border border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-300 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-bell animate-pulse"></i> <?= $pendingCount ?> Menunggu Persetujuan
        </span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- Daftar Verifikasi Pembayaran Langganan (8 Kolom) -->
    <div class="lg:col-span-8 space-y-4">
        <h3 class="text-lg font-extrabold text-slate-900 dark:text-white font-heading flex items-center gap-2">
            <i class="fa-solid fa-list-check text-indigo-600"></i> Pengajuan Pembayaran Langganan
        </h3>

        <?php if (empty($allOrders)): ?>
            <div class="p-8 rounded-3xl border border-dashed border-slate-300 dark:border-slate-800 text-center bg-white dark:bg-slate-900/60">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto text-xl mb-3">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <div class="text-sm font-bold text-slate-900 dark:text-white">Belum Ada Pengajuan Pembayaran</div>
                <p class="text-xs text-slate-500 mt-1">Saat ada pemilik kos yang membayar via QRIS, pengajuan akan muncul di sini untuk Anda verifikasi.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($allOrders as $ord): ?>
                    <div class="glass-card rounded-3xl p-6 border <?= $ord['status'] === 'menunggu_konfirmasi' ? 'border-amber-400 dark:border-amber-500/40 ring-1 ring-amber-400/20' : 'border-slate-200 dark:border-slate-800' ?> bg-white dark:bg-slate-900 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                        
                        <!-- Left: Info Transaksi & Pemilik -->
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold text-indigo-600 dark:text-cyan-400"><?= htmlspecialchars($ord['order_code']) ?></span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider 
                                    <?= $ord['status'] === 'disetujui' ? 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' : ($ord['status'] === 'ditolak' ? 'bg-rose-50 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300' : 'bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 animate-pulse') ?>">
                                    <?= $ord['status'] === 'disetujui' ? 'LUNAS & AKTIF' : ($ord['status'] === 'ditolak' ? 'DITOLAK' : 'MENUNGGU APPROVE') ?>
                                </span>
                            </div>

                            <div>
                                <h4 class="text-base font-extrabold text-slate-900 dark:text-white font-heading"><?= htmlspecialchars($ord['owner_name']) ?></h4>
                                <div class="text-xs text-slate-500 flex items-center gap-3 mt-0.5">
                                    <span><i class="fa-solid fa-envelope text-slate-400"></i> <?= htmlspecialchars($ord['owner_email']) ?></span>
                                    <span><i class="fa-solid fa-phone text-slate-400"></i> <?= htmlspecialchars($ord['owner_phone']) ?></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 pt-1 text-xs">
                                <span class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($ord['plan_name']) ?></span>
                                <span class="text-slate-300 dark:text-slate-700">•</span>
                                <span class="font-extrabold text-indigo-600 dark:text-amber-400"><?= formatRupiah($ord['amount']) ?></span>
                                <span class="text-slate-300 dark:text-slate-700">•</span>
                                <span class="text-slate-400"><?= date('d M Y, H:i', strtotime($ord['created_at'])) ?></span>
                            </div>

                            <?php if (!empty($ord['notes'])): ?>
                                <div class="text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800">
                                    <span class="font-bold">Catatan Pemilik:</span> <?= htmlspecialchars($ord['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right: Bukti Bayar & Tombol Aksi -->
                        <div class="flex flex-col sm:items-end gap-3 flex-shrink-0">
                            <?php if (!empty($ord['proof_image'])): ?>
                                <button onclick="viewProofModal('../<?= htmlspecialchars($ord['proof_image']) ?>', '<?= htmlspecialchars($ord['order_code']) ?>')" type="button" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold flex items-center gap-2 border border-slate-200 dark:border-slate-700 transition-all">
                                    <i class="fa-solid fa-image text-indigo-500"></i> Lihat Bukti Bayar
                                </button>
                            <?php else: ?>
                                <span class="px-2.5 py-1 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-[11px] font-bold border border-emerald-200 dark:border-emerald-500/20 flex items-center gap-1.5">
                                    <i class="fa-solid fa-qrcode text-emerald-600"></i> Scan QRIS Merchant
                                </span>
                            <?php endif; ?>

                            <?php if ($ord['status'] === 'menunggu_konfirmasi'): ?>
                                <div class="flex items-center gap-2">
                                    <!-- Tombol Reject -->
                                    <form method="POST" action="admin_subscriptions.php" onsubmit="return confirm('Apakah Anda yakin ingin menolak pembayaran ini?');">
                                        <input type="hidden" name="action" value="reject_order">
                                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                        <button type="submit" class="px-3.5 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 text-xs font-bold border border-rose-200 dark:border-rose-500/30 transition-all">
                                            <i class="fa-solid fa-xmark mr-1"></i> Tolak
                                        </button>
                                    </form>

                                    <!-- Tombol Approve -->
                                    <form method="POST" action="admin_subscriptions.php" onsubmit="return confirm('Konfirmasi bahwa dana telah masuk ke akun Anda. Setujui langganan?');">
                                        <input type="hidden" name="action" value="approve_order">
                                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/30 flex items-center gap-1.5 transition-all">
                                            <i class="fa-solid fa-check"></i> Setujui / Approve
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Form Ganti QRIS GoPay Merchant (4 Kolom) -->
    <div class="lg:col-span-4 space-y-4">
        <div class="glass-card rounded-3xl p-6 border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm space-y-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 to-indigo-600 text-white flex items-center justify-center text-lg shadow-md">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white font-heading">Pengaturan QRIS Merchant</h3>
                    <p class="text-xs text-slate-500">Ganti gambar barcode QRIS kapan saja.</p>
                </div>
            </div>

            <!-- Preview QRIS Saat Ini -->
            <div class="text-center p-4 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
                <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Gambar QRIS Saat Ini:</div>
                <div class="w-44 h-56 mx-auto rounded-xl overflow-hidden border border-slate-300 dark:border-slate-700 bg-white p-1 shadow-sm">
                    <img src="../<?= htmlspecialchars($currentQris) ?>" alt="QRIS Merchant" class="w-full h-full object-contain">
                </div>
                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-bold">
                    <i class="fa-solid fa-circle-check"></i> Siap Discan di Halaman Checkout
                </div>
            </div>

            <!-- Form Upload QRIS Baru -->
            <form method="POST" action="admin_subscriptions.php" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="update_qris_settings">

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                        Upload Foto/Gambar QRIS Baru
                    </label>
                    <input type="file" name="qris_file" accept="image/*" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-2 text-xs file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer">
                    <p class="text-[10px] text-slate-400 mt-1">Saat QRIS GoPay Merchant Anda sudah jadi, cukup upload gambarnya di sini.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                        Nama Merchant / Usaha
                    </label>
                    <input type="text" name="merchant_name" value="<?= htmlspecialchars($currentName) ?>" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3 text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                        NMID Merchant (Opsional)
                    </label>
                    <input type="text" name="merchant_nmid" value="<?= htmlspecialchars($currentNmid) ?>" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3 text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                </div>

                <button type="submit" class="w-full py-3 px-4 rounded-xl font-bold text-xs text-white shadow-md shadow-indigo-600/20 transition-all flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan QRIS
                </button>
            </form>
        </div>
    </div>

</div>

<!-- Modal Zoom Bukti Pembayaran -->
<div id="proofModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="relative max-w-lg w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
        <div class="p-4 px-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div class="text-sm font-bold text-slate-900 dark:text-white font-heading" id="proofModalTitle">Bukti Pembayaran</div>
            <button onclick="closeProofModal()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>
        <div class="p-4 flex items-center justify-center max-h-[70vh] overflow-y-auto">
            <img id="proofModalImage" src="" alt="Bukti Transfer" class="max-w-full rounded-2xl shadow-md">
        </div>
    </div>
</div>

<script>
function viewProofModal(imageSrc, orderCode) {
    document.getElementById('proofModalImage').src = imageSrc;
    document.getElementById('proofModalTitle').innerText = 'Bukti Pembayaran: ' + orderCode;
    document.getElementById('proofModal').classList.remove('hidden');
}

function closeProofModal() {
    document.getElementById('proofModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
