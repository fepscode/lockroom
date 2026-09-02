<?php
// Top Level Authentication & Logic Execution
require_once __DIR__ . '/../helpers/auth.php';
requireLogin('pemilik');

$user = currentUser();
$pdo = getDBConnection();

$newTenantCredentials = $_SESSION['new_tenant_registered'] ?? null;
unset($_SESSION['new_tenant_registered']);

// Fetch Owner's Properties
$stmtOwnerProps = $pdo->prepare("SELECT * FROM properties WHERE owner_id = ? ORDER BY name ASC");
$stmtOwnerProps->execute([$user['id']]);
$ownerProperties = $stmtOwnerProps->fetchAll();

// Fetch available rooms owned by this user
$stmtAvailRooms = $pdo->prepare("SELECT r.*, p.name as property_name, p.id as property_id 
                                 FROM rooms r 
                                 JOIN properties p ON r.property_id = p.id 
                                 WHERE p.owner_id = ? AND r.status = 'tersedia' 
                                 ORDER BY p.name ASC, r.room_number ASC");
$stmtAvailRooms->execute([$user['id']]);
$availableRooms = $stmtAvailRooms->fetchAll();

// Handle Owner Actions: Register New Tenant or End Lease BEFORE loading header HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ================= REGISTER NEW TENANT & ASSIGN ROOM =================
    if ($action === 'register_and_assign') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = strtolower(sanitizeInput($_POST['email'] ?? ''));
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $passwordPlain = $_POST['password'] ?? 'penyewa123';
        $propertyId = (int)($_POST['property_id'] ?? 0);
        $roomId = (int)$_POST['room_id'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $rentType = $_POST['rent_type'] ?? 'bulanan';
        $price = (float)$_POST['price'];
        $notes = sanitizeInput($_POST['notes'] ?? '');

        if (empty($name) || empty($email) || empty($phone) || empty($roomId) || empty($startDate) || empty($endDate) || $price <= 0) {
            setFlash('error', 'Semua data identitas penyewa, pemilihan rumah kos, kamar, dan periode sewa wajib diisi lengkap!');
        } else {
            try {
                // 1. Check if user already exists
                $stmtUserCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmtUserCheck->execute([$email]);
                $existingUser = $stmtUserCheck->fetch();

                if ($existingUser) {
                    $tenantId = $existingUser['id'];
                    // Update phone and name if needed
                    $stmtUpdateUser = $pdo->prepare("UPDATE users SET name = ?, phone = ?, role = 'penyewa' WHERE id = ?");
                    $stmtUpdateUser->execute([$name, $phone, $tenantId]);
                } else {
                    // Create new user record
                    $hashedPassword = password_hash($passwordPlain, PASSWORD_BCRYPT);
                    $stmtInsertUser = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'penyewa')");
                    $stmtInsertUser->execute([$name, $email, $phone, $hashedPassword]);
                    $tenantId = $pdo->lastInsertId();
                }

                // 2. Fetch Room & Property Details
                $stmtRoomDetails = $pdo->prepare("SELECT r.*, p.name as property_name FROM rooms r JOIN properties p ON r.property_id = p.id WHERE r.id = ? LIMIT 1");
                $stmtRoomDetails->execute([$roomId]);
                $roomInfo = $stmtRoomDetails->fetch();

                // 3. Insert Lease
                $stmtLease = $pdo->prepare("INSERT INTO leases (room_id, tenant_id, start_date, end_date, rent_type, price, status, notes) VALUES (?, ?, ?, ?, ?, ?, 'aktif', ?)");
                $stmtLease->execute([$roomId, $tenantId, $startDate, $endDate, $rentType, $price, $notes]);
                $leaseId = $pdo->lastInsertId();

                // 4. Update Room Status to 'terisi'
                $stmtRoom = $pdo->prepare("UPDATE rooms SET status = 'terisi' WHERE id = ?");
                $stmtRoom->execute([$roomId]);

                // 5. Generate Initial Bill
                $billCode = 'INV-' . date('Ym') . '-' . str_pad($leaseId, 3, '0', STR_PAD_LEFT);
                $stmtBill = $pdo->prepare("INSERT INTO bills (lease_id, tenant_id, bill_code, title, amount, due_date, status) VALUES (?, ?, ?, ?, ?, ?, 'belum_bayar')");
                $stmtBill->execute([$leaseId, $tenantId, $billCode, 'Sewa Kamar Periode Pertama (' . ucfirst($rentType) . ')', $price, date('Y-m-d', strtotime('+7 days'))]);

                // Save credentials to session to show pop-up card
                $_SESSION['new_tenant_registered'] = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $passwordPlain,
                    'room_number' => $roomInfo['room_number'] ?? '-',
                    'property_name' => $roomInfo['property_name'] ?? 'LOCK & ROOM',
                    'price' => $price,
                    'rent_type' => $rentType,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ];

                setFlash('success', "Penyewa <strong>" . htmlspecialchars($name) . "</strong> berhasil didaftarkan di <strong>" . htmlspecialchars($roomInfo['property_name']) . "</strong> - Kamar " . htmlspecialchars($roomInfo['room_number']) . "!");
                header("Location: tenants.php");
                exit;

            } catch (Exception $e) {
                setFlash('error', "Gagal mendaftarkan penyewa: " . $e->getMessage());
            }
        }
        header("Location: tenants.php");
        exit;
    }

    // ================= END LEASE (Penyewa Keluar) =================
    if ($action === 'end_lease') {
        $leaseId = (int)$_POST['lease_id'];
        $roomId = (int)$_POST['room_id'];

        // End Lease
        $stmt = $pdo->prepare("UPDATE leases SET status = 'selesai' WHERE id = ?");
        $stmt->execute([$leaseId]);

        // Free up room
        $stmtRoom = $pdo->prepare("UPDATE rooms SET status = 'tersedia' WHERE id = ?");
        $stmtRoom->execute([$roomId]);

        setFlash('success', "Masa sewa telah diselesaikan. Kamar kini kembali berstatus 'tersedia'.");
        header("Location: tenants.php");
        exit;
    }
}

// Fetch Active Leases
$stmtLeases = $pdo->prepare("SELECT l.*, u.name as tenant_name, u.email as tenant_email, u.phone as tenant_phone, 
                                    r.room_number, r.room_type, r.id as room_id, p.name as property_name 
                             FROM leases l 
                             JOIN users u ON l.tenant_id = u.id 
                             JOIN rooms r ON l.room_id = r.id 
                             JOIN properties p ON r.property_id = p.id
                             WHERE p.owner_id = ?
                             ORDER BY l.status ASC, l.id DESC");
$stmtLeases->execute([$user['id']]);
$leases = $stmtLeases->fetchAll();

$pageTitle = 'Pendaftaran & Manajemen Penyewa';
require_once __DIR__ . '/header.php';
?>

<!-- Header Actions -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Data & Pendaftaran Penyewa</h2>
        <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">
            Pendaftaran penyewa dilakukan oleh pemilik. Pilih Rumah Kos, tetapkan nomor kamar, dan berikan akun login ke penghuni.
        </p>
    </div>
    <button onclick="openRegisterTenantModal()" class="px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-600/30 transition-all flex items-center justify-center gap-2">
        <i class="fa-solid fa-user-plus"></i> Daftarkan Penyewa & Kamar
    </button>
</div>

<!-- Pop-Up Modal / Alert: Baru Saja Mendaftarkan Penyewa -->
<?php if ($newTenantCredentials): ?>
    <?php 
        $waMessage = urlencode("Halo " . $newTenantCredentials['name'] . ",\n\nSelamat datang di " . $newTenantCredentials['property_name'] . " (LOCK & ROOM)!\nBerikut adalah data akun Anda untuk login ke portal penghuni kos:\n\n🌐 Login: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/lockroom/auth/login.php?role=penyewa\n📧 Email: " . $newTenantCredentials['email'] . "\n🔑 Password: " . $newTenantCredentials['password'] . "\n🏠 Rumah Kos: " . $newTenantCredentials['property_name'] . "\n🚪 Nomor Kamar: " . $newTenantCredentials['room_number'] . "\n\nSilakan login untuk melihat tagihan, fasilitas kamar, dan mengajukan laporan fasilitas.\nTerima kasih!");
    ?>
    <div class="p-6 rounded-3xl bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-indigo-500/10 border-2 border-emerald-500/30 dark:border-emerald-500/40 relative overflow-hidden shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs font-bold uppercase tracking-wider">
                    <i class="fa-solid fa-circle-check"></i> Pendaftaran Berhasil & Akun Telah Diterbitkan!
                </div>
                <h3 class="text-xl font-bold text-slate-900 dark:text-white font-heading">
                    Kredensial Akses Penyewa: <span class="text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars($newTenantCredentials['name']) ?></span>
                </h3>
                <p class="text-xs text-slate-600 dark:text-slate-400">
                    Berikan informasi akun berikut kepada penyewa untuk login ke portal <strong>(L n' R)</strong>:
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-2">
                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="text-[10px] text-slate-400 uppercase font-bold">Rumah Kos & Kamar</div>
                        <div class="font-extrabold text-sm text-slate-900 dark:text-white font-heading"><?= htmlspecialchars($newTenantCredentials['property_name']) ?> (Kamar <?= htmlspecialchars($newTenantCredentials['room_number']) ?>)</div>
                    </div>
                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="text-[10px] text-slate-400 uppercase font-bold">Email Login</div>
                        <div class="font-bold text-xs text-slate-900 dark:text-white font-mono"><?= htmlspecialchars($newTenantCredentials['email']) ?></div>
                    </div>
                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="text-[10px] text-slate-400 uppercase font-bold">Kata Sandi Awal</div>
                        <div class="font-bold text-xs text-amber-600 dark:text-amber-400 font-mono"><?= htmlspecialchars($newTenantCredentials['password']) ?></div>
                    </div>
                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="text-[10px] text-slate-400 uppercase font-bold">Tarif Sewa</div>
                        <div class="font-bold text-xs text-emerald-600 dark:text-emerald-400"><?= formatRupiah($newTenantCredentials['price']) ?></div>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Direct Share Button -->
            <div class="flex flex-col sm:flex-row md:flex-col gap-2">
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $newTenantCredentials['phone']) ?>?text=<?= $waMessage ?>" target="_blank" class="px-5 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/30 flex items-center justify-center gap-2 transition-all">
                    <i class="fa-brands fa-whatsapp text-lg"></i> Kirim Akun via WhatsApp
                </a>
                <button onclick="navigator.clipboard.writeText('Email: <?= $newTenantCredentials['email'] ?> | Sandi: <?= $newTenantCredentials['password'] ?> | Kamar: <?= $newTenantCredentials['room_number'] ?>'); alert('Kredensial berhasil disalin ke clipboard!');" class="px-4 py-2 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold border border-slate-200 dark:border-slate-700 flex items-center justify-center gap-1.5 transition-all">
                    <i class="fa-solid fa-copy"></i> Salin Kredensial
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Tenants List Table -->
<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
    <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white font-heading">Daftar Kontrak & Penghuni Kamar</h3>
            <p class="text-slate-500 dark:text-slate-400 text-xs">Riwayat penghuni kamar kos aktif dan selesai.</p>
        </div>
        <span class="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold">
            Total: <?= count($leases) ?> Kontrak
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                    <th class="py-4 px-6">Identitas Penyewa</th>
                    <th class="py-4 px-6">Rumah Kos & Kamar</th>
                    <th class="py-4 px-6">Kontak / No WA</th>
                    <th class="py-4 px-6">Periode Sewa</th>
                    <th class="py-4 px-6">Tarif Sewa</th>
                    <th class="py-4 px-6">Status</th>
                    <th class="py-4 px-6 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if (!empty($leases)): ?>
                    <?php foreach ($leases as $lease): ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="py-4 px-6">
                                <div class="font-bold text-slate-900 dark:text-white text-sm"><?= htmlspecialchars(formatTitleCase($lease['tenant_name'])) ?></div>
                                <div class="text-slate-500 dark:text-slate-400 text-[11px] font-mono"><?= htmlspecialchars($lease['tenant_email']) ?></div>
                            </td>
                            <td class="py-4 px-6">
                                <div class="text-[11px] font-bold text-amber-600 dark:text-amber-400"><?= htmlspecialchars(formatTitleCase($lease['property_name'] ?? 'Properti Kos')) ?></div>
                                <div class="font-extrabold text-slate-900 dark:text-white">Kamar <?= htmlspecialchars($lease['room_number']) ?></div>
                                <div class="text-slate-500 text-[10px]"><?= htmlspecialchars(formatTitleCase($lease['room_type'])) ?></div>
                            </td>
                            <td class="py-4 px-6">
                                <div class="font-mono text-slate-700 dark:text-slate-300"><?= htmlspecialchars($lease['tenant_phone'] ?: '-') ?></div>
                                <?php if ($lease['tenant_phone']): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $lease['tenant_phone']) ?>" target="_blank" class="text-emerald-600 dark:text-emerald-400 text-[11px] hover:underline flex items-center gap-1 mt-0.5">
                                        <i class="fa-brands fa-whatsapp"></i> Hubungi WA
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6">
                                <div class="text-slate-700 dark:text-slate-300 font-medium"><?= formatDateIndo($lease['start_date']) ?> s/d <?= formatDateIndo($lease['end_date']) ?></div>
                                <span class="text-[10px] text-slate-500 uppercase font-semibold">Tipe: <?= ucfirst($lease['rent_type']) ?></span>
                            </td>
                            <td class="py-4 px-6">
                                <div class="font-bold text-emerald-600 dark:text-emerald-400"><?= formatRupiah($lease['price']) ?></div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider <?= $lease['status'] === 'aktif' ? 'badge-aktif' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' ?>">
                                    <?= ucfirst($lease['status']) ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <?php if ($lease['status'] === 'aktif'): ?>
                                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menyelesaikan masa sewa penyewa ini? Kamar akan berstatus kembali tersedia.');" class="inline">
                                        <input type="hidden" name="action" value="end_lease">
                                        <input type="hidden" name="lease_id" value="<?= $lease['id'] ?>">
                                        <input type="hidden" name="room_id" value="<?= $lease['room_id'] ?>">
                                        <button type="submit" class="py-1.5 px-3 rounded-lg bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 hover:bg-rose-600 hover:text-white border border-rose-200 dark:border-rose-500/30 text-[11px] font-bold transition-all">
                                            Selesaikan Sewa
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-slate-400 text-[11px] italic">Selesai</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <i class="fa-solid fa-users-slash text-3xl mb-2"></i>
                            <div>Belum ada data penyewa yang didaftarkan.</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODAL PENDAFTARAN PENYEWA BARU ================= -->
<div id="registerTenantModal" class="fixed inset-0 z-50 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
            <div>
                <h3 class="text-xl font-bold text-slate-900 dark:text-white font-heading">Daftarkan Penyewa & Kamar Baru</h3>
                <p class="text-slate-500 text-xs mt-0.5">Lengkapi identitas penyewa, pilih rumah kos, dan tetapkan nomor kamar.</p>
            </div>
            <button onclick="closeRegisterTenantModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <?php if (empty($availableRooms)): ?>
            <div class="p-6 rounded-2xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-center space-y-3">
                <i class="fa-solid fa-triangle-exclamation text-3xl text-amber-500"></i>
                <div class="text-sm font-bold text-slate-900 dark:text-white">Tidak Ada Kamar yang Berstatus 'Tersedia'</div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Semua kamar saat ini terisi atau belum ada kamar yang didaftarkan. Silakan tambahkan kamar baru atau selesaikan sewa kamar yang telah selesai.</p>
                <a href="rooms.php" class="inline-block px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-md">
                    + Kelola Rumah & Kamar
                </a>
            </div>
        <?php else: ?>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="register_and_assign">

                <!-- STEP 1: IDENTITAS PENYEWA -->
                <div class="space-y-3">
                    <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-1.5">
                        <span class="w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-[10px]">1</span>
                        Data Identitas Penyewa
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Nama Lengkap Penyewa *</label>
                            <input type="text" name="name" required placeholder="Contoh: Budi Santoso" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Nomor WhatsApp / HP Aktif *</label>
                            <input type="tel" name="phone" required placeholder="Contoh: 081234567890" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Catatan Identitas / No. KTP (Opsional)</label>
                        <input type="text" name="notes" placeholder="Contoh: 3175000000000001 (Karyawan PT ABC)" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                    </div>
                </div>

                <!-- STEP 2: AKUN LOGIN PORTAL PENYEWA -->
                <div class="space-y-3">
                    <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-1.5">
                        <span class="w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-[10px]">2</span>
                        Akun Login Portal Penyewa
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Alamat Email (Username) *</label>
                            <input type="email" name="email" required placeholder="penyewa@gmail.com" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Kata Sandi Awal *</label>
                            <input type="text" name="password" required value="penyewa123" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm font-mono focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- STEP 3: PENEMPATAN RUMAH KOS & KAMAR -->
                <div class="space-y-3">
                    <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-1.5">
                        <span class="w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-[10px]">3</span>
                        Pilih Rumah Kos & Penempatan Kamar
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Pilih Rumah Kos / Kontrakan *</label>
                            <select id="modalSelectProperty" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                                <option value="">-- Pilih Rumah Kos --</option>
                                <?php foreach ($ownerProperties as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['city']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Pilih Nomor Kamar Tersedia *</label>
                            <select name="room_id" id="modalSelectRoom" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                                <option value="">-- Pilih Rumah Kos Terlebih Dahulu --</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Tanggal Mulai Sewa *</label>
                            <input type="date" name="start_date" id="inputStartDate" required value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2 px-3 text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Tanggal Selesai / Tempo *</label>
                            <input type="date" name="end_date" id="inputEndDate" required value="<?= date('Y-m-d', strtotime('+1 month')) ?>" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2 px-3 text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Tarif Sewa (Rp) *</label>
                            <input type="number" name="price" id="inputRentPrice" required placeholder="0" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2 px-3 text-slate-900 dark:text-white text-xs font-bold focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" onclick="closeRegisterTenantModal()" class="py-2.5 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold">Batal</button>
                    <button type="submit" class="py-2.5 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-600/30">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan & Daftarkan Penyewa
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    // All available rooms data in JSON
    const allAvailableRooms = <?= json_encode($availableRooms) ?>;

    const tenantModal = document.getElementById('registerTenantModal');
    const selectProp = document.getElementById('modalSelectProperty');
    const selectRoom = document.getElementById('modalSelectRoom');
    const inputPrice = document.getElementById('inputRentPrice');

    function openRegisterTenantModal() {
        tenantModal.classList.remove('hidden');
        if (selectProp && selectProp.options.length > 1) {
            // Auto select first property if not selected
            if (!selectProp.value) {
                selectProp.selectedIndex = 1;
            }
            updateAvailableRooms();
        }
    }

    function closeRegisterTenantModal() {
        tenantModal.classList.add('hidden');
    }

    function updateAvailableRooms() {
        if (!selectProp || !selectRoom) return;
        const selectedPropId = parseInt(selectProp.value);
        selectRoom.innerHTML = '';

        const filtered = allAvailableRooms.filter(r => parseInt(r.property_id) === selectedPropId);

        if (filtered.length === 0) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = '-- Tidak ada kamar kosong di rumah kos ini --';
            selectRoom.appendChild(opt);
            if (inputPrice) inputPrice.value = '';
        } else {
            filtered.forEach((r, idx) => {
                const opt = document.createElement('option');
                opt.value = r.id;
                opt.dataset.price = r.price_monthly;
                opt.textContent = `Kamar ${r.room_number} (${r.room_type}) - Rp ${parseInt(r.price_monthly).toLocaleString('id-ID')}/bln`;
                selectRoom.appendChild(opt);
            });
            // Trigger price update on first room
            if (inputPrice && filtered[0]) {
                inputPrice.value = filtered[0].price_monthly;
            }
        }
    }

    if (selectProp) {
        selectProp.addEventListener('change', updateAvailableRooms);
    }

    if (selectRoom) {
        selectRoom.addEventListener('change', function() {
            const selectedOpt = selectRoom.options[selectRoom.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.price && inputPrice) {
                inputPrice.value = selectedOpt.dataset.price;
            }
        });
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
