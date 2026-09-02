<?php
$pageTitle = 'Dashboard Penyewa';
require_once __DIR__ . '/header.php';

// Fetch freshest user data for avatar
$stmtUser = $pdo->prepare("SELECT id, name, email, phone, avatar FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$user['id']]);
$userData = $stmtUser->fetch() ?: $user;
$avatarUrl = getUserAvatar($userData['avatar'] ?? null, $userData['name']);

// Fetch pending bills for this tenant
$stmtBills = $pdo->prepare("SELECT * FROM bills WHERE tenant_id = ? ORDER BY due_date ASC LIMIT 3");
$stmtBills->execute([$user['id']]);
$tenantBills = $stmtBills->fetchAll();

// Fetch recent complaints
$stmtComplaints = $pdo->prepare("SELECT * FROM complaints WHERE tenant_id = ? ORDER BY id DESC LIMIT 3");
$stmtComplaints->execute([$user['id']]);
$tenantComplaints = $stmtComplaints->fetchAll();

// Fetch broadcasts for this tenant from property owner
$tenantBroadcasts = [];
if (!empty($activeLease)) {
    try {
        $stmtBc = $pdo->prepare("SELECT b.*, u.name as owner_name 
                                 FROM broadcasts b 
                                 JOIN properties p ON (b.property_id = p.id OR (b.property_id IS NULL AND b.owner_id = p.owner_id))
                                 JOIN users u ON b.owner_id = u.id 
                                 WHERE p.id = ? 
                                 ORDER BY b.id DESC LIMIT 3");
        $stmtBc->execute([$activeLease['property_id']]);
        $tenantBroadcasts = $stmtBc->fetchAll();
    } catch (Exception $e) {}
}
?>

<!-- ================= BAGIAN PROFIL PENYEWA (BAGIAN ATAS) ================= -->
<div class="glass-card rounded-3xl p-6 sm:p-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        
        <!-- Left: Avatar Photo & Personal Info -->
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 text-center sm:text-left">
            
            <!-- Clickable Profile Avatar to Zoom -->
            <div class="relative group flex-shrink-0 cursor-pointer" onclick="openAvatarZoomModal('<?= htmlspecialchars($avatarUrl) ?>', '<?= htmlspecialchars(formatTitleCase($userData['name'])) ?>')" title="Klik untuk memperbesar foto profil">
                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-3xl overflow-hidden border-4 border-white dark:border-slate-800 shadow-xl bg-slate-200 dark:bg-slate-800 relative transition-transform duration-300 group-hover:scale-105">
                    <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Foto Profil <?= htmlspecialchars(formatTitleCase($userData['name'])) ?>" class="w-full h-full object-cover">
                    
                    <!-- Hover Zoom Overlay -->
                    <div class="absolute inset-0 bg-slate-950/40 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center text-white">
                        <i class="fa-solid fa-magnifying-glass-plus text-base"></i>
                        <span class="text-[9px] font-bold mt-0.5">Perbesar</span>
                    </div>
                </div>

                <!-- Enlarge Badge -->
                <span class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs shadow-md border-2 border-white dark:border-slate-900 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-expand text-[9px]"></i>
                </span>
            </div>

            <!-- Profile Details -->
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-[11px] font-bold uppercase tracking-wider border border-emerald-200 dark:border-emerald-500/30 flex items-center gap-1">
                        <i class="fa-solid fa-circle-check text-[9px]"></i> Penyewa Aktif
                    </span>
                    <?php if ($activeLease): ?>
                        <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-[11px] font-bold border border-indigo-200 dark:border-indigo-500/30">
                            <i class="fa-solid fa-hotel text-[9px]"></i> <?= htmlspecialchars(formatTitleCase($activeLease['property_name'])) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white font-heading">
                    <?= htmlspecialchars(formatTitleCase($userData['name'])) ?>
                </h1>

                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-mono flex items-center gap-1.5">
                        <i class="fa-solid fa-envelope text-indigo-500"></i> <?= htmlspecialchars($userData['email']) ?>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <i class="fa-solid fa-phone text-emerald-500"></i> <?= htmlspecialchars($userData['phone'] ?: '-') ?>
                    </span>
                </div>
            </div>

        </div>

        <!-- Right: Action Buttons -->
        <div class="flex flex-wrap items-center justify-center sm:justify-end gap-3 pt-2 sm:pt-0">
            <a href="profile.php" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition-all flex items-center gap-2 border border-slate-200 dark:border-slate-700 shadow-sm">
                <i class="fa-solid fa-user-pen text-indigo-500"></i> Edit Profil & Foto
            </a>
            <a href="bills.php" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20 transition-all flex items-center gap-2">
                <i class="fa-solid fa-credit-card"></i> Cek Tagihan
            </a>
        </div>

    </div>
</div>

<!-- ================= MODAL PERBESAR FOTO PROFIL (AVATAR LIGHTBOX) ================= -->
<div id="avatarZoomModal" class="fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-md hidden flex items-center justify-center p-4" onclick="closeAvatarZoomModal(event)">
    <div class="relative max-w-lg w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-2xl animate-in zoom-in-95 duration-200" onclick="event.stopPropagation()">
        
        <!-- Modal Header -->
        <div class="p-4 px-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-image"></i>
                </div>
                <div class="text-sm font-bold text-slate-900 dark:text-white font-heading" id="avatarZoomTitle">Foto Profil</div>
            </div>
            <button onclick="closeAvatarZoomModal()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-rose-500 hover:text-white text-slate-500 transition-all flex items-center justify-center">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- High Resolution Image Display -->
        <div class="p-4 sm:p-6 flex items-center justify-center bg-slate-950/5 dark:bg-slate-950/50">
            <div class="w-72 h-72 sm:w-80 sm:h-80 rounded-3xl overflow-hidden shadow-2xl border-4 border-white dark:border-slate-800 bg-slate-200 dark:bg-slate-800">
                <img id="avatarZoomImage" src="" alt="Foto Profil Diperbesar" class="w-full h-full object-cover">
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <span class="text-xs text-slate-500">Tampilan Foto Resolusi Penuh</span>
            <a href="profile.php" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs shadow-sm">
                Ganti Foto Profil
            </a>
        </div>

    </div>
</div>

<script>
function openAvatarZoomModal(imageUrl, userName) {
    const modal = document.getElementById('avatarZoomModal');
    const zoomImg = document.getElementById('avatarZoomImage');
    const zoomTitle = document.getElementById('avatarZoomTitle');

    zoomImg.src = imageUrl;
    zoomTitle.innerText = 'Foto Profil: ' + userName;
    modal.classList.remove('hidden');
}

function closeAvatarZoomModal() {
    const modal = document.getElementById('avatarZoomModal');
    modal.classList.add('hidden');
}
</script>

<!-- ================= PENGUMUMAN & BROADCAST PEMILIK KOS ================= -->
<?php if (!empty($tenantBroadcasts)): ?>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 animate-ping"></span>
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white font-heading flex items-center gap-2">
                    <i class="fa-solid fa-bullhorn text-indigo-600 dark:text-indigo-400"></i> Pengumuman Kos Terkini
                </h3>
            </div>
            <span class="text-xs text-slate-500 dark:text-slate-400">Dari Pemilik Kos</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-<?= min(count($tenantBroadcasts), 2) ?> gap-4">
            <?php foreach ($tenantBroadcasts as $b): ?>
                <?php
                    $badgeStyle = 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/30';
                    $typeIcon = 'fa-circle-info';
                    $cardBorder = 'border-indigo-200 dark:border-indigo-500/30';
                    if ($b['type'] === 'penting') {
                        $badgeStyle = 'bg-rose-50 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30';
                        $typeIcon = 'fa-triangle-exclamation';
                        $cardBorder = 'border-rose-200 dark:border-rose-500/30';
                    } elseif ($b['type'] === 'peringatan') {
                        $badgeStyle = 'bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30';
                        $typeIcon = 'fa-hand';
                        $cardBorder = 'border-amber-200 dark:border-amber-500/30';
                    } elseif ($b['type'] === 'kegiatan') {
                        $badgeStyle = 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30';
                        $typeIcon = 'fa-calendar-check';
                        $cardBorder = 'border-emerald-200 dark:border-emerald-500/30';
                    }
                ?>
                <div class="glass-card rounded-2xl p-5 border <?= $cardBorder ?> bg-white dark:bg-slate-900 shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider border flex items-center gap-1.5 <?= $badgeStyle ?>">
                            <i class="fa-solid <?= $typeIcon ?>"></i> <?= ucfirst($b['type']) ?>
                        </span>
                        <span class="text-[11px] text-slate-400">
                            <?= date('d M Y, H:i', strtotime($b['created_at'])) ?>
                        </span>
                    </div>

                    <h4 class="text-sm font-extrabold text-slate-900 dark:text-white font-heading mb-1"><?= htmlspecialchars($b['title']) ?></h4>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($b['message']) ?></p>

                    <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-500">
                        <span class="flex items-center gap-1">
                            <i class="fa-solid fa-user-tie text-indigo-500"></i> Pengelola: <?= htmlspecialchars($b['owner_name']) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Active Room Quick Info Cards -->
<?php if ($activeLease): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm">
            <div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Unit Kamar</div>
                <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 font-heading mt-1">Kamar <?= htmlspecialchars($activeLease['room_number']) ?></div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5"><?= htmlspecialchars($activeLease['room_type']) ?></div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl">
                <i class="fa-solid fa-door-open"></i>
            </div>
        </div>

        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm">
            <div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tarif Sewa</div>
                <div class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading mt-1"><?= formatRupiah($activeLease['price']) ?></div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Per <?= $activeLease['rent_type'] ?></div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl">
                <i class="fa-solid fa-receipt"></i>
            </div>
        </div>

        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm">
            <div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Masa Sewa Berakhir</div>
                <div class="text-lg font-extrabold text-amber-600 dark:text-amber-400 font-heading mt-1"><?= formatDateIndo($activeLease['end_date']) ?></div>
                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5"><i class="fa-solid fa-circle-check"></i> Status Aktif</div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
        </div>

        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm">
            <div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pemilik Kos</div>
                <div class="text-base font-bold text-slate-900 dark:text-white font-heading mt-1 truncate max-w-[140px]"><?= htmlspecialchars($activeLease['owner_name']) ?></div>
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $activeLease['owner_phone']) ?>" target="_blank" class="text-[11px] text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1 mt-0.5">
                    <i class="fa-brands fa-whatsapp"></i> Chat Pemilik
                </a>
            </div>
            <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl">
                <i class="fa-solid fa-user-tie"></i>
            </div>
        </div>

    </div>
<?php else: ?>
    <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center py-10 shadow-sm">
        <i class="fa-solid fa-triangle-exclamation text-4xl text-amber-500 mb-3"></i>
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Akun Anda Belum Memiliki Kamar Terhubung</h3>
        <p class="text-slate-500 dark:text-slate-400 text-xs max-w-md mx-auto mt-1">Silakan konfirmasi ke pengelola kos untuk menghubungkan akun email Anda (<strong><?= htmlspecialchars($user['email']) ?></strong>) ke nomor kamar yang disewa.</p>
    </div>
<?php endif; ?>

<!-- Content Grid (Active Bills & Complaints) -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    
    <!-- Tagihan Terkini -->
    <div class="lg:col-span-7 p-6 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white font-heading">Tagihan Sewa Saya</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Daftar invoice dan riwayat pembayaran</p>
            </div>
            <a href="bills.php" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline font-semibold flex items-center gap-1">
                Buka Semua Tagihan <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <div class="space-y-3">
            <?php if (!empty($tenantBills)): ?>
                <?php foreach ($tenantBills as $b): ?>
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($b['bill_code']) ?></span>
                                <?php if ($b['status'] === 'lunas'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">Lunas</span>
                                <?php elseif ($b['status'] === 'menunggu_verifikasi'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">Verifikasi</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-500/20 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30">Belum Bayar</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white mt-1"><?= htmlspecialchars($b['title']) ?></div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">Jatuh Tempo: <?= formatDateIndo($b['due_date']) ?></div>
                        </div>

                        <div class="flex items-center sm:flex-col sm:items-end justify-between gap-2 border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-200 dark:border-slate-700">
                            <div class="text-base font-extrabold text-emerald-600 dark:text-emerald-400 font-heading">
                                <?= formatRupiah($b['amount']) ?>
                            </div>
                            <?php if ($b['status'] === 'belum_bayar'): ?>
                                <a href="bills.php" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm transition-all">
                                    <i class="fa-solid fa-upload mr-1"></i> Bayar Sekarang
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 text-center text-slate-500 text-xs">
                    <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-400 dark:text-slate-600"></i>
                    <div>Tidak ada tagihan yang harus dibayar saat ini.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aduan Terkini -->
    <div class="lg:col-span-5 p-6 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white font-heading">Aduan Kerusakan</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Status keluhan fasilitas kamar Anda</p>
            </div>
            <a href="complaints.php" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline font-semibold flex items-center gap-1">
                Lapor Baru <i class="fa-solid fa-plus text-[10px]"></i>
            </a>
        </div>

        <div class="space-y-3">
            <?php if (!empty($tenantComplaints)): ?>
                <?php foreach ($tenantComplaints as $c): ?>
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-900 dark:text-white truncate max-w-[180px]"><?= htmlspecialchars($c['title']) ?></span>
                            <?php if ($c['status'] === 'menunggu'): ?>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-50 dark:bg-rose-500/20 text-rose-600 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">Menunggu</span>
                            <?php elseif ($c['status'] === 'diproses'): ?>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">Diproses</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">Selesai</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 line-clamp-2"><?= htmlspecialchars($c['description']) ?></p>
                        
                        <?php if (!empty($c['admin_response'])): ?>
                            <div class="text-[10px] text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/50 p-2 rounded-lg border border-indigo-200 dark:border-indigo-500/30">
                                <strong>Respon Pemilik:</strong> <?= htmlspecialchars($c['admin_response']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="text-[10px] text-slate-500 pt-1 text-right">
                            <?= date('d M Y, H:i', strtotime($c['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 text-center text-slate-500 text-xs">
                    <i class="fa-solid fa-thumbs-up text-3xl mb-2 text-emerald-500"></i>
                    <div>Tidak ada keluhan fasilitas yang dilaporkan.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
