<?php
// App Lock & Unlock Endpoint for LOCK & ROOM
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi login tidak ditemukan']);
    exit;
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? 'unlock';

if ($action === 'lock') {
    $_SESSION['is_app_locked'] = true;
    echo json_encode(['status' => 'success', 'locked' => true]);
    exit;
}

if ($action === 'check') {
    echo json_encode([
        'status' => 'success',
        'is_locked' => !empty($_SESSION['is_app_locked'])
    ]);
    exit;
}

// Unlock Action
$password = $_POST['password'] ?? '';

if (empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Kata sandi tidak boleh kosong']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $userRecord = $stmt->fetch();

    if ($userRecord && password_verify($password, $userRecord['password'])) {
        $_SESSION['is_app_locked'] = false;
        echo json_encode(['status' => 'success', 'message' => 'Aplikasi berhasil dibuka']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Kata sandi salah! Masukkan kata sandi login Anda yang benar.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
}
