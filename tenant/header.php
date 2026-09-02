<?php
// Tenant Layout Header
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../helpers/auth.php';
requireLogin('penyewa');

$user = currentUser();
$pdo = getDBConnection();
$currentPage = basename($_SERVER['PHP_SELF']);

// Fetch tenant's active lease and room
$activeLease = null;
$unpaidBillsCount = 0;

if ($pdo) {
    try {
        $stmtLease = $pdo->prepare("SELECT l.*, r.room_number, r.room_type, r.facilities, r.price_monthly, r.image as room_image, p.name as property_name, p.address as property_address, p.type as property_type, p.image as property_image, u.name as owner_name, u.phone as owner_phone 
                                    FROM leases l 
                                    JOIN rooms r ON l.room_id = r.id 
                                    JOIN properties p ON r.property_id = p.id 
                                    JOIN users u ON p.owner_id = u.id 
                                    WHERE l.tenant_id = ? AND l.status = 'aktif' 
                                    LIMIT 1");
        $stmtLease->execute([$user['id']]);
        $activeLease = $stmtLease->fetch();

        // Unpaid bills count
        $stmtUnpaid = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE tenant_id = ? AND status = 'belum_bayar'");
        $stmtUnpaid->execute([$user['id']]);
        $unpaidBillsCount = $stmtUnpaid->fetchColumn();

        // User Avatar
        $stmtUserAvatar = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
        $stmtUserAvatar->execute([$user['id']]);
        $userAvatar = getUserAvatar($stmtUserAvatar->fetchColumn() ?: null, $user['name']);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Portal Penyewa' ?> - LOCK & ROOM (L n' R)</title>
    
    <!-- Theme Switcher Init (Prevents Flash) -->
    <script src="../assets/js/theme.js"></script>

    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            amber: '#f59e0b'
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
    <script>
        window.LOCKROOM_USER_ID = <?= json_encode($user['id']) ?>;
        window.LOCKROOM_USER_NAME = <?= json_encode($user['name'] ?? 'Penyewa') ?>;
        window.LOCKROOM_USER_EMAIL = <?= json_encode($user['email'] ?? '') ?>;
        window.LOCKROOM_USER_ROLE = 'penyewa';
        window.ONESIGNAL_APP_ID = <?= json_encode(defined('ONESIGNAL_APP_ID') ? ONESIGNAL_APP_ID : '') ?>;

        // Initialize OneSignal Web Push
        if (window.ONESIGNAL_APP_ID && window.ONESIGNAL_APP_ID !== 'YOUR_ONESIGNAL_APP_ID') {
            window.OneSignalDeferred = window.OneSignalDeferred || [];
            OneSignalDeferred.push(async function(OneSignal) {
                await OneSignal.init({
                    appId: window.ONESIGNAL_APP_ID,
                    allowLocalhostAsSecureOrigin: true,
                    notifyButton: {
                        enable: false
                    }
                });
                if (window.LOCKROOM_USER_ID) {
                    await OneSignal.login(String(window.LOCKROOM_USER_ID));
                    await OneSignal.User.addTags({
                        role: 'penyewa',
                        email: window.LOCKROOM_USER_EMAIL,
                        name: window.LOCKROOM_USER_NAME
                    });
                }
            });
        }
    </script>
    <script src="../assets/js/notifications.js"></script>
    <script src="../assets/js/app_lock.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex selection:bg-emerald-500 selection:text-white transition-colors duration-200">

    <!-- Mobile Backdrop -->
    <div id="mobileSidebarBackdrop" onclick="closeMobileMenu()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 hidden transition-opacity duration-300 md:hidden"></div>

    <!-- Sidebar Navigation (Desktop Fixed & Mobile Slide Drawer) -->
    <aside id="sidebarNav" class="w-72 md:w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col justify-between fixed h-full z-50 shadow-2xl md:shadow-sm transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0">
        <div>
            <!-- Brand Logo & Mobile Close Button -->
            <div class="h-20 flex items-center justify-between px-5 border-b border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-emerald-600 flex items-center justify-center shadow-md shadow-emerald-600/20 flex-shrink-0">
                        <i class="fa-solid fa-house-user text-white text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 whitespace-nowrap">
                            <span class="text-[15px] font-extrabold text-slate-900 dark:text-white font-heading tracking-tight">LOCK & ROOM</span>
                            <span class="px-1.5 py-0.5 rounded bg-amber-500/15 border border-amber-500/30 text-[10px] text-amber-600 dark:text-amber-400 font-extrabold font-mono whitespace-nowrap">L n' R</span>
                        </div>
                        <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold uppercase tracking-wider mt-0.5">Tenant Portal</div>
                    </div>
                </div>

                <!-- Close Button (Mobile Only) -->
                <button onclick="closeMobileMenu()" type="button" class="md:hidden p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="Tutup Menu">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Nav Links -->
            <nav class="p-4 space-y-1.5 text-sm font-medium">
                <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $currentPage === 'index.php' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <i class="fa-solid fa-gauge-high w-5 text-center"></i>
                    <span>Dashboard Saya</span>
                </a>

                <a href="my-room.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $currentPage === 'my-room.php' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <i class="fa-solid fa-bed w-5 text-center"></i>
                    <span>Kamar & Fasilitas</span>
                </a>

                <a href="bills.php" class="flex items-center justify-between px-4 py-3 rounded-xl transition-all <?= $currentPage === 'bills.php' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-file-invoice-dollar w-5 text-center"></i>
                        <span>Tagihan & Bayar</span>
                    </div>
                    <?php if ($unpaidBillsCount > 0): ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-500 text-white animate-pulse"><?= $unpaidBillsCount ?></span>
                    <?php endif; ?>
                </a>

                <a href="complaints.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $currentPage === 'complaints.php' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <i class="fa-solid fa-screwdriver-wrench w-5 text-center"></i>
                    <span>Pengaduan Fasilitas</span>
                </a>

                <a href="profile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $currentPage === 'profile.php' ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <i class="fa-solid fa-user-gear w-5 text-center"></i>
                    <span>Profil Saya</span>
                </a>
            </nav>
        </div>

        <!-- User Profile & Logout Bottom -->
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            <div class="p-3 bg-slate-100 dark:bg-slate-800/80 rounded-2xl border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <a href="profile.php" class="flex items-center gap-3 overflow-hidden group flex-1">
                    <div class="w-9 h-9 rounded-xl overflow-hidden border border-emerald-300 dark:border-emerald-500/40 flex-shrink-0 shadow-sm">
                        <img src="<?= htmlspecialchars($userAvatar ?? 'https://ui-avatars.com/api/?name=Penyewa') ?>" alt="<?= htmlspecialchars($user['name']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform">
                    </div>
                    <div class="truncate">
                        <div class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-emerald-600 transition-colors"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">
                            <?= $activeLease ? 'Kamar ' . htmlspecialchars($activeLease['room_number']) : 'Penyewa Kos' ?>
                        </div>
                    </div>
                </a>
                <div class="flex items-center gap-1">
                    <button onclick="lockAppNow()" type="button" class="p-2 text-slate-400 hover:text-amber-500 transition-colors" title="Kunci Aplikasi">
                        <i class="fa-solid fa-lock"></i>
                    </button>
                    <a href="../auth/logout.php" class="p-2 text-slate-400 hover:text-rose-500 transition-colors" title="Keluar">
                        <i class="fa-solid fa-power-off"></i>
                    </a>
                </div>
            </div>
            <div class="mt-2 text-center">
                <a href="../index.php" class="text-[11px] text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-amber-400 transition-colors flex items-center justify-center gap-1">
                    <i class="fa-solid fa-globe"></i> Lihat Website Publik
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Wrapper Content -->
    <div class="flex-1 md:ml-64 flex flex-col min-h-screen">
        
        <!-- Top Navbar -->
        <header class="h-20 bg-white/80 dark:bg-slate-900/60 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 flex items-center justify-between sticky top-0 z-20 transition-colors duration-200">
            <div class="flex items-center gap-3 min-w-0">
                <!-- Hamburger Menu Button (Mobile Only) -->
                <button onclick="openMobileMenu()" type="button" class="md:hidden p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition-all flex items-center justify-center flex-shrink-0" aria-label="Buka Menu Navigasi">
                    <i class="fa-solid fa-bars-staggered text-base"></i>
                </button>
                <div class="text-base sm:text-lg font-bold text-slate-900 dark:text-white font-heading truncate"><?= $pageTitle ?? 'Portal Penyewa' ?></div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Theme Switcher Button -->
                <button onclick="toggleTheme()" type="button" class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-amber-400 border border-slate-200 dark:border-slate-700 transition-all flex items-center justify-center" title="Ubah Mode Tampilan (Dark / Light)">
                    <i class="fa-solid fa-moon text-sm theme-toggle-icon"></i>
                </button>

                <!-- Lock App Button -->
                <button onclick="lockAppNow()" type="button" class="p-2.5 rounded-xl bg-slate-100 hover:bg-amber-50 dark:hover:bg-amber-500/20 text-slate-700 dark:text-slate-300 hover:text-amber-600 dark:hover:text-amber-400 border border-slate-200 dark:border-slate-700 transition-all flex items-center justify-center gap-1.5 text-xs font-bold" title="Kunci Aplikasi Sekarang">
                    <i class="fa-solid fa-lock text-amber-500"></i>
                    <span class="hidden sm:inline">Kunci</span>
                </button>

                <?php if ($activeLease && !empty($activeLease['owner_phone'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $activeLease['owner_phone']) ?>" target="_blank" class="flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-bold border border-emerald-200 dark:border-emerald-500/20 transition-all">
                        <i class="fa-brands fa-whatsapp text-base"></i> <span class="hidden xs:inline">Hubungi</span> Pemilik
                    </a>
                <?php endif; ?>

                <a href="../index.php" target="_blank" class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold border border-slate-200 dark:border-slate-700 transition-all">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Beranda Publik
                </a>
            </div>
        </header>

        <!-- Dynamic View Content Container -->
        <main class="p-4 sm:p-6 flex-1 max-w-7xl w-full mx-auto space-y-6 pb-24 md:pb-8">
