-- Database Schema for LOCK & ROOM (L n' R)
CREATE DATABASE IF NOT EXISTS `lockroom_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lockroom_db`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `phone` VARCHAR(25) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('superadmin', 'pemilik', 'penyewa') NOT NULL DEFAULT 'penyewa',
    `subscription_status` ENUM('trial', 'active', 'expired') NOT NULL DEFAULT 'trial',
    `trial_ends_at` DATETIME NULL,
    `subscription_ends_at` DATETIME NULL,
    `subscription_plan` VARCHAR(50) DEFAULT 'Free Trial 14 Hari',
    `avatar` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 2. Properties Table (Kos / Kontrakan milik Owner)
CREATE TABLE IF NOT EXISTS `properties` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `type` ENUM('kos_putra', 'kos_putri', 'kos_campur', 'kontrakan') NOT NULL DEFAULT 'kos_campur',
    `address` TEXT NOT NULL,
    `city` VARCHAR(100) NOT NULL DEFAULT 'Jakarta',
    `description` TEXT,
    `rules` TEXT,
    `image` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Rooms Table (Kamar)
CREATE TABLE IF NOT EXISTS `rooms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `property_id` INT NOT NULL,
    `room_number` VARCHAR(50) NOT NULL,
    `room_type` VARCHAR(100) NOT NULL DEFAULT 'Standard Room',
    `price_monthly` DECIMAL(12, 2) NOT NULL,
    `price_yearly` DECIMAL(12, 2) DEFAULT NULL,
    `size` VARCHAR(50) DEFAULT '3x4 meter',
    `facilities` TEXT, -- JSON or comma separated (AC, WiFi, Kamar Mandi Dalam, Kasur, Lemari, dll)
    `status` ENUM('tersedia', 'terisi', 'perbaikan') NOT NULL DEFAULT 'tersedia',
    `image` VARCHAR(255) DEFAULT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Leases Table (Data Sewa / Kontrak)
CREATE TABLE IF NOT EXISTS `leases` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `tenant_id` INT NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `rent_type` ENUM('bulanan', 'tahunan') NOT NULL DEFAULT 'bulanan',
    `price` DECIMAL(12, 2) NOT NULL,
    `status` ENUM('aktif', 'selesai', 'dibatalkan', 'menunggu_konfirmasi') NOT NULL DEFAULT 'aktif',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tenant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Bills Table (Tagihan Sewa & Tambahan)
CREATE TABLE IF NOT EXISTS `bills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lease_id` INT NOT NULL,
    `tenant_id` INT NOT NULL,
    `bill_code` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(150) NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `due_date` DATE NOT NULL,
    `status` ENUM('belum_bayar', 'menunggu_verifikasi', 'lunas', 'ditolak') NOT NULL DEFAULT 'belum_bayar',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`lease_id`) REFERENCES `leases`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tenant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Payments Table (Pembayaran & Bukti Transfer)
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bill_id` INT NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Transfer Bank BCA',
    `payment_date` DATETIME NOT NULL,
    `proof_image` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT,
    `verified_by` INT DEFAULT NULL,
    `verified_at` DATETIME DEFAULT NULL,
    `status` ENUM('menunggu', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Complaints Table (Pengaduan & Perbaikan)
CREATE TABLE IF NOT EXISTS `complaints` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `room_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `priority` ENUM('rendah', 'sedang', 'tinggi') NOT NULL DEFAULT 'sedang',
    `status` ENUM('menunggu', 'diproses', 'selesai', 'ditolak') NOT NULL DEFAULT 'menunggu',
    `admin_response` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Broadcasts Table (Pengumuman & Notifikasi Massal dari Pemilik ke Penghuni)
CREATE TABLE IF NOT EXISTS `broadcasts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id` INT NOT NULL,
    `property_id` INT DEFAULT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'peringatan', 'penting', 'kegiatan') NOT NULL DEFAULT 'info',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Subscription Orders Table (Pesanan Langganan Pemilik Kos via QRIS)
CREATE TABLE IF NOT EXISTS `subscription_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id` INT NOT NULL,
    `order_code` VARCHAR(50) NOT NULL UNIQUE,
    `plan_name` VARCHAR(100) NOT NULL,
    `duration_days` INT NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'QRIS GoPay',
    `proof_image` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('menunggu_konfirmasi', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu_konfirmasi',
    `admin_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `verified_at` DATETIME DEFAULT NULL,
    `verified_by` INT DEFAULT NULL,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. System Settings Table (Konfigurasi QRIS Merchant & Pengaturan Global)
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Login Attempts Table (Proteksi Serangan Brute Force Kata Sandi)
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip_email_time` (`ip_address`, `email`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


