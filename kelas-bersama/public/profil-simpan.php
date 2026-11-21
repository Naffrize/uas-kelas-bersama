<?php
// =====================================================================
// public/profil-simpan.php — ubah nama lengkap (F-05). Email & peran tidak dapat diubah (R-01).
// =====================================================================
require __DIR__ . '/../app/keamanan.php'; // POST tanpa token CSRF → 400
require __DIR__ . '/../app/auth.php';
$pengguna = wajibLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('profil.php');
}

// ===== BAGIAN 1: VALIDASI =====
$nama = ambilPost('nama');
if ($nama === '' || mb_strlen($nama) > 100) {
    simpanFormLama('profil', ['nama' => $nama], ['nama' => 'Nama lengkap wajib diisi, maksimal 100 karakter.']);
    redirect303('profil.php');
}

// ===== BAGIAN 2: SIMPAN (hanya akun milik session ini) =====
$query = db()->prepare('UPDATE pengguna SET nama = ? WHERE id = ?');
$query->execute([$nama, $pengguna['id']]);

flash('sukses', 'Profil diperbarui', 'Informasi akun Anda berhasil disimpan.', true); // desain m12
redirect303('profil.php');
