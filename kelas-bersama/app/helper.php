<?php
// =====================================================================
// app/helper.php — fungsi bantu umum yang dipakai banyak halaman
//
//   1. db(), jalankan()             koneksi PDO & query berparameter
//   2. url(), aset()                alamat halaman/aset dari folder public
//   3. e()                          escaping output HTML (aturan keamanan no. 6)
//   4. redirect303()                pola Post/Redirect/Get
//   5. flash()                      pesan yang tampil sekali
//   6. simpanFormLama()             isian form yang gagal divalidasi
//   7. ambilPost/ambilGet/ambilId   membaca input dengan aman
//   8. tanggal & waktu WITA         format Indonesia, salam, semester
//   9. teks, angka, status          nama depan, inisial, ukuran file, label
//  10. tampilkanGalat()             halaman 400/403/404/413
//  11. alamatKembali()              tujuan redirect internal yang aman
// =====================================================================

// ===== BAGIAN 1: DATABASE =====

// Koneksi dibuka sekali per permintaan lalu dipakai ulang ($pdo pola jobsheet).
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $fileKoneksi = __DIR__ . '/../config/koneksi.php';
        if (!is_file($fileKoneksi)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            exit('config/koneksi.php belum ada. Salin config/koneksi.example.php menjadi config/koneksi.php.');
        }
        $pdo = require $fileKoneksi;
    }
    return $pdo;
}

// Menjalankan prepared statement. Nilai int diikat sebagai PARAM_INT agar bisa
// dipakai pada LIMIT/OFFSET. Semua nilai tetap dikirim lewat tanda ? (aturan no. 1).
function jalankan(string $sql, array $parameter = []): PDOStatement
{
    $query = db()->prepare($sql);
    foreach (array_values($parameter) as $urutan => $nilai) {
        $tipe = is_int($nilai) ? PDO::PARAM_INT : ($nilai === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $query->bindValue($urutan + 1, $nilai, $tipe);
    }
    $query->execute();
    return $query;
}

// ===== BAGIAN 2: ALAMAT (URL) =====

// Folder URL dari public/, dihitung otomatis sehingga aplikasi tetap berjalan walau
// nama folder di htdocs berbeda (kelas-bersama, uas-kelas-bersama, dan seterusnya).
function urlDasar(): string
{
    static $dasar = null;
    if ($dasar === null) {
        $folderUrl = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $folderFile = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
        // Halaman di public/admin/ atau public/mahasiswa/ berada satu tingkat di bawah public/.
        if ($folderFile !== false && $folderFile !== realpath(__DIR__ . '/../public')) {
            $folderUrl = str_replace('\\', '/', dirname($folderUrl));
        }
        $dasar = rtrim($folderUrl, '/') . '/';
    }
    return $dasar;
}

// url('admin/tugas.php?id=3') → /kelas-bersama/public/admin/tugas.php?id=3
function url(string $path = ''): string
{
    return urlDasar() . ltrim($path, '/');
}

// Alamat file di public/assets dengan penanda versi (?v=waktu ubah file) agar
// browser langsung memakai CSS/JS terbaru setelah build.
function aset(string $path): string
{
    $file = __DIR__ . '/../public/assets/' . $path;
    return url('assets/' . $path) . '?v=' . (is_file($file) ? filemtime($file) : 0);
}

// ===== BAGIAN 3: OUTPUT AMAN =====

// Semua data yang dicetak ke HTML wajib lewat e() (aturan keamanan no. 6).
function e(string|int|float|null $teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

// ===== BAGIAN 4: REDIRECT 303 (POST/REDIRECT/GET) =====

// $tujuan relatif terhadap folder public, misalnya 'admin/matkul.php'.
// Status 303 membuat browser membuka tujuan dengan GET, jadi tombol Refresh
// tidak mengirim ulang form.
function redirect303(string $tujuan): void
{
    header('Location: ' . url($tujuan), true, 303);
    exit;
}

// ===== BAGIAN 5: PESAN FLASH =====

// $jenis: 'sukses' atau 'galat'. $modal = true → tampil sebagai kotak dialog
// (desain m12/m13/m02); selain itu sebagai toast kecil yang hilang sendiri.
function flash(string $jenis, string $judul, string $pesan = '', bool $modal = false): void
{
    $_SESSION['flash'][] = ['jenis' => $jenis, 'judul' => $judul, 'pesan' => $pesan, 'modal' => $modal];
}

function ambilFlash(): array
{
    $daftar = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($daftar) ? $daftar : [];
}

// ===== BAGIAN 6: ISIAN FORM LAMA =====

// Validasi di file aksi gagal → nilai & pesan galat disimpan sebentar, lalu
// halaman form menampilkannya lagi (diagram 6: "isian tetap terisi").
function simpanFormLama(string $namaForm, array $nilai, array $galat): void
{
    $_SESSION['form_lama'] = ['nama' => $namaForm, 'nilai' => $nilai, 'galat' => $galat];
}

// Diambil sekali saja: ['nama' => ..., 'nilai' => [...], 'galat' => [...]].
function ambilFormLama(): array
{
    $data = $_SESSION['form_lama'] ?? null;
    unset($_SESSION['form_lama']);
    if (!is_array($data)) {
        return ['nama' => '', 'nilai' => [], 'galat' => []];
    }
    return $data;
}

// ===== BAGIAN 7: MEMBACA INPUT =====

// Pola wajib CLAUDE.md: hanya terima string, lalu trim. Array/nilai aneh → ''.
function ambilPost(string $nama): string
{
    return is_string($_POST[$nama] ?? null) ? trim($_POST[$nama]) : '';
}

function ambilGet(string $nama): string
{
    return is_string($_GET[$nama] ?? null) ? trim($_GET[$nama]) : '';
}

// ID dari URL/form harus bilangan bulat positif; selain itu dianggap 0 (tidak ada).
function ambilId(string $nilai): int
{
    $id = filter_var($nilai, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : $id;
}

// ===== BAGIAN 8: TANGGAL & WAKTU (WITA) =====

const NAMA_BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const NAMA_BULAN_PENDEK = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

// "2026-11-19 23:59:00" → "19 November 2026, 23.59 WITA"
function formatTanggalIndo(?string $waktu, bool $denganJam = true): string
{
    $detik = $waktu ? strtotime($waktu) : false;
    if ($detik === false) {
        return '—';
    }
    $teks = date('j', $detik) . ' ' . NAMA_BULAN[(int) date('n', $detik)] . ' ' . date('Y', $detik);
    return $denganJam ? $teks . ', ' . date('H.i', $detik) . ' WITA' : $teks;
}

// "2026-11-18 10:00:00" → "18 Nov 2026"
function formatTanggalPendek(?string $waktu): string
{
    $detik = $waktu ? strtotime($waktu) : false;
    if ($detik === false) {
        return '—';
    }
    return date('j', $detik) . ' ' . NAMA_BULAN_PENDEK[(int) date('n', $detik)] . ' ' . date('Y', $detik);
}

// Selisih hari kalender dari hari ini (0 = hari ini, 1 = besok, -1 = kemarin).
function selisihHari(string $waktu): int
{
    $hariItu = strtotime(date('Y-m-d', strtotime($waktu)));
    return (int) round(($hariItu - strtotime(date('Y-m-d'))) / 86400);
}

// Label ringkas di daftar: "Hari ini · 23.59", "Besok · 23.59", "18 Nov · 23.59".
function labelDeadline(string $waktu): string
{
    $detik = strtotime($waktu);
    $jam = date('H.i', $detik);
    $tanggal = date('j', $detik) . ' ' . NAMA_BULAN_PENDEK[(int) date('n', $detik)];
    return match (selisihHari($waktu)) {
        0 => "Hari ini · $jam",
        1 => "Besok · $jam",
        -1 => "Kemarin · $jam",
        default => (date('Y', $detik) === date('Y') ? $tanggal : $tanggal . ' ' . date('Y', $detik)) . " · $jam",
    };
}

// Kalimat untuk kartu "Jangan terlewat".
function kalimatDeadline(string $waktu): string
{
    $detik = strtotime($waktu);
    $hari = selisihHari($waktu);
    $jam = date('H.i', $detik);
    if ($detik < time()) {
        return $hari === 0 ? "Deadline hari ini pukul $jam sudah lewat." : 'Deadline sudah lewat ' . abs($hari) . ' hari.';
    }
    return match ($hari) {
        0 => "Deadline hari ini, pukul $jam.",
        1 => "Deadline besok, pukul $jam.",
        default => 'Deadline ' . date('j', $detik) . ' ' . NAMA_BULAN[(int) date('n', $detik)] . ", pukul $jam ($hari hari lagi).",
    };
}

// "19 NOVEMBER" untuk label tanggal berbentuk pil.
function tanggalPill(string $waktu): string
{
    $detik = strtotime($waktu);
    return mb_strtoupper(date('j', $detik) . ' ' . NAMA_BULAN[(int) date('n', $detik)]);
}

function sudahLewat(string $waktu): bool
{
    return strtotime($waktu) < time();
}

// Salam sesuai jam WITA (F-60).
function salam(): string
{
    $jam = (int) date('G');
    return match (true) {
        $jam >= 4 && $jam < 11 => 'Selamat pagi',
        $jam >= 11 && $jam < 15 => 'Selamat siang',
        $jam >= 15 && $jam < 18 => 'Selamat sore',
        default => 'Selamat malam',
    };
}

// Semester berjalan: Agustus–Januari = ganjil, Februari–Juli = genap.
function semesterSekarang(): string
{
    $bulan = (int) date('n');
    $tahun = (int) date('Y');
    if ($bulan >= 8) {
        return "Semester ganjil $tahun/" . ($tahun + 1);
    }
    if ($bulan === 1) {
        return 'Semester ganjil ' . ($tahun - 1) . "/$tahun";
    }
    return 'Semester genap ' . ($tahun - 1) . "/$tahun";
}

// ===== BAGIAN 9: TEKS, ANGKA, STATUS =====

const LABEL_PROGRES = ['belum_mulai' => 'Belum mulai', 'dikerjakan' => 'Sedang dikerjakan', 'selesai' => 'Selesai'];
const PERSEN_PROGRES = ['belum_mulai' => 0, 'dikerjakan' => 60, 'selesai' => 100];
const LABEL_PENDAFTARAN = ['menunggu' => 'Menunggu persetujuan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'dikeluarkan' => 'Dikeluarkan'];

function namaDepan(string $nama): string
{
    $bagian = preg_split('/\s+/', trim($nama)) ?: [];
    return $bagian[0] ?? $nama;
}

// "Nadia Putri" → "NP" (avatar tanpa foto).
function inisial(string $nama): string
{
    $hasil = '';
    foreach (array_slice(preg_split('/\s+/', trim($nama)) ?: [], 0, 2) as $kata) {
        $hasil .= mb_strtoupper(mb_substr($kata, 0, 1));
    }
    return $hasil !== '' ? $hasil : '?';
}

// 4 → "04" (angka statistik bergaya desain).
function duaDigit(int $angka): string
{
    return str_pad((string) $angka, 2, '0', STR_PAD_LEFT);
}

// 1258291 → "1,2 MB"
function formatUkuran(int|string|null $byte): string
{
    if ($byte === null || $byte === '') {
        return '—';
    }
    $byte = (int) $byte;
    if ($byte < 1024) {
        return $byte . ' B';
    }
    if ($byte < 1024 * 1024) {
        return number_format($byte / 1024, 0, ',', '.') . ' KB';
    }
    return number_format($byte / 1024 / 1024, 1, ',', '.') . ' MB';
}

// Teks banyak baris → daftar baris yang tidak kosong ("Yang perlu disiapkan", satu poin per baris).
function pecahBaris(?string $teks): array
{
    $baris = array_map('trim', preg_split('/\R/', (string) $teks) ?: []);
    return array_values(array_filter($baris, fn ($isi) => $isi !== ''));
}

// Label jenis file dari MIME yang tersimpan di database.
function labelJenisFile(?string $mime): string
{
    return match ($mime) {
        'application/pdf' => 'PDF',
        'application/msword' => 'DOC',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
        'image/jpeg' => 'JPG',
        'image/png' => 'PNG',
        'image/webp' => 'WEBP',
        default => 'FILE',
    };
}

// ===== BAGIAN 10: HALAMAN GALAT =====

// Tampilan: app/tampilan/galat.html. Bisa dipanggil dari mana saja, termasuk
// app/keamanan.php sebelum app/auth.php dimuat (karena itu tampilan.php dimuat di sini).
function tampilkanGalat(int $kode, string $judul, string $pesan): void
{
    require_once __DIR__ . '/tampilan.php';
    http_response_code($kode);
    // Tombol kembali menyesuaikan status login.
    [$tujuan, $label] = match ($_SESSION['peran'] ?? '') {
        'admin' => ['admin/dashboard.php', 'Kembali ke dashboard'],
        'mahasiswa' => ['mahasiswa/dashboard.php', 'Kembali ke dashboard'],
        default => ['login.php', 'Ke halaman masuk'],
    };
    echo renderTemplat('galat', dataUmum($judul) + [
        'kode' => $kode,
        'judul' => $judul,
        'pesan' => $pesan,
        'alamatKembali' => url($tujuan),
        'labelKembali' => $label,
    ]);
    exit;
}

function tidakDitemukan(string $pesan = 'Data yang Anda cari tidak ada atau tidak dapat Anda akses.'): void
{
    tampilkanGalat(404, 'Tidak ditemukan', $pesan);
}

// ===== BAGIAN 11: ALAMAT KEMBALI YANG AMAN =====

// Form boleh mengirim "kembali" agar pengguna kembali ke daftar yang sama (filter tetap).
// Hanya halaman internal dari daftar $halamanBoleh yang diterima → mencegah open redirect.
function alamatKembali(string $kembali, array $halamanBoleh, string $bawaan): string
{
    $path = (string) parse_url($kembali, PHP_URL_PATH);
    $aman = in_array($path, $halamanBoleh, true)
        && !preg_match('#//|\\\\|[\x00-\x20]#', $kembali)
        && !str_contains($kembali, ':');
    return $aman ? $kembali : $bawaan;
}
