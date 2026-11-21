<?php
// =====================================================================
// app/keamanan.php — dipanggil PALING AWAL oleh setiap halaman (pola P09–P10)
//
//   1. Zona waktu WITA
//   2. Header keamanan (nosniff, anti-clickjacking, CSP, tanpa cache)
//   3. Session dengan cookie httponly + samesite=Lax
//   4. Token CSRF: setiap POST wajib membawa token yang sama dengan session,
//      selain itu ditolak dengan HTTP 400 (aturan keamanan no. 3)
// =====================================================================
require_once __DIR__ . '/helper.php';

// ===== BAGIAN 1: ZONA WAKTU =====
date_default_timezone_set('Asia/Makassar');

// ===== BAGIAN 2: HEADER KEAMANAN =====
header('X-Content-Type-Options: nosniff');   // browser tidak menebak jenis file
header('X-Frame-Options: SAMEORIGIN');       // halaman tidak bisa dibingkai situs lain (clickjacking)
header('Referrer-Policy: same-origin');
// CSP: skrip hanya dari server sendiri + CDN Tailwind (jsDelivr, versi dipatok & dicek
// hash integrity). Skrip inline TETAP ditolak, sehingga XSS yang lolos pun sulit dijalankan.
// Gaya inline diizinkan karena Tailwind CDN menyusun CSS di browser lalu menyisipkannya
// sebagai elemen <style>; semua data tetap di-escape, jadi tidak ada gaya dari pengguna.
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; form-action 'self'; "
    . "frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
// Halaman pribadi tidak disimpan cache: setelah logout, tombol Back tidak menampilkan data lama.
header('Cache-Control: no-store, max-age=0');

// ===== BAGIAN 3: SESSION =====
if (session_status() === PHP_SESSION_NONE) {
    // Nama cookie khusus agar tidak tercampur session aplikasi lain di localhost (P10, phpMyAdmin).
    session_name('KELASBERSAMA');
    session_set_cookie_params([
        'lifetime' => 0,                                         // hilang saat browser ditutup
        'path' => '/',
        'httponly' => true,                                      // tidak dapat dibaca JavaScript
        'samesite' => 'Lax',                                     // tidak ikut pada POST dari situs lain
        'secure' => ($_SERVER['HTTPS'] ?? 'off') !== 'off',      // hanya HTTPS bila situs memakai HTTPS
    ]);
    ini_set('session.use_strict_mode', '1');                     // tolak ID session buatan penyerang
    session_start();
}

if (!is_string($_SESSION['csrf'] ?? null) || $_SESSION['csrf'] === '') {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ===== BAGIAN 4: CEK TOKEN CSRF UNTUK SETIAP POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiriman lebih besar dari post_max_size: PHP mengosongkan $_POST dan $_FILES.
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        tampilkanGalat(413, 'Kiriman terlalu besar', 'Ukuran file melebihi batas server. Batas maksimal 5 MB per file. Kembali, lalu pilih file yang lebih kecil.');
    }

    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        // Belum login (misalnya session habis lalu menekan Back): arahkan ke halaman masuk.
        if (empty($_SESSION['pengguna_id'])) {
            redirect303('login.php?sesi=habis');
        }
        tampilkanGalat(400, 'Permintaan tidak valid', 'Token keamanan tidak cocok. Buka kembali halaman sebelumnya, muat ulang, lalu coba lagi.');
    }
}

// Token CSRF dicetak di SETIAP form POST pada file .html:
//     <input type="hidden" name="csrf" value="{{csrf}}">
// Nilainya dikirim ke tampilan oleh dataUmum() di app/tampilan.php.
