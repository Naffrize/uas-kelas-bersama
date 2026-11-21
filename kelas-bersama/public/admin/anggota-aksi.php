<?php
// =====================================================================
// public/admin/anggota-aksi.php — setujui / tolak pengajuan, keluarkan anggota
// F-23, F-24, R-03, R-04; diagram 4. Kuota dicek di dalam transaksi dengan
// SELECT ... FOR UPDATE agar dua persetujuan bersamaan tidak melebihi kapasitas.
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php'; // POST tanpa token CSRF → 400
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('admin/anggota.php');
}

$aksi = ambilPost('aksi');
$idPendaftaran = ambilId(ambilPost('id'));
$kembali = alamatKembali(ambilPost('kembali'), ['admin/anggota.php'], 'admin/anggota.php');
if (!in_array($aksi, ['setujui', 'tolak', 'keluarkan'], true)) {
    tampilkanGalat(400, 'Permintaan tidak valid', 'Aksi tidak dikenal.');
}

$pdo = db();
try {
    $pdo->beginTransaction();

    // ===== BAGIAN 2: KUNCI MATA KULIAH, LALU PENDAFTARAN =====
    // Urutan kunci sama dengan mahasiswa/matkul-aksi.php (mata kuliah dulu) agar tidak deadlock.
    $query = $pdo->prepare('SELECT mata_kuliah_id FROM pendaftaran WHERE id = ?');
    $query->execute([$idPendaftaran]);
    $idMataKuliah = (int) $query->fetchColumn();
    // Baris mata kuliah dikunci: persetujuan untuk mata kuliah yang sama diproses bergiliran.
    $pdo->prepare('SELECT id FROM mata_kuliah WHERE id = ? FOR UPDATE')->execute([$idMataKuliah]);

    $query = $pdo->prepare('SELECT p.id, p.status, p.mata_kuliah_id, u.nama, mk.nama AS mk_nama, mk.kapasitas
        FROM pendaftaran p
        JOIN pengguna u ON u.id = p.pengguna_id
        JOIN mata_kuliah mk ON mk.id = p.mata_kuliah_id
        WHERE p.id = ? FOR UPDATE');
    $query->execute([$idPendaftaran]);
    $pendaftaran = $query->fetch();
    if (!$pendaftaran) {
        $pdo->rollBack();
        tidakDitemukan('Pengajuan tidak ditemukan.');
    }

    // Transisi yang sah (R-03): menunggu → disetujui/ditolak, disetujui → dikeluarkan.
    $statusAwalSah = $aksi === 'keluarkan' ? 'disetujui' : 'menunggu';
    if ($pendaftaran['status'] !== $statusAwalSah) {
        $pdo->rollBack();
        flash('galat', 'Status sudah berubah', 'Pengajuan ini sudah diputuskan sebelumnya. Muat ulang daftar.');
        redirect303($kembali);
    }

    // ===== BAGIAN 3: CEK KUOTA SAAT MENYETUJUI (R-04, U-07) =====
    if ($aksi === 'setujui' && jumlahAnggotaDisetujui((int) $pendaftaran['mata_kuliah_id']) >= (int) $pendaftaran['kapasitas']) {
        $pdo->rollBack();
        flash('galat', 'Kapasitas penuh', $pendaftaran['mk_nama'] . ' sudah mencapai kapasitas ' . $pendaftaran['kapasitas'] . ' anggota. Naikkan kapasitas atau tolak pengajuan.', true);
        redirect303($kembali);
    }

    // ===== BAGIAN 4: SIMPAN KEPUTUSAN + WAKTU & ADMIN YANG MEMUTUSKAN (F-23) =====
    $statusBaru = ['setujui' => 'disetujui', 'tolak' => 'ditolak', 'keluarkan' => 'dikeluarkan'][$aksi];
    $ubah = $pdo->prepare('UPDATE pendaftaran SET status = ?, diputuskan = NOW(), diputuskan_oleh = ? WHERE id = ?');
    $ubah->execute([$statusBaru, $pengguna['id'], $idPendaftaran]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$pesan = match ($aksi) {
    'setujui' => [$pendaftaran['nama'] . ' disetujui', 'Kini dapat melihat tugas ' . $pendaftaran['mk_nama'] . '.'],
    'tolak' => ['Pengajuan ditolak', $pendaftaran['nama'] . ' dapat mengajukan ulang bila perlu.'],
    'keluarkan' => [$pendaftaran['nama'] . ' dikeluarkan', 'Akses ke ' . $pendaftaran['mk_nama'] . ' dicabut; progres & hasil kerja tetap tersimpan.'],
};
flash('sukses', $pesan[0], $pesan[1]);
redirect303($kembali);
