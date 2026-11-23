<?php
// =====================================================================
// app/kueri.php — query yang dipakai lebih dari satu halaman
//
// Semua nilai dari pengguna dikirim lewat tanda ? (prepared statement).
// Potongan SQL yang digabung (kondisi filter, ORDER BY) hanya teks TETAP
// dari file ini, dipilih lewat daftar putih — bukan teks dari input.
// =====================================================================

// ===== BAGIAN 1: MATA KULIAH & PENDAFTARAN =====

function semuaMataKuliah(): array
{
    return db()->query('SELECT id, kode, nama, kapasitas FROM mata_kuliah ORDER BY nama')->fetchAll();
}

// Mata kuliah yang pendaftarannya sudah 'disetujui' untuk mahasiswa ini (R-05).
function mataKuliahDisetujui(int $idPengguna): array
{
    $query = db()->prepare("SELECT mk.id, mk.kode, mk.nama, mk.kapasitas
        FROM pendaftaran p JOIN mata_kuliah mk ON mk.id = p.mata_kuliah_id
        WHERE p.pengguna_id = ? AND p.status = 'disetujui'
        ORDER BY mk.nama");
    $query->execute([$idPengguna]);
    return $query->fetchAll();
}

function jumlahAnggotaDisetujui(int $idMataKuliah): int
{
    $query = db()->prepare("SELECT COUNT(*) FROM pendaftaran WHERE mata_kuliah_id = ? AND status = 'disetujui'");
    $query->execute([$idMataKuliah]);
    return (int) $query->fetchColumn();
}

function jumlahPengajuanMenunggu(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM pendaftaran WHERE status = 'menunggu'")->fetchColumn();
}

// Angka di panel navy halaman masuk & daftar (PRD bagian 9: tampilkan jumlah mata kuliah).
function ringkasPanelTamu(): array
{
    return array_map('intval', db()->query("SELECT
            (SELECT COUNT(*) FROM mata_kuliah) AS mata_kuliah,
            (SELECT COUNT(*) FROM tugas) AS tugas,
            (SELECT COUNT(DISTINCT pengguna_id) FROM pendaftaran WHERE status = 'disetujui') AS anggota,
            (SELECT COALESCE(SUM(kapasitas), 0) FROM mata_kuliah) AS kapasitas")->fetch());
}

// ===== BAGIAN 2: FILTER DAFTAR TUGAS (F-34) =====
// Parameter GET: q (cari), mk, status (khusus mahasiswa), deadline, urut, hal.

function bacaFilterTugas(bool $untukMahasiswa): array
{
    $status = ambilGet('status');
    $deadline = ambilGet('deadline');
    $urut = ambilGet('urut');
    return [
        'q' => mb_substr(ambilGet('q'), 0, 100),
        'mk' => ambilId(ambilGet('mk')),
        'status' => $untukMahasiswa && array_key_exists($status, LABEL_PROGRES) ? $status : '',
        'deadline' => in_array($deadline, ['minggu_ini', 'terlewat'], true) ? $deadline : '',
        'urut' => in_array($urut, ['jauh', 'baru'], true) ? $urut : '',
        'hal' => max(1, ambilId(ambilGet('hal'))),
    ];
}

// Parameter filter yang terisi, untuk membuat tautan (paginasi, chip, "lihat semua").
// Halaman 1 tidak perlu ditulis di alamat.
function parameterFilter(array $filter, array $ganti = []): array
{
    $hasil = array_merge($filter, ['hal' => 0], $ganti);
    if ($hasil['hal'] === 1) {
        $hasil['hal'] = 0;
    }
    return array_filter($hasil, fn ($nilai) => $nilai !== '' && $nilai !== 0 && $nilai !== null);
}

function tautan(string $halaman, array $parameter = []): string
{
    $query = http_build_query($parameter);
    return $halaman . ($query === '' ? '' : '?' . $query);
}

// Kondisi WHERE + parameternya dari filter.
function kondisiFilterTugas(array $filter, bool $untukMahasiswa): array
{
    $bagian = ['1 = 1'];
    $parameter = [];
    if ($filter['q'] !== '') {
        // % dan _ dari pengguna di-escape agar dicari sebagai huruf biasa.
        $pola = '%' . addcslashes($filter['q'], '%_\\') . '%';
        $bagian[] = '(t.judul LIKE ? OR mk.nama LIKE ? OR mk.kode LIKE ?)';
        array_push($parameter, $pola, $pola, $pola);
    }
    if ($filter['mk'] > 0) {
        $bagian[] = 't.mata_kuliah_id = ?';
        $parameter[] = $filter['mk'];
    }
    if ($untukMahasiswa && $filter['status'] !== '') {
        $bagian[] = "COALESCE(tm.status, 'belum_mulai') = ?";
        $parameter[] = $filter['status'];
    }
    if ($filter['deadline'] === 'minggu_ini') {
        $bagian[] = 't.deadline BETWEEN ? AND ?';
        $parameter[] = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $parameter[] = date('Y-m-d 23:59:59', strtotime('sunday this week'));
    } elseif ($filter['deadline'] === 'terlewat') {
        $bagian[] = $untukMahasiswa
            ? "t.deadline < NOW() AND COALESCE(tm.status, 'belum_mulai') <> 'selesai'"
            : 't.deadline < NOW()';
    }
    return [implode(' AND ', $bagian), $parameter];
}

// ORDER BY dari daftar putih. "dekat": tugas mendatang paling dekat dulu, lalu yang sudah lewat.
function urutanTugas(string $urut): string
{
    return match ($urut) {
        'jauh' => 't.deadline DESC, t.id DESC',
        'baru' => 't.dibuat DESC, t.id DESC',
        default => '(t.deadline < NOW()) ASC, CASE WHEN t.deadline >= NOW() THEN t.deadline END ASC, t.deadline DESC, t.id ASC',
    };
}

// Hitung halaman. Nomor halaman di luar jangkauan dikoreksi otomatis.
function paginasi(int $total, int $hal, int $perHalaman): array
{
    $jumlahHal = max(1, (int) ceil($total / $perHalaman));
    $hal = min(max(1, $hal), $jumlahHal);
    $offset = ($hal - 1) * $perHalaman;
    return [
        'hal' => $hal,
        'jumlahHal' => $jumlahHal,
        'offset' => $offset,
        'perHalaman' => $perHalaman,
        'total' => $total,
        'awal' => $total === 0 ? 0 : $offset + 1,
        'akhir' => min($offset + $perHalaman, $total),
    ];
}

// ===== BAGIAN 3: DAFTAR TUGAS =====

// To-do mahasiswa: HANYA tugas dari mata kuliah berstatus 'disetujui' (PRD 8.2, R-05).
function daftarTugasMahasiswa(int $idPengguna, array $filter, int $hal, int $perHalaman): array
{
    [$kondisi, $parameter] = kondisiFilterTugas($filter, true);
    $dari = "FROM tugas t
        JOIN mata_kuliah mk ON mk.id = t.mata_kuliah_id
        JOIN pendaftaran p ON p.mata_kuliah_id = t.mata_kuliah_id AND p.pengguna_id = ? AND p.status = 'disetujui'
        LEFT JOIN tugas_mahasiswa tm ON tm.tugas_id = t.id AND tm.pengguna_id = ?
        WHERE $kondisi";
    $dasar = array_merge([$idPengguna, $idPengguna], $parameter);

    $halaman = paginasi((int) jalankan("SELECT COUNT(*) $dari", $dasar)->fetchColumn(), $hal, $perHalaman);
    $baris = jalankan("SELECT t.id, t.judul, t.deadline, mk.id AS mk_id, mk.nama AS mk_nama, mk.kode AS mk_kode, mk.warna AS mk_warna,
            COALESCE(tm.status, 'belum_mulai') AS status, (tm.hasil_file IS NOT NULL) AS ada_hasil
        $dari ORDER BY " . urutanTugas($filter['urut']) . ' LIMIT ? OFFSET ?',
        array_merge($dasar, [$halaman['perHalaman'], $halaman['offset']]))->fetchAll();

    return ['baris' => $baris, 'paginasi' => $halaman];
}

// Daftar tugas admin + ringkasan pengumpulan "x/y selesai" (F-33).
function daftarTugasAdmin(array $filter, int $hal, int $perHalaman): array
{
    [$kondisi, $parameter] = kondisiFilterTugas($filter, false);
    $dari = "FROM tugas t JOIN mata_kuliah mk ON mk.id = t.mata_kuliah_id WHERE $kondisi";

    $halaman = paginasi((int) jalankan("SELECT COUNT(*) $dari", $parameter)->fetchColumn(), $hal, $perHalaman);
    $baris = jalankan("SELECT t.id, t.judul, t.deadline, mk.id AS mk_id, mk.nama AS mk_nama, mk.kode AS mk_kode, mk.warna AS mk_warna,
            (SELECT COUNT(*) FROM pendaftaran pa
                WHERE pa.mata_kuliah_id = t.mata_kuliah_id AND pa.status = 'disetujui') AS jumlah_anggota,
            (SELECT COUNT(*) FROM tugas_mahasiswa tm
                JOIN pendaftaran pa ON pa.pengguna_id = tm.pengguna_id AND pa.mata_kuliah_id = t.mata_kuliah_id AND pa.status = 'disetujui'
                WHERE tm.tugas_id = t.id AND tm.status = 'selesai') AS jumlah_selesai
        $dari ORDER BY " . urutanTugas($filter['urut']) . ' LIMIT ? OFFSET ?',
        array_merge($parameter, [$halaman['perHalaman'], $halaman['offset']]))->fetchAll();

    return ['baris' => $baris, 'paginasi' => $halaman];
}

// ===== BAGIAN 4: SATU TUGAS (cegah IDOR) =====

// Detail tugas untuk mahasiswa: null jika tugas tidak ada ATAU bukan dari mata
// kuliah yang disetujui untuk mahasiswa ini (U-08 → 404). ID mahasiswa dari session.
function tugasUntukMahasiswa(int $idTugas, int $idPengguna): ?array
{
    $query = db()->prepare("SELECT t.*, mk.nama AS mk_nama, mk.kode AS mk_kode, mk.warna AS mk_warna, pb.nama AS pembuat_nama,
            COALESCE(tm.status, 'belum_mulai') AS status, tm.hasil_file, tm.hasil_nama, tm.hasil_mime, tm.hasil_ukuran, tm.diunggah
        FROM tugas t
        JOIN mata_kuliah mk ON mk.id = t.mata_kuliah_id
        JOIN pendaftaran p ON p.mata_kuliah_id = t.mata_kuliah_id AND p.pengguna_id = ? AND p.status = 'disetujui'
        LEFT JOIN tugas_mahasiswa tm ON tm.tugas_id = t.id AND tm.pengguna_id = ?
        LEFT JOIN pengguna pb ON pb.id = t.dibuat_oleh
        WHERE t.id = ?");
    $query->execute([$idPengguna, $idPengguna, $idTugas]);
    return $query->fetch() ?: null;
}

function tugasUntukAdmin(int $idTugas): ?array
{
    $query = db()->prepare('SELECT t.*, mk.nama AS mk_nama, mk.kode AS mk_kode, mk.warna AS mk_warna, pb.nama AS pembuat_nama
        FROM tugas t
        JOIN mata_kuliah mk ON mk.id = t.mata_kuliah_id
        LEFT JOIN pengguna pb ON pb.id = t.dibuat_oleh
        WHERE t.id = ?');
    $query->execute([$idTugas]);
    return $query->fetch() ?: null;
}

// ===== BAGIAN 5: DASHBOARD =====

// Statistik progres pribadi (F-61, F-62). Belum ada baris progres = belum_mulai.
function statistikMahasiswa(int $idPengguna): array
{
    $query = db()->prepare("SELECT COUNT(*) AS total,
            COALESCE(SUM(COALESCE(tm.status, 'belum_mulai') = 'belum_mulai'), 0) AS belum,
            COALESCE(SUM(tm.status = 'dikerjakan'), 0) AS dikerjakan,
            COALESCE(SUM(tm.status = 'selesai'), 0) AS selesai
        FROM tugas t
        JOIN pendaftaran p ON p.mata_kuliah_id = t.mata_kuliah_id AND p.pengguna_id = ? AND p.status = 'disetujui'
        LEFT JOIN tugas_mahasiswa tm ON tm.tugas_id = t.id AND tm.pengguna_id = ?");
    $query->execute([$idPengguna, $idPengguna]);
    return array_map('intval', $query->fetch());
}

// Tugas belum selesai dengan deadline terdekat (F-63).
function tugasTerdekatMahasiswa(int $idPengguna): ?array
{
    $query = db()->prepare("SELECT t.id, t.judul, t.deadline, mk.nama AS mk_nama, mk.warna AS mk_warna, COALESCE(tm.status, 'belum_mulai') AS status
        FROM tugas t
        JOIN mata_kuliah mk ON mk.id = t.mata_kuliah_id
        JOIN pendaftaran p ON p.mata_kuliah_id = t.mata_kuliah_id AND p.pengguna_id = ? AND p.status = 'disetujui'
        LEFT JOIN tugas_mahasiswa tm ON tm.tugas_id = t.id AND tm.pengguna_id = ?
        WHERE COALESCE(tm.status, 'belum_mulai') <> 'selesai'
        ORDER BY " . urutanTugas('') . ' LIMIT 1');
    $query->execute([$idPengguna, $idPengguna]);
    return $query->fetch() ?: null;
}

function statistikAdmin(): array
{
    return array_map('intval', db()->query("SELECT
            (SELECT COUNT(*) FROM tugas) AS tugas,
            (SELECT COUNT(DISTINCT pengguna_id) FROM pendaftaran WHERE status = 'disetujui') AS mahasiswa_aktif,
            (SELECT COUNT(*) FROM pengguna WHERE peran = 'mahasiswa') AS mahasiswa_total,
            (SELECT COUNT(*) FROM mata_kuliah) AS mata_kuliah,
            (SELECT COUNT(*) FROM pendaftaran WHERE status = 'menunggu') AS menunggu")->fetch());
}

// Tugas mendatang dengan deadline terdekat + jumlah anggota yang sudah selesai.
function tugasTerdekatAdmin(): ?array
{
    $baris = db()->query("SELECT t.id, t.judul, t.deadline, mk.nama AS mk_nama, mk.warna AS mk_warna,
            (SELECT COUNT(*) FROM pendaftaran pa WHERE pa.mata_kuliah_id = t.mata_kuliah_id AND pa.status = 'disetujui') AS jumlah_anggota,
            (SELECT COUNT(*) FROM tugas_mahasiswa tm
                JOIN pendaftaran pa ON pa.pengguna_id = tm.pengguna_id AND pa.mata_kuliah_id = t.mata_kuliah_id AND pa.status = 'disetujui'
                WHERE tm.tugas_id = t.id AND tm.status = 'selesai') AS jumlah_selesai
        FROM tugas t JOIN mata_kuliah mk ON mk.id = t.mata_kuliah_id
        WHERE t.deadline >= NOW()
        ORDER BY t.deadline ASC LIMIT 1")->fetch();
    return $baris ?: null;
}
