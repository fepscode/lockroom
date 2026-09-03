<?php
// Landing Page Utama - LOCK & ROOM (L n' R)
require_once __DIR__ . '/helpers/auth.php';

$pdo = getDBConnection();
$properties = [];
$rooms = [];
$stats = [
    'total_rooms' => 0,
    'available_rooms' => 0,
    'total_properties' => 0,
    'total_tenants' => 0
];

if ($pdo) {
    try {
        // Fetch properties with room count and min price for promotion
        $stmtProps = $pdo->query("SELECT p.*, 
                                  (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.id) as total_rooms,
                                  (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.id AND r.status = 'tersedia') as available_rooms,
                                  (SELECT MIN(r.price_monthly) FROM rooms r WHERE r.property_id = p.id) as min_price
                                  FROM properties p 
                                  ORDER BY p.id ASC LIMIT 6");
        $properties = $stmtProps->fetchAll();

        // Fetch rooms for promotion catalog
        $stmtRooms = $pdo->query("SELECT r.*, p.name as property_name, p.city, p.type as property_type, u.phone as owner_phone, u.name as owner_name 
                                  FROM rooms r 
                                  JOIN properties p ON r.property_id = p.id 
                                  JOIN users u ON p.owner_id = u.id
                                  ORDER BY r.status ASC, r.id ASC LIMIT 9");
        $rooms = $stmtRooms->fetchAll();

        // Count stats
        $stats['total_rooms'] = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
        $stats['available_rooms'] = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'tersedia'")->fetchColumn();
        $stats['total_properties'] = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
        $stats['total_tenants'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'penyewa'")->fetchColumn();
    } catch (Exception $e) {
        // Table may not be seeded yet
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOCK & ROOM (L n' R) - Solusi Cerdas Manajemen Kos & Kontrakan</title>
    <meta name="description" content="Aplikasi pengelolaan rumah kos dan kontrakan modern LOCK & ROOM (L n' R). Promosi foto rumah kos dan unit kamar terbaik.">
    
    <!-- Theme Switcher Init (Prevents Flash) -->
    <script src="assets/js/theme.js"></script>

    <!-- Google Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Tailwind CSS CDN with Dark Mode support -->
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
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="assets/images/icons/icon-192.png">
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js').catch(() => {});
        });
    }
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 selection:bg-indigo-500 selection:text-white antialiased transition-colors duration-200">

    <!-- Main Navigation Header -->
    <header class="sticky top-0 z-40 bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl border-b border-slate-200 dark:border-slate-800 transition-all shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <!-- Logo & Brand -->
                <a href="index.php" class="flex items-center gap-3 group">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-amber-500 via-indigo-600 to-indigo-700 flex items-center justify-center shadow-md shadow-indigo-600/20 group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-hotel text-white text-lg"></i>
                    </div>
                    <div>
                        <div class="text-xl sm:text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white font-heading leading-none flex items-center gap-1.5">
                            LOCK & ROOM <span class="px-2 py-0.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-xs font-bold font-mono">L n' R</span>
                        </div>
                        <div class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 font-medium tracking-wider mt-1">Smart Boarding House & Rental Hub</div>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden lg:flex items-center gap-1 bg-slate-100/90 dark:bg-slate-950/60 p-1.5 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    <a href="#beranda" class="px-3 py-2 rounded-xl hover:text-indigo-600 dark:hover:text-white hover:bg-white dark:hover:bg-slate-800/80 transition-all">Beranda</a>
                    <a href="#informasi" class="px-3 py-2 rounded-xl hover:text-indigo-600 dark:hover:text-white hover:bg-white dark:hover:bg-slate-800/80 transition-all">Informasi</a>
                    <a href="#tatacara" class="px-3 py-2 rounded-xl hover:text-indigo-600 dark:hover:text-white hover:bg-white dark:hover:bg-slate-800/80 transition-all">Tata Cara</a>
                    <a href="#rumah-kos" class="px-3 py-2 rounded-xl hover:text-indigo-600 dark:hover:text-white hover:bg-white dark:hover:bg-slate-800/80 transition-all text-amber-600 dark:text-amber-400 font-bold">Rumah Kos</a>
                    <a href="#kamar" class="px-3 py-2 rounded-xl hover:text-indigo-600 dark:hover:text-white hover:bg-white dark:hover:bg-slate-800/80 transition-all">Katalog Kamar</a>
                    <a href="#kontak" class="px-3 py-2 rounded-xl hover:text-indigo-600 dark:hover:text-white hover:bg-white dark:hover:bg-slate-800/80 transition-all">Bantuan</a>
                </nav>

                <!-- Auth Action Buttons & Theme Switcher -->
                <div class="hidden sm:flex items-center gap-2.5">
                    
                    <!-- Theme Mode Toggle Switch Button -->
                    <button onclick="toggleTheme()" type="button" class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-amber-400 border border-slate-200 dark:border-slate-700 transition-all flex items-center justify-center" title="Ubah Mode Tampilan (Dark / Light)">
                        <i class="fa-solid fa-moon text-sm theme-toggle-icon"></i>
                    </button>

                    <?php if (isLoggedIn()): ?>
                        <?php if ($_SESSION['user_role'] === 'pemilik'): ?>
                            <a href="owner/index.php" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-600/30 flex items-center gap-2 transition-all">
                                <i class="fa-solid fa-gauge-high"></i> Dashboard Pemilik
                            </a>
                        <?php else: ?>
                            <a href="tenant/index.php" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/30 flex items-center gap-2 transition-all">
                                <i class="fa-solid fa-gauge-high"></i> Dashboard Penyewa
                            </a>
                        <?php endif; ?>
                        <a href="auth/logout.php" class="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-600/20 text-slate-600 dark:text-slate-300 hover:text-rose-600 dark:hover:text-rose-400 text-xs transition-all" title="Logout">
                            <i class="fa-solid fa-power-off"></i>
                        </a>
                    <?php else: ?>
                        <!-- Login Pemilik Dropdown -->
                        <div class="relative group">
                            <button class="px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800/90 hover:bg-slate-200 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 text-indigo-700 dark:text-indigo-300 font-bold text-xs flex items-center gap-2 transition-all">
                                <i class="fa-solid fa-user-tie text-indigo-600 dark:text-indigo-400"></i>
                                <span>PEMILIK</span>
                                <i class="fa-solid fa-chevron-down text-[9px] text-slate-400"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-52 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl p-1.5 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 z-50">
                                <a href="auth/login.php?role=pemilik" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-indigo-600 hover:text-indigo-600 dark:hover:text-white transition-all">
                                    <i class="fa-solid fa-right-to-bracket text-indigo-500"></i> Login Pemilik
                                </a>
                                <a href="auth/register.php" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-indigo-600 hover:text-indigo-600 dark:hover:text-white transition-all">
                                    <i class="fa-brands fa-google text-rose-500"></i> Daftar Akun Pemilik
                                </a>
                            </div>
                        </div>

                        <!-- Login Penyewa Direct Button -->
                        <a href="auth/login.php?role=penyewa" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20 flex items-center gap-2 transition-all">
                            <i class="fa-solid fa-house-user"></i>
                            <span>LOGIN PENYEWA</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Header Right Actions (Theme + Hamburger) -->
                <div class="flex sm:hidden items-center gap-2">
                    <button onclick="toggleTheme()" type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-amber-400 border border-slate-200 dark:border-slate-700">
                        <i class="fa-solid fa-moon text-sm theme-toggle-icon"></i>
                    </button>
                    <button id="mobileMenuBtn" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        <i class="fa-solid fa-bars text-base"></i>
                    </button>
                </div>

            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobileMenu" class="hidden lg:hidden border-t border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-900/98 backdrop-blur-xl px-4 py-4 space-y-2">
            <a href="#beranda" class="block py-2 px-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 font-medium text-xs">Beranda</a>
            <a href="#informasi" class="block py-2 px-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 font-medium text-xs">Informasi Aplikasi</a>
            <a href="#tatacara" class="block py-2 px-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 font-medium text-xs">Tata Cara Penggunaan</a>
            <a href="#rumah-kos" class="block py-2 px-3 rounded-lg text-amber-600 dark:text-amber-400 font-bold text-xs">Rumah Kos & Kontrakan</a>
            <a href="#kamar" class="block py-2 px-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 font-medium text-xs">Katalog Kamar</a>
            <a href="#kontak" class="block py-2 px-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 font-medium text-xs">Bantuan</a>
            
            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 grid grid-cols-2 gap-2">
                <a href="auth/login.php?role=pemilik" class="py-2.5 px-3 text-center rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold text-indigo-700 dark:text-indigo-300">
                    <i class="fa-solid fa-user-tie mr-1"></i> Login Pemilik
                </a>
                <a href="auth/login.php?role=penyewa" class="py-2.5 px-3 text-center rounded-xl bg-emerald-600 text-xs font-bold text-white shadow-sm">
                    <i class="fa-solid fa-house-user mr-1"></i> Login Penyewa
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="beranda" class="relative pt-12 pb-20 overflow-hidden">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[700px] h-[500px] bg-gradient-to-tr from-indigo-500/15 via-purple-500/10 to-amber-500/15 dark:from-indigo-600/20 dark:via-purple-600/15 dark:to-amber-500/20 blur-[130px] pointer-events-none rounded-full"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                <!-- Left Column: Copywriting -->
                <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-300 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                        Aplikasi Manajemen Properti & Kamar Terintegrasi
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 dark:text-white tracking-tight font-heading leading-tight">
                        Temukan & Kelola Kos Idaman di <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-500 via-indigo-600 to-indigo-800 dark:from-amber-400 dark:via-indigo-300 dark:to-indigo-500">LOCK & ROOM</span>
                    </h1>

                    <p class="text-base sm:text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                        Platform pintar <strong>(L n' R)</strong> dengan foto nyata unit kamar dan rumah kos. Memudahkan <strong>Pemilik Kos</strong> mendaftarkan identitas penghuni, menetapkan kamar, dan memberikan kenyamanan hunian modern bagi <strong>Penyewa</strong>.
                    </p>

                    <!-- Call to Action Buttons -->
                    <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2">
                        <a href="auth/login.php?role=pemilik" class="px-6 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-xl shadow-indigo-600/25 flex items-center gap-2.5 transition-all transform hover:-translate-y-0.5">
                            <i class="fa-solid fa-user-tie text-amber-300"></i> Portal Pemilik
                        </a>
                        <a href="auth/login.php?role=penyewa" class="px-6 py-3.5 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-sm shadow-xl shadow-emerald-600/25 flex items-center gap-2.5 transition-all transform hover:-translate-y-0.5">
                            <i class="fa-solid fa-house-user"></i> Portal Penyewa
                        </a>
                        <a href="#rumah-kos" class="px-5 py-3.5 rounded-2xl bg-white dark:bg-slate-900/90 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-sm flex items-center gap-2 transition-all shadow-sm">
                            <i class="fa-solid fa-images text-amber-500"></i> Lihat Galeri Kos
                        </a>
                    </div>

                    <!-- Micro Highlights -->
                    <div class="pt-6 grid grid-cols-3 gap-4 border-t border-slate-200 dark:border-slate-800/80 text-left">
                        <div class="p-3 bg-white dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                            <div class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white font-heading"><?= $stats['total_rooms'] ?>+</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Kamar</div>
                        </div>
                        <div class="p-3 bg-white dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                            <div class="text-xl sm:text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 font-heading"><?= $stats['available_rooms'] ?></div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Kamar Tersedia</div>
                        </div>
                        <div class="p-3 bg-white dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                            <div class="text-xl sm:text-2xl font-extrabold text-amber-500 dark:text-amber-400 font-heading"><?= $stats['total_properties'] ?></div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Rumah Kos Aktif</div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Interactive Role Selection Cards -->
                <div class="lg:col-span-5 space-y-5">
                    <!-- Card Pemilik Kos -->
                    <div class="glass-card rounded-3xl p-6 relative overflow-hidden border border-indigo-200 dark:border-indigo-500/30 group">
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-600/20 border border-indigo-200 dark:border-indigo-500/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-2xl flex-shrink-0">
                                <i class="fa-solid fa-building-user"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Untuk Pemilik Kos</span>
                                    <span class="px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 text-[11px] font-semibold">Owner Hub</span>
                                </div>
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white font-heading mt-1">Kelola Rumah & Kamar Kos</h2>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed">
                                    Upload foto kamar, daftarkan penyewa, pilih kamar, dan kelola keuangan kos secara terpusat.
                                </p>
                                <div class="mt-4 flex items-center gap-2">
                                    <a href="auth/login.php?role=pemilik" class="flex-1 py-2 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs text-center transition-all shadow-sm">
                                        <i class="fa-solid fa-right-to-bracket mr-1"></i> Login Pemilik
                                    </a>
                                    <a href="auth/register.php" class="py-2 px-3.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold text-center border border-slate-200 dark:border-slate-700 transition-all">
                                        Daftar Baru
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Penyewa Kos -->
                    <div class="glass-card rounded-3xl p-6 relative overflow-hidden border border-emerald-200 dark:border-emerald-500/30 group">
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-600/20 border border-emerald-200 dark:border-emerald-500/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-2xl flex-shrink-0">
                                <i class="fa-solid fa-key"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Untuk Penyewa</span>
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-[11px] font-semibold">Tenant Hub</span>
                                </div>
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white font-heading mt-1">Portal Penghuni Kos</h2>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed">
                                    Gunakan akun yang diberikan pemilik kos untuk melihat tagihan, konfirmasi pembayaran, dan lapor kendala fasilitas.
                                </p>
                                <div class="mt-4 flex items-center gap-2">
                                    <a href="auth/login.php?role=penyewa" class="flex-1 py-2 px-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs text-center transition-all shadow-sm">
                                        <i class="fa-solid fa-right-to-bracket mr-1"></i> Login Penyewa
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>

    <!-- Section: Promosi Rumah Kos & Kontrakan Unggulan -->
    <section id="rumah-kos" class="py-20 bg-slate-100/70 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 text-xs font-bold uppercase tracking-wider mb-3 border border-amber-500/20">
                        <i class="fa-solid fa-hotel"></i> Galeri Rumah Kos Pilihan
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white font-heading">
                        Rumah Kos & Kontrakan Terdaftar
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm mt-2">Daftar hunian kos eksklusif dengan fasilitas lengkap di berbagai kota.</p>
                </div>
            </div>

            <!-- Properties Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (!empty($properties)): ?>
                    <?php foreach ($properties as $prop): ?>
                        <?php $propPhoto = getPropertyImage($prop['image'] ?? null, $prop['type']); ?>
                        <div class="glass-card rounded-3xl overflow-hidden border border-slate-200 dark:border-slate-800 flex flex-col justify-between shadow-sm bg-white dark:bg-slate-900 group hover:shadow-xl transition-all">
                            
                            <!-- Image Banner -->
                            <div class="h-52 relative overflow-hidden bg-slate-200 dark:bg-slate-800">
                                <img src="<?= htmlspecialchars($propPhoto) ?>" alt="<?= htmlspecialchars(formatTitleCase($prop['name'])) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent"></div>

                                <div class="absolute top-3 left-3 right-3 flex items-center justify-between z-10">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-500 text-slate-950 shadow-md">
                                        <?= htmlspecialchars(formatTitleCase($prop['type'])) ?>
                                    </span>
                                    <span class="text-xs text-white bg-slate-900/80 backdrop-blur-md px-2.5 py-1 rounded-xl border border-white/20 shadow-sm font-semibold">
                                        <i class="fa-solid fa-location-dot text-amber-400 mr-1"></i> <?= htmlspecialchars(formatTitleCase($prop['city'])) ?>
                                    </span>
                                </div>

                                <div class="absolute bottom-3 left-4 right-4 z-10 text-white">
                                    <h3 class="text-xl font-extrabold font-heading leading-tight"><?= htmlspecialchars(formatTitleCase($prop['name'])) ?></h3>
                                </div>
                            </div>

                            <!-- Body -->
                            <div class="p-6 space-y-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed line-clamp-2">
                                        <i class="fa-solid fa-map-pin text-indigo-500 mr-1"></i> <?= htmlspecialchars($prop['address']) ?>
                                    </p>
                                    
                                    <?php if (!empty($prop['description'])): ?>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 line-clamp-2 italic">
                                            <?= htmlspecialchars($prop['description']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <!-- Quick Stats -->
                                    <div class="grid grid-cols-2 gap-3 mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                                        <div class="p-2.5 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800">
                                            <div class="text-[10px] text-slate-500 uppercase font-bold">Kapasitas</div>
                                            <div class="text-sm font-extrabold text-slate-900 dark:text-white"><?= $prop['total_rooms'] ?> Unit Kamar</div>
                                        </div>
                                        <div class="p-2.5 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800">
                                            <div class="text-[10px] text-slate-500 uppercase font-bold">Kamar Kosong</div>
                                            <div class="text-sm font-extrabold text-emerald-600 dark:text-emerald-400"><?= $prop['available_rooms'] ?> Unit</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                    <div>
                                        <div class="text-[10px] text-slate-500">Mulai Dari</div>
                                        <div class="text-base font-extrabold text-slate-900 dark:text-white font-heading">
                                            <?= $prop['min_price'] ? formatRupiah($prop['min_price']) : 'Hubungi Pemilik' ?>
                                        </div>
                                    </div>
                                    <a href="#kamar" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-sm flex items-center gap-1.5">
                                        Lihat Kamar <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </a>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <!-- Section: Katalog Kamar Promosi Showcase -->
    <section id="kamar" class="py-20 bg-slate-50 dark:bg-slate-950 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs font-bold uppercase tracking-wider mb-3 border border-emerald-500/20">
                        <i class="fa-solid fa-bed"></i> Galeri Kamar & Unit
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white font-heading">
                        Katalog Kamar Kos Siap Huni
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm mt-2">Pilihan kamar berfasilitas lengkap dengan foto asli dan tarif transparan.</p>
                </div>

                <!-- Filter Controls -->
                <div class="flex items-center gap-2">
                    <button class="room-filter-btn px-4 py-2 rounded-xl text-xs bg-amber-500 text-slate-950 font-bold transition-all" data-filter="all">Semua Kamar</button>
                    <button class="room-filter-btn px-4 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white border border-slate-200 dark:border-transparent transition-all" data-filter="tersedia">Tersedia Saja</button>
                    <button class="room-filter-btn px-4 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white border border-slate-200 dark:border-transparent transition-all" data-filter="terisi">Terisi</button>
                </div>
            </div>

            <!-- Rooms Grid with Real Photos -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (!empty($rooms)): ?>
                    <?php foreach ($rooms as $idx => $room): ?>
                        <?php $roomPhoto = getRoomImage($room['image'] ?? null, $room['room_type'], $idx); ?>
                        <div class="room-card-item glass-card rounded-3xl overflow-hidden border border-slate-200 dark:border-slate-800 flex flex-col justify-between shadow-sm bg-white dark:bg-slate-900 group hover:shadow-xl transition-all" data-status="<?= htmlspecialchars($room['status']) ?>">
                            
                            <!-- Card Header Image -->
                            <div class="h-48 relative overflow-hidden bg-slate-200 dark:bg-slate-800">
                                <img src="<?= htmlspecialchars($roomPhoto) ?>" alt="Foto Kamar <?= htmlspecialchars($room['room_number']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent"></div>

                                <div class="absolute top-3 left-3 right-3 flex items-center justify-between z-10">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider <?= $room['status'] === 'tersedia' ? 'badge-tersedia' : ($room['status'] === 'terisi' ? 'badge-terisi' : 'badge-perbaikan') ?>">
                                        <i class="fa-solid fa-circle text-[7px] mr-1"></i> <?= htmlspecialchars(formatTitleCase($room['status'])) ?>
                                    </span>
                                    <span class="text-xs text-white bg-slate-900/80 backdrop-blur-md px-2.5 py-1 rounded-xl border border-white/20 shadow-sm font-semibold">
                                        <i class="fa-solid fa-ruler-combined text-amber-400 mr-1"></i> <?= htmlspecialchars($room['size']) ?>
                                    </span>
                                </div>

                                <div class="absolute bottom-3 left-4 right-4 z-10 text-white">
                                    <div class="text-xs text-amber-300 font-bold flex items-center gap-1">
                                        <i class="fa-solid fa-hotel text-xs"></i> <?= htmlspecialchars(formatTitleCase($room['property_name'])) ?>
                                    </div>
                                    <div class="text-xl font-extrabold font-heading">Kamar <?= htmlspecialchars($room['room_number']) ?></div>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
                                <div>
                                    <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider mb-1"><?= htmlspecialchars(formatTitleCase($room['room_type'])) ?></div>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2 leading-relaxed">
                                        <?= htmlspecialchars($room['description'] ?? 'Unit kamar nyaman berfasilitas lengkap dengan sirkulasi udara baik.') ?>
                                    </p>
                                </div>

                                <!-- Facilities Tags -->
                                <div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 uppercase font-bold tracking-wider mb-2">Fasilitas Termasuk:</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php 
                                            $facs = explode(',', $room['facilities'] ?? '');
                                            foreach (array_slice($facs, 0, 4) as $fac): 
                                                if (trim($fac)):
                                        ?>
                                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800/90 text-slate-700 dark:text-slate-300 text-[11px] border border-slate-200 dark:border-slate-700/60 flex items-center gap-1.5">
                                                <i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400 text-[10px]"></i> <?= htmlspecialchars(formatTitleCase(trim($fac))) ?>
                                            </span>
                                        <?php 
                                                endif;
                                            endforeach; 
                                        ?>
                                    </div>
                                </div>

                                <!-- Price & Action -->
                                <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                    <div>
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400">Tarif Sewa</div>
                                        <div class="text-lg font-extrabold text-slate-900 dark:text-white font-heading">
                                            <?= formatRupiah($room['price_monthly']) ?> <span class="text-xs font-normal text-slate-500 dark:text-slate-400">/bln</span>
                                        </div>
                                    </div>
                                    <?php if ($room['status'] === 'tersedia'): 
                                        $ownerPhone = !empty($room['owner_phone']) ? preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $room['owner_phone'])) : '6281234567890';
                                        $waText = "Halo pengelola " . $room['property_name'] . ", saya tertarik dengan Kamar " . $room['room_number'] . " (" . $room['room_type'] . ") tarif " . formatRupiah($room['price_monthly']) . "/bln di website LOCK & ROOM. Apakah masih tersedia untuk disurvei/booking?";
                                        $waBookingUrl = "https://api.whatsapp.com/send?phone=" . $ownerPhone . "&text=" . urlencode($waText);
                                    ?>
                                        <a href="<?= $waBookingUrl ?>" target="_blank" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm shadow-emerald-600/30">
                                            <i class="fa-brands fa-whatsapp text-sm"></i> Tanya / Booking
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 text-xs font-semibold">
                                            Terisi Penuh
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <!-- Section: Informasi Aplikasi -->
    <section id="informasi" class="py-20 bg-slate-100/70 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 text-xs font-bold uppercase tracking-wider mb-3 border border-amber-500/20">
                    <i class="fa-solid fa-circle-info"></i> Keunggulan Sistem
                </div>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white font-heading">
                    Mengenal LOCK & ROOM (L n' R)
                </h2>
                <p class="text-slate-600 dark:text-slate-400 text-sm sm:text-base mt-3 leading-relaxed">
                    Sistem manajemen kos modern yang memudahkan pemilik mempromosikan foto rumah kos, mendaftarkan penghuni, memverifikasi transfer sewa, dan merespon keluhan fasilitas secara realtime.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl mb-6">
                        <i class="fa-solid fa-images"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white font-heading mb-2">Promosi Foto & Galeri</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                        Tampilkan foto asli tampak depan rumah kos dan interior unit kamar untuk menarik calon penyewa potensial.
                    </p>
                </div>

                <div class="p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl mb-6">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white font-heading mb-2">Tagihan & Bukti Digital</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                        Penyewa mengunggah struk transfer bank secara mandiri, pemilik memverifikasi dan sistem menandai lunas.
                    </p>
                </div>

                <div class="p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl mb-6">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white font-heading mb-2">Pusat Pengaduan Fasilitas</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                        Laporan perbaikan AC, kran air, lampu, atau WiFi tertangani dengan riwayat status penanganan yang transparan.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="kontak" class="bg-white dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 pt-16 pb-12 transition-colors">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-slate-200 dark:border-slate-800">
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-indigo-600 flex items-center justify-center shadow-md">
                            <i class="fa-solid fa-hotel text-white text-lg"></i>
                        </div>
                        <span class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white font-heading">LOCK & ROOM <span class="text-amber-500 dark:text-amber-400 font-bold text-sm">L n' R</span></span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 text-xs sm:text-sm leading-relaxed max-w-md">
                        Sistem manajemen kos dan rumah kontrakan cerdas berbasis web untuk transparansi tagihan, kemudahan kontrol properti, dan promosi hunian.
                    </p>
                </div>

                <div>
                    <div class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 font-heading">Menu Navigasi</div>
                    <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-400">
                        <li><a href="#beranda" class="hover:text-indigo-600 dark:hover:text-amber-400 transition-colors">Beranda Utama</a></li>
                        <li><a href="#rumah-kos" class="hover:text-indigo-600 dark:hover:text-amber-400 transition-colors">Galeri Rumah Kos</a></li>
                        <li><a href="#kamar" class="hover:text-indigo-600 dark:hover:text-amber-400 transition-colors">Katalog Kamar</a></li>
                        <li><a href="#informasi" class="hover:text-indigo-600 dark:hover:text-amber-400 transition-colors">Informasi & Fitur</a></li>
                    </ul>
                </div>

                <div>
                    <div class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 font-heading">Pintu Masuk (Auth)</div>
                    <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-400">
                        <li><a href="auth/login.php?role=pemilik" class="text-indigo-600 dark:text-indigo-400 hover:underline"><i class="fa-solid fa-user-tie mr-1"></i> Login Pemilik</a></li>
                        <li><a href="auth/register.php" class="hover:text-indigo-600 dark:hover:text-white transition-colors"><i class="fa-solid fa-user-plus mr-1"></i> Register Pemilik Baru</a></li>
                        <li><a href="auth/login.php?role=penyewa" class="text-emerald-600 dark:text-emerald-400 hover:underline"><i class="fa-solid fa-house-user mr-1"></i> Login Penyewa</a></li>
                    </ul>
                </div>
            </div>

            <div class="pt-8 mt-12 border-t border-slate-200 dark:border-slate-800 flex flex-col md:flex-row items-center justify-between text-xs text-slate-500 gap-4">
                <!-- Sisi Kiri: Copyright -->
                <div class="text-center md:text-left order-2 md:order-1">
                    &copy; <?= date('Y') ?> <strong>LOCK & ROOM (L n' R)</strong>. All rights reserved.
                </div>

                <!-- Bagian Tengah: POWERED BY FECO (Mencolok & Keren) -->
                <div class="order-1 md:order-2 flex items-center justify-center">
                    <a href="https://fepscode.my.id" target="_blank" rel="noopener noreferrer" class="group relative inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-gradient-to-r from-indigo-500/10 via-purple-500/10 to-pink-500/10 hover:from-indigo-500/20 hover:via-purple-500/20 hover:to-pink-500/20 border border-indigo-500/30 hover:border-indigo-500/70 shadow-md shadow-indigo-500/10 hover:shadow-indigo-500/25 backdrop-blur-xl transition-all duration-300 transform hover:-translate-y-0.5">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                        </span>
                        <span class="text-[10px] font-extrabold text-slate-500 dark:text-slate-400 tracking-[0.2em] uppercase">POWERED BY</span>
                        <strong class="font-black text-xs tracking-wider bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 dark:from-indigo-400 dark:via-purple-300 dark:to-pink-400 bg-clip-text text-transparent font-heading">FECO</strong>
                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-indigo-500 dark:text-cyan-400 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform duration-300"></i>
                    </a>
                </div>

                <!-- Sisi Kanan: Foto Terverifikasi -->
                <div class="text-center md:text-right order-3">
                    <span class="text-slate-500 dark:text-slate-400"><i class="fa-solid fa-shield-cat text-amber-500 mr-1"></i> Foto Terverifikasi Pemilik</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Frontend Interactive Script -->
    <script src="assets/js/app.js"></script>
</body>
</html>
