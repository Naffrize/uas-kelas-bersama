<?php
// =====================================================================
// public/profil.php — LOGIKA profil admin & mahasiswa (F-05, F-06)
// Tampilan (HTML): app/tampilan/profil.html (desain 17 & 28; modal m03/m04/m05, m11, m12).
// Form dikirim ke profil-simpan.php, profil-sandi.php, dan profil-foto.php.
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../app/keamanan.php';
require __DIR__ . '/../app/auth.php';
$pengguna = wajibLogin();
$adalahAdminProfil = $pengguna['peran'] === 'admin';

// ===== BAGIAN 2: DATA PENDUKUNG =====
if ($adalahAdminProfil) {
    $query = db()->prepare('SELECT COUNT(*) FROM mata_kuliah WHERE dibuat_oleh = ?');
    $query->execute([$pengguna['id']]);
    $infoMataKuliah = (int) $query->fetchColumn() . ' mata kuliah dibuat · ' . count(semuaMataKuliah()) . ' mata kuliah dikelola';
} else {
    $namaMk = array_column(mataKuliahDisetujui($pengguna['id']), 'nama');
    $infoMataKuliah = $namaMk ? implode(', ', $namaMk) : 'Belum ada mata kuliah yang disetujui';
}

// Isian lama bila validasi di profil-simpan.php / profil-sandi.php gagal.
$formLama = ambilFormLama();

// ===== BAGIAN 3: TAMPILKAN HTML =====
tampilkanHalaman('profil', [
    'judulHalaman' => $adalahAdminProfil ? 'Profil admin' : 'Profil mahasiswa',
    'menuAktif' => 'profil',
    'pengguna' => $pengguna,
    'adalahAdminProfil' => $adalahAdminProfil,
    'infoMataKuliah' => $infoMataKuliah,
    'nilaiNama' => $formLama['nama'] === 'profil' ? $formLama['nilai']['nama'] : $pengguna['nama'],
    'galatProfil' => $formLama['nama'] === 'profil' ? $formLama['galat'] : [],
    'galatSandi' => $formLama['nama'] === 'sandi' ? $formLama['galat'] : [],
    'fotoSaatIni' => urlFoto($pengguna['foto']),
    'inisialPengguna' => inisial($pengguna['nama']), // ditampilkan bila belum ada foto
]);
