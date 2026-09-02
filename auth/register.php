<?php
// Register Page for Pemilik Kos with Instant Gmail & OTP Verification
// LOCK & ROOM (L n' R)

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/mail.php';

$requestedRole = $_GET['role'] ?? 'pemilik';

// If someone attempts to register as 'penyewa', show informative screen
$isPenyewaNotice = ($requestedRole === 'penyewa');

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
$step = isset($_SESSION['pending_otp']) ? 'otp' : 'form';
$regMode = $_GET['mode'] ?? 'gmail'; // 'gmail' (instant) or 'manual'

// Helper to convert email handle into a neat Name
function formatNameFromEmail($email) {
    $username = explode('@', $email)[0];
    $clean = preg_replace('/[._\-+0-9]+/', ' ', $username);
    $words = ucwords(trim($clean));
    return !empty($words) ? $words : 'Pemilik ' . ucfirst(explode('@', $email)[0]);
}

// Handle Cancel / Reset OTP
if (isset($_GET['action']) && $_GET['action'] === 'cancel_otp') {
    clearOTPSession();
    header("Location: register.php?mode=" . $regMode);
    exit;
}

// Handle Resend OTP
if (isset($_GET['action']) && $_GET['action'] === 'resend_otp' && isset($_SESSION['pending_otp'])) {
    $email = $_SESSION['pending_otp']['email'];
    $regData = $_SESSION['pending_otp']['reg_data'];
    $newOtp = createRegistrationOTP($email, $regData);
    $_SESSION['pending_otp']['resend_count'] = ($_SESSION['pending_otp']['resend_count'] ?? 0) + 1;
    $success = "Kode OTP baru telah berhasil dikirim ulang ke $email.";
    $step = 'otp';
}

// Form Submission Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isPenyewaNotice) {
    $action = $_POST['form_step'] ?? 'form';

    // Step 1: Initial Registration (Instant Gmail OR Manual Form)
    if ($action === 'submit_form') {
        $mode = sanitizeInput($_POST['reg_mode'] ?? 'gmail');
        $email = strtolower(sanitizeInput($_POST['email'] ?? ''));
        $formRole = 'pemilik'; // Only Pemilik can register publicly

        if (empty($email)) {
            $error = 'Harap masukkan alamat email Anda!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format alamat email tidak valid!';
        } elseif ($mode === 'gmail' && !str_ends_with($email, '@gmail.com')) {
            $error = 'Harap gunakan alamat email berakhiran @gmail.com!';
        } else {
            $pdo = getDBConnection();
            if ($pdo) {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Alamat email ini sudah terdaftar! Silakan langsung login.';
                } else {
                    if ($mode === 'gmail') {
                        // INSTANT GMAIL MODE FOR PEMILIK
                        $name = formatNameFromEmail($email);
                        $phone = '08' . rand(1111111111, 9999999999);
                        $tempPassword = bin2hex(random_bytes(6));
                        
                        $regPayload = [
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone,
                            'password' => password_hash($tempPassword, PASSWORD_BCRYPT),
                            'role' => 'pemilik',
                            'mode' => 'gmail'
                        ];
                    } else {
                        // MANUAL MODE FOR PEMILIK
                        $name = sanitizeInput($_POST['name'] ?? '');
                        $phone = sanitizeInput($_POST['phone'] ?? '');
                        $password = $_POST['password'] ?? '';
                        $confirmPassword = $_POST['confirm_password'] ?? '';

                        if (empty($name) || empty($phone) || empty($password)) {
                            $error = 'Semua kolom formulir wajib diisi!';
                        } elseif (strlen($password) < 6) {
                            $error = 'Kata sandi minimal harus 6 karakter!';
                        } elseif ($password !== $confirmPassword) {
                            $error = 'Konfirmasi kata sandi tidak cocok!';
                        }

                        if (!empty($error)) {
                            $regPayload = null;
                        } else {
                            $regPayload = [
                                'name' => $name,
                                'email' => $email,
                                'phone' => $phone,
                                'password' => password_hash($password, PASSWORD_BCRYPT),
                                'role' => 'pemilik',
                                'mode' => 'manual'
                            ];
                        }
                    }

                    if (!empty($regPayload)) {
                        createRegistrationOTP($email, $regPayload);
                        $step = 'otp';
                        $success = "Kode OTP verifikasi telah dikirimkan ke <strong>$email</strong>.";
                    }
                }
            } else {
                $error = 'Koneksi database gagal. Silakan jalankan install.php.';
            }
        }
    }

    // Step 2: Verify Submitted OTP
    if ($action === 'submit_otp') {
        $submittedOtp = sanitizeInput($_POST['otp'] ?? '');
        $verifyResult = verifyRegistrationOTP($submittedOtp);

        if ($verifyResult['status'] === true) {
            $data = $verifyResult['reg_data'];
            $pdo = getDBConnection();

            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'pemilik')");
                    $stmt->execute([$data['name'], $data['email'], $data['phone'], $data['password']]);
                    $newUserId = $pdo->lastInsertId();

                    // Create default property for owner
                    $propStmt = $pdo->prepare("INSERT INTO properties (owner_id, name, type, address, city, description) VALUES (?, ?, ?, ?, ?, ?)");
                    $propStmt->execute([
                        $newUserId,
                        'Kost ' . $data['name'],
                        'kos_campur',
                        'Alamat Properti Kos Anda',
                        'Jakarta',
                        'Kelola kos dan kontrakan Anda dengan mudah di LOCK & ROOM.'
                    ]);

                    // Clear OTP session
                    clearOTPSession();

                    // Set Login Session
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['user_name'] = $data['name'];
                    $_SESSION['user_email'] = $data['email'];
                    $_SESSION['user_phone'] = $data['phone'];
                    $_SESSION['user_role'] = 'pemilik';

                    setFlash('success', 'Selamat datang! Akun Pemilik Kos Anda berhasil diverifikasi dan aktif.');
                    header("Location: " . BASE_URL . "/owner/index.php");
                    exit;
                } catch (Exception $e) {
                    $error = 'Gagal menyimpan data pengguna: ' . $e->getMessage();
                    $step = 'otp';
                }
            } else {
                $error = 'Koneksi database terputus.';
                $step = 'otp';
            }
        } else {
            $error = $verifyResult['message'];
            $step = 'otp';
        }
    }
}

$currentOTPData = $_SESSION['pending_otp'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Pemilik Kos - LOCK & ROOM (L n' R)</title>
    
    <!-- Theme Switcher Init (Prevents Flash) -->
    <script src="../assets/js/theme.js"></script>

    <!-- Tailwind & Google Fonts -->
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
            
            <?php if ($isPenyewaNotice): ?>
                <!-- Notice for Penyewa attempting public registration -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-xl text-center space-y-5">
                    <div class="w-16 h-16 rounded-2xl bg-amber-50 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30 flex items-center justify-center mx-auto text-2xl">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Pendaftaran Oleh Pemilik</h2>
                        <p class="text-slate-600 dark:text-slate-400 text-sm mt-2 leading-relaxed">
                            Penyewa <strong>tidak perlu mendaftar mandiri</strong>. Data identitas, nomor kamar, dan akun login Anda akan didaftarkan langsung oleh <strong>Pemilik Kos</strong> saat Anda mulai menyewa kamar.
                        </p>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 text-left space-y-2">
                        <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-check text-emerald-500"></i> Cara Mendapatkan Akses:
                        </div>
                        <p>1. Hubungi pemilik atau pengelola kos tempat Anda menyewa.</p>
                        <p>2. Pemilik akan menginput data diri dan menetapkan nomor kamar Anda.</p>
                        <p>3. Anda akan menerima email dan kata sandi untuk login ke portal penyewa.</p>
                    </div>

                    <div class="pt-2 space-y-2">
                        <a href="login.php?role=penyewa" class="block w-full py-3.5 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-md transition-all">
                            <i class="fa-solid fa-right-to-bracket mr-1"></i> Langsung Login Penyewa
                        </a>
                        <a href="../index.php" class="block w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-all">
                            Kembali ke Beranda
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <!-- Registration Box for Pemilik -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-xl relative overflow-hidden">
                    <div class="absolute -right-12 -top-12 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

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

                    <!-- ================= STEP 1: FORMULIR PEMILIK ================= -->
                    <?php if ($step === 'form'): ?>
                        
                        <div class="mb-6">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-2 bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30">
                                <i class="fa-solid fa-shield-halved"></i> Registrasi Pemilik Kos (Owner)
                            </div>
                            <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">
                                <?= $regMode === 'gmail' ? 'Daftar Pemilik via Gmail' : 'Daftar Pemilik Manual' ?>
                            </h2>
                            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1">
                                <?= $regMode === 'gmail' ? 'Daftarkan properti kos Anda secara instan menggunakan alamat Gmail.' : 'Lengkapi formulir di bawah untuk mendaftarkan akun pemilik kos.' ?>
                            </p>
                        </div>

                        <!-- Mode Toggle (Gmail Instan vs Manual) -->
                        <div class="flex items-center gap-2 p-1 bg-slate-100 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 mb-6">
                            <a href="register.php?mode=gmail" class="flex-1 py-2 rounded-xl text-xs font-bold text-center flex items-center justify-center gap-2 transition-all <?= $regMode === 'gmail' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                                <i class="fa-brands fa-google text-rose-500"></i> Akun Gmail (Instan)
                            </a>
                            <a href="register.php?mode=manual" class="flex-1 py-2 rounded-xl text-xs font-bold text-center flex items-center justify-center gap-2 transition-all <?= $regMode === 'manual' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200' ?>">
                                <i class="fa-solid fa-file-lines"></i> Formulir Manual
                            </a>
                        </div>

                        <?php if ($regMode === 'gmail'): ?>
                            <!-- GMAIL INSTANT REGISTRATION FOR OWNER -->
                            <form method="POST" action="register.php?mode=gmail" class="space-y-4">
                                <input type="hidden" name="form_step" value="submit_form">
                                <input type="hidden" name="reg_mode" value="gmail">

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                        Alamat Email Gmail Pemilik Kos
                                    </label>
                                    <div class="relative">
                                        <i class="fa-brands fa-google absolute left-4 top-1/2 -translate-y-1/2 text-rose-500"></i>
                                        <input type="email" name="email" required placeholder="pemilik@gmail.com" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-3.5 pl-11 pr-4 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition-all placeholder:text-slate-400 dark:placeholder:text-slate-600">
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 flex items-start gap-1.5">
                                        <i class="fa-solid fa-circle-info text-amber-500 mt-0.5 flex-shrink-0"></i>
                                        <span>Kode OTP verifikasi akan dikirim langsung ke Gmail Anda untuk aktivasi instan dashboard pemilik.</span>
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <button type="submit" class="w-full py-3.5 px-6 rounded-xl font-bold text-sm text-white shadow-lg transition-all flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 shadow-indigo-600/30">
                                        <i class="fa-brands fa-google"></i> Lanjut & Kirim Kode OTP
                                    </button>
                                </div>
                            </form>

                        <?php else: ?>
                            <!-- MANUAL REGISTRATION FOR OWNER -->
                            <form method="POST" action="register.php?mode=manual" class="space-y-4">
                                <input type="hidden" name="form_step" value="submit_form">
                                <input type="hidden" name="reg_mode" value="manual">

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Nama Lengkap Pemilik</label>
                                    <div class="relative">
                                        <i class="fa-solid fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
                                        <input type="text" name="name" required placeholder="Haji Sulaiman" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 pl-11 pr-4 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Alamat Email</label>
                                    <div class="relative">
                                        <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
                                        <input type="email" name="email" required placeholder="nama@email.com" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 pl-11 pr-4 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Nomor WhatsApp / HP</label>
                                    <div class="relative">
                                        <i class="fa-solid fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
                                        <input type="tel" name="phone" required placeholder="08123456789" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 pl-11 pr-4 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Kata Sandi</label>
                                        <input type="password" name="password" required placeholder="Min 6 karakter" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Ulangi Sandi</label>
                                        <input type="password" name="confirm_password" required placeholder="Ulangi" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <button type="submit" class="w-full py-3.5 px-6 rounded-xl font-bold text-sm text-white shadow-lg transition-all flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 shadow-indigo-600/30">
                                        <i class="fa-solid fa-paper-plane"></i> Lanjut & Kirim Kode OTP
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                    <!-- ================= STEP 2: VERIFIKASI KODE OTP ================= -->
                    <?php else: ?>

                        <div class="text-center mb-6">
                            <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30 flex items-center justify-center mx-auto text-2xl mb-4">
                                <i class="fa-solid fa-shield-keyhole animate-pulse"></i>
                            </div>
                            <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">Verifikasi Kode OTP</h2>
                            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1 max-w-xs mx-auto">
                                Masukkan 6 digit kode verifikasi yang dikirimkan ke email:
                            </p>
                            <div class="mt-2 inline-block px-3 py-1 bg-slate-100 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-700 font-mono text-sm text-amber-600 dark:text-amber-400 font-bold">
                                <?= htmlspecialchars($currentOTPData['email'] ?? '') ?>
                            </div>
                        </div>

                        <!-- Local Dev OTP helper simulator -->
                        <?php if ($currentOTPData): ?>
                            <div class="mb-5 p-4 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-500/30 text-center">
                                <div class="text-[11px] text-slate-600 dark:text-slate-400 mb-1">
                                    <i class="fa-solid fa-envelope-circle-check text-emerald-600 dark:text-emerald-400 mr-1"></i> Kode OTP Masuk (Simulasi):
                                </div>
                                <div class="text-2xl font-extrabold font-mono text-indigo-700 dark:text-cyan-400 tracking-widest bg-white dark:bg-slate-950/80 py-2 rounded-xl border border-slate-200 dark:border-slate-800 select-all">
                                    <?= htmlspecialchars($currentOTPData['otp']) ?>
                                </div>
                                <div class="text-[10px] text-slate-500 mt-1.5">Ketik 6 digit kode di atas untuk menyelesaikan aktivasi akun.</div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="register.php?mode=<?= $regMode ?>" class="space-y-5">
                            <input type="hidden" name="form_step" value="submit_otp">

                            <div>
                                <label class="block text-xs font-semibold text-center text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Kode Verifikasi 6-Digit</label>
                                <input type="text" name="otp" required maxlength="6" autofocus placeholder="••••••" class="w-full bg-slate-50 dark:bg-slate-950 border border-indigo-300 dark:border-indigo-500/60 rounded-2xl py-4 px-4 text-center font-mono text-3xl font-extrabold tracking-[10px] text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/50 focus:outline-none transition-all placeholder:text-slate-300 dark:placeholder:text-slate-700">
                            </div>

                            <button type="submit" class="w-full py-3.5 px-6 rounded-xl font-bold text-sm text-white shadow-lg transition-all flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 shadow-indigo-600/30">
                                <i class="fa-solid fa-circle-check"></i> Verifikasi & Buka Dashboard Pemilik
                            </button>
                        </form>

                        <!-- Resend & Change Email Actions -->
                        <div class="mt-6 pt-5 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                            <a href="register.php?mode=<?= $regMode ?>&action=resend_otp" class="text-amber-600 dark:text-amber-400 hover:underline font-semibold flex items-center gap-1.5">
                                <i class="fa-solid fa-rotate-right"></i> Kirim Ulang OTP
                            </a>
                            <a href="register.php?mode=<?= $regMode ?>&action=cancel_otp" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors flex items-center gap-1.5">
                                <i class="fa-solid fa-pen-to-square"></i> Ganti Email
                            </a>
                        </div>

                    <?php endif; ?>

                    <!-- Login Link -->
                    <div class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
                        Sudah memiliki akun Pemilik? 
                        <a href="login.php?role=pemilik" class="font-bold hover:underline text-indigo-600 dark:text-indigo-400">
                            Masuk di sini <i class="fa-solid fa-arrow-right text-xs ml-1"></i>
                        </a>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </main>

    <!-- Footer Simple -->
    <footer class="py-4 text-center text-xs text-slate-500 border-t border-slate-200 dark:border-slate-900">
        &copy; <?= date('Y') ?> LOCK & ROOM (L n' R) - Sistem Manajemen Kos & Kontrakan Modern.
    </footer>
</body>
</html>
