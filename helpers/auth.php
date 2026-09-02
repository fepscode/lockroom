<?php
// Authentication & Session Helper
// LOCK & ROOM (L n' R)

if (session_status() === PHP_SESSION_NONE) {
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

function requireLogin($requiredRole = null) {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.';
        $redirectRole = $requiredRole ?: 'pemilik';
        header("Location: " . BASE_URL . "/auth/login.php?role=" . $redirectRole);
        exit;
    }

    if ($requiredRole && $_SESSION['user_role'] !== $requiredRole) {
        $_SESSION['flash_error'] = 'Anda tidak memiliki hak akses untuk membuka halaman tersebut.';
        if ($_SESSION['user_role'] === 'pemilik') {
            header("Location: " . BASE_URL . "/owner/index.php");
        } else {
            header("Location: " . BASE_URL . "/tenant/index.php");
        }
        exit;
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
