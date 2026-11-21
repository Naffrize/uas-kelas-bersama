<?php
// =====================================================================
// public/admin/tugas-form.php — LOGIKA form tambah & edit tugas (?id= untuk edit)
// F-30, F-31. Tampilan (HTML): app/tampilan/admin/tugas-form.html
// (desain 24-tambah-tugas-admin, 25-edit-tugas-admin; modal m01 & m02).
// Form dikirim ke tugas-simpan.php; bila validasi gagal, isian tampil kembali.
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php';
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibAdmin();

// ===== BAGIAN 2: DATA TUGAS (mode edit) =====
$idTugas = ambilId(ambilGet('id'));
$tugas = null;
if ($idTugas > 0) {
    $tugas = tugasUntukAdmin($idTugas);
    if ($tugas === null) {
        tidakDitemukan('Tugas yang ingin diedit tidak ditemukan.');
    }
}
$modeEdit = $tugas !== null;
$daftarMk = semuaMataKuliah();

// ===== BAGIAN 3: NILAI AWAL (dari database, atau isian lama bila validasi gagal) =====
$formLama = ambilFormLama();
$dariFormLama = $formLama['nama'] === ($modeEdit ? 'tugas-' . $idTugas : 'tugas-baru');
$nilai = $dariFormLama ? $formLama['nilai'] : [
    'judul' => $tugas['judul'] ?? '',
    'mata_kuliah_id' => (string) ($tugas['mata_kuliah_id'] ?? (ambilId(ambilGet('mk')) ?: '')),
    'deskripsi' => $tugas['deskripsi'] ?? '',
    'persiapan' => $tugas['persiapan'] ?? '',
    'tanggal' => $modeEdit ? date('Y-m-d', strtotime($tugas['deadline'])) : '',
    'waktu' => $modeEdit ? date('H:i', strtotime($tugas['deadline'])) : '23:59',
];
$galat = $dariFormLama ? $formLama['galat'] : [];

$opsiMk = ['' => 'Pilih mata kuliah'];
foreach ($daftarMk as $baris) {
    $opsiMk[$baris['id']] = $baris['nama'] . ' (' . $baris['kode'] . ')';
}

// ===== BAGIAN 4: TAMPILKAN HTML =====
tampilkanHalaman('admin/tugas-form', [
    'judulHalaman' => $modeEdit ? 'Edit tugas' : 'Tambah tugas',
    'menuAktif' => 'tugas',
    'pengguna' => $pengguna,
    'modeEdit' => $modeEdit,
    'tugas' => $tugas,
    'adaMataKuliah' => (bool) $daftarMk,
    'opsiMk' => dataOpsi($opsiMk, $nilai['mata_kuliah_id']),
    'nilai' => $nilai,
    'galat' => $galat,
    'adaGalat' => (bool) $galat,
    'alamatBatal' => $modeEdit ? url('admin/tugas-detail.php?id=' . $idTugas) : url('admin/tugas.php'),
    // Lampiran yang sudah ada (mode edit): bisa dihapus atau diganti.
    'lampiranSaatIni' => $modeEdit && $tugas['lampiran_file'] ? [
        'jenis' => labelJenisFile($tugas['lampiran_mime']),
        'nama' => $tugas['lampiran_nama'],
        'ukuran' => formatUkuran($tugas['lampiran_ukuran']),
    ] : null,
]);
