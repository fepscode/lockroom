<?php
// Top Level Authentication & Logic Execution (Prevents Header Sent Warnings)
require_once __DIR__ . '/../helpers/auth.php';
requireLogin('pemilik');

$user = currentUser();
$pdo = getDBConnection();

// Ensure upload directories exist
$uploadDirProps = __DIR__ . '/../assets/uploads/properties/';
$uploadDirRooms = __DIR__ . '/../assets/uploads/rooms/';
if (!is_dir($uploadDirProps)) mkdir($uploadDirProps, 0777, true);
if (!is_dir($uploadDirRooms)) mkdir($uploadDirRooms, 0777, true);

// Fetch all properties owned by this user
$stmtProps = $pdo->prepare("SELECT p.*, 
                            (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.id) as total_rooms,
                            (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.id AND r.status = 'tersedia') as available_rooms 
                            FROM properties p 
                            WHERE p.owner_id = ? 
                            ORDER BY p.id ASC");
$stmtProps->execute([$user['id']]);
$properties = $stmtProps->fetchAll();

// If no property exists, create default one
if (empty($properties)) {
    $stmtInitProp = $pdo->prepare("INSERT INTO properties (owner_id, name, type, address, city, description) VALUES (?, ?, 'kos_campur', 'Jl. Harmoni No. 10', 'Jakarta Selatan', 'Properti kos utama.')");
    $stmtInitProp->execute([$user['id'], 'Kost ' . $user['name']]);
    
    $stmtProps->execute([$user['id']]);
    $properties = $stmtProps->fetchAll();
}

// Active Property Filter
$selectedPropId = isset($_GET['property_id']) && is_numeric($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
$activeTab = $_GET['tab'] ?? 'kamar'; // 'kamar' or 'rumah'

// Handle POST actions for Properties & Rooms BEFORE loading header HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectTab = $_POST['redirect_tab'] ?? $activeTab;

    // ================= 1. PROPERTY ACTIONS =================
    if ($action === 'add_property') {
        $name = formatTitleCase(sanitizeInput($_POST['name']));
        $type = sanitizeInput($_POST['type']);
        $address = sanitizeInput($_POST['address']);
        $city = formatTitleCase(sanitizeInput($_POST['city']));
        $description = sanitizeInput($_POST['description'] ?? '');
        $rules = sanitizeInput($_POST['rules'] ?? '');
        $imagePath = null;

        // Handle Image Upload for Property
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = 'prop_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDirProps . $filename)) {
                    $imagePath = 'assets/uploads/properties/' . $filename;
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO properties (owner_id, name, type, address, city, description, rules, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], $name, $type, $address, $city, $description, $rules, $imagePath]);

        setFlash('success', "Rumah Kos / Properti <strong>$name</strong> berhasil ditambahkan!");
        header("Location: rooms.php?tab=rumah");
        exit;
    }

    if ($action === 'edit_property') {
        $propId = (int)$_POST['property_id'];
        $name = formatTitleCase(sanitizeInput($_POST['name']));
        $type = sanitizeInput($_POST['type']);
        $address = sanitizeInput($_POST['address']);
        $city = formatTitleCase(sanitizeInput($_POST['city']));
        $description = sanitizeInput($_POST['description'] ?? '');
        $rules = sanitizeInput($_POST['rules'] ?? '');

        // Handle Image Upload for Property
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = 'prop_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDirProps . $filename)) {
                    $imagePath = 'assets/uploads/properties/' . $filename;
                    $stmtImg = $pdo->prepare("UPDATE properties SET image = ? WHERE id = ? AND owner_id = ?");
                    $stmtImg->execute([$imagePath, $propId, $user['id']]);
                }
            }
        }

        $stmt = $pdo->prepare("UPDATE properties SET name = ?, type = ?, address = ?, city = ?, description = ?, rules = ? WHERE id = ? AND owner_id = ?");
        $stmt->execute([$name, $type, $address, $city, $description, $rules, $propId, $user['id']]);

        setFlash('success', "Data Rumah Kos <strong>$name</strong> berhasil diperbarui!");
        header("Location: rooms.php?tab=rumah");
        exit;
    }

    if ($action === 'delete_property') {
        $propId = (int)$_POST['property_id'];
        $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ? AND owner_id = ?");
        $stmt->execute([$propId, $user['id']]);

        setFlash('success', "Rumah Kos dan seluruh kamar di dalamnya berhasil dihapus!");
        header("Location: rooms.php?tab=rumah");
        exit;
    }

    // ================= 2. ROOM ACTIONS =================
    if ($action === 'add_room') {
        $propId = (int)$_POST['property_id'];
        $roomNumber = sanitizeInput($_POST['room_number']);
        $roomType = formatTitleCase(sanitizeInput($_POST['room_type']));
        $priceMonthly = (float)$_POST['price_monthly'];
        $priceYearly = !empty($_POST['price_yearly']) ? (float)$_POST['price_yearly'] : null;
        $size = sanitizeInput($_POST['size'] ?? '3x4 meter');
        $facilities = sanitizeInput($_POST['facilities'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'tersedia');
        $imagePath = null;

        // Handle Image Upload for Room
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = 'room_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDirRooms . $filename)) {
                    $imagePath = 'assets/uploads/rooms/' . $filename;
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO rooms (property_id, room_number, room_type, price_monthly, price_yearly, size, facilities, status, description, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$propId, $roomNumber, $roomType, $priceMonthly, $priceYearly, $size, $facilities, $status, $description, $imagePath]);

        setFlash('success', "Kamar <strong>$roomNumber</strong> berhasil ditambahkan!");
        header("Location: rooms.php?tab=" . $redirectTab . "&property_id=" . $propId);
        exit;
    }

    if ($action === 'edit_room') {
        $roomId = (int)$_POST['room_id'];
        $propId = (int)$_POST['property_id'];
        $roomNumber = sanitizeInput($_POST['room_number']);
        $roomType = sanitizeInput($_POST['room_type']);
        $priceMonthly = (float)$_POST['price_monthly'];
        $priceYearly = !empty($_POST['price_yearly']) ? (float)$_POST['price_yearly'] : null;
        $size = sanitizeInput($_POST['size'] ?? '3x4 meter');
        $facilities = sanitizeInput($_POST['facilities'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'tersedia');

        // Handle Image Upload for Room
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = 'room_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDirRooms . $filename)) {
                    $imagePath = 'assets/uploads/rooms/' . $filename;
                    $stmtImg = $pdo->prepare("UPDATE rooms SET image = ? WHERE id = ?");
                    $stmtImg->execute([$imagePath, $roomId]);
                }
            }
        }

        $stmt = $pdo->prepare("UPDATE rooms SET property_id = ?, room_number = ?, room_type = ?, price_monthly = ?, price_yearly = ?, size = ?, facilities = ?, status = ?, description = ? WHERE id = ?");
        $stmt->execute([$propId, $roomNumber, $roomType, $priceMonthly, $priceYearly, $size, $facilities, $status, $description, $roomId]);

        setFlash('success', "Data Kamar <strong>$roomNumber</strong> berhasil diperbarui!");
        header("Location: rooms.php?tab=" . $redirectTab . "&property_id=" . $propId);
        exit;
    }

    if ($action === 'delete_room') {
        $roomId = (int)$_POST['room_id'];
        $propId = (int)($_POST['property_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE r FROM rooms r JOIN properties p ON r.property_id = p.id WHERE r.id = ? AND p.owner_id = ?");
        $stmt->execute([$roomId, $user['id']]);

        setFlash('success', "Kamar berhasil dihapus!");
        header("Location: rooms.php?tab=" . $redirectTab . ($propId > 0 ? "&property_id=" . $propId : ''));
        exit;
    }
}

// Fetch all rooms for owner
$stmtAllRooms = $pdo->prepare("SELECT r.*, p.name as property_name, p.city FROM rooms r JOIN properties p ON r.property_id = p.id WHERE p.owner_id = ? ORDER BY p.name ASC, r.room_number ASC");
$stmtAllRooms->execute([$user['id']]);
$allRooms = $stmtAllRooms->fetchAll();

if ($selectedPropId > 0) {
    $rooms = array_filter($allRooms, function($r) use ($selectedPropId) {
        return (int)$r['property_id'] === $selectedPropId;
    });
} else {
    $rooms = $allRooms;
}

// Now render HTML layout safely
$pageTitle = 'Kelola Rumah Kos & Kamar';
require_once __DIR__ . '/header.php';
?>

<!-- Datalist Autocomplete Pilihan Kota di Indonesia -->
<datalist id="citySuggestions">
    <option value="Jakarta Selatan">Jakarta Selatan</option>
    <option value="Jakarta Pusat">Jakarta Pusat</option>
    <option value="Jakarta Barat">Jakarta Barat</option>
    <option value="Jakarta Timur">Jakarta Timur</option>
    <option value="Jakarta Utara">Jakarta Utara</option>
    <option value="Bogor">Bogor</option>
    <option value="Depok">Depok</option>
    <option value="Tangerang">Tangerang</option>
    <option value="Tangerang Selatan">Tangerang Selatan</option>
    <option value="Bekasi">Bekasi</option>
    <option value="Bandung">Bandung</option>
    <option value="Semarang">Semarang</option>
    <option value="Yogyakarta">Yogyakarta</option>
    <option value="Surakarta (Solo)">Surakarta (Solo)</option>
    <option value="Surabaya">Surabaya</option>
    <option value="Malang">Malang</option>
    <option value="Denpasar">Denpasar</option>
    <option value="Medan">Medan</option>
    <option value="Palembang">Palembang</option>
    <option value="Balikpapan">Balikpapan</option>
    <option value="Makassar">Makassar</option>
</datalist>

<!-- Header Actions & Tabs -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Kelola Rumah Kos & Kamar</h2>
        <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">
            Unggah foto rumah kos dan unit kamar Anda agar tampil menarik di katalog dan halaman utama promosi.
        </p>
    </div>

    <!-- Action Buttons -->
    <div class="flex items-center gap-2.5">
        <button onclick="openAddPropertyModal()" class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-sm transition-all flex items-center gap-2">
            <i class="fa-solid fa-hotel"></i> + Tambah Rumah Kos
        </button>
        <button onclick="openAddRoomModal()" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-600/30 transition-all flex items-center gap-2">
            <i class="fa-solid fa-door-open"></i> + Tambah Kamar
        </button>
    </div>
</div>

<!-- Navigation Tabs (Kamar vs Rumah Kos) -->
<div class="flex items-center gap-3 border-b border-slate-200 dark:border-slate-800 pb-3">
    <a href="rooms.php?tab=kamar" class="px-5 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 <?= $activeTab === 'kamar' ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' ?>">
        <i class="fa-solid fa-bed"></i> Daftar Unit Kamar (<?= count($allRooms) ?>)
    </a>
    <a href="rooms.php?tab=rumah" class="px-5 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 <?= $activeTab === 'rumah' ? 'bg-amber-500 text-slate-950 shadow-md font-extrabold' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' ?>">
        <i class="fa-solid fa-city"></i> Daftar Rumah Kos & Kontrakan (<?= count($properties) ?>)
    </a>
</div>

<!-- ================= TAB 1: DAFTAR KAMAR ================= -->
<?php if ($activeTab === 'kamar'): ?>
    
    <!-- Filter by Rumah Kos -->
    <div class="flex flex-wrap items-center justify-between gap-4 p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-700 dark:text-slate-300"><i class="fa-solid fa-filter text-indigo-500 mr-1"></i> Filter Rumah Kos:</span>
            <select onchange="location.href='rooms.php?tab=kamar&property_id=' + this.value" class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-1.5 px-3 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none">
                <option value="0">Semua Rumah Kos (<?= count($properties) ?> Properti)</option>
                <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $selectedPropId === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['name']) ?> (<?= $p['total_rooms'] ?> Kamar)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400">
            Menampilkan <strong><?= count($rooms) ?> unit kamar</strong> terdaftar
        </div>
    </div>

    <!-- Rooms Card Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (!empty($rooms)): ?>
            <?php foreach ($rooms as $idx => $r): ?>
                <?php $roomPhotoUrl = getRoomImage($r['image'] ?? null, $r['room_type'], $idx); ?>
                <div class="glass-card rounded-3xl overflow-hidden border border-slate-200 dark:border-slate-800 flex flex-col justify-between shadow-sm bg-white dark:bg-slate-900 group">
                    
                    <!-- Card Photo Banner -->
                    <div class="h-48 relative overflow-hidden bg-slate-200 dark:bg-slate-800">
                        <img src="<?= htmlspecialchars($roomPhotoUrl) ?>" alt="Foto Kamar <?= htmlspecialchars($r['room_number']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent"></div>
                        
                        <!-- Top Badges -->
                        <div class="absolute top-3 left-3 right-3 flex items-center justify-between z-10">
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider <?= $r['status'] === 'tersedia' ? 'badge-tersedia' : ($r['status'] === 'terisi' ? 'badge-terisi' : 'badge-perbaikan') ?>">
                                <i class="fa-solid fa-circle text-[7px] mr-1"></i> <?= ucfirst($r['status']) ?>
                            </span>
                            <span class="text-xs text-white bg-slate-900/80 backdrop-blur-md px-2.5 py-1 rounded-xl border border-white/20 shadow-sm font-semibold">
                                <i class="fa-solid fa-ruler-combined text-amber-400 mr-1"></i> <?= htmlspecialchars($r['size']) ?>
                            </span>
                        </div>

                        <!-- Bottom Banner Info -->
                        <div class="absolute bottom-3 left-4 right-4 z-10 text-white">
                            <div class="text-[11px] text-amber-300 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-hotel text-xs"></i> <?= htmlspecialchars(formatTitleCase($r['property_name'])) ?>
                            </div>
                            <div class="text-xl font-extrabold font-heading">Kamar <?= htmlspecialchars($r['room_number']) ?></div>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-5 space-y-4 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider"><?= htmlspecialchars(formatTitleCase($r['room_type'])) ?></span>
                                <span class="text-base font-extrabold text-emerald-600 dark:text-emerald-400 font-heading">
                                    <?= formatRupiah($r['price_monthly']) ?><span class="text-[10px] font-normal text-slate-500">/bln</span>
                                </span>
                            </div>

                            <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Fasilitas:</div>
                            <div class="flex flex-wrap gap-1.5">
                                <?php 
                                $facs = explode(',', $r['facilities'] ?? '');
                                foreach (array_slice($facs, 0, 3) as $f): 
                                    if (trim($f)):
                                ?>
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[11px] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        <i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400 text-[9px] mr-1"></i> <?= htmlspecialchars(formatTitleCase(trim($f))) ?>
                                    </span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-2">
                            <button onclick='openEditRoomModal(<?= json_encode($r) ?>)' class="flex-1 py-2 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-indigo-600 hover:text-white text-slate-700 dark:text-slate-300 text-xs font-bold transition-all flex items-center justify-center gap-1.5 border border-slate-200 dark:border-slate-700">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </button>
                            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Kamar <?= htmlspecialchars($r['room_number']) ?>?');" class="inline">
                                <input type="hidden" name="action" value="delete_room">
                                <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="redirect_tab" value="kamar">
                                <button type="submit" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-rose-600 hover:text-white text-rose-500 dark:text-rose-400 text-xs font-bold transition-all border border-slate-200 dark:border-slate-700" title="Hapus Kamar">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-3 text-center py-16 p-8 bg-white dark:bg-slate-900/80 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <i class="fa-solid fa-door-closed text-5xl text-slate-400 dark:text-slate-600 mb-3"></i>
                <h4 class="text-lg font-bold text-slate-900 dark:text-white">Belum Ada Kamar Terdaftar</h4>
                <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Tambahkan unit kamar ke dalam Rumah Kos Anda.</p>
                <button onclick="openAddRoomModal()" class="mt-4 px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold shadow-md">Tambah Kamar Sekarang</button>
            </div>
        <?php endif; ?>
    </div>

<!-- ================= TAB 2: DAFTAR RUMAH KOS & PROPERTI ================= -->
<?php else: ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($properties as $p): ?>
            <?php $propPhotoUrl = getPropertyImage($p['image'] ?? null, $p['type']); ?>
            <div class="glass-card rounded-3xl overflow-hidden border border-slate-200 dark:border-slate-800 flex flex-col justify-between shadow-sm bg-white dark:bg-slate-900 group">
                
                <!-- Property Photo Banner -->
                <div class="h-48 relative overflow-hidden bg-slate-200 dark:bg-slate-800">
                    <img src="<?= htmlspecialchars($propPhotoUrl) ?>" alt="Foto <?= htmlspecialchars(formatTitleCase($p['name'])) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent"></div>

                    <!-- Badges -->
                    <div class="absolute top-3 left-3 right-3 flex items-center justify-between z-10">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-500/90 text-slate-950 backdrop-blur-md shadow-sm">
                            <?= htmlspecialchars(formatTitleCase($p['type'])) ?>
                        </span>
                        <span class="text-xs text-white bg-slate-900/80 backdrop-blur-md px-2.5 py-1 rounded-xl border border-white/20 shadow-sm font-semibold">
                            <i class="fa-solid fa-location-dot text-amber-400 mr-1"></i> <?= htmlspecialchars(formatTitleCase($p['city'])) ?>
                        </span>
                    </div>

                    <div class="absolute bottom-3 left-4 right-4 z-10 text-white">
                        <h3 class="text-xl font-extrabold font-heading leading-tight"><?= htmlspecialchars(formatTitleCase($p['name'])) ?></h3>
                    </div>
                </div>

                <div class="p-5 space-y-4 flex-1 flex flex-col justify-between">
                    <div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed line-clamp-2"><?= htmlspecialchars($p['address']) ?></p>
                        
                        <!-- Rooms Summary Micro Badge -->
                        <div class="grid grid-cols-2 gap-3 mt-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <div class="p-2 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800">
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold">Total Kamar</div>
                                <div class="text-base font-extrabold text-slate-900 dark:text-white font-heading"><?= $p['total_rooms'] ?> Unit</div>
                            </div>
                            <div class="p-2 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800">
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold">Kamar Kosong</div>
                                <div class="text-base font-extrabold text-emerald-600 dark:text-emerald-400 font-heading"><?= $p['available_rooms'] ?> Unit</div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions: Button PENGATURAN & Edit -->
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2">
                        <button onclick='openManageRoomsModal(<?= json_encode($p) ?>)' class="flex-1 py-2.5 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold text-center transition-all flex items-center justify-center gap-1.5 shadow-md shadow-indigo-600/20">
                            <i class="fa-solid fa-sliders"></i>
                            <span>PENGATURAN</span>
                        </button>
                        <button onclick='openEditPropertyModal(<?= json_encode($p) ?>)' class="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold border border-slate-200 dark:border-slate-700" title="Edit Rumah Kos">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <?php if (count($properties) > 1): ?>
                            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Rumah Kos <?= htmlspecialchars($p['name']) ?> beserta seluruh kamarnya?');" class="inline">
                                <input type="hidden" name="action" value="delete_property">
                                <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-rose-600 hover:text-white text-rose-500 dark:text-rose-400 text-xs font-bold border border-slate-200 dark:border-slate-700" title="Hapus Rumah Kos">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<!-- ================= MODAL: PENGATURAN KAMAR RUMAH KOS ================= -->
<div id="manageRoomsModal" class="fixed inset-0 z-50 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl max-w-4xl w-full p-6 sm:p-8 shadow-2xl overflow-y-auto max-h-[90vh] space-y-6">
        
        <!-- Header Modal -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30 mb-1">
                    <i class="fa-solid fa-sliders"></i> Panel Pengaturan Kamar
                </div>
                <h3 id="manageModalPropTitle" class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Pengaturan Kamar Kos</h3>
                <p id="manageModalPropSubtitle" class="text-slate-500 dark:text-slate-400 text-xs mt-0.5">Kelola, tambah, edit, dan hapus unit kamar di rumah kos ini.</p>
            </div>
            
            <div class="flex items-center gap-2">
                <button id="btnQuickAddRoom" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-plus"></i> + Tambah Kamar di Rumah Ini
                </button>
                <button onclick="closeManageRoomsModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Table of Rooms in this Property -->
        <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                            <th class="py-3.5 px-4">Foto & No. Kamar</th>
                            <th class="py-3.5 px-4">Tipe & Ukuran</th>
                            <th class="py-3.5 px-4">Tarif Bulanan</th>
                            <th class="py-3.5 px-4">Fasilitas</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 text-right">Aksi Kamar</th>
                        </tr>
                    </thead>
                    <tbody id="manageModalTableBody" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-500">
            <span id="manageModalRoomsCount">0 Kamar Terdaftar</span>
            <button onclick="closeManageRoomsModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold">Tutup</button>
        </div>
    </div>
</div>

<!-- ================= MODAL 1: TAMBAH / EDIT RUMAH KOS ================= -->
<div id="propertyModal" class="fixed inset-0 z-50 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-5">
            <h3 id="propModalTitle" class="text-xl font-bold text-slate-900 dark:text-white font-heading">Tambah Rumah Kos Baru</h3>
            <button onclick="closePropertyModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" id="propFormAction" value="add_property">
            <input type="hidden" name="property_id" id="propFormId" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Nama Rumah Kos / Kontrakan *</label>
                <input type="text" name="name" id="inputPropName" required placeholder="Contoh: Kost Putri Melati / Kontrakan Harmoni" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-amber-500 focus:outline-none">
            </div>

            <!-- Upload Foto Rumah Kos -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                    <i class="fa-solid fa-camera text-amber-500 mr-1"></i> Foto Rumah Kos (Untuk Promosi)
                </label>
                <input type="file" name="image" accept="image/*" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2 px-3 text-xs text-slate-900 dark:text-white focus:outline-none file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-amber-500 file:text-slate-950 hover:file:bg-amber-400">
                <div class="text-[10px] text-slate-500 mt-1">Format: JPG, PNG, atau WEBP (Maksimal 5MB).</div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Jenis Properti</label>
                    <select name="type" id="inputPropType" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-amber-500 focus:outline-none">
                        <option value="kos_campur">Kos Campur</option>
                        <option value="kos_putra">Kos Putra</option>
                        <option value="kos_putri">Kos Putri</option>
                        <option value="kontrakan">Rumah Kontrakan</option>
                    </select>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Kota / Wilayah *</label>
                        <span class="text-[10px] text-amber-600 dark:text-amber-400"><i class="fa-solid fa-lightbulb mr-1"></i>Ketik untuk sugesti</span>
                    </div>
                    <input type="text" name="city" id="inputPropCity" list="citySuggestions" autocomplete="off" required placeholder="Pilih atau ketik kota..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-amber-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Alamat Lengkap Rumah Kos *</label>
                <input type="text" name="address" id="inputPropAddress" required placeholder="Jl. Melati No. 12, RT 01/RW 02" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-amber-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Deskripsi & Fasilitas Umum Rumah</label>
                <textarea name="description" id="inputPropDesc" rows="2" placeholder="Fasilitas dapur bersama, parkir motor/mobil, CCTV, dll." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-amber-500 focus:outline-none"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Aturan & Tata Tertib Rumah Kos</label>
                <textarea name="rules" id="inputPropRules" rows="2" placeholder="Tata tertib jam malam, larangan merokok, tamu menginap dll." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-amber-500 focus:outline-none"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-200 dark:border-slate-800">
                <button type="button" onclick="closePropertyModal()" class="py-2.5 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold">Batal</button>
                <button type="submit" class="py-2.5 px-6 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-md">Simpan Rumah Kos</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL 2: TAMBAH / EDIT KAMAR ================= -->
<div id="roomModal" class="fixed inset-0 z-50 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-5">
            <h3 id="roomModalTitle" class="text-xl font-bold text-slate-900 dark:text-white font-heading">Tambah Kamar Baru</h3>
            <button onclick="closeRoomModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" id="roomFormAction" value="add_room">
            <input type="hidden" name="room_id" id="roomFormId" value="">
            <input type="hidden" name="redirect_tab" id="roomRedirectTab" value="kamar">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Pilih Rumah Kos / Kontrakan *</label>
                <select name="property_id" id="inputRoomPropertyId" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                    <?php foreach ($properties as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $selectedPropId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['city']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Upload Foto Unit Kamar -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                    <i class="fa-solid fa-camera text-indigo-500 mr-1"></i> Foto Unit Kamar (Untuk Promosi)
                </label>
                <input type="file" name="image" accept="image/*" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2 px-3 text-xs text-slate-900 dark:text-white focus:outline-none file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500">
                <div class="text-[10px] text-slate-500 mt-1">Format: JPG, PNG, atau WEBP. Foto akan ditampilkan di katalog promosi kamar.</div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Nomor / Kode Kamar *</label>
                    <input type="text" name="room_number" id="inputRoomNumber" required placeholder="Contoh: A-01" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Tipe Kamar *</label>
                    <select name="room_type" id="inputRoomType" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                        <option value="Standard Room">Standard Room</option>
                        <option value="Premium Room">Premium Room</option>
                        <option value="Deluxe Room">Deluxe Room</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Tarif Bulanan (Rp) *</label>
                    <input type="number" name="price_monthly" id="inputPriceMonthly" required placeholder="Contoh: 1500000" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Tarif Tahunan (Rp) Opsional</label>
                    <input type="number" name="price_yearly" id="inputPriceYearly" placeholder="Contoh: 16000000" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Ukuran Kamar</label>
                    <input type="text" name="size" id="inputSize" placeholder="Contoh: 3x4 meter" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Status Kamar</label>
                    <select name="status" id="inputStatus" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                        <option value="tersedia">Tersedia (Kosong)</option>
                        <option value="terisi">Terisi</option>
                        <option value="perbaikan">Dalam Perbaikan</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Fasilitas Kamar (Pisahkan dengan koma)</label>
                <input type="text" name="facilities" id="inputFacilities" placeholder="AC, Kamar Mandi Dalam, Kasur Queen, WiFi, Lemari" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Keterangan / Catatan Tambahan</label>
                <textarea name="description" id="inputDescription" rows="2" placeholder="Catatan lantai, token listrik mandiri, view jendela dll." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-200 dark:border-slate-800">
                <button type="button" onclick="closeRoomModal()" class="py-2.5 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold">Batal</button>
                <button type="submit" class="py-2.5 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-md shadow-indigo-600/30">Simpan Data Kamar</button>
            </div>
        </form>
    </div>
</div>

<script>
    // All rooms in JSON
    const allRoomsData = <?= json_encode($allRooms) ?>;

    // Property Modal
    const propModal = document.getElementById('propertyModal');
    function openAddPropertyModal() {
        document.getElementById('propModalTitle').innerText = 'Tambah Rumah Kos / Kontrakan Baru';
        document.getElementById('propFormAction').value = 'add_property';
        document.getElementById('propFormId').value = '';
        document.getElementById('inputPropName').value = '';
        document.getElementById('inputPropType').value = 'kos_campur';
        document.getElementById('inputPropCity').value = 'Jakarta Selatan';
        document.getElementById('inputPropAddress').value = '';
        document.getElementById('inputPropDesc').value = '';
        document.getElementById('inputPropRules').value = '';
        propModal.classList.remove('hidden');
    }

    function openEditPropertyModal(prop) {
        document.getElementById('propModalTitle').innerText = 'Edit Rumah Kos ' + prop.name;
        document.getElementById('propFormAction').value = 'edit_property';
        document.getElementById('propFormId').value = prop.id;
        document.getElementById('inputPropName').value = prop.name;
        document.getElementById('inputPropType').value = prop.type;
        document.getElementById('inputPropCity').value = prop.city;
        document.getElementById('inputPropAddress').value = prop.address;
        document.getElementById('inputPropDesc').value = prop.description || '';
        document.getElementById('inputPropRules').value = prop.rules || '';
        propModal.classList.remove('hidden');
    }

    function closePropertyModal() {
        propModal.classList.add('hidden');
    }

    // Room Modal
    const roomModal = document.getElementById('roomModal');
    function openAddRoomModal(propertyId = null, redirectTab = 'kamar') {
        document.getElementById('roomModalTitle').innerText = 'Tambah Kamar Baru';
        document.getElementById('roomFormAction').value = 'add_room';
        document.getElementById('roomFormId').value = '';
        document.getElementById('roomRedirectTab').value = redirectTab;
        if (propertyId) {
            document.getElementById('inputRoomPropertyId').value = propertyId;
        }
        document.getElementById('inputRoomNumber').value = '';
        document.getElementById('inputRoomType').value = 'Standard Room';
        document.getElementById('inputPriceMonthly').value = '';
        document.getElementById('inputPriceYearly').value = '';
        document.getElementById('inputSize').value = '3x4 meter';
        document.getElementById('inputFacilities').value = 'AC, Kamar Mandi Dalam, Kasur, WiFi';
        document.getElementById('inputDescription').value = '';
        document.getElementById('inputStatus').value = 'tersedia';
        roomModal.classList.remove('hidden');
    }

    function openEditRoomModal(room, redirectTab = 'kamar') {
        document.getElementById('roomModalTitle').innerText = 'Edit Kamar ' + room.room_number;
        document.getElementById('roomFormAction').value = 'edit_room';
        document.getElementById('roomFormId').value = room.id;
        document.getElementById('roomRedirectTab').value = redirectTab;
        document.getElementById('inputRoomPropertyId').value = room.property_id;
        document.getElementById('inputRoomNumber').value = room.room_number;
        document.getElementById('inputRoomType').value = room.room_type;
        document.getElementById('inputPriceMonthly').value = room.price_monthly;
        document.getElementById('inputPriceYearly').value = room.price_yearly || '';
        document.getElementById('inputSize').value = room.size || '';
        document.getElementById('inputFacilities').value = room.facilities || '';
        document.getElementById('inputDescription').value = room.description || '';
        document.getElementById('inputStatus').value = room.status;
        roomModal.classList.remove('hidden');
    }

    function closeRoomModal() {
        roomModal.classList.add('hidden');
    }

    // ================= MANAGE ROOMS MODAL (PENGATURAN) =================
    const manageModal = document.getElementById('manageRoomsModal');
    let currentManagingProperty = null;

    function openManageRoomsModal(prop) {
        currentManagingProperty = prop;
        document.getElementById('manageModalPropTitle').innerText = 'Pengaturan Kamar - ' + prop.name;
        document.getElementById('manageModalPropSubtitle').innerText = prop.address + ' (' + prop.city + ')';

        // Setup Quick Add Button
        const btnAdd = document.getElementById('btnQuickAddRoom');
        btnAdd.onclick = function() {
            closeManageRoomsModal();
            openAddRoomModal(prop.id, 'rumah');
        };

        // Filter rooms belonging to this property
        const propRooms = allRoomsData.filter(r => parseInt(r.property_id) === parseInt(prop.id));
        document.getElementById('manageModalRoomsCount').innerText = propRooms.length + ' Kamar Terdaftar di ' + prop.name;

        const tbody = document.getElementById('manageModalTableBody');
        tbody.innerHTML = '';

        if (propRooms.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="py-8 text-center text-slate-400">
                        <i class="fa-solid fa-door-closed text-3xl mb-2"></i>
                        <div class="font-bold">Belum ada kamar di rumah kos ini.</div>
                        <p class="text-[11px] mt-1 text-slate-500">Klik tombol "+ Tambah Kamar di Rumah Ini" untuk membuat unit kamar pertama.</p>
                    </td>
                </tr>
            `;
        } else {
            propRooms.forEach((r, idx) => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors';

                const statusClass = r.status === 'tersedia' ? 'badge-tersedia' : (r.status === 'terisi' ? 'badge-terisi' : 'badge-perbaikan');
                const priceFormatted = 'Rp ' + parseInt(r.price_monthly).toLocaleString('id-ID');
                const roomThumb = r.image ? '../' + r.image : 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=150&auto=format&fit=crop&q=80';

                tr.innerHTML = `
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-3">
                            <img src="${roomThumb}" alt="Kamar" class="w-12 h-10 object-cover rounded-lg border border-slate-200 dark:border-slate-700 flex-shrink-0">
                            <div>
                                <div class="text-sm font-bold font-heading text-slate-900 dark:text-white">Kamar ${r.room_number}</div>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-4">
                        <div class="font-semibold text-slate-800 dark:text-slate-200">${r.room_type}</div>
                        <div class="text-[10px] text-slate-400">${r.size || '3x4 meter'}</div>
                    </td>
                    <td class="py-3 px-4 font-bold text-emerald-600 dark:text-emerald-400">
                        ${priceFormatted} <span class="text-[10px] font-normal text-slate-400">/bln</span>
                    </td>
                    <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                        <div class="truncate max-w-[140px]" title="${r.facilities || '-'}">${r.facilities || '-'}</div>
                    </td>
                    <td class="py-3 px-4">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${statusClass}">
                            ${r.status}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <button onclick='closeManageRoomsModal(); openEditRoomModal(${JSON.stringify(r)}, "rumah");' class="py-1 px-2.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-indigo-600 hover:text-white text-slate-700 dark:text-slate-300 text-[11px] font-bold transition-all border border-slate-200 dark:border-slate-700">
                                <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                            </button>
                            <form method="POST" onsubmit="return confirm('Hapus Kamar ${r.room_number}?');" class="inline">
                                <input type="hidden" name="action" value="delete_room">
                                <input type="hidden" name="room_id" value="${r.id}">
                                <input type="hidden" name="property_id" value="${prop.id}">
                                <input type="hidden" name="redirect_tab" value="rumah">
                                <button type="submit" class="p-1 px-2 rounded-lg bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-600 hover:text-white text-rose-600 dark:text-rose-400 text-[11px] font-bold transition-all border border-rose-200 dark:border-rose-500/30" title="Hapus Kamar">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        manageModal.classList.remove('hidden');
    }

    function closeManageRoomsModal() {
        manageModal.classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
