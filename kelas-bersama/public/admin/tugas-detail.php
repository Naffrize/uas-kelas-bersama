<?php
// =====================================================================
// public/admin/tugas-detail.php — LOGIKA detail tugas admin + "Pengumpulan" (F-35, F-50)
// Tampilan (HTML): app/tampilan/admin/tugas-detail.html (desain 23; modal m09).
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php';
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibAdmin();

// ===== BAGIAN 2: DATA TUGAS =====
$idTugas = ambilId(ambilGet('id'));
$tugas = $idTugas > 0 ? tugasUntukAdmin($idTugas) : null;
if ($tugas === null) {
    tidakDitemukan('Tugas tidak ditemukan.');
}

// ===== BAGIAN 3: PENGUMPULAN — anggota disetujui LEFT JOIN progres (PRD 8.2) =====
$query = db()->prepare("SELECT u.id, u.nama, u.email, u.foto,
        COALESCE(tm.status, 'belum_mulai') AS status, tm.hasil_file, tm.hasil_nama, tm.hasil_mime, tm.hasil_ukuran, tm.diunggah
    FROM pendaftaran p
    JOIN pengguna u ON u.id = p.pengguna_id
    LEFT JOIN tugas_mahasiswa tm ON tm.tugas_id = ? AND tm.pengguna_id = p.pengguna_id
    WHERE p.mata_kuliah_id = ? AND p.status = 'disetujui'
    ORDER BY u.nama");
$query->execute([$tugas['id'], $tugas['mata_kuliah_id']]);

$jumlahPerStatus = ['belum_mulai' => 0, 'dikerjakan' => 0, 'selesai' => 0];
$jumlahMengunggah = 0;
$pengumpulan = [];
foreach ($query->fetchAll() as $baris) {
    $jumlahPerStatus[$baris['status']]++;
    $jumlahMengunggah += $baris['hasil_file'] ? 1 : 0;
    $pengumpulan[] = [
        'avatar' => dataAvatar($baris),
        'nama' => $baris['nama'],
        'email' => $baris['email'],
        'progres' => dataProgres($baris['status']),
        'hasil' => $baris['hasil_file'] ? [
            'alamat' => url('unduh.php?jenis=hasil&tugas=' . (int) $tugas['id'] . '&mhs=' . (int) $baris['id']),
            'nama' => $baris['hasil_nama'],
            'jenis' => labelJenisFile($baris['hasil_mime']),
            'ukuran' => formatUkuran($baris['hasil_ukuran']),
        ] : null,
        'diunggah' => $baris['diunggah'] ? formatTanggalIndo($baris['diunggah']) : null,
        // R-08: unggahan setelah deadline ditandai "Terlambat".
        'terlambat' => $baris['diunggah'] !== null && strtotime($baris['diunggah']) > strtotime($tugas['deadline']),
    ];
}

// ===== BAGIAN 4: TAMPILKAN HTML =====
tampilkanHalaman('admin/tugas-detail', [
    'judulHalaman' => $tugas['judul'],
    'menuAktif' => 'tugas',
    'pengguna' => $pengguna,
    'tugas' => $tugas,
    'info' => [
        'pembuat' => $tugas['pembuat_nama'] ?? 'Admin',
        'dibuat' => formatTanggalPendek($tugas['dibuat']),
        'diperbarui' => $tugas['diubah'] ? formatTanggalPendek($tugas['diubah']) : null,
        'deadline' => formatTanggalIndo($tugas['deadline']),
        'terlewat' => sudahLewat($tugas['deadline']),
    ],
    'tentang' => dataTentangTugas($tugas),
    'ringkasan' => [
        'anggota' => count($pengumpulan),
        'maksBilah' => max(1, count($pengumpulan)),
        'belumMulai' => $jumlahPerStatus['belum_mulai'],
        'dikerjakan' => $jumlahPerStatus['dikerjakan'],
        'selesai' => $jumlahPerStatus['selesai'],
        'mengunggah' => $jumlahMengunggah,
    ],
    'adaPengumpulan' => (bool) $pengumpulan,
    'pengumpulan' => $pengumpulan,
]);
