<?php
// =====================================================================
// public/admin/anggota.php — LOGIKA anggota & persetujuan (F-22..F-24)
// Tampilan (HTML): app/tampilan/admin/anggota.html (desain 27 + tab Menunggu/Disetujui; modal m10).
// Tombol Setujui / Tolak / Keluarkan dikirim ke anggota-aksi.php.
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php';
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibAdmin();

// ===== BAGIAN 2: FILTER (GET: tab, mk, q, hal) =====
$tab = ambilGet('tab') === 'disetujui' ? 'disetujui' : 'menunggu';
$idMk = ambilId(ambilGet('mk'));
$cari = mb_substr(ambilGet('q'), 0, 100);
$daftarMk = semuaMataKuliah();
$mataKuliahPilihan = null;
$opsiMk = ['' => 'Semua mata kuliah'];
foreach ($daftarMk as $baris) {
    $opsiMk[$baris['id']] = $baris['nama'];
    if ((int) $baris['id'] === $idMk) {
        $mataKuliahPilihan = $baris;
    }
}
if ($mataKuliahPilihan === null) {
    $idMk = 0;
}

// Kondisi WHERE dari potongan SQL tetap; nilai pengguna tetap lewat tanda ?.
$kondisi = ['1 = 1'];
$parameter = [];
if ($idMk > 0) {
    $kondisi[] = 'p.mata_kuliah_id = ?';
    $parameter[] = $idMk;
}
if ($cari !== '') {
    $pola = '%' . addcslashes($cari, '%_\\') . '%';
    $kondisi[] = '(u.nama LIKE ? OR u.email LIKE ?)';
    array_push($parameter, $pola, $pola);
}
$where = implode(' AND ', $kondisi);

// ===== BAGIAN 3: JUMLAH PER TAB & DAFTAR =====
$jumlahTab = jalankan("SELECT p.status, COUNT(*) FROM pendaftaran p JOIN pengguna u ON u.id = p.pengguna_id
    WHERE $where AND p.status IN ('menunggu', 'disetujui') GROUP BY p.status", $parameter)->fetchAll(PDO::FETCH_KEY_PAIR);
$paginasi = paginasi((int) ($jumlahTab[$tab] ?? 0), max(1, ambilId(ambilGet('hal'))), 15);
$daftarAnggota = jalankan("SELECT p.id, p.status, p.diajukan, p.diputuskan, u.nama, u.email, u.foto, mk.nama AS mk_nama, mk.kode AS mk_kode
    FROM pendaftaran p
    JOIN pengguna u ON u.id = p.pengguna_id
    JOIN mata_kuliah mk ON mk.id = p.mata_kuliah_id
    WHERE $where AND p.status = ?
    ORDER BY " . ($tab === 'menunggu' ? 'p.diajukan ASC, p.id ASC' : 'u.nama ASC, mk.nama ASC') . '
    LIMIT ? OFFSET ?', array_merge($parameter, [$tab, $paginasi['perHalaman'], $paginasi['offset']]))->fetchAll();

// ===== BAGIAN 4: TEKS BANNER (mata kuliah terpilih atau semua) =====
if ($mataKuliahPilihan) {
    $anggotaAktif = jumlahAnggotaDisetujui($idMk);
    $sisaTempat = max(0, (int) $mataKuliahPilihan['kapasitas'] - $anggotaAktif);
    $subjudul = $mataKuliahPilihan['nama'] . ' · ' . $anggotaAktif . ' dari ' . $mataKuliahPilihan['kapasitas'] . ' tempat terisi';
    $teksBanner = $sisaTempat . ' tempat tersedia. Kode MK ' . $mataKuliahPilihan['kode'] . '.';
} else {
    $anggotaAktif = (int) db()->query("SELECT COUNT(DISTINCT pengguna_id) FROM pendaftaran WHERE status = 'disetujui'")->fetchColumn();
    $subjudul = 'Semua mata kuliah · ' . count($daftarMk) . ' mata kuliah';
    $teksBanner = jumlahPengajuanMenunggu() . ' pengajuan menunggu persetujuan di semua mata kuliah.';
}

// Parameter tautan tab & alamat kembali setelah aksi (filter tetap).
$parameterDasar = array_filter(['mk' => $idMk, 'q' => $cari], fn ($nilai) => $nilai !== '' && $nilai !== 0);
$daftarTab = [];
foreach (['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui'] as $kunciTab => $labelTab) {
    $daftarTab[] = [
        'label' => $labelTab,
        'alamat' => url(tautan('admin/anggota.php', $parameterDasar + ['tab' => $kunciTab])),
        'aktif' => $tab === $kunciTab,
        'jumlah' => (int) ($jumlahTab[$kunciTab] ?? 0),
    ];
}
$barisAnggota = [];
foreach ($daftarAnggota as $baris) {
    $barisAnggota[] = [
        'id' => (int) $baris['id'],
        'avatar' => dataAvatar($baris),
        'nama' => $baris['nama'],
        'email' => $baris['email'],
        'mkNama' => $baris['mk_nama'],
        'mkKode' => $baris['mk_kode'],
        'tanggal' => formatTanggalIndo($tab === 'menunggu' ? $baris['diajukan'] : $baris['diputuskan']),
    ];
}

// ===== BAGIAN 5: TAMPILKAN HTML =====
tampilkanHalaman('admin/anggota', [
    'judulHalaman' => 'Anggota',
    'menuAktif' => 'anggota',
    'pengguna' => $pengguna,
    'tab' => $tab,
    'tabMenunggu' => $tab === 'menunggu',
    'cari' => $cari,
    'adaCari' => $cari !== '',
    'opsiMk' => dataOpsi($opsiMk, $idMk ?: ''),
    'daftarTab' => $daftarTab,
    'adaAnggota' => (bool) $barisAnggota,
    'daftarAnggota' => $barisAnggota,
    'anggotaAktif' => $anggotaAktif,
    'subjudul' => $subjudul,
    'teksBanner' => $teksBanner,
    'alamatIni' => tautan('admin/anggota.php', $parameterDasar + ['tab' => $tab] + ($paginasi['hal'] > 1 ? ['hal' => $paginasi['hal']] : [])),
    'paginasi' => dataPaginasi($paginasi, 'admin/anggota.php', $parameterDasar + ['tab' => $tab], $tab === 'menunggu' ? 'pengajuan' : 'anggota'),
]);
