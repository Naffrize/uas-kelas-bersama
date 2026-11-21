<?php
// =====================================================================
// public/admin/tugas.php — LOGIKA daftar tugas admin (F-33, F-34, F-36)
// Tampilan (HTML): app/tampilan/admin/tugas.html (desain 29: struktur tabel,
// kolom "Dibuat oleh" → "Pengumpulan x/y", aksi Detail · Edit · Hapus).
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php';
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibAdmin();

// ===== BAGIAN 2: FILTER & DATA =====
$filter = bacaFilterTugas(false);
$hasil = daftarTugasAdmin($filter, $filter['hal'], 10);
$daftarMk = semuaMataKuliah();
$opsiMk = ['' => 'Semua mata kuliah'];
foreach ($daftarMk as $baris) {
    $opsiMk[$baris['id']] = $baris['nama'];
}

// ===== BAGIAN 3: TAMPILKAN HTML =====
tampilkanHalaman('admin/tugas', [
    'judulHalaman' => 'Daftar tugas',
    'menuAktif' => 'tugas',
    'pengguna' => $pengguna,
    'filter' => $filter,
    'opsiMk' => dataOpsi($opsiMk, $filter['mk'] ?: ''),
    'opsiDeadline' => dataOpsi(['' => 'Semua', 'minggu_ini' => 'Minggu ini', 'terlewat' => 'Terlewat'], $filter['deadline']),
    'opsiUrut' => dataOpsi(['' => 'Terdekat', 'jauh' => 'Terjauh', 'baru' => 'Terbaru'], $filter['urut']),
    'adaFilter' => $filter['q'] !== '' || $filter['mk'] > 0 || $filter['deadline'] !== '' || $filter['urut'] !== '',
    'adaMataKuliah' => (bool) $daftarMk,
    'adaDaftar' => (bool) $hasil['baris'],
    'daftar' => array_map('dataBarisTugasAdmin', $hasil['baris']),
    'paginasi' => dataPaginasi($hasil['paginasi'], 'admin/tugas.php', parameterFilter($filter), 'tugas'),
]);
