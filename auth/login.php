<?php
// Login Page for Pemilik & Penyewa with Light & Dark Mode
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../helpers/auth.php';

$role = isset($_GET['role']) && in_array($_GET['role'], ['pemilik', 'penyewa']) ? $_GET['role'] : 'pemilik';
$isOwner = ($role === 'pemilik');

// Redirect if already logged in
if (isLoggedIn()) {
    if ($_SESSION['user_role'] === 'pemilik') {
        header("Location: " . BASE_URL . "/owner/index.php");
    } else {
        header("Location: " . BASE_URL . "/tenant/index.php");
    }
    exit;
}

$error = getFlash('error');
$success = getFlash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $formRole = sanitizeInput($_POST['role'] ?? 'pemilik');

    if (empty($email) || empty($password)) {
        $error = 'Harap isi email dan kata sandi!';
    } else {
        $pdo = getDBConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1");
            $stmt->execute([$email, $formRole]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login Success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_phone'] = $user['phone'];
                $_SESSION['user_role'] = $user['role'];

                if ($user['role'] === 'pemilik') {
                    header("Location: " . BASE_URL . "/owner/index.php");
                } else {
                    header("Location: " . BASE_URL . "/tenant/index.php");
                }
                exit;
            } else {
                $error = 'Email atau kata sandi tidak cocok untuk role ' . ($formRole === 'pemilik' ? 'Pemilik' : 'Penyewa') . '!';
            }
        } else {
            $error = 'Koneksi database gagal. Silakan buka halaman install.php terlebih dahulu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login <?= $isOwner ? 'Pemilik Kos' : 'Penyewa' ?> - LOCK & ROOM (L n' R)</title>
    
    <!-- Theme Switcher Init (Prevents Flash) -->
    <script src="../assets/js/theme.js"></script>

    <!-- Google Fonts & Tailwind -->
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white transition-colors duration-200">

    <!-- Top Navigation Simple -->
    <header class="py-4 px-6 border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/60 backdrop-blur-md">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="../index.php" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-indigo-600 flex items-center justify-center shadow-md">
                    <i class="fa-solid fa-hotel text-white text-lg"></i>
                </div>
                <div class="flex items-center gap-2 whitespace-nowrap">
                    <span class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white font-heading">LOCK & ROOM</span>
                    <span class="px-1.5 py-0.5 rounded bg-amber-500/15 border border-amber-500/30 text-[11px] text-amber-600 dark:text-amber-400 font-extrabold font-mono whitespace-nowrap">L n' R</span>
                </div>
            </a>

            <div class="flex items-center gap-3">
                <!-- Theme Switcher Button -->
                <button onclick="toggleTheme()" type="button" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-amber-400 border border-slate-200 dark:border-slate-700 transition-all flex items-center justify-center" title="Ubah Tema">
                    <i class="fa-solid fa-moon text-sm theme-toggle-icon"></i>
                </button>
                <a href="../index.php" class="text-xs sm:text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-white transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Form -->
    <main class="flex-1 flex items-center justify-center p-4 py-8">
        <div class="max-w-md w-full">
            
            <!-- Role Toggle Switcher -->
            <div class="bg-slate-200/80 dark:bg-slate-900 p-1.5 rounded-2xl border border-slate-300 dark:border-slate-800 flex mb-6 shadow-inner">
                <a href="login.php?role=pemilik" class="flex-1 py-2.5 rounded-xl text-center text-sm font-bold transition-all flex items-center justify-center gap-2 <?= $isOwner ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <i class="fa-solid fa-user-tie"></i> LOGIN PEMILIK
                </a>
                <a href="login.php?role=penyewa" class="flex-1 py-2.5 rounded-xl text-center text-sm font-bold transition-all flex items-center justify-center gap-2 <?= !$isOwner ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                    <i class="fa-solid fa-user"></i> LOGIN PENYEWA
                </a>
            </div>

            <!-- Login Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-xl relative overflow-hidden">
                <div class="absolute -right-12 -top-12 w-40 h-40 <?= $isOwner ? 'bg-indigo-500/10' : 'bg-emerald-500/10' ?> rounded-full blur-3xl pointer-events-none"></div>

                <div class="mb-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-2 <?= $isOwner ? 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30' : 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30' ?>">
                        <i class="<?= $isOwner ? 'fa-solid fa-shield-halved' : 'fa-solid fa-house-user' ?>"></i> Portal <?= $isOwner ? 'Pemilik (Owner)' : 'Penyewa (Tenant)' ?>
                    </div>
                    <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Selamat Datang Kembali</h2>
                    <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">
                        <?= $isOwner ? 'Masuk untuk mengelola properti kos, kamar, dan tagihan penyewa.' : 'Masuk menggunakan email dan kata sandi yang telah didaftarkan oleh pemilik kos.' ?>
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="mb-5 p-4 rounded-xl bg-rose-50 dark:bg-rose-500/20 border border-rose-200 dark:border-rose-500/40 text-rose-700 dark:text-rose-300 text-sm flex items-start gap-3">
                        <i class="fa-solid fa-circle-exclamation text-lg mt-0.5 flex-shrink-0"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-5 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/20 border border-emerald-200 dark:border-emerald-500/40 text-emerald-700 dark:text-emerald-300 text-sm flex items-start gap-3">
                        <i class="fa-solid fa-circle-check text-lg mt-0.5 flex-shrink-0"></i>
                        <div><?= htmlspecialchars($success) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php?role=<?= $role ?>" class="space-y-4">
                    <input type="hidden" name="role" value="<?= $role ?>">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Alamat Email</label>
                        <div class="relative">
                            <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
                            <input type="email" name="email" id="emailInput" required placeholder="nama@email.com" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-3 pl-11 pr-4 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition-all placeholder:text-slate-400 dark:placeholder:text-slate-600">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Kata Sandi</label>
                            <a href="forgot-password.php" class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Lupa Kata Sandi?</a>
                        </div>
                        <div class="relative">
                            <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
                            <input type="password" name="password" id="passwordInput" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-3 pl-11 pr-11 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition-all placeholder:text-slate-400 dark:placeholder:text-slate-600">
                            <button type="button" id="togglePassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-sm">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3.5 px-6 rounded-xl font-bold text-sm text-white shadow-lg transition-all flex items-center justify-center gap-2 <?= $isOwner ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 shadow-indigo-600/30' : 'bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 shadow-emerald-600/30' ?>">
                        <i class="fa-solid fa-right-to-bracket"></i> Masuk Sekarang
                    </button>
                </form>

                <!-- Demo Account Quick Fill -->
                <div class="mt-6 pt-5 border-t border-slate-200 dark:border-slate-800">
                    <div class="text-xs text-slate-500 dark:text-slate-400 mb-2 flex items-center justify-between">
                        <span>Ingin coba akun demo?</span>
                        <button type="button" id="fillDemoBtn" class="text-amber-600 dark:text-amber-400 hover:underline font-semibold flex items-center gap-1">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Isi Akun Demo
                        </button>
                    </div>
                </div>

                <!-- Footer Help / Register Link -->
                <div class="mt-6 text-center text-xs text-slate-600 dark:text-slate-400">
                    <?php if ($isOwner): ?>
                        Belum memiliki akun Pemilik? 
                        <a href="register.php" class="font-bold hover:underline text-indigo-600 dark:text-indigo-400">
                            Daftar Pemilik Kos di sini <i class="fa-solid fa-arrow-right text-[10px] ml-1"></i>
                        </a>
                    <?php else: ?>
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 text-left">
                            <i class="fa-solid fa-circle-info text-emerald-500 mr-1"></i>
                            <span>Akun Penyewa didaftarkan langsung oleh Pemilik Kos. Jika belum memiliki akses, hubungi pemilik kos tempat Anda menyewa.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer Simple -->
    <footer class="py-4 text-center text-xs text-slate-500 border-t border-slate-200 dark:border-slate-900">
        &copy; <?= date('Y') ?> LOCK & ROOM (L n' R) - Sistem Manajemen Kos & Kontrakan Modern.
    </footer>

    <script>
        // Password Visibility Toggle
        const toggleBtn = document.getElementById('togglePassword');
        const passInput = document.getElementById('passwordInput');
        if (toggleBtn && passInput) {
            toggleBtn.addEventListener('click', () => {
                const type = passInput.type === 'password' ? 'text' : 'password';
                passInput.type = type;
                toggleBtn.innerHTML = type === 'password' ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-solid fa-eye-slash"></i>';
            });
        }

        // Quick Demo Fill
        const demoBtn = document.getElementById('fillDemoBtn');
        const emailInput = document.getElementById('emailInput');
        if (demoBtn && emailInput && passInput) {
            demoBtn.addEventListener('click', () => {
                const isOwner = <?= $isOwner ? 'true' : 'false' ?>;
                emailInput.value = isOwner ? 'pemilik@lockroom.com' : 'penyewa@lockroom.com';
                passInput.value = isOwner ? 'pemilik123' : 'penyewa123';
            });
        }
    </script>
</body>
</html>
