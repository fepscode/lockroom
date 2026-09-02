<?php
// Forgot Password Page with OTP Verification & Reset
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/mail.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if ($_SESSION['user_role'] === 'pemilik') {
        header("Location: " . BASE_URL . "/owner/index.php");
    } else {
        header("Location: " . BASE_URL . "/tenant/index.php");
    }
    exit;
}

$error = '';
$success = '';
$step = isset($_SESSION['reset_password_otp']) ? 'otp' : 'email';

// Cancel Reset Session
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    clearOTPSession();
    header("Location: forgot-password.php");
    exit;
}

// Resend OTP
if (isset($_GET['action']) && $_GET['action'] === 'resend_otp' && isset($_SESSION['reset_password_otp'])) {
    $email = $_SESSION['reset_password_otp']['email'];
    $name = $_SESSION['reset_password_otp']['name'];
    createResetPasswordOTP($email, $name);
    $success = "Kode OTP baru telah berhasil dikirimkan ke $email.";
    $step = 'otp';
}

// Form Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_step'] ?? 'email';

    // Step 1: Submit Email for Reset
    if ($action === 'submit_email') {
        $email = strtolower(sanitizeInput($_POST['email'] ?? ''));

        if (empty($email)) {
            $error = 'Harap masukkan alamat email akun Anda!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format alamat email tidak valid!';
        } else {
            $pdo = getDBConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    createResetPasswordOTP($email, $user['name']);
                    $step = 'otp';
                    $success = "Kode OTP verifikasi reset kata sandi telah dikirim ke <strong>$email</strong>.";
                } else {
                    $error = "Alamat email <strong>$email</strong> tidak terdaftar dalam sistem!";
                }
            } else {
                $error = 'Koneksi database gagal.';
            }
        }
    }

    // Step 2: Verify OTP & Change to New Password
    if ($action === 'submit_new_password') {
        $submittedOtp = sanitizeInput($_POST['otp'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($submittedOtp) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Semua kolom wajib diisi!';
            $step = 'otp';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Kata sandi baru minimal harus 6 karakter!';
            $step = 'otp';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Konfirmasi kata sandi baru tidak cocok!';
            $step = 'otp';
        } else {
            $verifyResult = verifyResetPasswordOTP($submittedOtp);

            if ($verifyResult['status'] === true) {
                $email = $verifyResult['email'];
                $pdo = getDBConnection();

                if ($pdo) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $stmtUpdate->execute([$hashedPassword, $email]);

                    clearOTPSession();
                    setFlash('success', 'Kata sandi Anda berhasil diperbarui! Silakan masuk dengan kata sandi baru.');
                    header("Location: login.php");
                    exit;
                } else {
                    $error = 'Koneksi database gagal.';
                    $step = 'otp';
                }
            } else {
                $error = $verifyResult['message'];
                $step = 'otp';
            }
        }
    }
}

$currentResetData = $_SESSION['reset_password_otp'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Sandi - LOCK & ROOM (L n' R)</title>
    
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
                <a href="login.php" class="text-xs sm:text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-white transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Login
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Form -->
    <main class="flex-1 flex items-center justify-center p-4 py-8">
        <div class="max-w-md w-full">
            
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-xl relative overflow-hidden">
                <div class="absolute -right-12 -top-12 w-40 h-40 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <!-- Alert Messages -->
                <?php if ($error): ?>
                    <div class="mb-5 p-4 rounded-xl bg-rose-50 dark:bg-rose-500/20 border border-rose-200 dark:border-rose-500/40 text-rose-700 dark:text-rose-300 text-sm flex items-start gap-3">
                        <i class="fa-solid fa-circle-exclamation text-lg mt-0.5 flex-shrink-0"></i>
                        <div><?= $error ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-5 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/20 border border-emerald-200 dark:border-emerald-500/40 text-emerald-700 dark:text-emerald-300 text-sm flex items-start gap-3">
                        <i class="fa-solid fa-circle-check text-lg mt-0.5 flex-shrink-0"></i>
                        <div><?= $success ?></div>
                    </div>
                <?php endif; ?>

                <!-- ================= STEP 1: MASUKKAN EMAIL ================= -->
                <?php if ($step === 'email'): ?>
                    <div class="mb-6">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-2 bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30">
                            <i class="fa-solid fa-key"></i> Pemulihan Akun
                        </div>
                        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Lupa Kata Sandi?</h2>
                        <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1 leading-relaxed">
                            Masukkan alamat email yang terdaftar (Pemilik atau Penyewa). Kami akan mengirimkan 6 digit kode OTP verifikasi untuk mengatur ulang kata sandi Anda.
                        </p>
                    </div>

                    <form method="POST" action="forgot-password.php" class="space-y-4">
                        <input type="hidden" name="form_step" value="submit_email">

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Alamat Email Terdaftar</label>
                            <div class="relative">
                                <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
                                <input type="email" name="email" required placeholder="nama@email.com" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-3.5 pl-11 pr-4 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none transition-all placeholder:text-slate-400 dark:placeholder:text-slate-600">
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full py-3.5 px-6 rounded-xl font-bold text-sm text-white shadow-lg transition-all flex items-center justify-center gap-2 bg-gradient-to-r from-amber-500 via-amber-600 to-indigo-600 hover:from-amber-600 hover:to-indigo-700 shadow-amber-500/20">
                                <i class="fa-solid fa-paper-plane"></i> Kirim Kode OTP Reset
                            </button>
                        </div>
                    </form>

                <!-- ================= STEP 2: VERIFIKASI OTP & KATA SANDI BARU ================= -->
                <?php else: ?>
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 rounded-2xl bg-amber-50 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30 flex items-center justify-center mx-auto text-2xl mb-4">
                            <i class="fa-solid fa-shield-keyhole animate-pulse"></i>
                        </div>
                        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Buat Kata Sandi Baru</h2>
                        <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1 max-w-xs mx-auto">
                            Masukkan kode OTP yang dikirimkan ke email:
                        </p>
                        <div class="mt-2 inline-block px-3 py-1 bg-slate-100 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-700 font-mono text-sm text-amber-600 dark:text-amber-400 font-bold">
                            <?= htmlspecialchars($currentResetData['email'] ?? '') ?>
                        </div>
                    </div>

                    <!-- Local Dev OTP helper simulator -->
                    <?php if ($currentResetData): ?>
                        <div class="mb-5 p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-500/30 text-center">
                            <div class="text-[11px] text-slate-600 dark:text-slate-400 mb-1">
                                <i class="fa-solid fa-envelope-circle-check text-emerald-600 dark:text-emerald-400 mr-1"></i> Kode OTP Masuk (Simulasi):
                            </div>
                            <div class="text-2xl font-extrabold font-mono text-amber-600 dark:text-amber-400 tracking-widest bg-white dark:bg-slate-950/80 py-2 rounded-xl border border-slate-200 dark:border-slate-800 select-all">
                                <?= htmlspecialchars($currentResetData['otp']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="forgot-password.php" class="space-y-4">
                        <input type="hidden" name="form_step" value="submit_new_password">

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Kode OTP 6-Digit</label>
                            <input type="text" name="otp" required maxlength="6" autofocus placeholder="••••••" class="w-full bg-slate-50 dark:bg-slate-950 border border-amber-300 dark:border-amber-500/60 rounded-xl py-3 px-4 text-center font-mono text-2xl font-extrabold tracking-[8px] text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none transition-all placeholder:text-slate-300 dark:placeholder:text-slate-700">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Kata Sandi Baru</label>
                            <input type="password" name="new_password" required placeholder="Minimal 6 karakter" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Konfirmasi Kata Sandi Baru</label>
                            <input type="password" name="confirm_password" required placeholder="Ulangi kata sandi baru" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3.5 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full py-3.5 px-6 rounded-xl font-bold text-sm text-white shadow-lg transition-all flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 shadow-emerald-600/30">
                                <i class="fa-solid fa-circle-check"></i> Simpan Kata Sandi & Masuk
                            </button>
                        </div>
                    </form>

                    <!-- Resend & Cancel Actions -->
                    <div class="mt-6 pt-5 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                        <a href="forgot-password.php?action=resend_otp" class="text-amber-600 dark:text-amber-400 hover:underline font-semibold flex items-center gap-1.5">
                            <i class="fa-solid fa-rotate-right"></i> Kirim Ulang OTP
                        </a>
                        <a href="forgot-password.php?action=cancel" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors flex items-center gap-1.5">
                            <i class="fa-solid fa-pen-to-square"></i> Ganti Email
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Back to Login Link -->
                <div class="mt-6 text-center text-xs text-slate-600 dark:text-slate-400">
                    Ingat kata sandi Anda? 
                    <a href="login.php" class="font-bold hover:underline text-indigo-600 dark:text-indigo-400">
                        Masuk di sini <i class="fa-solid fa-arrow-right text-[10px] ml-1"></i>
                    </a>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer Simple -->
    <footer class="py-4 text-center text-xs text-slate-500 border-t border-slate-200 dark:border-slate-900">
        &copy; <?= date('Y') ?> LOCK & ROOM (L n' R) - Sistem Manajemen Kos & Kontrakan Modern.
    </footer>
</body>
</html>
