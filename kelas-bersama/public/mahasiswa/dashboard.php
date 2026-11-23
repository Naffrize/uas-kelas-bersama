<?php
// =====================================================================
// public/mahasiswa/dashboard.php — LOGIKA dashboard mahasiswa
// F-25 empty state · F-60 salam · F-61 statistik · F-62 banner progres pribadi ·
// F-63 "Jangan terlewat" · cari & filter singkat.
// Tampilan (HTML): app/tampilan/mahasiswa/dashboard.html (desain 10, 11, 12; modal m15).
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php';
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibMahasiswa();

// ===== BAGIAN 2: DATA DASHBOARD =====
$mkDiikuti = mataKuliahDisetujui($pengguna['id']);
$filter = bacaFilterTugas(true);
$statistik = ['total' => 0, 'belum' => 0, 'dikerjakan' => 0, 'selesai' => 0];
$daftarRingkas = ['baris' => [], 'paginasi' => paginasi(0, 1, 5)];
$terdekat = null;
$jumlahMenungguSaya = 0;

if ($mkDiikuti) {
    $statistik = statistikMahasiswa($pengguna['id']);
    $daftarRingkas = daftarTugasMahasiswa($pengguna['id'], $filter, 1, 5);
    $terdekat = tugasTerdekatMahasiswa($pengguna['id']);
} else {
    $query = db()->prepare("SELECT COUNT(*) FROM pendaftaran WHERE pengguna_id = ? AND status = 'menunggu'");
    $query->execute([$pengguna['id']]);
    $jumlahMenungguSaya = (int) $query->fetchColumn();
}

// Judul kartu daftar mengikuti pencarian/filter (desain 11 "1 hasil ditemukan", 12 "1 tugas dikerjakan").
$totalDaftar = $daftarRingkas['paginasi']['total'];
$judulDaftar = match (true) {
    $filter['q'] !== '' => $totalDaftar . ' hasil ditemukan',
    $filter['status'] !== '' => $totalDaftar . ' tugas ' . mb_strtolower(LABEL_PROGRES[$filter['status']]),
    default => 'Tugas kelas',
};
$adaFilterLain = $filter['mk'] > 0 || $filter['deadline'] !== '' || $filter['urut'] !== '';

// ===== BAGIAN 3: TAMPILKAN HTML =====
tampilkanHalaman('mahasiswa/dashboard', [
    'judulHalaman' => 'Dashboard',
    'menuAktif' => 'dashboard',
    'pengguna' => $pengguna,
    'salam' => salam(),
    'namaDepan' => namaDepan($pengguna['nama']),
    'adaMkDiikuti' => (bool) $mkDiikuti,
    'jumlahMenungguSaya' => $jumlahMenungguSaya,
    'kartu' => [
        'total' => duaDigit($statistik['total']),
        'dikerjakan' => duaDigit($statistik['dikerjakan']),
        'selesai' => duaDigit($statistik['selesai']),
        'mataKuliah' => duaDigit(count($mkDiikuti)),
    ],
    'statistik' => $statistik + ['maksBilah' => max(1, $statistik['total'])],
    'judulDaftar' => $judulDaftar,
    'filter' => $filter,
    'filterTersembunyi' => dataInputTersembunyi(parameterFilter($filter, ['q' => ''])),
    'chip' => [
        'semua' => [
            'alamat' => url(tautan('mahasiswa/dashboard.php', parameterFilter($filter, ['status' => '']))),
            'aktif' => $filter['status'] === '',
        ],
        'dikerjakan' => [
            'alamat' => url(tautan('mahasiswa/dashboard.php', parameterFilter($filter, ['status' => 'dikerjakan']))),
            'aktif' => $filter['status'] === 'dikerjakan',
        ],
    ],
    'adaFilterLain' => $adaFilterLain,
    'adaFilterApaPun' => $filter['q'] !== '' || $filter['status'] !== '' || $adaFilterLain,
    'adaDaftar' => (bool) $daftarRingkas['baris'],
    'daftar' => array_map('dataBarisTugasMahasiswa', $daftarRingkas['baris']),
    'totalDaftar' => $totalDaftar,
    'alamatLihatSemua' => url(tautan('mahasiswa/tugas.php', parameterFilter($filter))),
    'terdekat' => $terdekat ? [
        'id' => (int) $terdekat['id'],
        'tanggalPill' => tanggalPill($terdekat['deadline']),
        'judul' => $terdekat['judul'],
        'mkNama' => $terdekat['mk_nama'],
        'mkWarna' => warnaMatkul($terdekat['mk_warna']),
        'kalimatDeadline' => kalimatDeadline($terdekat['deadline']),
        'sudahDimulai' => $terdekat['status'] === 'dikerjakan',
    ] : null,
    // Modal "Filter tugas" (desain m15 mahasiswa).
    'filterModal' => dataModalFilter($filter, $mkDiikuti, true, url('mahasiswa/dashboard.php')),
]);
