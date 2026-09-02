<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDBConnection();
if ($pdo) {
    // Check properties table
    $stmtProp = $pdo->query("SHOW COLUMNS FROM properties LIKE 'image'");
    if (!$stmtProp->fetch()) {
        $pdo->exec("ALTER TABLE properties ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER rules");
        echo "Added column 'image' to properties table.\n";
    } else {
        echo "Column 'image' already exists in properties.\n";
    }

    // Check rooms table
    $stmtRoom = $pdo->query("SHOW COLUMNS FROM rooms LIKE 'image'");
    if (!$stmtRoom->fetch()) {
        $pdo->exec("ALTER TABLE rooms ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER status");
        echo "Added column 'image' to rooms table.\n";
    } else {
        echo "Column 'image' already exists in rooms.\n";
    }
}
