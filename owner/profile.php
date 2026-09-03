<?php
// Top Level Authentication & Logic Execution
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/indonesia_cities.php';
requireLogin('pemilik');

$user = currentUser();
$pdo = getDBConnection();

// Fetch freshest user data
$stmtUser = $pdo->prepare("SELECT id, name, email, phone, city, avatar, role FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$user['id']]);
$userData = $stmtUser->fetch() ?: $user;

$uploadDirAvatars = __DIR__ . '/../assets/uploads/avatars/';
if (!is_dir($uploadDirAvatars)) {
    mkdir($uploadDirAvatars, 0777, true);
}

// Handle Form Submissions BEFORE loading header HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update Profile & Avatar
    if ($action === 'update_profile') {
        $name = formatTitleCase(sanitizeInput($_POST['name']));
        $phone = sanitizeInput($_POST['phone']);
        $city = sanitizeInput($_POST['city'] ?? 'Jakarta');
        $avatarPath = $userData['avatar'] ?? null;

        // Handle Avatar Upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDirAvatars . $filename)) {
                    $avatarPath = 'assets/uploads/avatars/' . $filename;
                }
            } else {
                setFlash('error', "Format foto harus berupa JPG, JPEG, PNG, atau WEBP!");
                header("Location: profile.php");
                exit;
            }
        }

        // Handle Remove Avatar
        if (!empty($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1') {
            $avatarPath = null;
        }

        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, city = ?, avatar = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $city, $avatarPath, $user['id']]);

        $_SESSION['user_name'] = $name;
        $_SESSION['user_phone'] = $phone;
        $_SESSION['user_avatar'] = $avatarPath;

        setFlash('success', "Data profil dan foto profil Pemilik berhasil diperbarui!");
        header("Location: profile.php");
        exit;
    }

    // 2. Change Password
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            setFlash('error', "Semua kolom kata sandi wajib diisi!");
        } elseif (strlen($newPassword) < 6) {
            setFlash('error', "Kata sandi baru minimal harus 6 karakter!");
        } elseif ($newPassword !== $confirmPassword) {
            setFlash('error', "Konfirmasi kata sandi baru tidak cocok!");
        } else {
            $stmtPass = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmtPass->execute([$user['id']]);
            $userRow = $stmtPass->fetch();

            if ($userRow && password_verify($currentPassword, $userRow['password'])) {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmtUpPass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtUpPass->execute([$newHash, $user['id']]);

                setFlash('success', "Kata sandi akun Pemilik berhasil diperbarui!");
            } else {
                setFlash('error', "Kata sandi saat ini yang Anda masukkan salah!");
            }
        }
        header("Location: profile.php");
        exit;
    }
}

$avatarUrl = getUserAvatar($userData['avatar'] ?? null, $userData['name']);

// Render HTML layout
$pageTitle = 'Profil & Foto Pemilik';
require_once __DIR__ . '/header.php';
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Profil & Foto Pemilik Kos</h2>
        <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Perbarui foto identitas pemilik, kelola nomor kontak, dan atur kata sandi akun Anda.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 max-w-5xl">
    
    <!-- Left Column: Profile & Avatar Edit Card -->
    <div class="lg:col-span-7 p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-6 shadow-sm">
        
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="remove_avatar" id="inputRemoveAvatar" value="0">

            <!-- Avatar Section with Click-to-Enlarge Lightbox -->
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800/80">
                
                <!-- Avatar Image Container with Zoom Badge & Click Handler -->
                <div class="relative group flex-shrink-0 cursor-pointer" onclick="openAvatarZoomModal('<?= htmlspecialchars($avatarUrl) ?>', '<?= htmlspecialchars(formatTitleCase($userData['name'])) ?>')" title="Klik untuk memperbesar foto">
                    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-3xl overflow-hidden border-4 border-white dark:border-slate-800 shadow-xl bg-slate-200 dark:bg-slate-800 relative transition-transform duration-300 group-hover:scale-105">
                        <img id="avatarPreviewImg" src="<?= htmlspecialchars($avatarUrl) ?>" alt="Foto Profil <?= htmlspecialchars(formatTitleCase($userData['name'])) ?>" class="w-full h-full object-cover">
                        
                        <!-- Hover Overlay to Hint Zoom -->
                        <div class="absolute inset-0 bg-slate-950/40 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center text-white">
                            <i class="fa-solid fa-magnifying-glass-plus text-lg"></i>
                            <span class="text-[9px] font-bold mt-1">Perbesar</span>
                        </div>
                    </div>

                    <!-- Enlarge Badge Indicator -->
                    <span class="absolute -bottom-1 -right-1 w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs shadow-md border-2 border-white dark:border-slate-900 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-expand text-[10px]"></i>
                    </span>
                </div>

                <!-- Avatar Upload Controls -->
                <div class="flex-1 space-y-2 text-center sm:text-left">
                    <h4 class="text-base font-bold text-slate-900 dark:text-white font-heading">Foto Profil Pemilik</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Format didukung: <strong>JPG, PNG, atau WEBP</strong> (Maks. 5 MB). Klik pada foto untuk melihat ukuran penuh.
                    </p>

                    <div class="pt-2 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                        <label for="avatarFileInput" class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-sm transition-all cursor-pointer flex items-center gap-1.5">
                            <i class="fa-solid fa-camera"></i>
                            <span>Pilih Foto Baru</span>
                        </label>
                        <input type="file" name="avatar" id="avatarFileInput" accept="image/png, image/jpeg, image/jpg, image/webp" class="hidden" onchange="previewAvatar(this)">

                        <?php if (!empty($userData['avatar'])): ?>
                            <button type="button" onclick="removeAvatarNow()" class="px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-rose-600 hover:text-white text-rose-500 dark:text-rose-400 font-bold text-xs border border-slate-200 dark:border-slate-700 transition-all flex items-center gap-1">
                                <i class="fa-solid fa-trash-can text-[11px]"></i>
                                <span>Hapus Foto</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Info Fields -->
            <div class="space-y-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Nama Lengkap Pemilik</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($userData['name']) ?>" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-3 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Alamat Email (Akun Login)</label>
                    <input type="email" value="<?= htmlspecialchars($userData['email']) ?>" disabled class="w-full bg-slate-100 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 rounded-xl py-3 px-3.5 text-slate-400 text-sm cursor-not-allowed font-mono">
                    <p class="text-[11px] text-slate-400 mt-1">Email digunakan sebagai identitas akun login pemilik dan tidak dapat diubah sendiri.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Nomor WhatsApp / HP Aktif</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" required placeholder="0812xxxxxxxx" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-3 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Kota Lokasi Kos / Domisili</label>
                    <div class="relative">
                        <i class="fa-solid fa-location-dot absolute left-4 top-1/2 -translate-y-1/2 text-indigo-500"></i>
                        <input type="text" name="city" list="citiesProfileList" value="<?= htmlspecialchars($userData['city'] ?? 'Jakarta') ?>" required placeholder="Contoh: Jakarta Selatan, Bandung, Surabaya..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-3 pl-11 pr-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none transition-colors">
                    </div>
                </div>

                <!-- Complete Datalist for All Cities in Indonesia -->
                <?= renderCityDatalist('citiesProfileList') ?>

                <button type="submit" class="w-full py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-600/30 transition-all flex items-center justify-center gap-2 mt-4">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan Profil & Foto
                </button>
            </div>

        </form>

    </div>

    <!-- Right Column: Change Password Card -->
    <div class="lg:col-span-5 p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-6 shadow-sm h-fit">
        
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-600/20 border border-indigo-200 dark:border-indigo-500/30 flex items-center justify-center text-2xl text-indigo-600 dark:text-indigo-400 font-bold flex-shrink-0">
                <i class="fa-solid fa-key"></i>
            </div>
            <div>
                <h3 class="text-xl font-bold text-slate-900 dark:text-white font-heading">Ganti Kata Sandi</h3>
                <div class="text-xs text-slate-500 dark:text-slate-400">Amankan akun pemilik kos Anda</div>
            </div>
        </div>

        <form method="POST" class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
            <input type="hidden" name="action" value="change_password">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Kata Sandi Saat Ini *</label>
                <input type="password" name="current_password" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none transition-colors">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Kata Sandi Baru *</label>
                <input type="password" name="new_password" required placeholder="Minimal 6 karakter" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none transition-colors">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Konfirmasi Kata Sandi Baru *</label>
                <input type="password" name="confirm_password" required placeholder="Ulangi kata sandi baru" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none transition-colors">
            </div>

            <button type="submit" class="w-full py-3.5 rounded-xl bg-slate-800 hover:bg-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2 border border-slate-700 mt-2">
                <i class="fa-solid fa-lock"></i> Perbarui Kata Sandi
            </button>
        </form>

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
            <span class="text-xs text-slate-500">Tampilan Resolusi Penuh</span>
            <button onclick="closeAvatarZoomModal()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-sm">
                Tutup
            </button>
        </div>

    </div>
</div>

<script>
// Client-side Instant Image Preview
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran file terlalu besar! Maksimal 5 MB.');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreviewImg').src = e.target.result;
            document.getElementById('inputRemoveAvatar').value = '0';
        };
        reader.readAsDataURL(file);
    }
}

// Remove Avatar Handler
function removeAvatarNow() {
    if (confirm('Apakah Anda yakin ingin menghapus foto profil ini?')) {
        document.getElementById('inputRemoveAvatar').value = '1';
        document.getElementById('avatarFileInput').value = '';
        document.getElementById('avatarPreviewImg').src = 'https://ui-avatars.com/api/?name=<?= urlencode($userData['name']) ?>&background=4f46e5&color=fff&size=256&bold=true';
    }
}

// Open Avatar Zoom Modal
function openAvatarZoomModal(imageUrl, userName) {
    const modal = document.getElementById('avatarZoomModal');
    const zoomImg = document.getElementById('avatarZoomImage');
    const zoomTitle = document.getElementById('avatarZoomTitle');

    const currentSrc = document.getElementById('avatarPreviewImg').src;
    zoomImg.src = currentSrc || imageUrl;
    zoomTitle.innerText = 'Foto Profil: ' + userName;

    modal.classList.remove('hidden');
}

// Close Avatar Zoom Modal
function closeAvatarZoomModal() {
    const modal = document.getElementById('avatarZoomModal');
    modal.classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
