<?php
// =====================================================================
// app/templat.php — mesin templat untuk file tampilan .html murni
//
// File app/tampilan/*.html hanya berisi HTML + class Tailwind, TANPA kode PHP.
// File logika (public/*.php) mengirim data, lalu tanda di bawah ini diganti isinya:
//
//   {{nama}}                    teks biasa, OTOMATIS di-escape (aturan keamanan no. 6)
//   {{pengguna.nama}}           ambil isi array bertingkat (titik = masuk ke dalam)
//   {{{ikonHtml}}}              potongan HTML jadi; hanya objek HtmlAman yang dicetak apa adanya
//   {{#daftar}} ... {{/daftar}} ulangi untuk setiap isi daftar, atau tampilkan sekali bila nilainya "ya"
//   {{^daftar}} ... {{/daftar}} tampilkan bila daftar kosong / nilainya "tidak"
//   {{> layout/sidebar}}        sisipkan file tampilan lain (app/tampilan/layout/sidebar.html)
//   {{! catatan }}              komentar untuk pembaca file, tidak dikirim ke browser
//
// Sintaksnya mengikuti Mustache (https://mustache.github.io), versi ringkas tanpa Composer.
// Mesin ini tidak memakai eval: teks templat hanya dipotong-potong lalu disusun ulang.
// =====================================================================

// ===== BAGIAN 1: HTML YANG SUDAH AMAN =====
// {{{...}}} hanya mencetak apa adanya bila nilainya objek ini. String biasa tetap di-escape,
// jadi data dari pengguna tidak mungkin lolos sebagai HTML walau salah memakai {{{ }}}.
final class HtmlAman
{
    public function __construct(private string $html)
    {
    }

    public function __toString(): string
    {
        return $this->html;
    }
}

// ===== BAGIAN 2: FUNGSI UTAMA =====

// renderTemplat('admin/matkul', $data) → isi app/tampilan/admin/matkul.html dengan data terisi.
function renderTemplat(string $nama, array $data): string
{
    return renderSimpul(muatTemplat($nama), [$data], $nama, 0);
}

// Membaca & mengurai file templat sekali per permintaan (hasilnya disimpan sementara).
function muatTemplat(string $nama): array
{
    static $simpanan = [];
    if (isset($simpanan[$nama])) {
        return $simpanan[$nama];
    }
    // Nama templat berasal dari kode, tetapi polanya tetap dibatasi (tanpa "..", tanpa ekstensi lain).
    if (!preg_match('#^[a-z0-9]+(-[a-z0-9]+)*(/[a-z0-9]+(-[a-z0-9]+)*)*$#', $nama)) {
        throw new InvalidArgumentException('Nama templat tidak valid: ' . $nama);
    }
    $file = __DIR__ . '/tampilan/' . $nama . '.html';
    if (!is_file($file)) {
        throw new RuntimeException('File templat tidak ditemukan: app/tampilan/' . $nama . '.html');
    }
    return $simpanan[$nama] = uraiTemplat((string) file_get_contents($file), $nama);
}

// ===== BAGIAN 3: MENGURAI TEKS TEMPLAT MENJADI POHON =====

// Hasil: daftar simpul ['teks', isi] | ['data', nama, escape?] | ['bagian', nama, kebalikan?, anak[]]
//        | ['sisipan', namaTemplat]. Komentar dibuang.
function uraiTemplat(string $teks, string $namaTemplat): array
{
    // {{{nama}}} | {{#nama}} {{^nama}} {{/nama}} {{>nama}} {{!komentar}} {{nama}}
    $pola = '/\{\{\{\s*([\w.]+)\s*\}\}\}|\{\{([#^\/>!]?)\s*(.*?)\s*\}\}/s';
    preg_match_all($pola, $teks, $cocok, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    // 1) Potong menjadi token teks & tanda secara bergantian.
    $token = [];
    $posisi = 0;
    foreach ($cocok as $c) {
        [$utuh, $mulai] = $c[0];
        $token[] = ['teks', substr($teks, $posisi, $mulai - $posisi)];
        if (isset($c[1]) && $c[1][1] !== -1 && $c[1][0] !== '') {
            $token[] = ['{', $c[1][0]];
        } else {
            $token[] = [$c[2][0] === '' ? 'data' : $c[2][0], $c[3][0]];
        }
        $posisi = $mulai + strlen($utuh);
    }
    $token[] = ['teks', substr($teks, $posisi)];

    // 2) Tanda blok yang berdiri sendiri di satu baris ({{#..}}, {{/..}}, {{>..}}, {{!..}})
    //    tidak meninggalkan baris kosong di hasil HTML.
    //    Keputusan diambil dari teks asli dulu, baru dipotong, agar dua tanda blok
    //    di baris berurutan sama-sama dikenali.
    $jumlah = count($token);
    $potongAwal = array_fill(0, $jumlah, 0);
    $potongAkhir = array_fill(0, $jumlah, 0);
    for ($i = 1; $i < $jumlah - 1; $i += 2) {
        if (!in_array($token[$i][0], ['#', '^', '/', '>', '!'], true)) {
            continue;
        }
        $sebelum = $token[$i - 1][1];
        $sesudah = $token[$i + 1][1];
        $awalBaris = strrpos($sebelum, "\n");
        $sisaKiri = $awalBaris === false ? $sebelum : substr($sebelum, $awalBaris + 1);
        $kiriKosong = trim($sisaKiri, " \t") === '' && ($awalBaris !== false || $i === 1);
        $akhirBaris = strpos($sesudah, "\n");
        $sisaKanan = $akhirBaris === false ? $sesudah : substr($sesudah, 0, $akhirBaris);
        $kananKosong = trim($sisaKanan, " \t\r") === '' && ($akhirBaris !== false || $i === $jumlah - 2);
        if ($kiriKosong && $kananKosong) {
            $potongAkhir[$i - 1] = strlen($sisaKiri);
            $potongAwal[$i + 1] = $akhirBaris === false ? strlen($sesudah) : $akhirBaris + 1;
        }
    }
    for ($i = 0; $i < $jumlah; $i += 2) {
        $isi = $token[$i][1];
        $token[$i][1] = (string) substr($isi, $potongAwal[$i], max(0, strlen($isi) - $potongAwal[$i] - $potongAkhir[$i]));
    }

    // 3) Susun pohon: isi {{#..}}/{{^..}} menjadi anak sampai tanda {{/..}} yang sesuai.
    $akar = [];
    $tumpukan = [[&$akar, null]];
    foreach ($token as [$jenis, $isi]) {
        $tempat = &$tumpukan[count($tumpukan) - 1][0];
        switch ($jenis) {
            case 'teks':
                if ($isi !== '') {
                    $tempat[] = ['teks', $isi];
                }
                break;
            case 'data':
                $tempat[] = ['data', $isi, true];
                break;
            case '{':
                $tempat[] = ['data', $isi, false];
                break;
            case '>':
                $tempat[] = ['sisipan', $isi];
                break;
            case '!':
                break;
            case '#':
            case '^':
                $tempat[] = ['bagian', $isi, $jenis === '^', []];
                $baru = &$tempat[count($tempat) - 1][3];
                $tumpukan[] = [&$baru, $isi];
                unset($baru);
                break;
            case '/':
                $terbuka = $tumpukan[count($tumpukan) - 1][1];
                if ($terbuka !== $isi) {
                    throw new RuntimeException("Templat $namaTemplat: {{/$isi}} tidak cocok dengan pembukanya ({{#" . ($terbuka ?? '?') . '}}).');
                }
                array_pop($tumpukan);
                break;
        }
        unset($tempat);
    }
    if (count($tumpukan) !== 1) {
        throw new RuntimeException("Templat $namaTemplat: {{#" . $tumpukan[count($tumpukan) - 1][1] . '}} belum ditutup.');
    }
    return $akar;
}

// ===== BAGIAN 4: MENGISI POHON DENGAN DATA =====

function renderSimpul(array $simpul, array $konteks, string $namaTemplat, int $kedalaman): string
{
    $hasil = '';
    foreach ($simpul as $s) {
        switch ($s[0]) {
            case 'teks':
                $hasil .= $s[1];
                break;

            case 'data':
                [$ada, $nilai] = cariData($s[1], $konteks);
                if (!$ada) {
                    // Salah ketik nama data langsung ketahuan, tidak diam-diam kosong.
                    throw new RuntimeException("Templat $namaTemplat: data {{{$s[1]}}} tidak dikirim oleh file logika.");
                }
                $hasil .= $s[2] || !($nilai instanceof HtmlAman) ? teksAman($nilai, $s[1], $namaTemplat) : (string) $nilai;
                break;

            case 'bagian':
                [, $nilai] = cariData($s[1], $konteks);
                if ($s[2]) {
                    // {{^nama}}: tampil bila kosong/tidak.
                    if (!bernilai($nilai)) {
                        $hasil .= renderSimpul($s[3], $konteks, $namaTemplat, $kedalaman);
                    }
                } elseif (is_array($nilai) && adalahDaftar($nilai)) {
                    // {{#daftar}}: ulangi untuk setiap isi.
                    foreach ($nilai as $isi) {
                        $hasil .= renderSimpul($s[3], [...$konteks, $isi], $namaTemplat, $kedalaman);
                    }
                } elseif (bernilai($nilai)) {
                    // {{#nama}} dengan nilai tunggal: tampil sekali (array → isinya bisa dipanggil langsung).
                    $hasil .= renderSimpul($s[3], [...$konteks, $nilai], $namaTemplat, $kedalaman);
                }
                break;

            case 'sisipan':
                if ($kedalaman > 20) {
                    throw new RuntimeException("Templat $namaTemplat: sisipan {{> {$s[1]}}} terlalu dalam (berulang?).");
                }
                $hasil .= renderSimpul(muatTemplat($s[1]), $konteks, $s[1], $kedalaman + 1);
                break;
        }
    }
    return $hasil;
}

// Mencari data dari konteks paling dalam (isi perulangan) ke paling luar (data halaman).
// Hasil: [ditemukan?, nilai]. "." = isi perulangan saat ini.
function cariData(string $nama, array $konteks): array
{
    if ($nama === '.') {
        return [true, $konteks[count($konteks) - 1]];
    }
    $bagian = explode('.', $nama);
    $pertama = array_shift($bagian);
    for ($i = count($konteks) - 1; $i >= 0; $i--) {
        if (!is_array($konteks[$i]) || !array_key_exists($pertama, $konteks[$i])) {
            continue;
        }
        $nilai = $konteks[$i][$pertama];
        foreach ($bagian as $kunci) {
            if (!is_array($nilai) || !array_key_exists($kunci, $nilai)) {
                return [false, null];
            }
            $nilai = $nilai[$kunci];
        }
        return [true, $nilai];
    }
    return [false, null];
}

// Nilai "kosong" untuk {{#..}} dan {{^..}}: null, false, '', 0, '0', daftar kosong.
function bernilai(mixed $nilai): bool
{
    return is_array($nilai) ? $nilai !== [] : (bool) $nilai;
}

// Array berurutan 0,1,2,... dianggap daftar (diulang); array berkunci nama dianggap satu data.
function adalahDaftar(array $nilai): bool
{
    return $nilai === [] || array_keys($nilai) === range(0, count($nilai) - 1);
}

// Semua teks dicetak lewat htmlspecialchars — sama dengan e() di app/helper.php.
function teksAman(mixed $nilai, string $nama, string $namaTemplat): string
{
    if (is_array($nilai)) {
        throw new RuntimeException("Templat $namaTemplat: {{{$nama}}} berisi daftar; pakai {{#$nama}} ... {{/$nama}}.");
    }
    if ($nilai === null || $nilai === false) {
        return '';
    }
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}
