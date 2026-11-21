<?php
// =====================================================================
// public/mahasiswa/tugas.php — LOGIKA daftar tugas / to-do list mahasiswa
// F-33, F-34 (cari, filter, paginasi), F-36 (Terlewat), F-41 (centang = selesai).
// Tampilan (HTML): app/tampilan/mahasiswa/tugas.html (desain 18, kolom aksi → checkbox).
// Hanya tugas dari mata kuliah berstatus 'disetujui' yang tampil (R-05).
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php';
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibMahasiswa();

// ===== BAGIAN 2: FILTER & DATA =====
$mkDiikuti = mataKuliahDisetujui($pengguna['id']);
$filter = bacaFilterTugas(true);
$hasil = $mkDiikuti ? daftarTugasMahasiswa($pengguna['id'], $filter, $filter['hal'], 10) : ['baris' => [], 'paginasi' => paginasi(0, 1, 10)];

$opsiMk = ['' => 'Semua mata kuliah'];
foreach ($mkDiikuti as $baris) {
    $opsiMk[$baris['id']] = $baris['nama'];
}

// ===== BAGIAN 3: TAMPILKAN HTML =====
tampilkanHalaman('mahasiswa/tugas', [
    'judulHalaman' => 'Daftar tugas',
    'menuAktif' => 'tugas',
    'pengguna' => $pengguna,
    'adaMkDiikuti' => (bool) $mkDiikuti,
    'filter' => $filter,
    'opsiMk' => dataOpsi($opsiMk, $filter['mk'] ?: ''),
    'opsiStatus' => dataOpsi(['' => 'Semua'] + LABEL_PROGRES, $filter['status']),
    'opsiDeadline' => dataOpsi(['' => 'Semua', 'minggu_ini' => 'Minggu ini', 'terlewat' => 'Terlewat'], $filter['deadline']),
    'adaFilter' => $filter['q'] !== '' || $filter['mk'] > 0 || $filter['status'] !== '' || $filter['deadline'] !== '' || $filter['urut'] !== '',
    'adaUrut' => $filter['urut'] !== '',
    'adaDaftar' => (bool) $hasil['baris'],
    'daftar' => array_map('dataBarisTugasMahasiswa', $hasil['baris']),
    // Setelah centang, pengguna kembali ke daftar yang sama (filter & halaman tetap).
    'alamatDaftar' => tautan('mahasiswa/tugas.php', parameterFilter($filter, ['hal' => $hasil['paginasi']['hal']])),
    'paginasi' => dataPaginasi($hasil['paginasi'], 'mahasiswa/tugas.php', parameterFilter($filter), 'tugas'),
]);
