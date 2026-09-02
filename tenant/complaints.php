<?php
// Top Level Authentication & Logic Execution
require_once __DIR__ . '/../helpers/auth.php';
requireLogin('penyewa');

$user = currentUser();
$pdo = getDBConnection();

// Fetch active lease
$stmtLease = $pdo->prepare("SELECT l.*, r.room_number, r.room_type, p.name as property_name, p.address as property_address 
                            FROM leases l 
                            JOIN rooms r ON l.room_id = r.id 
                            JOIN properties p ON r.property_id = p.id 
                            WHERE l.tenant_id = ? AND l.status = 'aktif' 
                            ORDER BY l.id DESC LIMIT 1");
$stmtLease->execute([$user['id']]);
$activeLease = $stmtLease->fetch();

// Handle Submit New Complaint BEFORE loading header HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $priority = sanitizeInput($_POST['priority'] ?? 'sedang');
    $roomId = $activeLease ? $activeLease['room_id'] : 1;

    $stmt = $pdo->prepare("INSERT INTO complaints (tenant_id, room_id, title, description, priority, status) VALUES (?, ?, ?, ?, ?, 'menunggu')");
    $stmt->execute([$user['id'], $roomId, $title, $description, $priority]);

    setFlash('success', "Laporan pengaduan fasilitas berhasil dikirimkan ke pemilik kos!");
    header("Location: complaints.php");
    exit;
}

$pageTitle = 'Pengaduan & Kerusakan Fasilitas';
require_once __DIR__ . '/header.php';

// Fetch complaints for tenant
$stmtC = $pdo->prepare("SELECT c.*, r.room_number 
                        FROM complaints c 
                        JOIN rooms r ON c.room_id = r.id 
                        WHERE c.tenant_id = ? 
                        ORDER BY c.id DESC");
$stmtC->execute([$user['id']]);
$complaints = $stmtC->fetchAll();
?>

<!-- Header Actions -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Pengaduan Kerusakan & Fasilitas</h2>
        <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Laporkan kendala fasilitas kamar (AC, kran air, lampu, WiFi) langsung ke pemilik kos.</p>
    </div>
    <button onclick="openComplaintModal()" class="px-5 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/30 transition-all flex items-center justify-center gap-2">
        <i class="fa-solid fa-plus"></i> Buat Laporan Baru
    </button>
</div>

<!-- Complaints Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <?php if (!empty($complaints)): ?>
        <?php foreach ($complaints as $c): ?>
            <div class="glass-card rounded-3xl p-6 border border-slate-200 dark:border-slate-800 flex flex-col justify-between space-y-4 bg-white dark:bg-slate-900 shadow-sm">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider 
                            <?= $c['status'] === 'menunggu' ? 'bg-rose-50 dark:bg-rose-500/20 text-rose-600 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30' : ($c['status'] === 'diproses' ? 'bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30' : 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30') ?>">
                            <i class="fa-solid fa-circle text-[7px] mr-1"></i> Status: <?= ucfirst($c['status']) ?>
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            <?= date('d M Y, H:i', strtotime($c['created_at'])) ?>
                        </span>
                    </div>

                    <h3 class="text-lg font-bold text-slate-900 dark:text-white font-heading"><?= htmlspecialchars($c['title']) ?></h3>
                    <p class="text-xs text-slate-700 dark:text-slate-300 mt-2 bg-slate-50 dark:bg-slate-950 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 leading-relaxed">
                        <?= nl2br(htmlspecialchars($c['description'])) ?>
                    </p>
                </div>

                <div>
                    <?php if (!empty($c['admin_response'])): ?>
                        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-500/30 text-xs text-emerald-800 dark:text-emerald-200">
                            <div class="font-bold text-[10px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400 mb-1"><i class="fa-solid fa-reply mr-1"></i> Tanggapan Pemilik Kos:</div>
                            <?= nl2br(htmlspecialchars($c['admin_response'])) ?>
                        </div>
                    <?php else: ?>
                        <div class="text-[11px] text-slate-500 italic">
                            <i class="fa-solid fa-clock mr-1"></i> Belum ada respon dari pemilik kos.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-span-2 text-center py-16 p-8 bg-white dark:bg-slate-900/80 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <i class="fa-solid fa-circle-check text-4xl text-emerald-500 mb-3"></i>
            <h4 class="text-lg font-bold text-slate-900 dark:text-white">Belum Ada Pengaduan</h4>
            <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Jika ada kerusakan atau kendala fasilitas di kamar, silakan ajukan laporan pengaduan.</p>
            <button onclick="openComplaintModal()" class="mt-4 px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold shadow-md">Buat Pengaduan</button>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Create Complaint -->
<div id="complaintModal" class="fixed inset-0 z-50 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white font-heading">Lapor Kendala / Kerusakan Fasilitas</h3>
            <button onclick="closeComplaintModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Judul Keluhan / Masalah</label>
                <input type="text" name="title" required placeholder="Contoh: Kran Kamar Mandi Bocor / AC Kurang Dingin" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Tingkat Urgensi</label>
                <select name="priority" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-emerald-500 focus:outline-none">
                    <option value="rendah">Rendah (Dapat menunggu beberapa hari)</option>
                    <option value="sedang" selected>Sedang (Perlu dicek dalam 1-2 hari)</option>
                    <option value="darurat">Darurat (Mendesak / Listrik Padam / Air Mati)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Deskripsi Rinci Masalah</label>
                <textarea name="description" rows="3" required placeholder="Jelaskan detail kendala kerusakan yang terjadi di kamar Anda..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-emerald-500 focus:outline-none"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-200 dark:border-slate-800">
                <button type="button" onclick="closeComplaintModal()" class="py-2.5 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold">Batal</button>
                <button type="submit" class="py-2.5 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/30">Kirim Laporan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openComplaintModal() {
        document.getElementById('complaintModal').classList.remove('hidden');
    }
    function closeComplaintModal() {
        document.getElementById('complaintModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
