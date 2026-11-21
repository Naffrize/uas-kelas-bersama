<?php
// =====================================================================
// public/admin/matkul.php — LOGIKA kelola mata kuliah (F-10..F-13)
// Tampilan (HTML): app/tampilan/admin/matkul.html (desain 26 "Kelas" → "Mata kuliah";
// modal m06 buat, m07 edit, m08 hapus, m13 tersimpan).
// Form dikirim ke matkul-simpan.php dan matkul-hapus.php.
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php';
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibAdmin();

// ===== BAGIAN 2: DAFTAR MATA KULIAH + JUMLAH ANGGOTA/PENGAJUAN/TUGAS =====
$daftarMataKuliah = db()->query("SELECT mk.id, mk.kode, mk.nama, mk.deskripsi, mk.kapasitas,
        (SELECT COUNT(*) FROM pendaftaran p WHERE p.mata_kuliah_id = mk.id AND p.status = 'disetujui') AS jumlah_anggota,
        (SELECT COUNT(*) FROM pendaftaran p WHERE p.mata_kuliah_id = mk.id AND p.status = 'menunggu') AS jumlah_menunggu,
        (SELECT COUNT(*) FROM tugas t WHERE t.mata_kuliah_id = mk.id) AS jumlah_tugas
    FROM mata_kuliah mk
    ORDER BY mk.nama")->fetchAll();

$kartu = [];
foreach ($daftarMataKuliah as $baris) {
    $kartu[] = [
        'id' => (int) $baris['id'],
        'kode' => $baris['kode'],
        'nama' => $baris['nama'],
        'deskripsi' => $baris['deskripsi'],
        'kapasitas' => (int) $baris['kapasitas'],
        'jumlahAnggota' => (int) $baris['jumlah_anggota'],
        'jumlahMenunggu' => (int) $baris['jumlah_menunggu'],
        'jumlahTugas' => (int) $baris['jumlah_tugas'],
        'penuh' => (int) $baris['jumlah_anggota'] >= (int) $baris['kapasitas'],
    ];
}

// ===== BAGIAN 3: DATA MODAL FORM (buat & edit) =====
// Bila validasi di matkul-simpan.php gagal, modal yang sama dibuka lagi dengan isian lama.
$formLama = ambilFormLama();
$siapkanForm = function (string $idModal, ?array $mataKuliah) use ($formLama): array {
    $namaForm = $mataKuliah ? 'matkul-' . $mataKuliah['id'] : 'matkul-baru';
    $dariFormLama = $formLama['nama'] === $namaForm;
    return [
        'idModal' => $idModal,
        'mataKuliah' => $mataKuliah,
        'bukaSaatMuat' => $dariFormLama,
        'galat' => $dariFormLama ? $formLama['galat'] : [],
        'nilai' => $dariFormLama ? $formLama['nilai'] : [
            'nama' => $mataKuliah['nama'] ?? '',
            'kode' => $mataKuliah['kode'] ?? '',
            'deskripsi' => $mataKuliah['deskripsi'] ?? '',
            'kapasitas' => (string) ($mataKuliah['kapasitas'] ?? 40),
        ],
    ];
};
$daftarModalForm = [$siapkanForm('modal-buat-matkul', null)];
foreach ($kartu as $baris) {
    $daftarModalForm[] = $siapkanForm('modal-edit-matkul-' . $baris['id'], $baris);
}

// ===== BAGIAN 4: TAMPILKAN HTML =====
tampilkanHalaman('admin/matkul', [
    'judulHalaman' => 'Mata kuliah',
    'menuAktif' => 'matkul',
    'pengguna' => $pengguna,
    'jumlahMataKuliah' => count($kartu),
    'adaMataKuliah' => (bool) $kartu,
    'daftarMataKuliah' => $kartu,
    'daftarModalForm' => $daftarModalForm,
]);
