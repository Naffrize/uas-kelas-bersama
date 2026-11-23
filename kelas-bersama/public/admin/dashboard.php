<?php
// =====================================================================
// public/admin/dashboard.php — LOGIKA dashboard admin (F-51)
// Tampilan (HTML): app/tampilan/admin/dashboard.html (desain 20, 21, 22; modal m14).
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php';
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibAdmin();

// ===== BAGIAN 2: DATA DASHBOARD =====
$statistik = statistikAdmin();
$filter = bacaFilterTugas(false);
$daftarRingkas = daftarTugasAdmin($filter, 1, 5);
$totalDaftar = $daftarRingkas['paginasi']['total'];
$judulDaftar = match (true) {
    $filter['q'] !== '' => $totalDaftar . ' hasil ditemukan',
    $filter['deadline'] === 'minggu_ini' => $totalDaftar . ' tugas minggu ini',
    $filter['deadline'] === 'terlewat' => $totalDaftar . ' tugas terlewat',
    default => 'Tugas kelas',
};
$adaFilterLain = $filter['mk'] > 0 || $filter['urut'] !== '';
$terdekat = tugasTerdekatAdmin();

// Tautan chip deadline: filter lain tetap terbawa.
$alamatChip = fn (string $deadline) => [
    'alamat' => url(tautan('admin/dashboard.php', parameterFilter($filter, ['deadline' => $deadline]))),
    'aktif' => $filter['deadline'] === $deadline,
];

// ===== BAGIAN 3: TAMPILKAN HTML =====
tampilkanHalaman('admin/dashboard', [
    'judulHalaman' => 'Dashboard admin',
    'menuAktif' => 'dashboard',
    'pengguna' => $pengguna,
    'statistik' => $statistik,
    'kartu' => [
        'tugas' => duaDigit($statistik['tugas']),
        'mahasiswaAktif' => duaDigit($statistik['mahasiswa_aktif']),
        'mataKuliah' => duaDigit($statistik['mata_kuliah']),
        'menunggu' => duaDigit($statistik['menunggu']),
    ],
    'judulDaftar' => $judulDaftar,
    'filter' => $filter,
    'filterTersembunyi' => dataInputTersembunyi(parameterFilter($filter, ['q' => ''])),
    'chip' => [
        'semua' => $alamatChip(''),
        'mingguIni' => $alamatChip('minggu_ini'),
        'terlewat' => $alamatChip('terlewat'),
    ],
    'adaFilterLain' => $adaFilterLain,
    'adaFilterApaPun' => $filter['q'] !== '' || $filter['deadline'] !== '' || $adaFilterLain,
    'adaDaftar' => (bool) $daftarRingkas['baris'],
    'daftar' => array_map('dataBarisTugasAdmin', $daftarRingkas['baris']),
    'totalDaftar' => $totalDaftar,
    'alamatLihatSemua' => url(tautan('admin/tugas.php', parameterFilter($filter))),
    'terdekat' => $terdekat ? [
        'id' => (int) $terdekat['id'],
        'tanggalPill' => tanggalPill($terdekat['deadline']),
        'judul' => $terdekat['judul'],
        'mkNama' => $terdekat['mk_nama'],
        'mkWarna' => warnaMatkul($terdekat['mk_warna']),
        'kalimatDeadline' => kalimatDeadline($terdekat['deadline']),
        'jumlahSelesai' => (int) $terdekat['jumlah_selesai'],
        'jumlahAnggota' => (int) $terdekat['jumlah_anggota'],
    ] : null,
    // Modal "Filter tugas" (desain m14 admin).
    'filterModal' => dataModalFilter($filter, semuaMataKuliah(), false, url('admin/dashboard.php')),
]);
