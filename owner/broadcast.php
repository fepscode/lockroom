<?php
// Owner Broadcast / Announcements Management
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/onesignal.php';
requireLogin('pemilik');

$user = currentUser();
$pdo = getDBConnection();

// Handle Submit New Broadcast & Delete Broadcast
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_broadcast') {
        $title = sanitizeInput($_POST['title'] ?? '');
        $message = sanitizeInput($_POST['message'] ?? '');
        $type = sanitizeInput($_POST['type'] ?? 'info');
        $propertyId = !empty($_POST['property_id']) ? (int)$_POST['property_id'] : null;

        if (empty($title) || empty($message)) {
            setFlash('error', 'Judul dan isi pesan broadcast wajib diisi!');
        } else {
            // Save to database
            $stmt = $pdo->prepare("INSERT INTO broadcasts (owner_id, property_id, title, message, type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $propertyId, $title, $message, $type]);

            // Fire OneSignal Web Push to all targeted active tenants
            sendOneSignalBroadcastToTenants($user['id'], $title, $message, $type, $propertyId);

            setFlash('success', 'Pesan broadcast berhasil dikirimkan ke seluruh penghuni kos beserta notifikasi layar HP!');
        }
        header("Location: broadcast.php");
        exit;
    }

    if ($action === 'delete_broadcast') {
        $broadcastId = (int)$_POST['broadcast_id'];
        $stmt = $pdo->prepare("DELETE FROM broadcasts WHERE id = ? AND owner_id = ?");
        $stmt->execute([$broadcastId, $user['id']]);

        setFlash('success', 'Riwayat pengumuman berhasil dihapus.');
        header("Location: broadcast.php");
        exit;
    }
}

$pageTitle = 'Broadcast & Pengumuman Penghuni';
require_once __DIR__ . '/header.php';

// Fetch properties owned by this owner
$stmtProps = $pdo->prepare("SELECT id, name FROM properties WHERE owner_id = ? ORDER BY id ASC");
$stmtProps->execute([$user['id']]);
$properties = $stmtProps->fetchAll();

// Count active tenants
$stmtActiveTenants = $pdo->prepare("SELECT COUNT(DISTINCT l.tenant_id) 
                                    FROM leases l
                                    JOIN rooms r ON l.room_id = r.id
                                    JOIN properties p ON r.property_id = p.id
                                    WHERE p.owner_id = ? AND l.status = 'aktif'");
$stmtActiveTenants->execute([$user['id']]);
$totalActiveTenants = $stmtActiveTenants->fetchColumn() ?: 0;

// Fetch sent broadcasts history
$stmtBroadcasts = $pdo->prepare("SELECT b.*, p.name as property_name 
                                  FROM broadcasts b 
                                  LEFT JOIN properties p ON b.property_id = p.id 
                                  WHERE b.owner_id = ? 
                                  ORDER BY b.id DESC");
$stmtBroadcasts->execute([$user['id']]);
$broadcasts = $stmtBroadcasts->fetchAll();
?>

<!-- Header Banner -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-1.5 bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30">
            <i class="fa-solid fa-bullhorn animate-bounce"></i> Fitur Mass Broadcast
        </div>
        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Broadcast & Notifikasi Massal</h2>
        <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Kirim pengumuman penting, tata tertib, jadwal kebersihan, atau informasi tagihan langsung ke HP semua penghuni kos.</p>
    </div>
    
    <div class="flex items-center gap-3">
        <div class="px-4 py-2.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-users-viewfinder"></i>
            </div>
            <div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold">Total Penerima Aktif</div>
                <div class="text-lg font-extrabold text-slate-900 dark:text-white"><?= $totalActiveTenants ?> Penghuni</div>
            </div>
        </div>
    </div>
</div>

<!-- Main Grid: Form Kirim & Riwayat -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- Form Kirim Broadcast (7 Kolom) -->
    <div class="lg:col-span-7 space-y-6">
        <div class="glass-card rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-slate-800 shadow-sm bg-white dark:bg-slate-900 relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 to-indigo-600 text-white flex items-center justify-center shadow-md">
                    <i class="fa-solid fa-paper-plane text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900 dark:text-white font-heading">Kirim Pengumuman Baru</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Pesan otomatis memicu pop-up push notification OneSignal di smartphone penghuni.</p>
                </div>
            </div>

            <form method="POST" action="broadcast.php" class="space-y-5">
                <input type="hidden" name="action" value="send_broadcast">

                <!-- Target Properti -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                        Target Penerima Broadcast
                    </label>
                    <select name="property_id" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-3 px-4 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none transition-all">
                        <option value="">🏢 Semua Properti Kos (Semua <?= $totalActiveTenants ?> Penghuni Aktif)</option>
                        <?php foreach ($properties as $p): ?>
                            <option value="<?= $p['id'] ?>">📍 Khusus Properti: <?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Kategori / Tipe -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                        Kategori Pengumuman
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="info" checked class="peer sr-only">
                            <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 text-center peer-checked:bg-indigo-50 peer-checked:border-indigo-500 peer-checked:text-indigo-700 dark:peer-checked:bg-indigo-500/20 dark:peer-checked:text-indigo-300 transition-all">
                                <i class="fa-solid fa-circle-info text-base mb-1 block text-indigo-500"></i>
                                <span class="text-xs font-bold">Info Umum</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="penting" class="peer sr-only">
                            <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 text-center peer-checked:bg-rose-50 peer-checked:border-rose-500 peer-checked:text-rose-700 dark:peer-checked:bg-rose-500/20 dark:peer-checked:text-rose-300 transition-all">
                                <i class="fa-solid fa-triangle-exclamation text-base mb-1 block text-rose-500"></i>
                                <span class="text-xs font-bold">Penting</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="peringatan" class="peer sr-only">
                            <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 text-center peer-checked:bg-amber-50 peer-checked:border-amber-500 peer-checked:text-amber-700 dark:peer-checked:bg-amber-500/20 dark:peer-checked:text-amber-300 transition-all">
                                <i class="fa-solid fa-hand text-base mb-1 block text-amber-500"></i>
                                <span class="text-xs font-bold">Peringatan</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="kegiatan" class="peer sr-only">
                            <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 text-center peer-checked:bg-emerald-50 peer-checked:border-emerald-500 peer-checked:text-emerald-700 dark:peer-checked:bg-emerald-500/20 dark:peer-checked:text-emerald-300 transition-all">
                                <i class="fa-solid fa-calendar-check text-base mb-1 block text-emerald-500"></i>
                                <span class="text-xs font-bold">Kegiatan</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Judul -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                        Judul Pengumuman
                    </label>
                    <div class="relative">
                        <i class="fa-solid fa-heading absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="title" id="broadcastTitleInput" required placeholder="Contoh: Jadwal Pembersihan Toren Air & Disinfeksi" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-3 pl-11 pr-4 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none transition-all placeholder:text-slate-400">
                    </div>
                </div>

                <!-- Pesan -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                        Isi Pesan Pengumuman
                    </label>
                    <textarea name="message" id="broadcastMsgInput" rows="5" required placeholder="Tuliskan isi pengumuman secara rinci untuk para penghuni..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-2xl p-4 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none transition-all placeholder:text-slate-400"></textarea>
                </div>

                <div class="p-4 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-500/30 flex items-start gap-3 text-xs text-slate-600 dark:text-slate-300">
                    <i class="fa-solid fa-tower-broadcast text-indigo-600 dark:text-cyan-400 text-lg mt-0.5 flex-shrink-0"></i>
                    <div>
                        <span class="font-bold text-slate-900 dark:text-white">Push Notification Otomatis:</span>
                        Saat tombol diklik, OneSignal akan otomatis memicu bunyi notifikasi dan memunculkan pop-up di status bar HP semua penghuni kos secara bersamaan.
                    </div>
                </div>

                <button type="submit" class="w-full py-4 px-6 rounded-2xl font-bold text-sm text-white shadow-xl transition-all flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 shadow-indigo-600/30">
                    <i class="fa-solid fa-paper-plane"></i> Kirim Broadcast Sekarang
                </button>
            </form>
        </div>
    </div>

    <!-- Riwayat Pengumuman Sebelumnya (5 Kolom) -->
    <div class="lg:col-span-5 space-y-4">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white font-heading flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-indigo-600"></i> Riwayat Broadcast
            </h3>
            <span class="text-xs text-slate-500 font-semibold"><?= count($broadcasts) ?> Pengumuman</span>
        </div>

        <?php if (empty($broadcasts)): ?>
            <div class="p-8 rounded-3xl border border-dashed border-slate-300 dark:border-slate-800 text-center bg-white dark:bg-slate-900/60">
                <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto text-2xl mb-3">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <div class="text-sm font-bold text-slate-900 dark:text-white">Belum Ada Broadcast Terkirim</div>
                <p class="text-xs text-slate-500 mt-1">Pengumuman yang Anda kirimkan akan tersimpan di riwayat ini.</p>
            </div>
        <?php else: ?>
            <div class="space-y-3.5 max-h-[600px] overflow-y-auto pr-1">
                <?php foreach ($broadcasts as $b): ?>
                    <?php
                        $badgeStyle = 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-500/30';
                        $typeIcon = 'fa-circle-info';
                        if ($b['type'] === 'penting') {
                            $badgeStyle = 'bg-rose-50 dark:bg-rose-500/20 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-500/30';
                            $typeIcon = 'fa-triangle-exclamation';
                        } elseif ($b['type'] === 'peringatan') {
                            $badgeStyle = 'bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/30';
                            $typeIcon = 'fa-hand';
                        } elseif ($b['type'] === 'kegiatan') {
                            $badgeStyle = 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30';
                            $typeIcon = 'fa-calendar-check';
                        }
                    ?>
                    <div class="glass-card rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm relative group">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider border flex items-center gap-1.5 <?= $badgeStyle ?>">
                                <i class="fa-solid <?= $typeIcon ?>"></i> <?= ucfirst($b['type']) ?>
                            </span>
                            <span class="text-[11px] text-slate-400">
                                <?= date('d M Y, H:i', strtotime($b['created_at'])) ?>
                            </span>
                        </div>

                        <h4 class="text-sm font-extrabold text-slate-900 dark:text-white font-heading mb-1.5"><?= htmlspecialchars($b['title']) ?></h4>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($b['message']) ?></p>

                        <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px]">
                            <span class="text-slate-500 flex items-center gap-1">
                                <i class="fa-solid fa-location-dot text-indigo-500"></i>
                                <?= $b['property_name'] ? htmlspecialchars($b['property_name']) : 'Semua Properti' ?>
                            </span>

                            <form method="POST" action="broadcast.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengumuman ini dari riwayat?');">
                                <input type="hidden" name="action" value="delete_broadcast">
                                <input type="hidden" name="broadcast_id" value="<?= $b['id'] ?>">
                                <button type="submit" class="text-slate-400 hover:text-rose-500 transition-colors p-1" title="Hapus Pengumuman">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
