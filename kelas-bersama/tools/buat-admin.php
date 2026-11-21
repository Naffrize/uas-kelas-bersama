<?php
// =====================================================================
// tools/buat-admin.php — membuat akun ADMIN lewat terminal (CLI)
//
// Admin tidak bisa mendaftar sendiri lewat web (PRD bagian 4), jadi akun
// admin dibuat dengan skrip ini. Jalankan dari folder proyek:
//     php tools/buat-admin.php
// Windows, bila php belum ada di PATH:
//     C:\xampp\php\php.exe tools\buat-admin.php
// =====================================================================

// ===== BAGIAN 1: HANYA BOLEH DIJALANKAN DARI TERMINAL =====
// tools/.htaccess sudah menolak akses browser; pemeriksaan ini lapisan kedua.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya dapat dijalankan dari terminal.');
}

// ===== BAGIAN 2: FUNGSI BANTU MEMBACA INPUT TERMINAL =====

// Membaca satu baris dari keyboard. Hanya enter di akhir baris yang dibuang.
function bacaBaris(string $label): string
{
    echo $label;
    $baris = fgets(STDIN);
    if ($baris === false) { // Ctrl+C atau input habis
        fwrite(STDERR, PHP_EOL . 'Dibatalkan.' . PHP_EOL);
        exit(1);
    }
    return rtrim($baris, "\r\n");
}

// Isian biasa: spasi di awal & akhir dihapus (sama seperti trim() pada form).
function tanya(string $label): string
{
    return trim(bacaBaris($label));
}

// Kata sandi TIDAK di-trim (sama dengan form login). Di macOS/Linux ketikan
// disembunyikan dengan stty; terminal Windows tidak punya cara bawaan,
// jadi ketikan tetap terlihat — pastikan tidak ada yang melihat layar.
function tanyaSandi(string $label): string
{
    $bisaSembunyi = DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);
    if ($bisaSembunyi) {
        shell_exec('stty -echo');
    }
    $sandi = bacaBaris($label);
    if ($bisaSembunyi) {
        shell_exec('stty echo');
        echo PHP_EOL;
    }
    return $sandi;
}

// ===== BAGIAN 3: SIAPKAN KONEKSI DATABASE =====
$fileKoneksi = __DIR__ . '/../config/koneksi.php';
if (!is_file($fileKoneksi)) {
    fwrite(STDERR, "config/koneksi.php belum ada. Salin config/koneksi.example.php menjadi config/koneksi.php dulu.\n");
    exit(1);
}
// Jika gagal tersambung, koneksi.php menampilkan saran lalu berhenti.
$pdo = require $fileKoneksi;

echo PHP_EOL . '=== Buat akun admin Kelas Bersama ===' . PHP_EOL;
if (DIRECTORY_SEPARATOR === '\\') {
    echo '(Catatan: di Windows kata sandi terlihat saat diketik.)' . PHP_EOL;
}
echo PHP_EOL;

try {
    // ===== BAGIAN 4: BACA & PERIKSA EMAIL =====
    // Aturan sama dengan registrasi (PRD F-01): format valid, maksimal 120 karakter, unik.
    $cekEmail = $pdo->prepare('SELECT id FROM pengguna WHERE email = ?');
    do {
        $email = mb_strtolower(tanya('Email              : '));
        $pesan = '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
            $pesan = 'Email tidak valid (maksimal 120 karakter).';
        } else {
            $cekEmail->execute([$email]);
            if ($cekEmail->fetch()) {
                $pesan = 'Email sudah terdaftar. Gunakan email lain.';
            }
        }
        if ($pesan !== '') {
            echo "  ! $pesan" . PHP_EOL;
        }
    } while ($pesan !== '');

    // ===== BAGIAN 5: BACA & PERIKSA NAMA =====
    do {
        $nama = tanya('Nama lengkap       : ');
        $pesan = '';
        if ($nama === '' || mb_strlen($nama) > 100) {
            $pesan = 'Nama wajib diisi, maksimal 100 karakter.';
            echo "  ! $pesan" . PHP_EOL;
        }
    } while ($pesan !== '');

    // ===== BAGIAN 6: BACA & PERIKSA KATA SANDI =====
    // Minimal 8 karakter; maksimal 72 byte karena bcrypt mengabaikan sisanya.
    do {
        $sandi = tanyaSandi('Kata sandi (8-72)  : ');
        $konfirmasi = tanyaSandi('Ulangi kata sandi  : ');
        $pesan = '';
        if (mb_strlen($sandi) < 8 || strlen($sandi) > 72) {
            $pesan = 'Kata sandi harus 8-72 karakter.';
        } elseif (!hash_equals($sandi, $konfirmasi)) {
            $pesan = 'Konfirmasi kata sandi tidak sama.';
        }
        if ($pesan !== '') {
            echo "  ! $pesan" . PHP_EOL;
        }
    } while ($pesan !== '');

    // ===== BAGIAN 7: SIMPAN AKUN ADMIN =====
    // password_hash() menghasilkan hash bcrypt (diawali $2y$); password asli tidak disimpan.
    $hash = password_hash($sandi, PASSWORD_DEFAULT);
    $query = $pdo->prepare("INSERT INTO pengguna (nama, email, password_hash, peran) VALUES (?, ?, ?, 'admin')");
    $query->execute([$nama, $email, $hash]);

    echo PHP_EOL . 'Berhasil: admin #' . $pdo->lastInsertId() . " ($email) dibuat." . PHP_EOL;
    echo 'Hash kata sandi tersimpan dengan awalan ' . substr($hash, 0, 7) . ' (bcrypt).' . PHP_EOL;
} catch (PDOException $e) {
    // 42S02 = tabel tidak ada; 23000 = email bentrok (didaftarkan bersamaan oleh proses lain).
    $saran = match ($e->getCode()) {
        '42S02' => 'Tabel belum ada. Impor database/schema.sql lewat phpMyAdmin dulu.',
        '23000' => 'Email sudah terdaftar.',
        default => $e->getMessage(),
    };
    fwrite(STDERR, PHP_EOL . 'Gagal menyimpan admin. ' . $saran . PHP_EOL);
    exit(1);
}
