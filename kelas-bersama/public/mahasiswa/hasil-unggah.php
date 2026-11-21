<?php
// =====================================================================
// public/mahasiswa/hasil-unggah.php — unggah / ganti hasil kerja (F-43..F-45, R-07, R-08)
// Diagram 7 (bagian upload) & diagram 8 (validasi di app/upload.php).
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
$alamatDetail = 'mahasiswa/tugas-detail.php?id=' . $idTugas;

// ===== BAGIAN 3: VALIDASI & SIMPAN FILE =====
try {
    $file = simpanUnggahan('hasil', 'hasil');
} catch (UnggahanGagal $e) {
    flash('galat', 'File belum dapat diunggah', $e->getMessage(), true); // desain m02
    redirect303($alamatDetail);
}
if ($file === null) {
    flash('galat', 'Belum ada file', 'Pilih file hasil kerja terlebih dulu.', true);
    redirect303($alamatDetail);
}

// ===== BAGIAN 4: SIMPAN KE DATABASE (transaksi) =====
$pdo = db();
try {
    $pdo->beginTransaction();
    $query = $pdo->prepare('SELECT hasil_file FROM tugas_mahasiswa WHERE tugas_id = ? AND pengguna_id = ? FOR UPDATE');
    $query->execute([$idTugas, $pengguna['id']]);
    $fileLama = $query->fetchColumn() ?: null;

    // Satu hasil per mahasiswa per tugas (R-07). Status belum_mulai naik menjadi
    // dikerjakan, tidak otomatis selesai (F-45). Baris baru langsung 'dikerjakan'.
    $simpan = $pdo->prepare("INSERT INTO tugas_mahasiswa
            (tugas_id, pengguna_id, status, hasil_file, hasil_nama, hasil_mime, hasil_ukuran, diunggah)
        VALUES (?, ?, 'dikerjakan', ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            status = IF(status = 'belum_mulai', 'dikerjakan', status),
            hasil_file = VALUES(hasil_file),
            hasil_nama = VALUES(hasil_nama),
            hasil_mime = VALUES(hasil_mime),
            hasil_ukuran = VALUES(hasil_ukuran),
            diunggah = VALUES(diunggah)");
    $simpan->execute([$idTugas, $pengguna['id'], $file['file'], $file['nama'], $file['mime'], $file['ukuran']]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    hapusFile('hasil', $file['file']); // DB gagal → file baru tidak boleh menjadi file yatim
    throw $e;
}

// File lama dihapus SETELAH database berhasil diperbarui (aturan keamanan no. 8).
hapusFile('hasil', $fileLama);

$terlambat = time() > strtotime($tugas['deadline']); // R-08
flash('sukses', 'Hasil kerja tersimpan',
    $terlambat ? 'File diterima dan ditandai Terlambat karena melewati deadline.' : 'File ' . $file['nama'] . ' berhasil diunggah.', true);
redirect303($alamatDetail);
