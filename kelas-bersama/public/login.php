<?php
// =====================================================================
// public/login.php — LOGIKA halaman masuk (F-02, F-03, F-07; diagram 3)
// Tampilan (HTML): app/tampilan/login.html (desain 01-login).
// Satu tombol "Masuk": sistem mengarahkan ke dashboard sesuai peran akun.
// =====================================================================

// ===== BAGIAN 1: SESSION, CSRF, DAN GUARD TAMU =====
require __DIR__ . '/../app/keamanan.php';
require __DIR__ . '/../app/auth.php';
wajibTamu(); // sudah login → langsung ke dashboard

// Hash bcrypt pengganti: dipakai saat email tidak terdaftar, agar lama proses
// sama dengan email terdaftar (penyerang tidak dapat menebak email dari waktu respons).
const HASH_PENGGANTI = '$2y$10$Rf0uwTz1/f9JM/pu33VQkO/oZBP7NlRy0xNiVUXxUON1aJVgdnxP2';

$email = '';
$ingat = false;
$pesanGalat = '';

// ===== BAGIAN 2: PERIKSA EMAIL DAN KATA SANDI =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(ambilPost('email'));
    $sandi = is_string($_POST['sandi'] ?? null) ? $_POST['sandi'] : ''; // kata sandi tidak di-trim
    $ingat = ambilPost('ingat') === '1';

    $query = db()->prepare('SELECT id, nama, peran, password_hash FROM pengguna WHERE email = ?');
    $query->execute([$email]);
    $akun = $query->fetch();

    // password_verify membandingkan kata sandi yang diketik dengan hash di database.
    $sandiBenar = password_verify($sandi, $akun ? $akun['password_hash'] : HASH_PENGGANTI);
    if ($akun && $sandiBenar) {
        // Hash lama diperbarui otomatis bila standar PHP berubah.
        if (password_needs_rehash($akun['password_hash'], PASSWORD_DEFAULT)) {
            $ubah = db()->prepare('UPDATE pengguna SET password_hash = ? WHERE id = ?');
            $ubah->execute([password_hash($sandi, PASSWORD_DEFAULT), $akun['id']]);
        }
        masukkanPengguna($akun, $ingat); // session_regenerate_id(true) ada di dalamnya
        redirect303(alamatSetelahLogin($akun));
    }
    // Aturan keamanan no. 10: pesan selalu sama agar tidak membocorkan email mana yang terdaftar.
    $pesanGalat = 'Email atau kata sandi salah.';
}

// ===== BAGIAN 3: PESAN DARI HALAMAN LAIN =====
$pesanInfo = match (true) {
    isset($_GET['keluar']) => 'Anda sudah keluar. Sampai jumpa lagi.',
    isset($_GET['sesi']) => 'Sesi Anda berakhir. Silakan masuk kembali.',
    default => '',
};

// ===== BAGIAN 4: TAMPILKAN HTML =====
tampilkanHalaman('login', [
    'judulHalaman' => 'Masuk',
    'tataLetak' => 'tamu',
    'email' => $email,
    'adaEmail' => $email !== '', // fokus otomatis ke kata sandi bila email sudah terisi
    'ingat' => $ingat,
    'pesanGalat' => $pesanGalat,
    'pesanInfo' => $pesanInfo,
]);
