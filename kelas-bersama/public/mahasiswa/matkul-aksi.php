<?php
// =====================================================================
// public/mahasiswa/matkul-aksi.php — ajukan gabung, ajukan ulang, batalkan pengajuan
// F-20, F-21, R-02..R-04; diagram 4 & state diagram pendaftaran.
// ID mahasiswa SELALU dari session, bukan dari form (cegah IDOR).
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php'; // POST tanpa token CSRF → 400
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibMahasiswa();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('mahasiswa/matkul.php');
}

$aksi = ambilPost('aksi');
$idMataKuliah = ambilId(ambilPost('mk'));
$pdo = db();

// ===== BAGIAN 2: AJUKAN GABUNG / AJUKAN ULANG =====
if ($aksi === 'ajukan') {
    $pdo->beginTransaction();
    try {
        // Kunci baris mata kuliah: pengajuan & persetujuan bersamaan diproses bergiliran,
        // sehingga jumlah anggota disetujui tidak melebihi kapasitas (R-04).
        $query = $pdo->prepare('SELECT id, nama, kapasitas FROM mata_kuliah WHERE id = ? FOR UPDATE');
        $query->execute([$idMataKuliah]);
        $mataKuliah = $query->fetch();
        if (!$mataKuliah) {
            $pdo->rollBack();
            tidakDitemukan('Mata kuliah tidak ditemukan.');
        }

        $query = $pdo->prepare('SELECT id, status FROM pendaftaran WHERE pengguna_id = ? AND mata_kuliah_id = ? FOR UPDATE');
        $query->execute([$pengguna['id'], $idMataKuliah]);
        $pendaftaran = $query->fetch();

        // F-21: tolak bila sudah ada pengajuan aktif (menunggu/disetujui).
        if ($pendaftaran && in_array($pendaftaran['status'], ['menunggu', 'disetujui'], true)) {
            $pdo->rollBack();
            flash('galat', 'Pengajuan sudah ada', 'Kamu sudah mengajukan atau sudah menjadi anggota ' . $mataKuliah['nama'] . '.');
            redirect303('mahasiswa/matkul.php');
        }
        // F-21: tolak bila kuota penuh.
        if (jumlahAnggotaDisetujui($idMataKuliah) >= (int) $mataKuliah['kapasitas']) {
            $pdo->rollBack();
            flash('galat', 'Mata kuliah penuh', 'Kuota ' . $mataKuliah['nama'] . ' sudah penuh. Pilih mata kuliah lain atau hubungi admin.', true);
            redirect303('mahasiswa/matkul.php');
        }

        if ($pendaftaran) {
            // Ajukan ulang (ditolak/dikeluarkan → menunggu): baris yang sama dipakai lagi (R-02).
            $ubah = $pdo->prepare("UPDATE pendaftaran SET status = 'menunggu', diajukan = NOW(), diputuskan = NULL, diputuskan_oleh = NULL WHERE id = ?");
            $ubah->execute([$pendaftaran['id']]);
        } else {
            $tambah = $pdo->prepare("INSERT INTO pendaftaran (pengguna_id, mata_kuliah_id, status) VALUES (?, ?, 'menunggu')");
            $tambah->execute([$pengguna['id'], $idMataKuliah]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    flash('sukses', 'Pengajuan terkirim', 'Pengajuan gabung ke ' . $mataKuliah['nama'] . ' menunggu persetujuan admin.');
    redirect303('mahasiswa/matkul.php');
}

// ===== BAGIAN 3: BATALKAN PENGAJUAN =====
if ($aksi === 'batal') {
    // Hanya pengajuan MILIK SENDIRI yang masih menunggu yang dapat dibatalkan (baris dihapus).
    $hapus = $pdo->prepare("DELETE FROM pendaftaran WHERE pengguna_id = ? AND mata_kuliah_id = ? AND status = 'menunggu'");
    $hapus->execute([$pengguna['id'], $idMataKuliah]);
    if ($hapus->rowCount() === 1) {
        flash('sukses', 'Pengajuan dibatalkan', 'Kamu dapat mengajukan lagi kapan saja.');
    } else {
        flash('galat', 'Pengajuan tidak dapat dibatalkan', 'Pengajuan ini sudah diputuskan admin atau sudah dibatalkan.');
    }
    redirect303('mahasiswa/matkul.php');
}

tampilkanGalat(400, 'Permintaan tidak valid', 'Aksi tidak dikenal.');
