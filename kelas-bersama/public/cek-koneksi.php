<?php
// =====================================================================
// public/cek-koneksi.php — LOGIKA pemeriksaan kesiapan instalasi (pola jobsheet P10)
// Tampilan (HTML): app/tampilan/cek-koneksi.html
//
// Dipakai setiap anggota kelompok setelah clone/instal untuk memastikan
// PHP, database, data contoh, akun admin, dan tema Tailwind sudah benar.
// Halaman ini menampilkan info teknis, jadi hanya dapat dibuka dari localhost.
// =====================================================================
date_default_timezone_set('Asia/Makassar');

// ===== BAGIAN 1: HANYA UNTUK KOMPUTER SERVER SENDIRI =====
$alamatPengunjung = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($alamatPengunjung, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Halaman pemeriksaan hanya dapat dibuka dari komputer server (localhost).');
}

// ===== BAGIAN 2: FUNGSI BANTU =====

// Hanya fungsi bantu & mesin templat (tanpa session/guard), agar halaman ini tetap
// bisa dibuka walau tabel aplikasi belum lengkap.
require __DIR__ . '/../app/helper.php';
require __DIR__ . '/../app/tampilan.php';

// Mengubah nilai php.ini seperti "40M" atau "512K" menjadi byte.
function keByte(string $nilai): int
{
    $nilai = trim($nilai);
    $angka = (int) $nilai;
    return match (strtoupper(substr($nilai, -1))) {
        'G'     => $angka * 1024 * 1024 * 1024,
        'M'     => $angka * 1024 * 1024,
        'K'     => $angka * 1024,
        default => $angka,
    };
}

// ===== BAGIAN 3: BUKA KONEKSI DATABASE =====
$fileKoneksi = __DIR__ . '/../config/koneksi.php';
if (!is_file($fileKoneksi)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('config/koneksi.php belum ada. Salin config/koneksi.example.php menjadi config/koneksi.php, lalu muat ulang halaman ini.');
}
// Jika gagal tersambung, koneksi.php menampilkan pesan + saran perbaikan lalu berhenti.
$pdo = require $fileKoneksi;

// ===== BAGIAN 4: KUMPULKAN HASIL PEMERIKSAAN =====
// Setiap baris berisi: judul, lulus (true/false), keterangan.
$hasil = [];

$namaDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$versiDatabase = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
$hasil[] = ['Koneksi database', true, "Database $namaDatabase tersambung (server $versiDatabase)."];

// 4a. PHP dan ekstensi yang dibutuhkan aplikasi.
$hasil[] = [
    'Versi PHP',
    version_compare(PHP_VERSION, '8.0.0', '>='),
    'PHP ' . PHP_VERSION . ' (minimal 8.0).',
];
$ekstensiKurang = array_filter(['pdo_mysql', 'fileinfo', 'mbstring'], fn ($nama) => !extension_loaded($nama));
$hasil[] = [
    'Ekstensi PHP',
    !$ekstensiKurang,
    $ekstensiKurang
        ? 'Belum aktif: ' . implode(', ', $ekstensiKurang) . '. Aktifkan di php.ini lalu restart Apache.'
        : 'pdo_mysql, fileinfo (cek jenis file unggahan), dan mbstring aktif.',
];

// 4b. Tabel dari database/schema.sql.
$tabelWajib = ['pengguna', 'mata_kuliah', 'pendaftaran', 'tugas', 'tugas_mahasiswa', 'token_ingat'];
$query = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
$query->execute([$namaDatabase]);
$tabelKurang = array_diff($tabelWajib, $query->fetchAll(PDO::FETCH_COLUMN));
$hasil[] = [
    'Tabel aplikasi',
    !$tabelKurang,
    $tabelKurang
        ? 'Belum ada: ' . implode(', ', $tabelKurang) . '. Impor database/schema.sql.'
        : count($tabelWajib) . ' tabel lengkap: ' . implode(', ', $tabelWajib) . '.',
];

// 4c. Data contoh & akun admin (hanya bila tabel sudah lengkap).
if (!$tabelKurang) {
    // Kolom warna mata kuliah (ditambahkan setelah versi awal; database lama perlu migrasi).
    $query = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'mata_kuliah' AND COLUMN_NAME = 'warna'");
    $query->execute([$namaDatabase]);
    $adaKolomWarna = (int) $query->fetchColumn() > 0;
    $hasil[] = [
        'Kolom warna mata kuliah',
        $adaKolomWarna,
        $adaKolomWarna ? 'Tersedia.' : 'Belum ada. Impor database/migrasi-warna-matkul.sql (data lama tidak terhapus).',
    ];

    $jumlahMataKuliah = (int) $pdo->query('SELECT COUNT(*) FROM mata_kuliah')->fetchColumn();
    $hasil[] = [
        'Data contoh mata kuliah',
        $jumlahMataKuliah > 0,
        $jumlahMataKuliah > 0 ? "$jumlahMataKuliah mata kuliah tersedia." : 'Masih kosong. Impor database/seed.sql.',
    ];

    $jumlahAdmin = (int) $pdo->query("SELECT COUNT(*) FROM pengguna WHERE peran = 'admin'")->fetchColumn();
    $hasil[] = [
        'Akun admin',
        $jumlahAdmin > 0,
        $jumlahAdmin > 0 ? "$jumlahAdmin akun admin terdaftar." : 'Belum ada. Jalankan: php tools/buat-admin.php',
    ];
}

// 4d. Tailwind CSS: dimuat dari CDN, tema dari tema.css, salinan lokal sebagai cadangan offline.
$fileTema = __DIR__ . '/assets/css/tema.css';
$fileCadangan = __DIR__ . '/assets/js/vendor/tailwindcss-browser-4.3.5.js';
$tailwindSiap = is_file($fileTema) && is_file($fileCadangan) && filesize($fileCadangan) > 0;
$hasil[] = [
    'Tailwind CSS (CDN + tema)',
    $tailwindSiap,
    $tailwindSiap
        ? 'tema.css ditemukan; Tailwind dimuat dari CDN jsDelivr, salinan lokal ' . number_format(filesize($fileCadangan) / 1024, 0, ',', '.') . ' KB siap bila offline.'
        : 'tema.css atau public/assets/js/vendor/tailwindcss-browser-4.3.5.js tidak ditemukan. Ambil ulang dari repo.',
];

// 4e. Batas unggah php.ini (PRD 10.1: upload_max_filesize & post_max_size minimal 8M).
$batasUnggah = (string) ini_get('upload_max_filesize');
$batasPost = (string) ini_get('post_max_size');
$minimal = 8 * 1024 * 1024;
$hasil[] = [
    'Batas unggah php.ini',
    keByte($batasUnggah) >= $minimal && (keByte($batasPost) >= $minimal || keByte($batasPost) === 0),
    "upload_max_filesize = $batasUnggah, post_max_size = $batasPost (minimal 8M).",
];

// 4f. Folder unggahan harus dapat ditulis PHP.
$folderUnggahan = ['storage/uploads/lampiran', 'storage/uploads/hasil', 'public/uploads/foto'];
$folderGagal = array_filter($folderUnggahan, fn ($folder) => !is_writable(__DIR__ . '/../' . $folder));
$hasil[] = [
    'Folder unggahan',
    !$folderGagal,
    $folderGagal
        ? 'Tidak dapat ditulis: ' . implode(', ', $folderGagal) . '.'
        : 'Ketiga folder unggahan dapat ditulis.',
];

// 4g. Zona waktu PHP dan MySQL harus sama-sama WITA.
$waktuPhp = time();
$waktuDatabase = strtotime((string) $pdo->query('SELECT NOW()')->fetchColumn());
$hasil[] = [
    'Zona waktu (WITA)',
    abs($waktuPhp - $waktuDatabase) <= 60,
    'PHP ' . date('d-m-Y H.i', $waktuPhp) . ' · MySQL ' . date('d-m-Y H.i', $waktuDatabase) . ' (WITA).',
];

$jumlahLulus = count(array_filter($hasil, fn ($baris) => $baris[1]));
$jumlahCek = count($hasil);

// ===== BAGIAN 5: TAMPILKAN HTML =====
// Sengaja tidak memakai tampilkanHalaman(): halaman ini harus tetap tampil walau
// tabel/sesi aplikasi belum siap, jadi tampilannya mandiri tanpa sidebar & topbar.
echo renderTemplat('cek-koneksi', dataUmum('Cek instalasi') + [
    'hasil' => array_map(fn ($baris) => ['judul' => $baris[0], 'lulus' => $baris[1], 'keterangan' => $baris[2]], $hasil),
    'jumlahLulus' => $jumlahLulus,
    'jumlahCek' => $jumlahCek,
    'persenLulus' => (int) round($jumlahLulus / $jumlahCek * 100),
    'semuaLulus' => $jumlahLulus === $jumlahCek,
]);
