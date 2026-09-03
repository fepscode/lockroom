<?php
// Authentication & Session Helper
// LOCK & ROOM (L n' R)

if (session_status() === PHP_SESSION_NONE) {
    // Session Security & Cookie Hardening Flags
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_trans_sid', 0);
    ini_set('session.cookie_httponly', 1);

    // Auto-detect HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 86400 * 7, // 7 days session
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

if (!ob_get_level()) {
    ob_start();
}

require_once __DIR__ . '/../config/database.php';


function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'phone' => $_SESSION['user_phone'] ?? '',
        'role' => $_SESSION['user_role'],
        'avatar' => $_SESSION['user_avatar'] ?? null
    ];
}

function isSuperAdmin() {
    return (isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin');
}

function requireLogin($requiredRole = null) {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.';
        $redirectRole = $requiredRole ?: 'pemilik';
        header("Location: " . BASE_URL . "/auth/login.php?role=" . $redirectRole);
        exit;
    }

    if ($requiredRole) {
        $userRole = $_SESSION['user_role'] ?? '';
        
        // Superadmin has access to both 'pemilik' and 'superadmin' areas
        if ($requiredRole === 'pemilik' && ($userRole === 'pemilik' || $userRole === 'superadmin')) {
            return;
        }

        if ($userRole !== $requiredRole) {
            $_SESSION['flash_error'] = 'Anda tidak memiliki hak akses untuk membuka halaman tersebut.';
            if ($userRole === 'pemilik' || $userRole === 'superadmin') {
                header("Location: " . BASE_URL . "/owner/index.php");
            } else {
                header("Location: " . BASE_URL . "/tenant/index.php");
            }
            exit;
        }
    }
}

function setFlash($key, $message) {
    $_SESSION['flash_' . $key] = $message;
}

function getFlash($key) {
    if (isset($_SESSION['flash_' . $key])) {
        $msg = $_SESSION['flash_' . $key];
        unset($_SESSION['flash_' . $key]);
        return $msg;
    }
    return null;
}

function formatRupiah($nominal) {
    return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
}

function formatDateIndo($dateStr) {
    if (!$dateStr) return '-';
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($dateStr);
    $d = date('j', $timestamp);
    $m = $months[(int)date('n', $timestamp)];
    $y = date('Y', $timestamp);
    return "$d $m $y";
}

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Format string ke Title Case (Setiap huruf awal kata menjadi huruf kapital)
 */
function formatTitleCase($str) {
    if (!$str) return '';
    $clean = str_replace('_', ' ', $str);
    return mb_convert_case($clean, MB_CASE_TITLE, "UTF-8");
}

/**
 * Get Property Image URL (Uploaded or Curated Default)
 */
function getPropertyImage($imagePath = null, $type = 'kos_campur') {
    if (!empty($imagePath)) {
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }
        $fullLocalPath = __DIR__ . '/../' . ltrim($imagePath, '/');
        if (file_exists($fullLocalPath)) {
            return BASE_URL . '/' . ltrim($imagePath, '/');
        }
    }

    // Curated High Quality Fallbacks
    $defaults = [
        'kos_putri' => 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=800&auto=format&fit=crop&q=80',
        'kos_putra' => 'https://images.unsplash.com/photo-1568605117036-5fe5e7bab0b7?w=800&auto=format&fit=crop&q=80',
        'kos_campur' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=800&auto=format&fit=crop&q=80',
        'kontrakan' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&auto=format&fit=crop&q=80'
    ];

    return $defaults[$type] ?? $defaults['kos_campur'];
}

/**
 * Get Room Image URL (Uploaded or Curated Default)
 */
function getRoomImage($imagePath = null, $roomType = 'Standard Room', $index = 0) {
    if (!empty($imagePath)) {
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }
        $fullLocalPath = __DIR__ . '/../' . ltrim($imagePath, '/');
        if (file_exists($fullLocalPath)) {
            return BASE_URL . '/' . ltrim($imagePath, '/');
        }
    }

    // Curated High Quality Room Fallbacks
    $defaults = [
        'Deluxe Room' => 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=800&auto=format&fit=crop&q=80',
        'Premium Room' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&auto=format&fit=crop&q=80',
        'Standard Room' => 'https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?w=800&auto=format&fit=crop&q=80'
    ];

    $generalPool = [
        'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1540518614846-7ede433c4550?w=800&auto=format&fit=crop&q=80'
    ];

    if (isset($defaults[$roomType])) {
        return $defaults[$roomType];
    }

    return $generalPool[$index % count($generalPool)];
}

/**
 * Get User Profile Avatar URL
 */
function getUserAvatar($avatarPath = null, $userName = 'User') {
    if (!empty($avatarPath)) {
        if (str_starts_with($avatarPath, 'http://') || str_starts_with($avatarPath, 'https://')) {
            return $avatarPath;
        }
        $fullLocalPath = __DIR__ . '/../' . ltrim($avatarPath, '/');
        if (file_exists($fullLocalPath)) {
            return BASE_URL . '/' . ltrim($avatarPath, '/');
        }
    }

    // High quality UI Avatars fallback
    $initials = urlencode($userName);
    return "https://ui-avatars.com/api/?name={$initials}&background=4f46e5&color=fff&size=256&bold=true";
}

/**
 * Get Client Real IP Address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Check Brute Force Attack on Login (Max 3 failed attempts in 5 minutes)
 */
function checkBruteForceLockout($email) {
    $pdo = getDBConnection();
    if (!$pdo) return ['is_blocked' => false, 'attempts' => 0];

    $ip = getClientIP();
    $maxAttempts = 3;
    $lockoutMinutes = 5;

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as failed_count, MAX(attempted_at) as last_attempt 
                                FROM login_attempts 
                                WHERE (email = ? OR ip_address = ?) 
                                  AND attempted_at > (NOW() - INTERVAL ? MINUTE)");
        $stmt->execute([$email, $ip, $lockoutMinutes]);
        $data = $stmt->fetch();

        $failedCount = (int)($data['failed_count'] ?? 0);
        $lastAttempt = $data['last_attempt'] ?? null;

        if ($failedCount >= $maxAttempts && $lastAttempt) {
            $lastTime = strtotime($lastAttempt);
            $unlockTime = $lastTime + ($lockoutMinutes * 60);
            $secondsRemaining = max(0, $unlockTime - time());
            $minutesRemaining = ceil($secondsRemaining / 60);

            if ($secondsRemaining > 0) {
                return [
                    'is_blocked' => true,
                    'attempts' => $failedCount,
                    'remaining_minutes' => $minutesRemaining,
                    'remaining_seconds' => $secondsRemaining
                ];
            }
        }

        return [
            'is_blocked' => false,
            'attempts' => $failedCount
        ];

    } catch (Exception $e) {
        return ['is_blocked' => false, 'attempts' => 0];
    }
}

/**
 * Record a Failed Login Attempt
 */
function recordFailedLogin($email) {
    $pdo = getDBConnection();
    if (!$pdo) return;

    $ip = getClientIP();
    try {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)");
        $stmt->execute([$ip, $email]);
    } catch (Exception $e) {}
}

/**
 * Clear Failed Login Attempts upon Successful Login
 */
function clearLoginAttempts($email) {
    $pdo = getDBConnection();
    if (!$pdo) return;

    $ip = getClientIP();
    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ? OR ip_address = ?");
        $stmt->execute([$email, $ip]);
    } catch (Exception $e) {}
}

