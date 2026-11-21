<?php
// =====================================================================
// public/admin/tugas-hapus.php — hapus tugas (F-32; diagram 6; R-10)
// Transaksi: kumpulkan nama file → DELETE (CASCADE ke progres & hasil) → COMMIT
// → baru hapus file fisik lampiran + semua hasil kerja.
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php'; // POST tanpa token CSRF → 400
require __DIR__ . '/../../app/auth.php';
require __DIR__ . '/../../app/upload.php';
$pengguna = wajibAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('admin/tugas.php');
}

$idTugas = ambilId(ambilPost('id'));
$pdo = db();

// ===== BAGIAN 2: HAPUS DI DALAM TRANSAKSI =====
try {
    $pdo->beginTransaction();
    $query = $pdo->prepare('SELECT judul, lampiran_file FROM tugas WHERE id = ? FOR UPDATE');
    $query->execute([$idTugas]);
    $tugas = $query->fetch();
    if (!$tugas) {
        $pdo->rollBack();
        tidakDitemukan('Tugas tidak ditemukan atau sudah dihapus.');
    }

    $query = $pdo->prepare('SELECT hasil_file FROM tugas_mahasiswa WHERE tugas_id = ? AND hasil_file IS NOT NULL');
    $query->execute([$idTugas]);
    $daftarHasil = $query->fetchAll(PDO::FETCH_COLUMN);

    $pdo->prepare('DELETE FROM tugas WHERE id = ?')->execute([$idTugas]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // gagal → file tidak disentuh
    }
    throw $e;
}

// ===== BAGIAN 3: HAPUS FILE FISIK SETELAH COMMIT =====
hapusFile('lampiran', $tugas['lampiran_file']);
foreach ($daftarHasil as $namaFile) {
    hapusFile('hasil', $namaFile);
}

flash('sukses', 'Tugas dihapus', '"' . $tugas['judul'] . '" beserta progres dan file terkait sudah dihapus.');
redirect303('admin/tugas.php');
