<?php
// =====================================================================
// app/auth.php — status login, guard peran, dan "Ingat saya" (aturan 2, 4, 5)
//
// Halaman terlindungi memanggil guard di baris awal:
//     $pengguna = wajibLogin();      // admin & mahasiswa
//     $pengguna = wajibAdmin();      // khusus admin
//     $pengguna = wajibMahasiswa();  // khusus mahasiswa
// Halaman tamu (login/registrasi) memanggil wajibTamu().
// =====================================================================
require_once __DIR__ . '/keamanan.php';
require_once __DIR__ . '/komponen.php';
require_once __DIR__ . '/kueri.php';
require_once __DIR__ . '/tampilan.php'; // tampilkanHalaman(): memisahkan logika & HTML

const COOKIE_INGAT = 'kelasbersama_ingat';
const HARI_INGAT = 30;

// ===== BAGIAN 1: PENGGUNA YANG SEDANG LOGIN =====

// Data diambil dari database (bukan hanya dari session) agar nama/foto selalu
// terbaru, dan akun yang sudah tidak ada otomatis dianggap keluar.
function penggunaSaatIni(bool $muatUlang = false): ?array
{
    static $pengguna = false;
    if ($pengguna === false || $muatUlang) {
        $pengguna = null;
        $id = (int) ($_SESSION['pengguna_id'] ?? 0);
        if ($id > 0) {
            $query = db()->prepare('SELECT id, nama, email, peran, foto FROM pengguna WHERE id = ?');
            $query->execute([$id]);
            $baris = $query->fetch();
            if ($baris) {
                $baris['id'] = (int) $baris['id'];
                $pengguna = $baris;
            } else {
                unset($_SESSION['pengguna_id'], $_SESSION['peran']);
            }
        }
    }
    return $pengguna;
}

// ===== BAGIAN 2: MASUK & KELUAR =====

function masukkanPengguna(array $akun, bool $ingat = false): void
{
    session_regenerate_id(true);                    // ID session baru: cegah session fixation
    $_SESSION['pengguna_id'] = (int) $akun['id'];
    $_SESSION['peran'] = $akun['peran'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));  // token CSRF baru setelah login
    if ($ingat) {
        buatTokenIngat((int) $akun['id']);
    }
}

// 1. Hapus token "Ingat saya"  2. Kosongkan session  3. Hapus cookie  4. Hancurkan session.
function keluarkanPengguna(): void
{
    hapusTokenIngatSaatIni();
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'],
    ]);
    session_destroy();
}

function dashboardUntuk(string $peran): string
{
    return $peran === 'admin' ? 'admin/dashboard.php' : 'mahasiswa/dashboard.php';
}

// ===== BAGIAN 3: GUARD =====

function wajibLogin(): array
{
    $pengguna = penggunaSaatIni() ?? masukDariCookieIngat();
    if ($pengguna === null) {
        // Simpan halaman yang diminta agar setelah login pengguna kembali ke sana.
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_SESSION['tujuan_login'] = $_SERVER['REQUEST_URI'] ?? '';
        }
        redirect303('login.php');
    }
    return $pengguna;
}

function wajibAdmin(): array
{
    $pengguna = wajibLogin();
    if ($pengguna['peran'] !== 'admin') {
        tampilkanGalat(403, 'Akses ditolak', 'Halaman ini khusus admin. Silakan kembali ke dashboard Anda.');
    }
    return $pengguna;
}

function wajibMahasiswa(): array
{
    $pengguna = wajibLogin();
    if ($pengguna['peran'] !== 'mahasiswa') {
        tampilkanGalat(403, 'Akses ditolak', 'Halaman ini khusus mahasiswa. Silakan kembali ke dashboard Anda.');
    }
    return $pengguna;
}

// Halaman tamu: pengguna yang sudah login langsung diarahkan ke dashboard-nya.
function wajibTamu(): void
{
    $pengguna = penggunaSaatIni() ?? masukDariCookieIngat();
    if ($pengguna !== null) {
        redirect303(dashboardUntuk($pengguna['peran']));
    }
}

// Tujuan setelah login: halaman yang tadi diminta (hanya alamat internal yang
// sesuai peran), selain itu dashboard. Mencegah open redirect.
function alamatSetelahLogin(array $akun): string
{
    $tujuan = $_SESSION['tujuan_login'] ?? '';
    unset($_SESSION['tujuan_login']);
    $dasar = urlDasar();
    if (is_string($tujuan) && str_starts_with($tujuan, $dasar) && !preg_match('#//|\\\\#', $tujuan)) {
        $relatif = substr($tujuan, strlen($dasar));
        $awalanBoleh = [$akun['peran'] === 'admin' ? 'admin/' : 'mahasiswa/', 'profil.php', 'unduh.php'];
        foreach ($awalanBoleh as $awalan) {
            if (str_starts_with($relatif, $awalan)) {
                return $relatif;
            }
        }
    }
    return dashboardUntuk($akun['peran']);
}

// ===== BAGIAN 4: "INGAT SAYA SELAMA 30 HARI" (PRD F-07) =====
// Cookie berisi "pemilih:validator". Database hanya menyimpan hash SHA-256 validator.
// Token dipakai sekali: setiap login otomatis, token lama dihapus dan diganti baru.

function aturCookieIngat(string $nilai, int $kedaluwarsa): void
{
    setcookie(COOKIE_INGAT, $nilai, [
        'expires' => $kedaluwarsa,
        'path' => urlDasar(),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => ($_SERVER['HTTPS'] ?? 'off') !== 'off',
    ]);
}

function buatTokenIngat(int $idPengguna): void
{
    db()->exec('DELETE FROM token_ingat WHERE kedaluwarsa < NOW()'); // bersihkan token kedaluwarsa
    $pemilih = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $kedaluwarsa = time() + HARI_INGAT * 86400;
    $query = db()->prepare('INSERT INTO token_ingat (pengguna_id, pemilih, validator_hash, kedaluwarsa) VALUES (?, ?, ?, ?)');
    $query->execute([$idPengguna, $pemilih, hash('sha256', $validator), date('Y-m-d H:i:s', $kedaluwarsa)]);
    aturCookieIngat($pemilih . ':' . $validator, $kedaluwarsa);
}

function masukDariCookieIngat(): ?array
{
    $cookie = $_COOKIE[COOKIE_INGAT] ?? '';
    if (!is_string($cookie) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $cocok)) {
        return null;
    }
    $query = db()->prepare('SELECT t.id, t.validator_hash, p.id AS pengguna_id, p.peran
        FROM token_ingat t JOIN pengguna p ON p.id = t.pengguna_id
        WHERE t.pemilih = ? AND t.kedaluwarsa > NOW()');
    $query->execute([$cocok[1]]);
    $token = $query->fetch();
    if ($token) {
        db()->prepare('DELETE FROM token_ingat WHERE id = ?')->execute([$token['id']]);
    }
    if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $cocok[2]))) {
        aturCookieIngat('', time() - 3600);
        return null;
    }
    masukkanPengguna(['id' => $token['pengguna_id'], 'peran' => $token['peran']], true);
    return penggunaSaatIni(true);
}

function hapusTokenIngatSaatIni(): void
{
    $cookie = $_COOKIE[COOKIE_INGAT] ?? '';
    if (is_string($cookie) && preg_match('/^([a-f0-9]{24}):/', $cookie, $cocok)) {
        db()->prepare('DELETE FROM token_ingat WHERE pemilih = ?')->execute([$cocok[1]]);
    }
    if ($cookie !== '') {
        aturCookieIngat('', time() - 3600);
    }
}

// Dipakai saat kata sandi diganti: semua perangkat yang "diingat" harus login ulang.
function hapusSemuaTokenIngat(int $idPengguna): void
{
    db()->prepare('DELETE FROM token_ingat WHERE pengguna_id = ?')->execute([$idPengguna]);
    aturCookieIngat('', time() - 3600);
}
