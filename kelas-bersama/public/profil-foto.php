<?php
// =====================================================================
// public/profil-foto.php — unggah / hapus foto profil (F-06; pola P10)
// Foto disimpan di public/uploads/foto dengan nama acak; .htaccess folder itu
// menolak eksekusi skrip. Foto lama dihapus SETELAH UPDATE berhasil.
// =====================================================================
require __DIR__ . '/../app/keamanan.php'; // POST tanpa token CSRF → 400
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/upload.php';
$pengguna = wajibLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('profil.php');
}

$fotoLama = $pengguna['foto'];

// ===== BAGIAN 1: HAPUS FOTO =====
if (ambilPost('aksi') === 'hapus') {
    $query = db()->prepare('UPDATE pengguna SET foto = NULL WHERE id = ?');
    $query->execute([$pengguna['id']]);
    hapusFile('foto', $fotoLama);
    flash('sukses', 'Foto profil dihapus', 'Avatar kini memakai inisial nama Anda.');
    redirect303('profil.php');
}

// ===== BAGIAN 2: VALIDASI & SIMPAN FOTO BARU =====
try {
    $file = simpanUnggahan('foto', 'foto');
} catch (UnggahanGagal $e) {
    // Desain m05 "Foto terlalu besar" bila ukuran melebihi batas.
    flash('galat', $e->alasan === 'ukuran' ? 'Foto terlalu besar' : 'Foto belum dapat diunggah', $e->getMessage(), true);
    redirect303('profil.php');
}
if ($file === null) {
    flash('galat', 'Belum ada foto', 'Pilih foto JPG, PNG, atau WEBP terlebih dulu.', true);
    redirect303('profil.php');
}

try {
    $query = db()->prepare('UPDATE pengguna SET foto = ? WHERE id = ?');
    $query->execute([$file['file'], $pengguna['id']]);
} catch (Throwable $e) {
    hapusFile('foto', $file['file']);
    throw $e;
}
hapusFile('foto', $fotoLama);

flash('sukses', 'Foto profil diperbarui', 'Foto baru sudah tampil di profil dan navigasi.', true);
redirect303('profil.php');
