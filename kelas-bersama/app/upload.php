<?php
// =====================================================================
// app/upload.php — validasi & penyimpanan file unggahan (diagram 8)
//
// Aturan keamanan no. 8: cek $_FILES[...]['error'], ukuran, MIME dari ISI file
// (finfo, bukan nama/ekstensi), nama acak bin2hex(random_bytes(16)), nama asli
// hanya disimpan di database. File lama dihapus SETELAH UPDATE/DELETE berhasil.
//
//   simpanUnggahan('hasil', 'hasil')  → null (tidak memilih file) atau
//       ['file' => nama acak, 'nama' => nama asli, 'mime' => ..., 'ukuran' => byte]
//       Gagal → melempar UnggahanGagal berisi pesan ramah.
//   hapusFile('hasil', $namaAcak)     → menghapus file fisik bila ada.
// =====================================================================

const BATAS_UKURAN_UNGGAH = 5 * 1024 * 1024; // 5 MB (PRD F-43)
const MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

// Whitelist PRD 10.1: MIME hasil finfo → ekstensi yang dipakai saat menyimpan.
const MIME_DOKUMEN = [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    MIME_DOCX => 'docx',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
const MIME_GAMBAR = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

class UnggahanGagal extends RuntimeException
{
    // Alasan: 'ukuran' (terlalu besar), 'jenis' (tidak didukung), atau 'lain'.
    public string $alasan;

    public function __construct(string $pesan, string $alasan = 'lain')
    {
        parent::__construct($pesan);
        $this->alasan = $alasan;
    }
}

// Folder tujuan & whitelist per jenis unggahan.
// lampiran & hasil → storage/ (privat, diunduh lewat unduh.php); foto → public/uploads/foto.
function aturanUnggahan(string $jenis): array
{
    return match ($jenis) {
        'lampiran' => ['folder' => __DIR__ . '/../storage/uploads/lampiran', 'mime' => MIME_DOKUMEN, 'daftar' => 'PDF, DOC, DOCX, JPG, PNG, atau WEBP'],
        'hasil' => ['folder' => __DIR__ . '/../storage/uploads/hasil', 'mime' => MIME_DOKUMEN, 'daftar' => 'PDF, DOC, DOCX, JPG, PNG, atau WEBP'],
        'foto' => ['folder' => __DIR__ . '/../public/uploads/foto', 'mime' => MIME_GAMBAR, 'daftar' => 'JPG, PNG, atau WEBP'],
    };
}

function simpanUnggahan(string $field, string $jenis): ?array
{
    $aturan = aturanUnggahan($jenis);
    $file = $_FILES[$field] ?? null;

    // 1. Tidak memilih file bukan galat.
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (!is_int($file['error']) || !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null)) {
        throw new UnggahanGagal('Kirim satu file saja.');
    }
    $namaAsli = namaFileAman($file['name']);

    // 2. Kode error bawaan PHP.
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => new UnggahanGagal("Ukuran $namaAsli melebihi batas server. Batas maksimal 5 MB per file. Pilih file yang lebih kecil.", 'ukuran'),
            UPLOAD_ERR_PARTIAL => new UnggahanGagal('File hanya terunggah sebagian. Periksa koneksi lalu coba lagi.'),
            default => new UnggahanGagal('Server tidak dapat menerima file ini. Coba lagi beberapa saat lagi.'),
        };
    }

    // 3. Ukuran maksimal 5 MB.
    $ukuran = (int) $file['size'];
    if ($ukuran > BATAS_UKURAN_UNGGAH) {
        throw new UnggahanGagal("Ukuran $namaAsli adalah " . formatUkuran($ukuran) . '. Batas maksimal 5 MB per file. Pilih file yang lebih kecil.', 'ukuran');
    }
    if ($ukuran === 0 || !is_uploaded_file($file['tmp_name'])) {
        throw new UnggahanGagal("File $namaAsli kosong atau tidak valid.");
    }

    // 4. Jenis file dibaca dari ISI file, bukan dari nama/ekstensi.
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ekstensiAsli = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));
    $ekstensi = $aturan['mime'][$mime] ?? null;

    // 4a. Sebagian versi libmagic membaca DOCX sebagai ZIP (PRD 10.1): terima hanya jika
    //     ekstensi aslinya .docx DAN arsip benar-benar berisi word/document.xml.
    if ($ekstensi === null && isset($aturan['mime'][MIME_DOCX]) && $ekstensiAsli === 'docx'
        && in_array($mime, ['application/zip', 'application/octet-stream'], true)
        && zipBerisi($file['tmp_name'], 'word/document.xml')) {
        [$mime, $ekstensi] = [MIME_DOCX, 'docx'];
    }
    // 4b. DOC lama (format OLE) pada sebagian libmagic terbaca sebagai berkas Office umum.
    if ($ekstensi === null && isset($aturan['mime']['application/msword']) && $ekstensiAsli === 'doc'
        && in_array($mime, ['application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'], true)) {
        [$mime, $ekstensi] = ['application/msword', 'doc'];
    }
    if ($ekstensi === null) {
        throw new UnggahanGagal("Jenis file $namaAsli tidak didukung. Gunakan {$aturan['daftar']}.", 'jenis');
    }

    // 5. Gambar diperiksa strukturnya agar bukan skrip yang disamarkan.
    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
        throw new UnggahanGagal("$namaAsli bukan gambar yang valid.", 'jenis');
    }

    // 6. Simpan dengan nama acak; nama asli hanya untuk database.
    if (!is_dir($aturan['folder']) && !mkdir($aturan['folder'], 0775, true)) {
        throw new UnggahanGagal('Folder unggahan tidak dapat dibuat di server.');
    }
    $namaBaru = bin2hex(random_bytes(16)) . '.' . $ekstensi;
    if (!move_uploaded_file($file['tmp_name'], $aturan['folder'] . '/' . $namaBaru)) {
        throw new UnggahanGagal('File gagal disimpan di server. Coba lagi.');
    }

    return ['file' => $namaBaru, 'nama' => $namaAsli, 'mime' => $mime, 'ukuran' => $ukuran];
}

// Nama asli dibersihkan: tanpa folder, tanpa karakter kontrol & tanda kutip, maksimal 200 huruf.
function namaFileAman(string $nama): string
{
    $nama = basename(str_replace('\\', '/', $nama));
    $nama = trim((string) preg_replace('/[\x00-\x1F\x7F"]/u', '', $nama));
    if ($nama === '' || $nama === '.' || $nama === '..') {
        $nama = 'file';
    }
    return mb_substr($nama, 0, 200);
}

// Nama file buatan server saja yang boleh dipakai untuk membuka/menghapus file.
function namaFileValid(?string $nama): bool
{
    return $nama !== null && preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx|jpg|png|webp)$/', $nama) === 1;
}

function pathFileUnggahan(string $jenis, ?string $nama): ?string
{
    if (!namaFileValid($nama)) {
        return null;
    }
    $path = aturanUnggahan($jenis)['folder'] . '/' . $nama;
    return is_file($path) ? $path : null;
}

function hapusFile(string $jenis, ?string $nama): void
{
    $path = pathFileUnggahan($jenis, $nama);
    if ($path !== null) {
        unlink($path);
    }
}

// Apakah arsip ZIP berisi entri tertentu? Memakai ZipArchive bila ekstensi zip aktif;
// XAMPP bawaan tidak mengaktifkannya, jadi ada cadangan: membaca "central directory" ZIP.
function zipBerisi(string $path, string $entri): bool
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $ada = $zip->locateName($entri) !== false;
        $zip->close();
        return $ada;
    }

    $isi = file_get_contents($path);
    $akhir = $isi === false ? false : strrpos($isi, "PK\x05\x06"); // End Of Central Directory
    if ($akhir === false || strlen($isi) < $akhir + 22) {
        return false;
    }
    $info = unpack('vjumlah/Vukuran/Voffset', substr($isi, $akhir + 10, 10));
    $posisi = $info['offset'];
    for ($i = 0; $i < $info['jumlah']; $i++) {
        if ($posisi + 46 > strlen($isi) || substr($isi, $posisi, 4) !== "PK\x01\x02") {
            return false;
        }
        $panjang = unpack('vnama/vekstra/vkomentar', substr($isi, $posisi + 28, 6));
        if (substr($isi, $posisi + 46, $panjang['nama']) === $entri) {
            return true;
        }
        $posisi += 46 + $panjang['nama'] + $panjang['ekstra'] + $panjang['komentar'];
    }
    return false;
}
