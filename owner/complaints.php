<?php
// Top Level Authentication & Logic Execution
require_once __DIR__ . '/../helpers/auth.php';
requireLogin('pemilik');

$user = currentUser();
$pdo = getDBConnection();

// Handle Action Update Complaint BEFORE loading header HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaintId = (int)$_POST['complaint_id'];
    $status = sanitizeInput($_POST['status']);
    $response = sanitizeInput($_POST['admin_response'] ?? '');

    $stmt = $pdo->prepare("UPDATE complaints SET status = ?, admin_response = ? WHERE id = ?");
    $stmt->execute([$status, $response, $complaintId]);

    // Send OneSignal Push Notification to Tenant
    require_once __DIR__ . '/../helpers/onesignal.php';
    notifyTenantComplaintResponse($complaintId, $status, $response);

    setFlash('success', "Status penanganan aduan berhasil diperbarui!");
    header("Location: complaints.php");
    exit;
}

$pageTitle = 'Manajemen Pengaduan Fasilitas';
require_once __DIR__ . '/header.php';

// Fetch all complaints
$stmtComplaints = $pdo->query("SELECT c.*, u.name as tenant_name, u.phone as tenant_phone, r.room_number 
                               FROM complaints c 
                               JOIN users u ON c.tenant_id = u.id 
                               JOIN rooms r ON c.room_id = r.id 
                               ORDER BY FIELD(c.status, 'menunggu', 'diproses', 'selesai', 'ditolak'), c.id DESC");
$complaints = $stmtComplaints->fetchAll();
?>

<!-- Header -->
<div>
    <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Laporan & Aduan Fasilitas</h2>
    <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Daftar keluhan kerusakan kamar/fasilitas yang diajukan oleh penyewa kos.</p>
</div>

<!-- Complaints Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <?php if (!empty($complaints)): ?>
        <?php foreach ($complaints as $c): ?>
            <div class="glass-card rounded-3xl p-6 border border-slate-200 dark:border-slate-800 flex flex-col justify-between space-y-4 shadow-sm bg-white dark:bg-slate-900">
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

                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 font-bold text-xs">Kamar <?= htmlspecialchars($c['room_number']) ?></span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($c['tenant_name']) ?></span>
                    </div>

                    <h3 class="text-lg font-bold text-slate-900 dark:text-white font-heading mt-2"><?= htmlspecialchars($c['title']) ?></h3>
                    <p class="text-xs text-slate-700 dark:text-slate-300 mt-2 bg-slate-50 dark:bg-slate-950 p-3 rounded-xl border border-slate-200 dark:border-slate-800 leading-relaxed">
                        <?= nl2br(htmlspecialchars($c['description'])) ?>
                    </p>

                    <?php if (!empty($c['admin_response'])): ?>
                        <div class="mt-3 p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-500/30 text-xs text-indigo-800 dark:text-indigo-200">
                            <div class="font-bold text-[10px] uppercase tracking-wider text-indigo-600 dark:text-indigo-400 mb-1"><i class="fa-solid fa-reply mr-1"></i> Respon Pemilik:</div>
                            <?= nl2br(htmlspecialchars($c['admin_response'])) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Update Status Form -->
                <form method="POST" class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-3">
                    <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Update Status</label>
                            <select name="status" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2 px-3 text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                                <option value="menunggu" <?= $c['status'] === 'menunggu' ? 'selected' : '' ?>>Menunggu Respon</option>
                                <option value="diproses" <?= $c['status'] === 'diproses' ? 'selected' : '' ?>>Sedang Diperbaiki / Diproses</option>
                                <option value="selesai" <?= $c['status'] === 'selesai' ? 'selected' : '' ?>>Selesai Diperbaiki</option>
                                <option value="ditolak" <?= $c['status'] === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Catatan / Respon Tindakan</label>
                            <input type="text" name="admin_response" value="<?= htmlspecialchars($c['admin_response'] ?? '') ?>" placeholder="Tukang akan datang besok..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2 px-3 text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-2 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-indigo-600 hover:text-white text-slate-700 dark:text-white font-bold text-xs transition-all flex items-center justify-center gap-1.5 border border-slate-200 dark:border-slate-700">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Respon Tindakan
                    </button>
                </form>

            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-span-2 text-center py-16 p-8 bg-white dark:bg-slate-900/80 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <i class="fa-solid fa-circle-check text-4xl text-emerald-500 mb-3"></i>
            <h4 class="text-lg font-bold text-slate-900 dark:text-white">Tidak Ada Pengaduan</h4>
            <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Semua fasilitas kos dalam kondisi baik dan tidak ada kendala aktif.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
