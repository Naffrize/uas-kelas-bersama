<?php
// =====================================================================
// config/koneksi.example.php — TEMPLAT koneksi database (ikut di-commit)
//
// Cara pakai:
//   1. Salin file ini menjadi config/koneksi.php
//      (koneksi.php tercantum di .gitignore, jadi tidak ikut di-commit).
//   2. Sesuaikan $dbPort / $dbSandi dengan XAMPP di laptop masing-masing.
//
// File ini MENGEMBALIKAN objek PDO, dipakai dengan pola jobsheet:
//   $pdo = require __DIR__ . '/../config/koneksi.php';
// =====================================================================

// ===== BAGIAN 1: PENGATURAN DATABASE =====
// Nama variabel diawali "db" agar tidak menimpa variabel halaman
// (misalnya $password dari form login).
$dbHost  = '127.0.0.1';
$dbPort  = 3306;                 // XAMPP standar 3306; sebagian laptop memakai 3307
$dbNama  = 'uas_kelas_bersama';  // sama dengan CREATE DATABASE di database/schema.sql
$dbUser  = 'root';
$dbSandi = '';                   // XAMPP standar: user root tanpa password

// ===== BAGIAN 2: BUKA KONEKSI PDO =====
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbNama;charset=utf8mb4",
        $dbUser,
        $dbSandi,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // kesalahan SQL dilempar sebagai exception
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // setiap baris hasil query = array asosiatif
            PDO::ATTR_EMULATE_PREPARES   => false,                  // prepared statement asli milik MySQL
        ]
    );

    // Samakan zona waktu MySQL dengan PHP (Asia/Makassar = WITA = UTC+8)
    // agar NOW() dan CURRENT_TIMESTAMP sama dengan jam yang ditampilkan PHP.
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    // ===== BAGIAN 3: PESAN GAGAL YANG AMAN =====
    // Kode error MySQL yang paling sering terjadi saat instalasi diberi saran perbaikan.
    $saran = match ((int) $e->getCode()) {
        2002    => 'MySQL belum berjalan atau port salah. Start MySQL di XAMPP Control Panel, lalu periksa $dbPort.',
        1049    => 'Database belum ada. Impor database/schema.sql lewat phpMyAdmin.',
        1045    => 'User atau password database salah. Periksa $dbUser dan $dbSandi.',
        default => 'Periksa pengaturan di config/koneksi.php.',
    };

    if (PHP_SAPI === 'cli') {
        // Di terminal (misalnya tools/buat-admin.php) detail aman ditampilkan untuk pengembang.
        fwrite(STDERR, "Koneksi database gagal. $saran\nDetail: {$e->getMessage()}\n");
        exit(1);
    }

    // Di browser: detail teknis hanya ditulis ke log server (apache/logs/error.log).
    error_log('Koneksi database gagal: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Koneksi database gagal. ' . $saran);
}

// Password database tidak diperlukan lagi setelah koneksi terbuka.
unset($dbSandi);

return $pdo;
