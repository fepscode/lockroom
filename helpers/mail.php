<?php
// Mail & OTP Verification Helper (Registration & Forgot Password)
// LOCK & ROOM (L n' R)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate 6-Digit OTP and store registration state
 */
function createRegistrationOTP($email, $regData) {
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    
    $_SESSION['pending_otp'] = [
        'email' => strtolower(trim($email)),
        'otp' => (string)$otp,
        'created_at' => time(),
        'expires_at' => time() + (10 * 60), // 10 minutes validity
        'reg_data' => $regData,
        'resend_count' => 0
    ];

    sendOTPEmail($email, $otp, $regData['name'] ?? 'Pengguna');
    return $otp;
}

/**
 * Generate 6-Digit OTP and store password reset state
 */
function createResetPasswordOTP($email, $userName = 'Pengguna') {
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    
    $_SESSION['reset_password_otp'] = [
        'email' => strtolower(trim($email)),
        'otp' => (string)$otp,
        'name' => $userName,
        'created_at' => time(),
        'expires_at' => time() + (10 * 60), // 10 minutes validity
        'resend_count' => 0
    ];

    sendResetPasswordOTPEmail($email, $otp, $userName);
    return $otp;
}

/**
 * Send HTML OTP Email for Registration
 */
function sendOTPEmail($toEmail, $otp, $recipientName = 'Pengguna') {
    $subject = "Kode Verifikasi OTP Akun LOCK & ROOM (L n' R)";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: LOCK & ROOM <noreply@lockroom.local>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Verifikasi OTP</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 20px; }
            .card { max-width: 500px; margin: 0 auto; background-color: #1e293b; border-radius: 16px; padding: 32px; border: 1px solid #334155; }
            .brand { color: #818cf8; font-size: 20px; font-weight: bold; margin-bottom: 16px; }
            .otp-box { background-color: #0f172a; border: 2px dashed #6366f1; border-radius: 12px; padding: 16px; text-align: center; margin: 24px 0; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #38bdf8; font-family: monospace; }
            .footer { font-size: 12px; color: #94a3b8; margin-top: 24px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='card'>
            <div class='brand'>LOCK & ROOM (L n' R)</div>
            <h2 style='color: #ffffff; margin-top: 0;'>Verifikasi Pendaftaran Akun</h2>
            <p>Halo <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
            <p>Terima kasih telah mendaftar di sistem LOCK & ROOM. Gunakan kode verifikasi OTP di bawah ini untuk mengaktifkan akun Anda:</p>
            
            <div class='otp-box'>" . htmlspecialchars($otp) . "</div>
            
            <p style='font-size: 13px; color: #cbd5e1;'>Kode OTP ini hanya berlaku selama <strong>10 menit</strong>. Jangan berikan kode ini kepada siapapun demi keamanan akun Anda.</p>
            
            <div class='footer'>
                &copy; " . date('Y') . " LOCK & ROOM (L n' R) - Solusi Manajemen Kos & Kontrakan Modern.
            </div>
        </div>
    </body>
    </html>
    ";

    @mail($toEmail, $subject, $htmlBody, $headers);
    return true;
}

/**
 * Send HTML OTP Email for Password Reset
 */
function sendResetPasswordOTPEmail($toEmail, $otp, $recipientName = 'Pengguna') {
    $subject = "Kode OTP Reset Kata Sandi - LOCK & ROOM (L n' R)";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: LOCK & ROOM <noreply@lockroom.local>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Reset Kata Sandi</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 20px; }
            .card { max-width: 500px; margin: 0 auto; background-color: #1e293b; border-radius: 16px; padding: 32px; border: 1px solid #334155; }
            .brand { color: #f59e0b; font-size: 20px; font-weight: bold; margin-bottom: 16px; }
            .otp-box { background-color: #0f172a; border: 2px dashed #f59e0b; border-radius: 12px; padding: 16px; text-align: center; margin: 24px 0; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #fbbf24; font-family: monospace; }
            .footer { font-size: 12px; color: #94a3b8; margin-top: 24px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='card'>
            <div class='brand'>LOCK & ROOM (L n' R)</div>
            <h2 style='color: #ffffff; margin-top: 0;'>Permintaan Reset Kata Sandi</h2>
            <p>Halo <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
            <p>Kami menerima permintaan untuk mereset kata sandi akun Anda. Gunakan kode verifikasi OTP di bawah ini untuk membuat kata sandi baru:</p>
            
            <div class='otp-box'>" . htmlspecialchars($otp) . "</div>
            
            <p style='font-size: 13px; color: #cbd5e1;'>Kode OTP ini hanya berlaku selama <strong>10 menit</strong>. Jika Anda tidak merasa meminta reset kata sandi, abaikan email ini.</p>
            
            <div class='footer'>
                &copy; " . date('Y') . " LOCK & ROOM (L n' R) - Solusi Manajemen Kos & Kontrakan Modern.
            </div>
        </div>
    </body>
    </html>
    ";

    @mail($toEmail, $subject, $htmlBody, $headers);
    return true;
}

/**
 * Verify submitted Registration OTP
 */
function verifyRegistrationOTP($submittedOTP) {
    if (!isset($_SESSION['pending_otp'])) {
        return ['status' => false, 'message' => 'Sesi verifikasi telah berakhir. Silakan daftar ulang.'];
    }

    $otpData = $_SESSION['pending_otp'];

    if (time() > $otpData['expires_at']) {
        return ['status' => false, 'message' => 'Kode OTP telah kadaluarsa! Silakan klik Kirim Ulang Kode OTP.'];
    }

    if (trim($submittedOTP) !== (string)$otpData['otp']) {
        return ['status' => false, 'message' => 'Kode OTP tidak cocok! Silakan periksa kembali email Anda.'];
    }

    return ['status' => true, 'reg_data' => $otpData['reg_data']];
}

/**
 * Verify submitted Reset Password OTP
 */
function verifyResetPasswordOTP($submittedOTP) {
    if (!isset($_SESSION['reset_password_otp'])) {
        return ['status' => false, 'message' => 'Sesi reset kata sandi telah berakhir. Silakan ajukan ulang.'];
    }

    $otpData = $_SESSION['reset_password_otp'];

    if (time() > $otpData['expires_at']) {
        return ['status' => false, 'message' => 'Kode OTP telah kadaluarsa! Silakan minta kode OTP baru.'];
    }

    if (trim($submittedOTP) !== (string)$otpData['otp']) {
        return ['status' => false, 'message' => 'Kode OTP tidak cocok! Silakan periksa kembali email Anda.'];
    }

    return ['status' => true, 'email' => $otpData['email']];
}

/**
 * Clear OTP Sessions
 */
function clearOTPSession() {
    unset($_SESSION['pending_otp']);
    unset($_SESSION['reset_password_otp']);
}
