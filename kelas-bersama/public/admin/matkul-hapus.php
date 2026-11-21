<?php
// =====================================================================
// public/admin/matkul-hapus.php — hapus mata kuliah (F-13; diagram 5; R-10)
// 1. Kumpulkan nama file lampiran & hasil dari semua tugas mata kuliah ini
// 2. BEGIN → DELETE mata_kuliah (CASCADE ke tugas, pendaftaran, tugas_mahasiswa) → COMMIT
// 3. Baru hapus file fisik. Gagal → ROLLBACK, file tidak disentuh.
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php'; // POST tanpa token CSRF → 400
require __DIR__ . '/../../app/auth.php';
require __DIR__ . '/../../app/upload.php';
$pengguna = wajibAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('admin/matkul.php');
}

$idMataKuliah = ambilId(ambilPost('id'));
$pdo = db();

// ===== BAGIAN 2: TRANSAKSI =====
try {
    $pdo->beginTransaction();
    $query = $pdo->prepare('SELECT nama FROM mata_kuliah WHERE id = ? FOR UPDATE');
    $query->execute([$idMataKuliah]);
    $namaMataKuliah = $query->fetchColumn();
    if ($namaMataKuliah === false) {
        $pdo->rollBack();
        tidakDitemukan('Mata kuliah tidak ditemukan atau sudah dihapus.');
    }

    $query = $pdo->prepare('SELECT lampiran_file FROM tugas WHERE mata_kuliah_id = ? AND lampiran_file IS NOT NULL');
    $query->execute([$idMataKuliah]);
    $daftarLampiran = $query->fetchAll(PDO::FETCH_COLUMN);

    $query = $pdo->prepare('SELECT tm.hasil_file FROM tugas_mahasiswa tm JOIN tugas t ON t.id = tm.tugas_id
        WHERE t.mata_kuliah_id = ? AND tm.hasil_file IS NOT NULL');
    $query->execute([$idMataKuliah]);
    $daftarHasil = $query->fetchAll(PDO::FETCH_COLUMN);

    $pdo->prepare('DELETE FROM mata_kuliah WHERE id = ?')->execute([$idMataKuliah]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

// ===== BAGIAN 3: HAPUS FILE FISIK SETELAH COMMIT (tidak ada file yatim, U-16) =====
foreach ($daftarLampiran as $namaFile) {
    hapusFile('lampiran', $namaFile);
}
foreach ($daftarHasil as $namaFile) {
    hapusFile('hasil', $namaFile);
}

flash('sukses', 'Mata kuliah dihapus', $namaMataKuliah . ' beserta tugas, pendaftaran, dan file terkait sudah dihapus.', true);
redirect303('admin/matkul.php');
