<?php
// Configuration & Database Connection Handler
// LOCK & ROOM (L n' R)

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lockroom_db');
define('APP_NAME', 'LOCK & ROOM');
define('APP_SHORTNAME', "L n' R");
define('APP_TAGLINE', 'Solusi Cerdas Manajemen Kos & Kontrakan');
define('BASE_URL', '/lockroom');

// OneSignal Push Notifications Configuration
// Dapatkan App ID dan REST API Key dari https://onesignal.com/ (Gratis)
define('ONESIGNAL_APP_ID', 'YOUR_ONESIGNAL_APP_ID'); 
define('ONESIGNAL_REST_API_KEY', 'YOUR_ONESIGNAL_REST_API_KEY');

function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check if DB doesn't exist, redirect to installer or return null
            return null;
        }
    }
    return $pdo;
}

// Function to check if app is installed
function isInstalled() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}
