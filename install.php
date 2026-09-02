<?php
// Auto-installer & Database Seeder for LOCK & ROOM (L n' R)
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';
$installed = false;

if (php_sapi_name() === 'cli' || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') || isset($_GET['auto'])) {
    try {
        // 1. Connect without DB name first
        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // 2. Create DB if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        
        // 3. Read and execute schema
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        $pdo->exec($sql);
        
        // 4. Seed initial data
        // Check if users already seeded
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            $ownerPass = password_hash('pemilik123', PASSWORD_BCRYPT);
            $tenantPass = password_hash('penyewa123', PASSWORD_BCRYPT);
            
            // Insert Owner
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['Haji Sulaiman (Owner)', 'pemilik@lockroom.com', '081234567890', $ownerPass, 'pemilik']);
            $ownerId = $pdo->lastInsertId();
            
            // Insert Tenant 1 & 2
            $stmt->execute(['Rizky Ramadhan', 'penyewa@lockroom.com', '089876543210', $tenantPass, 'penyewa']);
            $tenantId1 = $pdo->lastInsertId();
            
            $stmt->execute(['Anisa Putri', 'anisa@lockroom.com', '087811223344', $tenantPass, 'penyewa']);
            $tenantId2 = $pdo->lastInsertId();
            
            // Insert Property
            $stmtProp = $pdo->prepare("INSERT INTO properties (owner_id, name, type, address, city, description, rules) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtProp->execute([
                $ownerId,
                'Kost Exclusive Graha Harmoni L n\' R',
                'kos_campur',
                'Jl. Melati No. 18, Kebayoran Baru, Jakarta Selatan',
                'Jakarta Selatan',
                'Rumah kos modern, nyaman, dan strategis dekat pusat perkantoran dan stasiun MRT. Dilengkapi keamanan 24 jam dan CCTV.',
                '1. Dilarang merokok di dalam kamar\n2. Tamu menginap wajib lapor pemilik\n3. Pembayaran sewa maksimal tanggal 5 setiap bulan\n4. Menjaga kebersihan dan ketenangan bersama'
            ]);
            $propId = $pdo->lastInsertId();
            
            // Insert Rooms
            $stmtRoom = $pdo->prepare("INSERT INTO rooms (property_id, room_number, room_type, price_monthly, price_yearly, size, facilities, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $rooms = [
                [$propId, 'A-01', 'Deluxe Room', 1850000, 21000000, '4x4 meter', 'AC, Kamar Mandi Dalam, Kasur Springbed Queen, Lemari 2 Pintu, Meja Kerja, WiFi High Speed, Water Heater', 'terisi', 'Kamar deluxe lantai 1 dengan jendela view taman mini.'],
                [$propId, 'A-02', 'Standard Room', 1350000, 15000000, '3x4 meter', 'AC, Kamar Mandi Luar, Kasur Single, Meja Belajar, WiFi High Speed, Lemari', 'tersedia', 'Kamar nyaman dengan sirkulasi udara baik.'],
                [$propId, 'B-01', 'VIP Suite Room', 2400000, 27000000, '4x5 meter', 'AC, Kamar Mandi Dalam, Smart TV 32 Inch, Kulkas Mini, Balkon Pribadi, WiFi High Speed, Water Heater', 'terisi', 'Kamar luas dengan balkon pribadi dan fasilitas premium.'],
                [$propId, 'B-02', 'Standard Room', 1350000, 15000000, '3x4 meter', 'AC, Kamar Mandi Luar, Kasur Single, Meja Belajar, WiFi High Speed, Lemari', 'tersedia', 'Kamar lantai 2 tenang dan bersih.'],
                [$propId, 'C-01', 'Studio Room (Kontrakan)', 3000000, 34000000, '5x6 meter', 'AC, Dapur Mini, Kamar Mandi Dalam, Ruang Tamu Kecil, Parkir Mobil Khusus, Listrik Token Sendiri', 'tersedia', 'Unit kontrakan model studio terpisah dengan dapur pribadi.']
            ];
            
            foreach ($rooms as $r) {
                $stmtRoom->execute($r);
            }
            
            // Leases for occupied rooms
            $stmtLease = $pdo->prepare("INSERT INTO leases (room_id, tenant_id, start_date, end_date, rent_type, price, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtLease->execute([1, $tenantId1, date('Y-m-01'), date('Y-m-d', strtotime('+6 month')), 'bulanan', 1850000, 'aktif']);
            $lease1 = $pdo->lastInsertId();
            
            $stmtLease->execute([3, $tenantId2, date('Y-m-01'), date('Y-m-d', strtotime('+1 year')), 'bulanan', 2400000, 'aktif']);
            $lease2 = $pdo->lastInsertId();
            
            // Sample Bills
            $stmtBill = $pdo->prepare("INSERT INTO bills (lease_id, tenant_id, bill_code, title, amount, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $billCode1 = 'INV-' . date('Ym') . '-001';
            $billCode2 = 'INV-' . date('Ym') . '-002';
            $stmtBill->execute([$lease1, $tenantId1, $billCode1, 'Sewa Kamar A-01 - Periode ' . date('F Y'), 1850000, date('Y-m-10'), 'belum_bayar']);
            $stmtBill->execute([$lease2, $tenantId2, $billCode2, 'Sewa Kamar B-01 - Periode ' . date('F Y'), 2400000, date('Y-m-10'), 'lunas']);
            
            // Sample Complaints
            $stmtComp = $pdo->prepare("INSERT INTO complaints (tenant_id, room_id, title, description, priority, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtComp->execute([$tenantId1, 1, 'Kran Air Kamar Mandi Menetes', 'Kran air shower agak longgar sehingga air terus menetes saat ditutup.', 'sedang', 'menunggu']);
        }
        
        $installed = true;
        $message = 'Database dan data demo berhasil diinisialisasi!';
    } catch (Exception $e) {
        $error = 'Gagal menginstal database: ' . $e->getMessage();
    }
} else {
    $installed = isInstalled();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inisialisasi Database - LOCK & ROOM (L n' R)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        h1, h2, h3, .font-heading { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-slate-800/90 backdrop-blur-xl border border-slate-700/80 rounded-2xl p-8 shadow-2xl">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-tr from-amber-500 to-indigo-600 rounded-2xl shadow-lg mb-4">
                <i class="fa-solid fa-hotel text-2xl text-white"></i>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white">LOCK & ROOM <span class="text-amber-400 text-xl font-medium">(L n' R)</span></h1>
            <p class="text-slate-400 text-sm mt-1">Installer & Inisialisasi Database Sistem Kos & Kontrakan</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-xl bg-emerald-500/20 border border-emerald-500/50 text-emerald-300 text-sm flex items-center gap-3">
                <i class="fa-solid fa-circle-check text-xl flex-shrink-0"></i>
                <div>
                    <div class="font-semibold"><?= htmlspecialchars($message) ?></div>
                    <div class="text-xs text-emerald-400 mt-1">Struktur tabel, data kamar, akun Pemilik & Penyewa siap digunakan.</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-500/20 border border-rose-500/50 text-rose-300 text-sm flex items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation text-xl flex-shrink-0"></i>
                <div>
                    <div class="font-semibold">Terjadi Kesalahan</div>
                    <div class="text-xs text-rose-400 mt-1"><?= htmlspecialchars($error) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-slate-900/80 border border-slate-700 rounded-xl p-5 mb-6 text-sm">
            <div class="font-semibold text-amber-300 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-key"></i> Akun Demo Siap Pakai:
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-3 bg-slate-800 rounded-lg border border-slate-700">
                    <div class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-1"><i class="fa-solid fa-user-tie mr-1"></i> Akun Pemilik</div>
                    <div class="text-slate-300">Email: <span class="text-white font-mono font-medium">pemilik@lockroom.com</span></div>
                    <div class="text-slate-300">Password: <span class="text-amber-400 font-mono font-semibold">pemilik123</span></div>
                </div>
                <div class="p-3 bg-slate-800 rounded-lg border border-slate-700">
                    <div class="text-xs font-bold text-emerald-400 uppercase tracking-wider mb-1"><i class="fa-solid fa-user mr-1"></i> Akun Penyewa</div>
                    <div class="text-slate-300">Email: <span class="text-white font-mono font-medium">penyewa@lockroom.com</span></div>
                    <div class="text-slate-300">Password: <span class="text-amber-400 font-mono font-semibold">penyewa123</span></div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <form method="POST" class="w-full">
                <button type="submit" class="w-full py-3.5 px-6 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 text-white font-semibold shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-database"></i> <?= $installed ? 'Reset & Instal Ulang Database' : 'Instal Database Sekarang' ?>
                </button>
            </form>

            <a href="index.php" class="w-full py-3 px-6 rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-200 font-semibold text-center transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-arrow-right"></i> Masuk ke Halaman Utama LOCK & ROOM
            </a>
        </div>
    </div>
</body>
</html>
