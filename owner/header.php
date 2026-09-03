<?php
// Owner Layout Header
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/subscription.php';
requireLogin('pemilik');

$user = currentUser();
$pdo = getDBConnection();
$currentPage = basename($_SERVER['PHP_SELF']);

// Count unverified payments and pending complaints
$unverifiedCount = 0;
$pendingComplaintsCount = 0;
$pendingSubCount = 0;
$subInfo = [
    'status' => 'trial',
    'is_active' => true,
    'days_remaining' => 14,
    'plan_name' => 'Free Trial 14 Hari'
];

if ($pdo) {
    try {
        $unverifiedCount = $pdo->query("SELECT COUNT(*) FROM bills WHERE status = 'menunggu_verifikasi'")->fetchColumn();
        $pendingComplaintsCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'menunggu'")->fetchColumn();
        $pendingSubCount = $pdo->query("SELECT COUNT(*) FROM subscription_orders WHERE status = 'menunggu_konfirmasi'")->fetchColumn();
        $subInfo = getOwnerSubscriptionInfo($user['id']);

        // Owner Avatar
        $stmtOwnerAvatar = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
        $stmtOwnerAvatar->execute([$user['id']]);
        $userAvatar = getUserAvatar($stmtOwnerAvatar->fetchColumn() ?: null, $user['name']);
    } catch (Exception $e) {}
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard Pemilik' ?> - LOCK & ROOM (L n' R)</title>
    
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
        window.LOCKROOM_USER_NAME = <?= json_encode($user['name'] ?? 'Pemilik Kos') ?>;
        window.LOCKROOM_USER_EMAIL = <?= json_encode($user['email'] ?? '') ?>;
        window.LOCKROOM_USER_ROLE = 'pemilik';
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
                        role: 'pemilik',
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
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex selection:bg-indigo-500 selection:text-white transition-colors duration-200">

    <!-- Mobile Backdrop -->
    <div id="mobileSidebarBackdrop" onclick="closeMobileMenu()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 hidden transition-opacity duration-300 md:hidden"></div>

    <!-- Sidebar Navigation (Desktop Fixed & Mobile Slide Drawer) -->
    <aside id="sidebarNav" class="w-72 md:w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col justify-between fixed h-full z-50 shadow-2xl md:shadow-sm transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0">
        <div>
            <!-- Brand Logo & Mobile Close Button -->
            <div class="h-20 flex items-center justify-between px-5 border-b border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-indigo-600 flex items-center justify-center shadow-md shadow-indigo-600/20 flex-shrink-0">
                        <i class="fa-solid fa-hotel text-white text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 whitespace-nowrap">
                            <span class="text-[15px] font-extrabold text-slate-900 dark:text-white font-heading tracking-tight">LOCK & ROOM</span>
                            <span class="px-1.5 py-0.5 rounded bg-amber-500/15 border border-amber-500/30 text-[10px] text-amber-600 dark:text-amber-400 font-extrabold font-mono whitespace-nowrap">L n' R</span>
                        </div>
                        <div class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold uppercase tracking-wider mt-0.5">Owner Portal</div>
                    </div>
                </div>

                <!-- Close Button (Mobile Only) -->
                <button onclick="closeMobileMenu()" type="button" class="md:hidden p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="Tutup Menu">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Nav Links -->
            <nav class="p-4 space-y-1.5 text-sm font-medium">
                <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $currentPage === 'index.php' ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <i class="fa-solid fa-gauge-high w-5 text-center"></i>
                    <span>Ringkasan</span>
                </a>

                <a href="rooms.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $currentPage === 'rooms.php' ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <i class="fa-solid fa-city w-5 text-center"></i>
                    <span>Kelola Rumah & Kamar</span>
                </a>

                <a href="tenants.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $currentPage === 'tenants.php' ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <i class="fa-solid fa-users w-5 text-center"></i>
                    <span>Data Penyewa</span>
                </a>

                <a href="bills.php" class="flex items-center justify-between px-4 py-3 rounded-xl transition-all <?= $currentPage === 'bills.php' ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-receipt w-5 text-center"></i>
                        <span>Tagihan & Verifikasi</span>
                    </div>
                    <?php if ($unverifiedCount > 0): ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500 text-slate-950"><?= $unverifiedCount ?></span>
                    <?php endif; ?>
                </a>

                <a href="complaints.php" class="flex items-center justify-between px-4 py-3 rounded-xl transition-all <?= $currentPage === 'complaints.php' ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-screwdriver-wrench w-5 text-center"></i>
                        <span>Pengaduan Fasilitas</span>
                    </div>
                    <?php if ($pendingComplaintsCount > 0): ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-500 text-white"><?= $pendingComplaintsCount ?></span>
                    <?php endif; ?>
                </a>

                <a href="broadcast.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $currentPage === 'broadcast.php' ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <i class="fa-solid fa-bullhorn w-5 text-center"></i>
                    <span>Broadcast Penghuni</span>
                </a>

                <a href="subscription.php" class="flex items-center justify-between px-4 py-3 rounded-xl transition-all <?= $currentPage === 'subscription.php' ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-gem w-5 text-center"></i>
                        <span>Paket Langganan</span>
                    </div>
                    <?php if ($subInfo['status'] === 'active'): ?>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-500 text-white">Aktif</span>
                    <?php elseif ($subInfo['status'] === 'trial'): ?>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-amber-500 text-white"><?= $subInfo['days_remaining'] ?>h Trial</span>
                    <?php else: ?>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-rose-500 text-white animate-pulse">Habis</span>
                    <?php endif; ?>
                </a>

                <a href="admin_subscriptions.php" class="flex items-center justify-between px-4 py-3 rounded-xl transition-all <?= $currentPage === 'admin_subscriptions.php' ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-shield-halved w-5 text-center"></i>
                        <span>Admin &amp; QRIS</span>
                    </div>
                    <?php if ($pendingSubCount > 0): ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500 text-white animate-pulse"><?= $pendingSubCount ?></span>
                    <?php endif; ?>
                </a>

                <a href="profile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $currentPage === 'profile.php' ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <i class="fa-solid fa-user-gear w-5 text-center"></i>
                    <span>Profil Saya</span>
                </a>
            </nav>
        </div>

        <!-- User Profile & Logout Bottom -->
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            <div class="p-3 bg-slate-100 dark:bg-slate-800/80 rounded-2xl border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <a href="profile.php" class="flex items-center gap-3 overflow-hidden group flex-1">
                    <div class="w-9 h-9 rounded-xl overflow-hidden border border-indigo-300 dark:border-indigo-500/40 flex-shrink-0 shadow-sm">
                        <img src="<?= htmlspecialchars($userAvatar ?? 'https://ui-avatars.com/api/?name=Pemilik') ?>" alt="<?= htmlspecialchars($user['name']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform">
                    </div>
                    <div class="truncate">
                        <div class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-indigo-600 transition-colors"><?= htmlspecialchars(formatTitleCase($user['name'])) ?></div>
                        <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold">Pemilik Kos</div>
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
                <a href="../index.php" class="text-[11px] text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-amber-400 transition-colors flex items-center justify-center gap-1">
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
                <div class="text-base sm:text-lg font-bold text-slate-900 dark:text-white font-heading truncate"><?= $pageTitle ?? 'Dashboard Pemilik' ?></div>
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

                <a href="bills.php" class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 relative transition-all border border-slate-200 dark:border-slate-700" title="Verifikasi Pembayaran">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unverifiedCount > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-amber-500 text-slate-950 text-[10px] font-extrabold flex items-center justify-center animate-pulse">
                            <?= $unverifiedCount ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="../index.php" target="_blank" class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold border border-slate-200 dark:border-slate-700 transition-all">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Preview Website
                </a>
            </div>
        </header>

        <!-- Dynamic View Content Container -->
        <main class="p-4 sm:p-6 flex-1 max-w-7xl w-full mx-auto space-y-6 pb-24 md:pb-8">

        <!-- Banner Status Trial & Langganan Pemilik -->
        <?php if ($subInfo['status'] === 'trial' && $currentPage !== 'subscription.php'): ?>
            <div class="p-3.5 sm:p-4 rounded-2xl <?= $subInfo['days_remaining'] <= 3 ? 'bg-amber-500 text-slate-950 shadow-amber-500/20' : 'bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 text-white shadow-indigo-900/20' ?> shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3 border <?= $subInfo['days_remaining'] <= 3 ? 'border-amber-400' : 'border-indigo-700/50' ?>">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl <?= $subInfo['days_remaining'] <= 3 ? 'bg-slate-950 text-amber-400' : 'bg-white/10 text-amber-400' ?> flex items-center justify-center text-sm flex-shrink-0">
                        <i class="fa-solid fa-clock-rotate-left animate-spin-slow"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold leading-tight">
                            Masa Uji Coba Gratis (Trial 14 Hari)
                        </div>
                        <div class="text-[11px] <?= $subInfo['days_remaining'] <= 3 ? 'text-slate-900 font-semibold' : 'text-slate-300' ?> mt-0.5">
                            Tersisa <strong><?= $subInfo['days_remaining'] ?> hari lagi</strong> (berakhir pada <?= formatDateIndo($subInfo['ends_at']) ?>).
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="subscription.php" class="px-3.5 py-1.5 rounded-xl <?= $subInfo['days_remaining'] <= 3 ? 'bg-slate-950 text-white hover:bg-slate-900' : 'bg-white text-indigo-900 hover:bg-slate-100' ?> text-xs font-extrabold shadow-sm transition-all flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fa-solid fa-qrcode text-amber-500"></i> Upgrade Paket Sekarang
                    </a>
                </div>
            </div>
        <?php elseif ($subInfo['status'] === 'expired' && $currentPage !== 'subscription.php' && $currentPage !== 'admin_subscriptions.php'): ?>
            <div class="p-4 rounded-2xl bg-rose-600 text-white shadow-lg shadow-rose-600/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border border-rose-500 animate-pulse">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-white/20 text-white flex items-center justify-center text-base flex-shrink-0">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold">Masa Uji Coba Anda Telah Berakhir</div>
                        <div class="text-[11px] text-rose-100 mt-0.5">Silakan pilih paket langganan untuk terus mengelola kamar, tagihan, dan pengumuman kos.</div>
                    </div>
                </div>
                <a href="subscription.php" class="px-4 py-2 rounded-xl bg-white text-rose-600 hover:bg-slate-100 text-xs font-extrabold shadow-sm transition-all flex items-center justify-center gap-1.5 whitespace-nowrap">
                    <i class="fa-solid fa-gem"></i> Aktifkan Langganan
                </a>
            </div>
        <?php endif; ?>

