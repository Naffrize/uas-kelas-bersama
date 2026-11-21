<?php
// =====================================================================
// public/mahasiswa/hasil-hapus.php — hapus hasil kerja milik sendiri (F-44; diagram 7)
// Kolom hasil_* dikosongkan dulu, file fisik dihapus SETELAH UPDATE berhasil.
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php'; // POST tanpa token CSRF → 400
require __DIR__ . '/../../app/auth.php';
require __DIR__ . '/../../app/upload.php';
$pengguna = wajibMahasiswa();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('mahasiswa/tugas.php');
}

// ===== BAGIAN 2: PASTIKAN TUGAS BOLEH DIAKSES (cegah IDOR) =====
$idTugas = ambilId(ambilPost('tugas_id'));
$tugas = $idTugas > 0 ? tugasUntukMahasiswa($idTugas, $pengguna['id']) : null;
if ($tugas === null) {
    tidakDitemukan('Tugas tidak ditemukan.');
}

// ===== BAGIAN 3: KOSONGKAN DATA HASIL (hanya baris milik pengguna session) =====
$pdo = db();
try {
    $pdo->beginTransaction();
    $query = $pdo->prepare('SELECT hasil_file FROM tugas_mahasiswa WHERE tugas_id = ? AND pengguna_id = ? FOR UPDATE');
    $query->execute([$idTugas, $pengguna['id']]);
    $fileLama = $query->fetchColumn() ?: null;

    $ubah = $pdo->prepare('UPDATE tugas_mahasiswa
        SET hasil_file = NULL, hasil_nama = NULL, hasil_mime = NULL, hasil_ukuran = NULL, diunggah = NULL
        WHERE tugas_id = ? AND pengguna_id = ?');
    $ubah->execute([$idTugas, $pengguna['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

hapusFile('hasil', $fileLama);

flash('sukses', 'Hasil kerja dihapus', $fileLama ? 'Kamu dapat mengunggah file baru kapan saja.' : 'Tidak ada file yang perlu dihapus.');
redirect303('mahasiswa/tugas-detail.php?id=' . $idTugas);
