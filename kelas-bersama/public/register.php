<?php
// =====================================================================
// public/register.php — LOGIKA registrasi mahasiswa (F-01; diagram 3)
// Tampilan (HTML): app/tampilan/register.html (desain 02-register).
// Berhasil → login otomatis → halaman "Pilih mata kuliah".
// Admin tidak dapat mendaftar lewat halaman ini (dibuat dengan tools/buat-admin.php).
// =====================================================================

// ===== BAGIAN 1: SESSION, CSRF, DAN GUARD TAMU =====
require __DIR__ . '/../app/keamanan.php';
require __DIR__ . '/../app/auth.php';
wajibTamu();

$nilai = ['nama' => '', 'email' => ''];
$galat = [];

// ===== BAGIAN 2: VALIDASI DI SERVER =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nilai['nama'] = ambilPost('nama');
    $nilai['email'] = mb_strtolower(ambilPost('email'));
    $sandi = is_string($_POST['sandi'] ?? null) ? $_POST['sandi'] : '';
    $konfirmasi = is_string($_POST['konfirmasi'] ?? null) ? $_POST['konfirmasi'] : '';

    if ($nilai['nama'] === '' || mb_strlen($nilai['nama']) > 100) {
        $galat['nama'] = 'Nama lengkap wajib diisi, maksimal 100 karakter.';
    }
    if (!filter_var($nilai['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($nilai['email']) > 120) {
        $galat['email'] = 'Masukkan email yang valid, maksimal 120 karakter.';
    }
    // Minimal 8 karakter; maksimal 72 byte karena bcrypt mengabaikan sisanya.
    if (mb_strlen($sandi) < 8 || strlen($sandi) > 72) {
        $galat['sandi'] = 'Kata sandi harus 8–72 karakter.';
    } elseif (!hash_equals($sandi, $konfirmasi)) {
        $galat['konfirmasi'] = 'Konfirmasi kata sandi tidak sama.';
    }
    if (!isset($galat['email'])) {
        $query = db()->prepare('SELECT id FROM pengguna WHERE email = ?');
        $query->execute([$nilai['email']]);
        if ($query->fetch()) {
            $galat['email'] = 'Email sudah terdaftar. Silakan masuk.';
        }
    }

    // ===== BAGIAN 3: SIMPAN AKUN (peran selalu mahasiswa) =====
    if (!$galat) {
        try {
            $query = db()->prepare("INSERT INTO pengguna (nama, email, password_hash, peran) VALUES (?, ?, ?, 'mahasiswa')");
            $query->execute([$nilai['nama'], $nilai['email'], password_hash($sandi, PASSWORD_DEFAULT)]);
            masukkanPengguna(['id' => (int) db()->lastInsertId(), 'peran' => 'mahasiswa']);
            flash('sukses', 'Akun berhasil dibuat', 'Pilih mata kuliah yang kamu ikuti, lalu tunggu persetujuan admin.', true);
            redirect303('mahasiswa/matkul.php');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $galat['email'] = 'Email sudah terdaftar. Silakan masuk.'; // didaftarkan bersamaan oleh proses lain
        }
    }
}

// ===== BAGIAN 4: TAMPILKAN HTML =====
tampilkanHalaman('register', [
    'judulHalaman' => 'Daftar akun mahasiswa',
    'tataLetak' => 'tamu',
    'nilai' => $nilai,
    'galat' => $galat,
    'adaGalat' => (bool) $galat,
]);
