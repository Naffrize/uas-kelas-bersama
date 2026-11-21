<?php
// =====================================================================
// public/mahasiswa/matkul.php — LOGIKA "Pilih mata kuliah" (F-20, F-21)
// Tampilan (HTML): app/tampilan/mahasiswa/matkul.html (desain 03 → grid kartu).
// Form dikirim ke matkul-aksi.php (ajukan / batalkan / ajukan ulang).
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php';
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibMahasiswa();

// ===== BAGIAN 2: SEMUA MATA KULIAH + STATUS PENGAJUAN SAYA =====
$query = db()->prepare("SELECT mk.id, mk.kode, mk.nama, mk.deskripsi, mk.kapasitas,
        (SELECT COUNT(*) FROM pendaftaran pa WHERE pa.mata_kuliah_id = mk.id AND pa.status = 'disetujui') AS jumlah_anggota,
        (SELECT COUNT(*) FROM tugas t WHERE t.mata_kuliah_id = mk.id) AS jumlah_tugas,
        p.status AS status_saya, p.diajukan, p.diputuskan
    FROM mata_kuliah mk
    LEFT JOIN pendaftaran p ON p.mata_kuliah_id = mk.id AND p.pengguna_id = ?
    ORDER BY mk.nama");
$query->execute([$pengguna['id']]);
$daftarMataKuliah = $query->fetchAll();

// ===== BAGIAN 3: DATA TIAP KARTU (sisa kuota & keterangan status) =====
$kartu = [];
foreach ($daftarMataKuliah as $baris) {
    $sisa = max(0, (int) $baris['kapasitas'] - (int) $baris['jumlah_anggota']);
    $kartu[] = [
        'id' => (int) $baris['id'],
        'kode' => $baris['kode'],
        'nama' => $baris['nama'],
        'deskripsi' => $baris['deskripsi'],
        'jumlahAnggota' => (int) $baris['jumlah_anggota'],
        'kapasitas' => (int) $baris['kapasitas'],
        'jumlahTugas' => (int) $baris['jumlah_tugas'],
        'sisa' => $sisa,
        'penuh' => $sisa === 0,
        'pendaftaran' => dataPendaftaran($baris['status_saya']),
        // Tombol "Ajukan gabung / Ajukan ulang" untuk yang belum gabung, ditolak, atau dikeluarkan.
        'bisaAjukan' => !in_array($baris['status_saya'], ['disetujui', 'menunggu'], true),
        'keterangan' => match ($baris['status_saya']) {
            'menunggu' => 'Diajukan ' . formatTanggalIndo($baris['diajukan']) . ' · menunggu tinjauan admin.',
            'disetujui' => 'Disetujui ' . formatTanggalIndo($baris['diputuskan']) . '.',
            'ditolak' => 'Pengajuan ditolak ' . formatTanggalIndo($baris['diputuskan']) . '. Kamu dapat mengajukan ulang.',
            'dikeluarkan' => 'Kamu dikeluarkan ' . formatTanggalIndo($baris['diputuskan']) . '. Kamu dapat mengajukan ulang.',
            default => 'Belum bergabung.',
        },
    ];
}

// ===== BAGIAN 4: TAMPILKAN HTML =====
tampilkanHalaman('mahasiswa/matkul', [
    'judulHalaman' => 'Pilih mata kuliah',
    'menuAktif' => 'matkul',
    'pengguna' => $pengguna,
    'adaMataKuliah' => (bool) $kartu,
    'daftarMataKuliah' => $kartu,
    'adaDisetujui' => in_array('disetujui', array_column($daftarMataKuliah, 'status_saya'), true),
]);
