<?php
// =====================================================================
// public/mahasiswa/progres-simpan.php — simpan progres pribadi (F-41, F-42; R-05, R-06)
// Ceklis: centang → selesai, hapus centang → belum_mulai.
// Kartu "Progres saya": status dipilih langsung (belum_mulai / dikerjakan / selesai).
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php'; // POST tanpa token CSRF → 400
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibMahasiswa();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('mahasiswa/tugas.php');
}

// ===== BAGIAN 2: BACA & PERIKSA INPUT =====
$idTugas = ambilId(ambilPost('tugas_id'));
$status = ambilPost('aksi') === 'ceklis'
    ? (ambilPost('selesai') === '1' ? 'selesai' : 'belum_mulai')
    : ambilPost('status');
if (!array_key_exists($status, LABEL_PROGRES)) {
    tampilkanGalat(400, 'Permintaan tidak valid', 'Status progres tidak dikenal.');
}

// IDOR: tugas harus berasal dari mata kuliah yang DISETUJUI untuk mahasiswa di session ini.
$tugas = $idTugas > 0 ? tugasUntukMahasiswa($idTugas, $pengguna['id']) : null;
if ($tugas === null) {
    tidakDitemukan('Tugas tidak ditemukan.');
}

// ===== BAGIAN 3: SIMPAN (baris dibuat saat pertama kali diubah) =====
// Progres bersifat pribadi: hanya baris (tugas, pengguna session) yang berubah (R-06).
$query = db()->prepare('INSERT INTO tugas_mahasiswa (tugas_id, pengguna_id, status) VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE status = VALUES(status)');
$query->execute([$idTugas, $pengguna['id'], $status]);

flash('sukses', 'Progres disimpan', '"' . $tugas['judul'] . '" → ' . LABEL_PROGRES[$status] . '.');

// ===== BAGIAN 4: KEMBALI KE HALAMAN ASAL (hanya alamat internal yang diizinkan) =====
$kembali = alamatKembali(
    ambilPost('kembali'),
    ['mahasiswa/tugas.php', 'mahasiswa/dashboard.php', 'mahasiswa/tugas-detail.php'],
    'mahasiswa/tugas-detail.php?id=' . $idTugas
);
redirect303($kembali);
