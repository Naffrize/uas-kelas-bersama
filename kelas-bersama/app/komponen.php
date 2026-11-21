<?php
// =====================================================================
// app/komponen.php — DATA untuk potongan tampilan yang dipakai berulang
//
// HTML-nya ada di app/tampilan/komponen/*.html dan app/tampilan/layout/*.html.
// Fungsi di sini TIDAK membuat HTML: hanya menyiapkan nilai (teks, angka,
// tanda ya/tidak, alamat) yang diisikan ke file .html tersebut.
// =====================================================================

// ===== BAGIAN 1: PENGGUNA =====

// Foto profil hanya ditampilkan bila namanya sesuai pola buatan server (32 heksadesimal).
function urlFoto(?string $foto): ?string
{
    if ($foto === null || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $foto)) {
        return null;
    }
    return url('uploads/foto/' . $foto);
}

// Avatar (komponen/avatar-9.html, avatar-10.html): foto profil, atau inisial nama (F-06).
function dataAvatar(array $pengguna): array
{
    return [
        'fotoUrl' => urlFoto($pengguna['foto'] ?? null),
        'nama' => $pengguna['nama'],
        'inisial' => inisial($pengguna['nama']),
    ];
}

// ===== BAGIAN 2: LENCANA STATUS =====

// Lencana progres pribadi (komponen/lencana-progres.html): Belum mulai / Sedang dikerjakan / Selesai.
function dataProgres(string $status, bool $denganPersen = false): array
{
    $teks = LABEL_PROGRES[$status] ?? 'Belum mulai';
    if ($denganPersen) {
        $teks .= ' · ' . (PERSEN_PROGRES[$status] ?? 0) . '%';
    }
    return [
        'belumMulai' => $status !== 'selesai' && $status !== 'dikerjakan',
        'dikerjakan' => $status === 'dikerjakan',
        'selesai' => $status === 'selesai',
        'teks' => $teks,
        'persen' => PERSEN_PROGRES[$status] ?? 0,
    ];
}

// Lencana status pendaftaran mata kuliah (komponen/lencana-pendaftaran.html).
function dataPendaftaran(?string $status): array
{
    return [
        'menunggu' => $status === 'menunggu',
        'disetujui' => $status === 'disetujui',
        'ditolak' => $status === 'ditolak',
        'dikeluarkan' => $status === 'dikeluarkan',
        'belumGabung' => !in_array($status, ['menunggu', 'disetujui', 'ditolak', 'dikeluarkan'], true),
    ];
}

// ===== BAGIAN 3: FORM =====

// Pilihan <select>: [nilai => label] → daftar {nilai, label, terpilih} untuk <option>.
function dataOpsi(array $pilihan, string|int $terpilih): array
{
    $hasil = [];
    foreach ($pilihan as $nilai => $label) {
        $hasil[] = ['nilai' => (string) $nilai, 'label' => $label, 'terpilih' => (string) $nilai === (string) $terpilih];
    }
    return $hasil;
}

// Filter yang sedang aktif sebagai input tersembunyi (agar ikut terkirim bersama kolom cari).
function dataInputTersembunyi(array $parameter): array
{
    $hasil = [];
    foreach ($parameter as $nama => $nilai) {
        $hasil[] = ['nama' => $nama, 'nilai' => $nilai];
    }
    return $hasil;
}

// Modal "Filter tugas" (layout/modal-filter.html; desain m14 admin / m15 mahasiswa).
function dataModalFilter(array $filter, array $daftarMk, bool $untukMahasiswa, string $alamatTujuan): array
{
    $opsiMk = ['' => 'Semua mata kuliah'];
    foreach ($daftarMk as $mk) {
        $opsiMk[$mk['id']] = $mk['nama'];
    }
    return [
        'alamat' => $alamatTujuan,
        'adaCari' => $filter['q'] !== '',
        'cari' => $filter['q'],
        'untukMahasiswa' => $untukMahasiswa,
        'opsiMk' => dataOpsi($opsiMk, $filter['mk'] ?: ''),
        'opsiStatus' => dataOpsi(['' => 'Semua status'] + LABEL_PROGRES, $filter['status']),
        'opsiDeadline' => dataOpsi(['' => 'Semua deadline', 'minggu_ini' => 'Minggu ini', 'terlewat' => 'Terlewat'], $filter['deadline']),
        'opsiUrut' => dataOpsi(['' => 'Paling dekat', 'jauh' => 'Paling jauh', 'baru' => 'Terbaru ditambahkan'], $filter['urut']),
    ];
}

// ===== BAGIAN 4: DAFTAR =====

// Paginasi (layout/paginasi.html): "Menampilkan 1–10 dari 23 tugas" + nomor halaman.
// Filter tetap terbawa di setiap tautan halaman (F-34).
function dataPaginasi(array $paginasi, string $halamanDasar, array $parameterTautan, string $satuan): array
{
    $alamatHalaman = fn (int $nomor) => url(tautan($halamanDasar, $nomor > 1 ? $parameterTautan + ['hal' => $nomor] : $parameterTautan));
    $daftarNomor = [];
    for ($nomor = max(1, $paginasi['hal'] - 2); $nomor <= min($paginasi['jumlahHal'], $paginasi['hal'] + 2); $nomor++) {
        $daftarNomor[] = ['nomor' => $nomor, 'alamat' => $alamatHalaman($nomor), 'aktif' => $nomor === $paginasi['hal']];
    }
    return [
        'tampil' => $paginasi['total'] > 0,
        'awal' => $paginasi['awal'],
        'akhir' => $paginasi['akhir'],
        'total' => $paginasi['total'],
        'satuan' => $satuan,
        'banyakHalaman' => $paginasi['jumlahHal'] > 1,
        'sebelumnya' => $paginasi['hal'] > 1 ? $alamatHalaman($paginasi['hal'] - 1) : null,
        'berikutnya' => $paginasi['hal'] < $paginasi['jumlahHal'] ? $alamatHalaman($paginasi['hal'] + 1) : null,
        'daftarNomor' => $daftarNomor,
    ];
}

// Satu baris tugas di daftar mahasiswa (dashboard & daftar tugas).
function dataBarisTugasMahasiswa(array $baris): array
{
    return [
        'id' => (int) $baris['id'],
        'judul' => $baris['judul'],
        'mkNama' => $baris['mk_nama'],
        'deadline' => labelDeadline($baris['deadline']),
        'terlewat' => sudahLewat($baris['deadline']) && $baris['status'] !== 'selesai', // F-36
        'centang' => $baris['status'] === 'selesai',
        'progres' => dataProgres($baris['status']),
    ];
}

// Satu baris tugas di daftar admin (dashboard & daftar tugas) + ringkasan "x/y selesai".
function dataBarisTugasAdmin(array $baris): array
{
    return [
        'id' => (int) $baris['id'],
        'judul' => $baris['judul'],
        'mkNama' => $baris['mk_nama'],
        'mkKode' => $baris['mk_kode'],
        'deadline' => labelDeadline($baris['deadline']),
        'terlewat' => sudahLewat($baris['deadline']),
        'jumlahSelesai' => (int) $baris['jumlah_selesai'],
        'jumlahAnggota' => (int) $baris['jumlah_anggota'],
        'maksBilah' => max(1, (int) $baris['jumlah_anggota']),
    ];
}

// ===== BAGIAN 5: DETAIL TUGAS =====

// Kartu "Tentang tugas" (layout/tentang-tugas.html; desain 13 & 23):
// deskripsi, daftar persiapan bernomor 01, 02, ... (F-35), dan lampiran bersama.
function dataTentangTugas(array $tugas): array
{
    $poin = [];
    foreach (pecahBaris($tugas['persiapan']) as $urutan => $teks) {
        $poin[] = ['nomor' => duaDigit($urutan + 1), 'teks' => $teks];
    }
    return [
        'deskripsi' => $tugas['deskripsi'],
        'adaPersiapan' => (bool) $poin,
        'poinPersiapan' => $poin,
        'lampiran' => $tugas['lampiran_file'] ? [
            'jenis' => labelJenisFile($tugas['lampiran_mime']),
            'nama' => $tugas['lampiran_nama'],
            'ukuran' => formatUkuran($tugas['lampiran_ukuran']),
            'alamat' => url('unduh.php?jenis=lampiran&tugas=' . (int) $tugas['id']),
        ] : null,
    ];
}
