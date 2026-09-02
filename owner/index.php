<?php
$pageTitle = 'Ringkasan Dashboard';
require_once __DIR__ . '/header.php';

// Fetch statistics for owner
$totalRooms = 0;
$occupiedRooms = 0;
$availableRooms = 0;
$totalTenants = 0;
$totalIncomeMonth = 0;
$unpaidBillsTotal = 0;
$recentBills = [];
$recentComplaints = [];

try {
    $totalRooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    $occupiedRooms = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'terisi'")->fetchColumn();
    $availableRooms = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'tersedia'")->fetchColumn();
    $totalTenants = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'penyewa'")->fetchColumn();

    // Total income from paid bills this month
    $stmtIncome = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM bills WHERE status = 'lunas' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $totalIncomeMonth = $stmtIncome->fetchColumn();

    // Total unpaid
    $stmtUnpaid = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM bills WHERE status IN ('belum_bayar', 'menunggu_verifikasi')");
    $unpaidBillsTotal = $stmtUnpaid->fetchColumn();

    // Recent bills
    $stmtRecentBills = $pdo->query("SELECT b.*, u.name as tenant_name, r.room_number 
                                    FROM bills b 
                                    JOIN users u ON b.tenant_id = u.id 
                                    JOIN leases l ON b.lease_id = l.id 
                                    JOIN rooms r ON l.room_id = r.id 
                                    ORDER BY b.id DESC LIMIT 5");
    $recentBills = $stmtRecentBills->fetchAll();

    // Recent complaints
    $stmtComp = $pdo->query("SELECT c.*, u.name as tenant_name, r.room_number 
                             FROM complaints c 
                             JOIN users u ON c.tenant_id = u.id 
                             JOIN rooms r ON c.room_id = r.id 
                             ORDER BY c.id DESC LIMIT 4");
    $recentComplaints = $stmtComp->fetchAll();

} catch (Exception $e) {}

// Fetch freshest user data for avatar
$stmtUser = $pdo->prepare("SELECT id, name, email, phone, avatar FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$user['id']]);
$userData = $stmtUser->fetch() ?: $user;
$avatarUrl = getUserAvatar($userData['avatar'] ?? null, $userData['name']);

$occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0;
?>

<!-- ================= BAGIAN PROFIL PEMILIK (BAGIAN ATAS) ================= -->
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
                <span class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs shadow-md border-2 border-white dark:border-slate-900 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-expand text-[9px]"></i>
                </span>
            </div>

            <!-- Profile Details -->
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                    <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-[11px] font-bold uppercase tracking-wider border border-indigo-200 dark:border-indigo-500/30 flex items-center gap-1">
                        <i class="fa-solid fa-hotel text-[9px]"></i> Pemilik Properti Kos
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-[11px] font-bold border border-amber-200 dark:border-amber-500/30">
                        <i class="fa-solid fa-door-open text-[9px]"></i> <?= $totalRooms ?> Total Kamar
                    </span>
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

        <!-- Right: Quick Action Buttons -->
        <div class="flex flex-wrap items-center justify-center sm:justify-end gap-3 pt-2 sm:pt-0">
            <a href="profile.php" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition-all flex items-center gap-2 border border-slate-200 dark:border-slate-700 shadow-sm">
                <i class="fa-solid fa-user-pen text-indigo-500"></i> Edit Profil & Foto
            </a>
            <a href="rooms.php" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-md shadow-indigo-600/20 transition-all flex items-center gap-2">
                <i class="fa-solid fa-sliders"></i> Kelola Rumah & Kamar
            </a>
            <a href="tenants.php" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20 transition-all flex items-center gap-2">
                <i class="fa-solid fa-user-plus"></i> Daftarkan Penyewa
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
                <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-image"></i>
                </div>
                <div class="text-sm font-bold text-slate-900 dark:text-white font-heading" id="avatarZoomTitle">Foto Profil Pemilik</div>
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
            <a href="profile.php" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-sm">
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

<!-- Stats Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
    
    <!-- Total Rooms -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm">
        <div>
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Kamar</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading mt-1"><?= $totalRooms ?> Unit</div>
            <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium mt-1">
                <i class="fa-solid fa-circle-check mr-1"></i> <?= $availableRooms ?> Kamar Tersedia
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl">
            <i class="fa-solid fa-door-closed"></i>
        </div>
    </div>

    <!-- Occupancy Rate -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm">
        <div>
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tingkat Hunian</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading mt-1"><?= $occupancyRate ?>%</div>
            <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium mt-1">
                <?= $occupiedRooms ?> dari <?= $totalRooms ?> kamar terisi
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl">
            <i class="fa-solid fa-chart-pie"></i>
        </div>
    </div>

    <!-- Monthly Income -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm">
        <div>
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pemasukan Bulan Ini</div>
            <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 font-heading mt-1"><?= formatRupiah($totalIncomeMonth) ?></div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium mt-1">Periode <?= date('F Y') ?></div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl">
            <i class="fa-solid fa-wallet"></i>
        </div>
    </div>

    <!-- Unpaid Bills -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm">
        <div>
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tagihan Tertunda</div>
            <div class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 font-heading mt-1"><?= formatRupiah($unpaidBillsTotal) ?></div>
            <div class="text-[11px] text-amber-600 dark:text-amber-400 font-medium mt-1">
                <?= $unverifiedCount ?> butuh verifikasi
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
    </div>

</div>

<!-- Content Grid (Recent Bills & Complaints) -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    
    <!-- Recent Invoices / Bills -->
    <div class="lg:col-span-7 p-6 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white font-heading">Aktivitas Tagihan Terkini</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Riwayat tagihan dan status pembayaran sewa</p>
            </div>
            <a href="bills.php" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold flex items-center gap-1">
                Lihat Semua <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-100 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 uppercase font-semibold text-[10px] tracking-wider">
                    <tr>
                        <th class="p-3 rounded-l-xl">No. Invoice</th>
                        <th class="p-3">Penyewa / Kamar</th>
                        <th class="p-3">Nominal</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 rounded-r-xl text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <?php if (!empty($recentBills)): ?>
                        <?php foreach ($recentBills as $bill): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="p-3 font-mono font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($bill['bill_code']) ?></td>
                                <td class="p-3">
                                    <div class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($bill['tenant_name']) ?></div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Kamar <?= htmlspecialchars($bill['room_number']) ?></div>
                                </td>
                                <td class="p-3 font-bold text-slate-800 dark:text-slate-200"><?= formatRupiah($bill['amount']) ?></td>
                                <td class="p-3">
                                    <?php if ($bill['status'] === 'lunas'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">Lunas</span>
                                    <?php elseif ($bill['status'] === 'menunggu_verifikasi'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30 animate-pulse">Menunggu Verifikasi</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30">Belum Bayar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right">
                                    <a href="bills.php" class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-indigo-600 hover:text-white text-slate-600 dark:text-slate-300 transition-all inline-block">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="p-6 text-center text-slate-500">Belum ada aktivitas tagihan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Complaints / Maintenance Tickets -->
    <div class="lg:col-span-5 p-6 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white font-heading">Pengaduan Fasilitas</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Laporan kerusakan dari penghuni kos</p>
            </div>
            <a href="complaints.php" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold flex items-center gap-1">
                Lihat Semua <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <div class="space-y-3">
            <?php if (!empty($recentComplaints)): ?>
                <?php foreach ($recentComplaints as $comp): ?>
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-900 dark:text-white truncate max-w-[200px]"><?= htmlspecialchars($comp['title']) ?></span>
                            <?php if ($comp['status'] === 'menunggu'): ?>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-50 dark:bg-rose-500/20 text-rose-600 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">Menunggu</span>
                            <?php elseif ($comp['status'] === 'diproses'): ?>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">Diproses</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">Selesai</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 line-clamp-2"><?= htmlspecialchars($comp['description']) ?></p>
                        <div class="flex items-center justify-between text-[10px] text-slate-500 pt-1 border-t border-slate-200 dark:border-slate-700/60">
                            <span><i class="fa-solid fa-user text-slate-400 mr-1"></i> <?= htmlspecialchars($comp['tenant_name']) ?> (Kamar <?= htmlspecialchars($comp['room_number']) ?>)</span>
                            <span><?= date('d M Y', strtotime($comp['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 text-center text-slate-500 text-xs">
                    <i class="fa-solid fa-circle-check text-2xl text-emerald-500/40 mb-2"></i>
                    <div>Tidak ada pengaduan fasilitas aktif.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
