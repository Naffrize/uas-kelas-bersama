<?php
// =====================================================================
// public/profil-sandi.php — ganti kata sandi: lama + baru + konfirmasi (F-05)
// =====================================================================
require __DIR__ . '/../app/keamanan.php'; // POST tanpa token CSRF → 400
require __DIR__ . '/../app/auth.php';
$pengguna = wajibLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('profil.php');
}

// ===== BAGIAN 1: BACA INPUT (kata sandi tidak di-trim) =====
$sandiLama = is_string($_POST['sandi_lama'] ?? null) ? $_POST['sandi_lama'] : '';
$sandiBaru = is_string($_POST['sandi_baru'] ?? null) ? $_POST['sandi_baru'] : '';
$konfirmasi = is_string($_POST['konfirmasi_sandi'] ?? null) ? $_POST['konfirmasi_sandi'] : '';

// ===== BAGIAN 2: VALIDASI =====
$query = db()->prepare('SELECT password_hash FROM pengguna WHERE id = ?');
$query->execute([$pengguna['id']]);
$hashSaatIni = (string) $query->fetchColumn();

$galat = [];
if (!password_verify($sandiLama, $hashSaatIni)) {
    $galat['sandi_lama'] = 'Kata sandi lama tidak cocok.';
}
if (mb_strlen($sandiBaru) < 8 || strlen($sandiBaru) > 72) {
    $galat['sandi_baru'] = 'Kata sandi baru harus 8–72 karakter.';
} elseif (!hash_equals($sandiBaru, $konfirmasi)) {
    $galat['konfirmasi_sandi'] = 'Konfirmasi kata sandi tidak sama.';
} elseif (!isset($galat['sandi_lama']) && hash_equals($sandiLama, $sandiBaru)) {
    $galat['sandi_baru'] = 'Gunakan kata sandi yang berbeda dari kata sandi lama.';
}
if ($galat) {
    simpanFormLama('sandi', [], $galat);
    flash('galat', 'Kata sandi belum diganti', 'Periksa kembali isian yang ditandai.');
    redirect303('profil.php');
}

// ===== BAGIAN 3: SIMPAN HASH BARU =====
$query = db()->prepare('UPDATE pengguna SET password_hash = ? WHERE id = ?');
$query->execute([password_hash($sandiBaru, PASSWORD_DEFAULT), $pengguna['id']]);

// Perangkat yang "diingat" harus login ulang; ID session diganti.
hapusSemuaTokenIngat($pengguna['id']);
session_regenerate_id(true);

flash('sukses', 'Kata sandi diganti', 'Gunakan kata sandi baru saat masuk berikutnya.', true);
redirect303('profil.php');
