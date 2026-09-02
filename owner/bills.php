<?php
// Top Level Authentication & Logic Execution
require_once __DIR__ . '/../helpers/auth.php';
requireLogin('pemilik');

$user = currentUser();
$pdo = getDBConnection();

// Handle Actions (Create Bill, Verify Payment) BEFORE loading header HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_bill') {
        $leaseId = (int)$_POST['lease_id'];
        $title = sanitizeInput($_POST['title']);
        $amount = (float)$_POST['amount'];
        $dueDate = $_POST['due_date'];

        // Get tenant_id from lease
        $stmtL = $pdo->prepare("SELECT tenant_id FROM leases WHERE id = ?");
        $stmtL->execute([$leaseId]);
        $lease = $stmtL->fetch();

        if ($lease) {
            $billCode = 'INV-' . date('Ym') . '-' . rand(100, 999);
            $stmt = $pdo->prepare("INSERT INTO bills (lease_id, tenant_id, bill_code, title, amount, due_date, status) VALUES (?, ?, ?, ?, ?, ?, 'belum_bayar')");
            $stmt->execute([$leaseId, $lease['tenant_id'], $billCode, $title, $amount, $dueDate]);

            setFlash('success', "Tagihan baru berhasil diterbitkan untuk penyewa!");
        }
        header("Location: bills.php");
        exit;
    }

    if ($action === 'verify_payment') {
        $billId = (int)$_POST['bill_id'];
        $decision = $_POST['decision'] ?? 'lunas'; // lunas or ditolak

        if ($decision === 'lunas') {
            $stmt = $pdo->prepare("UPDATE bills SET status = 'lunas' WHERE id = ?");
            $stmt->execute([$billId]);

            // Update payments record
            $stmtPay = $pdo->prepare("UPDATE payments SET status = 'disetujui', verified_by = ?, verified_at = NOW() WHERE bill_id = ?");
            $stmtPay->execute([$user['id'], $billId]);

            setFlash('success', "Pembayaran tagihan telah diverifikasi dan dinyatakan LUNAS!");
        } else {
            $stmt = $pdo->prepare("UPDATE bills SET status = 'ditolak' WHERE id = ?");
            $stmt->execute([$billId]);

            $stmtPay = $pdo->prepare("UPDATE payments SET status = 'ditolak', verified_by = ?, verified_at = NOW() WHERE bill_id = ?");
            $stmtPay->execute([$user['id'], $billId]);

            setFlash('error', "Pembayaran tagihan ditolak. Penyewa diminta mengunggah ulang bukti transfer yang valid.");
        }

        // Send OneSignal Push Notification to Tenant
        require_once __DIR__ . '/../helpers/onesignal.php';
        notifyTenantPaymentDecision($billId, $decision);

        header("Location: bills.php");
        exit;
    }
}

$pageTitle = 'Manajemen Tagihan & Pembayaran';
require_once __DIR__ . '/header.php';

// Fetch all bills with payment info
$stmtBills = $pdo->query("SELECT b.*, u.name as tenant_name, u.phone as tenant_phone, r.room_number,
                                 p.proof_image, p.payment_method, p.payment_date, p.notes as payment_notes, p.id as payment_id 
                          FROM bills b 
                          JOIN leases l ON b.lease_id = l.id 
                          JOIN users u ON b.tenant_id = u.id 
                          JOIN rooms r ON l.room_id = r.id 
                          LEFT JOIN payments p ON b.id = p.bill_id 
                          ORDER BY b.id DESC");
$bills = $stmtBills->fetchAll();

// Fetch active leases for generating bill
$stmtActiveLeases = $pdo->query("SELECT l.id, u.name as tenant_name, r.room_number, l.price 
                                 FROM leases l 
                                 JOIN users u ON l.tenant_id = u.id 
                                 JOIN rooms r ON l.room_id = r.id 
                                 WHERE l.status = 'aktif'");
$activeLeases = $stmtActiveLeases->fetchAll();
?>

<!-- Header Actions -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Tagihan & Verifikasi Pembayaran</h2>
        <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Terbitkan invoice sewa bulanan dan verifikasi bukti transfer pembayaran dari penyewa.</p>
    </div>
    <button onclick="openCreateBillModal()" class="px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-600/30 transition-all flex items-center justify-center gap-2">
        <i class="fa-solid fa-plus"></i> Terbitkan Tagihan Baru
    </button>
</div>

<!-- Bills Table -->
<div class="bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
            <thead class="bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-400 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="p-4">No. Invoice</th>
                    <th class="p-4">Penyewa & Kamar</th>
                    <th class="p-4">Deskripsi Tagihan</th>
                    <th class="p-4">Nominal</th>
                    <th class="p-4">Jatuh Tempo</th>
                    <th class="p-4">Status Tagihan</th>
                    <th class="p-4 text-right">Bukti & Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                <?php if (!empty($bills)): ?>
                    <?php foreach ($bills as $b): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="p-4 font-mono font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($b['bill_code']) ?></td>
                            <td class="p-4">
                                <div class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($b['tenant_name']) ?></div>
                                <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold">Kamar <?= htmlspecialchars($b['room_number']) ?></div>
                            </td>
                            <td class="p-4 font-medium text-slate-700 dark:text-slate-300"><?= htmlspecialchars($b['title']) ?></td>
                            <td class="p-4 font-bold text-slate-900 dark:text-white text-sm"><?= formatRupiah($b['amount']) ?></td>
                            <td class="p-4">
                                <div class="text-slate-700 dark:text-slate-300"><?= formatDateIndo($b['due_date']) ?></div>
                            </td>
                            <td class="p-4">
                                <?php if ($b['status'] === 'lunas'): ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">
                                        <i class="fa-solid fa-check mr-1"></i> Lunas
                                    </span>
                                <?php elseif ($b['status'] === 'menunggu_verifikasi'): ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30 animate-pulse">
                                        <i class="fa-solid fa-hourglass-half mr-1"></i> Menunggu Verifikasi
                                    </span>
                                <?php elseif ($b['status'] === 'ditolak'): ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-500/20 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30">
                                        <i class="fa-solid fa-xmark mr-1"></i> Ditolak
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                        <i class="fa-solid fa-clock mr-1"></i> Belum Bayar
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-right">
                                <?php if ($b['status'] === 'menunggu_verifikasi'): ?>
                                    <button onclick='openVerifyModal(<?= json_encode($b) ?>)' class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-md transition-all flex items-center gap-1.5 inline-flex">
                                        <i class="fa-solid fa-file-circle-check"></i> Verifikasi Bayar
                                    </button>
                                <?php elseif ($b['status'] === 'lunas' && !empty($b['proof_image'])): ?>
                                    <button onclick='openProofPreview("<?= htmlspecialchars($b['proof_image']) ?>")' class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-indigo-600 hover:text-white text-slate-700 dark:text-slate-300 text-xs transition-all inline-flex items-center gap-1 border border-slate-200 dark:border-slate-700">
                                        <i class="fa-solid fa-image text-indigo-500"></i> Bukti Bayar
                                    </button>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs italic">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-500">
                            <i class="fa-solid fa-receipt text-3xl mb-2"></i>
                            <div>Belum ada data tagihan sewa.</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Create Bill -->
<div id="createBillModal" class="fixed inset-0 z-50 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white font-heading">Terbitkan Tagihan Baru</h3>
            <button onclick="closeCreateBillModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create_bill">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Pilih Kamar / Penyewa</label>
                <select name="lease_id" id="selectLease" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none" onchange="autoFillBillPrice(this)">
                    <option value="">-- Pilih Kontrak Sewa Aktif --</option>
                    <?php foreach ($activeLeases as $al): ?>
                        <option value="<?= $al['id'] ?>" data-price="<?= $al['price'] ?>">Kamar <?= htmlspecialchars($al['room_number']) ?> - <?= htmlspecialchars($al['tenant_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Deskripsi / Judul Tagihan</label>
                <input type="text" name="title" required value="Sewa Kamar Periode <?= date('F Y') ?>" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Nominal (Rp)</label>
                    <input type="number" name="amount" id="billAmount" required placeholder="Nominal tagihan" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Jatuh Tempo</label>
                    <input type="date" name="due_date" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                </div>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-200 dark:border-slate-800">
                <button type="button" onclick="closeCreateBillModal()" class="py-2.5 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold">Batal</button>
                <button type="submit" class="py-2.5 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-md shadow-indigo-600/30">Terbitkan Tagihan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Verify Payment -->
<div id="verifyModal" class="fixed inset-0 z-50 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-4">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white font-heading">Verifikasi Bukti Transfer</h3>
            <button onclick="closeVerifyModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="space-y-4">
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs space-y-1.5">
                <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Penyewa:</span> <span id="vTenant" class="font-bold text-slate-900 dark:text-white"></span></div>
                <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Tagihan:</span> <span id="vTitle" class="font-bold text-slate-900 dark:text-white"></span></div>
                <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Nominal:</span> <span id="vAmount" class="font-bold text-emerald-600 dark:text-emerald-400 text-sm"></span></div>
                <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Metode & Waktu:</span> <span id="vMeta" class="text-slate-700 dark:text-slate-300"></span></div>
                <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Catatan:</span> <span id="vNotes" class="text-slate-700 dark:text-slate-300 italic"></span></div>
            </div>

            <!-- Proof Image Container -->
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden max-h-64 bg-slate-100 dark:bg-slate-950 flex items-center justify-center">
                <img id="vProofImg" src="" alt="Bukti Transfer" class="max-h-64 object-contain">
            </div>

            <form method="POST" class="pt-2 flex items-center gap-3">
                <input type="hidden" name="action" value="verify_payment">
                <input type="hidden" name="bill_id" id="vBillId" value="">

                <button type="submit" name="decision" value="ditolak" class="flex-1 py-3 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-rose-600 hover:text-white text-rose-600 dark:text-rose-400 font-bold text-xs transition-all flex items-center justify-center gap-1.5 border border-slate-200 dark:border-slate-700">
                    <i class="fa-solid fa-xmark"></i> Tolak Bukti
                </button>
                <button type="submit" name="decision" value="lunas" class="flex-1 py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/30 transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-check"></i> Setujui (LUNAS)
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function openCreateBillModal() {
        document.getElementById('createBillModal').classList.remove('hidden');
    }
    function closeCreateBillModal() {
        document.getElementById('createBillModal').classList.add('hidden');
    }
    function autoFillBillPrice(selectElem) {
        const selectedOption = selectElem.options[selectElem.selectedIndex];
        const price = selectedOption.dataset.price;
        if (price) {
            document.getElementById('billAmount').value = price;
        }
    }

    function openVerifyModal(bill) {
        document.getElementById('vBillId').value = bill.id;
        document.getElementById('vTenant').innerText = bill.tenant_name + ' (Kamar ' + bill.room_number + ')';
        document.getElementById('vTitle').innerText = bill.title;
        document.getElementById('vAmount').innerText = 'Rp ' + Number(bill.amount).toLocaleString('id-ID');
        document.getElementById('vMeta').innerText = (bill.payment_method || 'Transfer Bank') + ' • ' + (bill.payment_date || '-');
        document.getElementById('vNotes').innerText = bill.payment_notes || '-';
        
        let proofSrc = bill.proof_image;
        if (proofSrc) {
            if (!proofSrc.startsWith('http') && !proofSrc.startsWith('../')) {
                proofSrc = '../' + proofSrc.replace(/^\//, '');
            }
        } else {
            proofSrc = 'https://placehold.co/500x350?text=Bukti+Transfer+Tidak+Ditemukan';
        }
        document.getElementById('vProofImg').src = proofSrc;
        document.getElementById('verifyModal').classList.remove('hidden');
    }

    function closeVerifyModal() {
        document.getElementById('verifyModal').classList.add('hidden');
    }

    function openProofPreview(imgPath) {
        let src = imgPath;
        if (!src.startsWith('http') && !src.startsWith('../')) {
            src = '../' + src.replace(/^\//, '');
        }
        Swal.fire({
            title: 'Bukti Pembayaran / Transfer',
            imageUrl: src,
            imageAlt: 'Bukti Transfer',
            confirmButtonText: 'Tutup',
            confirmButtonColor: '#4f46e5'
        });
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
