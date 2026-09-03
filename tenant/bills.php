<?php
// Top Level Authentication & Logic Execution
require_once __DIR__ . '/../helpers/auth.php';
requireLogin('penyewa');

$user = currentUser();
$pdo = getDBConnection();

// Handle Upload Bukti Bayar BEFORE loading header HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billId = (int)$_POST['bill_id'];
    $method = sanitizeInput($_POST['payment_method']);
    $notes = sanitizeInput($_POST['notes'] ?? '');
    $amount = (float)$_POST['amount'];

    $proofPath = 'assets/uploads/sample-receipt.png'; // default fallback

    // Handle file upload if present
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/uploads/payments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
            $filename = 'proof_' . $billId . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $target = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $target)) {
                $proofPath = 'assets/uploads/payments/' . $filename;
            }
        }
    }

    // Insert or update payment record
    $stmtCheck = $pdo->prepare("SELECT id FROM payments WHERE bill_id = ?");
    $stmtCheck->execute([$billId]);
    $payExists = $stmtCheck->fetch();

    if ($payExists) {
        $stmtP = $pdo->prepare("UPDATE payments SET amount = ?, payment_method = ?, payment_date = NOW(), proof_image = ?, notes = ?, status = 'menunggu' WHERE bill_id = ?");
        $stmtP->execute([$amount, $method, $proofPath, $notes, $billId]);
    } else {
        $stmtP = $pdo->prepare("INSERT INTO payments (bill_id, amount, payment_method, payment_date, proof_image, notes, status) VALUES (?, ?, ?, NOW(), ?, ?, 'menunggu')");
        $stmtP->execute([$billId, $amount, $method, $proofPath, $notes]);
    }

    // Update bill status to 'menunggu_verifikasi'
    $stmtB = $pdo->prepare("UPDATE bills SET status = 'menunggu_verifikasi' WHERE id = ?");
    $stmtB->execute([$billId]);

    // Send OneSignal Push Notification to Owner
    require_once __DIR__ . '/../helpers/onesignal.php';
    notifyOwnerNewPayment($billId);

    setFlash('success', "Bukti transfer pembayaran berhasil diunggah dan sedang menunggu verifikasi pemilik!");
    header("Location: bills.php");
    exit;
}

$pageTitle = 'Tagihan & Konfirmasi Pembayaran';
require_once __DIR__ . '/header.php';

// Fetch all bills for tenant
$stmtBills = $pdo->prepare("SELECT b.*, p.proof_image, p.payment_method, p.status as payment_status 
                            FROM bills b 
                            LEFT JOIN payments p ON b.id = p.bill_id 
                            WHERE b.tenant_id = ? 
                            ORDER BY b.id DESC");
$stmtBills->execute([$user['id']]);
$bills = $stmtBills->fetchAll();
?>

<!-- Header -->
<div>
    <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Tagihan & Riwayat Pembayaran</h2>
    <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Lakukan pembayaran sewa kos dan unggah bukti transfer langsung ke sistem.</p>
</div>

<!-- Bank Transfer Instruction Card -->
<div class="glass-card rounded-3xl p-6 border border-indigo-200 dark:border-indigo-500/30 bg-white dark:bg-slate-900 shadow-sm">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="space-y-1">
            <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-[11px] font-bold uppercase tracking-wider mb-1">
                <i class="fa-solid fa-building-columns"></i> Rekening Pembayaran Resmi
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white font-heading">Transfer Bank BCA & E-Wallet</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Pastikan nominal transfer sesuai dengan invoice dan simpan bukti transfer Anda.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="p-3 bg-slate-50 dark:bg-slate-950/80 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4">
                <div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold">BCA (Pemilik Kos)</div>
                    <div class="text-sm font-mono font-extrabold text-slate-900 dark:text-white">8801 2345 6789</div>
                    <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium">a/n Pengelola Kos</div>
                </div>
                <button onclick="copyToClipboard('880123456789')" class="p-2 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs" title="Salin Rekening">
                    <i class="fa-solid fa-copy"></i>
                </button>
            </div>

            <div class="p-3 bg-slate-50 dark:bg-slate-950/80 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4">
                <div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold">Mandiri (Pemilik Kos)</div>
                    <div class="text-sm font-mono font-extrabold text-slate-900 dark:text-white">1370 0192 8374</div>
                    <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium">a/n Pengelola Kos</div>
                </div>
                <button onclick="copyToClipboard('137001928374')" class="p-2 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs" title="Salin Rekening">
                    <i class="fa-solid fa-copy"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bills List -->
<div class="bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
            <thead class="bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-400 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="p-4">No. Invoice</th>
                    <th class="p-4">Deskripsi Tagihan</th>
                    <th class="p-4">Nominal</th>
                    <th class="p-4">Batas Pembayaran</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                <?php if (!empty($bills)): ?>
                    <?php foreach ($bills as $b): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="p-4 font-mono font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($b['bill_code']) ?></td>
                            <td class="p-4 font-bold text-slate-900 dark:text-white text-sm"><?= htmlspecialchars($b['title']) ?></td>
                            <td class="p-4 font-bold text-emerald-600 dark:text-emerald-400 text-sm"><?= formatRupiah($b['amount']) ?></td>
                            <td class="p-4 text-slate-700 dark:text-slate-300"><?= formatDateIndo($b['due_date']) ?></td>
                            <td class="p-4">
                                <?php if ($b['status'] === 'lunas'): ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">
                                        <i class="fa-solid fa-check mr-1"></i> Lunas
                                    </span>
                                <?php elseif ($b['status'] === 'menunggu_verifikasi'): ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30 animate-pulse">
                                        <i class="fa-solid fa-hourglass-half mr-1"></i> Menunggu Verifikasi
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-500/20 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30">
                                        <i class="fa-solid fa-clock mr-1"></i> Belum Bayar
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-right">
                                <?php if ($b['status'] === 'belum_bayar' || $b['status'] === 'ditolak'): ?>
                                    <button onclick='openPayModal(<?= json_encode($b) ?>)' class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/30 transition-all inline-flex items-center gap-1.5">
                                        <i class="fa-solid fa-upload"></i> Bayar Sekarang
                                    </button>
                                <?php elseif ($b['status'] === 'lunas'): ?>
                                    <div class="flex items-center justify-end gap-2">
                                        <span class="text-xs text-emerald-600 dark:text-emerald-400 font-bold"><i class="fa-solid fa-circle-check"></i> LUNAS</span>
                                        <a href="../bills/receipt.php?bill_id=<?= $b['id'] ?>" target="_blank" class="p-1 px-2.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 hover:bg-indigo-600 hover:text-white text-indigo-700 dark:text-indigo-300 text-[11px] font-bold transition-all border border-indigo-200 dark:border-indigo-500/30 inline-flex items-center gap-1" title="Cetak Kwitansi Resmi">
                                            <i class="fa-solid fa-print text-indigo-500"></i> Kwitansi
                                        </a>
                                        <?php if (!empty($b['proof_image'])): ?>
                                            <button onclick='openProofPreview("<?= htmlspecialchars($b['proof_image']) ?>")' class="p-1 px-2 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-emerald-600 hover:text-white text-slate-600 dark:text-slate-300 text-[11px] font-semibold transition-all border border-slate-200 dark:border-slate-700" title="Lihat Bukti Transfer">
                                                <i class="fa-solid fa-receipt"></i> Bukti
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="flex items-center justify-end gap-2">
                                        <span class="text-xs text-amber-600 dark:text-amber-400 font-semibold"><i class="fa-solid fa-hourglass-half"></i> Terkirim</span>
                                        <?php if (!empty($b['proof_image'])): ?>
                                            <button onclick='openProofPreview("<?= htmlspecialchars($b['proof_image']) ?>")' class="p-1 px-2 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-amber-500 hover:text-slate-950 text-slate-600 dark:text-slate-300 text-[11px] font-semibold transition-all border border-slate-200 dark:border-slate-700" title="Lihat Bukti Transfer">
                                                <i class="fa-solid fa-receipt"></i> Bukti
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-500">
                            <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-400 dark:text-slate-600"></i>
                            <div>Belum ada tagihan sewa yang diterbitkan.</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Upload Bukti Transfer -->
<div id="payModal" class="fixed inset-0 z-50 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white font-heading">Konfirmasi Pembayaran</h3>
            <button onclick="closePayModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="bill_id" id="payBillId" value="">

            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs space-y-1">
                <div class="text-slate-500 dark:text-slate-400">Invoice: <strong id="payBillCode" class="text-slate-900 dark:text-white font-mono"></strong></div>
                <div class="text-slate-500 dark:text-slate-400">Judul: <strong id="payBillTitle" class="text-slate-900 dark:text-white"></strong></div>
                <div class="text-slate-500 dark:text-slate-400">Nominal Wajib: <strong id="payBillAmount" class="text-emerald-600 dark:text-emerald-400 text-sm font-bold"></strong></div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Nominal yang Ditransfer (Rp)</label>
                <input type="number" name="amount" id="inputPayAmount" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Metode Transfer</label>
                <select name="payment_method" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-emerald-500 focus:outline-none">
                    <option value="Transfer BCA">Transfer BCA</option>
                    <option value="Transfer Mandiri">Transfer Mandiri</option>
                    <option value="QRIS / E-Wallet (GoPay/OVO/Dana)">QRIS / E-Wallet</option>
                    <option value="Tunai / Cash">Tunai / Cash ke Pengelola</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Upload Bukti Transfer / Struk (Gambar)</label>
                <input type="file" name="proof_image" accept="image/*" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2 px-3 text-slate-900 dark:text-white text-xs file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-600 file:text-white hover:file:bg-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Catatan Tambahan (Opsional)</label>
                <input type="text" name="notes" placeholder="Contoh: Transfer via rekening an Budi" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-200 dark:border-slate-800">
                <button type="button" onclick="closePayModal()" class="py-2.5 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold">Batal</button>
                <button type="submit" class="py-2.5 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-600/30">Kirim Bukti Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPayModal(bill) {
        document.getElementById('payBillId').value = bill.id;
        document.getElementById('payBillCode').innerText = bill.bill_code;
        document.getElementById('payBillTitle').innerText = bill.title;
        document.getElementById('payBillAmount').innerText = 'Rp ' + Number(bill.amount).toLocaleString('id-ID');
        document.getElementById('inputPayAmount').value = bill.amount;
        document.getElementById('payModal').classList.remove('hidden');
    }
    function closePayModal() {
        document.getElementById('payModal').classList.add('hidden');
    }
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text);
        alert('Nomor rekening disalin: ' + text);
    }
    function openProofPreview(imgPath) {
        let src = imgPath;
        if (!src.startsWith('http') && !src.startsWith('../')) {
            src = '../' + src.replace(/^\//, '');
        }
        Swal.fire({
            title: 'Bukti Pembayaran Terunggah',
            imageUrl: src,
            imageAlt: 'Bukti Pembayaran',
            confirmButtonText: 'Tutup',
            confirmButtonColor: '#059669'
        });
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
