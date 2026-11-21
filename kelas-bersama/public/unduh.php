<?php
// =====================================================================
// public/unduh.php — satu-satunya jalan mengunduh file di storage/ (aturan keamanan no. 9)
// Diagram 9: login → ambil data → 404 bila tidak ada → periksa hak → kirim file.
//   unduh.php?jenis=lampiran&tugas=ID          (lampiran referensi tugas)
//   unduh.php?jenis=hasil&tugas=ID&mhs=ID      (hasil kerja mahasiswa)
// Admin boleh semua. Mahasiswa: lampiran hanya dari mata kuliah yang disetujui;
// hasil kerja hanya miliknya sendiri (U-13 → 403).
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../app/keamanan.php';
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/upload.php';
$pengguna = wajibLogin();
$adalahAdminUnduh = $pengguna['peran'] === 'admin';

$jenis = ambilGet('jenis');
$idTugas = ambilId(ambilGet('tugas'));
if (!in_array($jenis, ['lampiran', 'hasil'], true) || $idTugas === 0) {
    tidakDitemukan('File tidak ditemukan.');
}

// ===== BAGIAN 2: AMBIL DATA FILE & PERIKSA HAK AKSES =====
if ($jenis === 'lampiran') {
    $query = db()->prepare('SELECT mata_kuliah_id, lampiran_file AS file, lampiran_nama AS nama, lampiran_mime AS mime FROM tugas WHERE id = ?');
    $query->execute([$idTugas]);
    $data = $query->fetch();
    if (!$data || !$data['file']) {
        tidakDitemukan('File lampiran tidak ditemukan.');
    }
    if (!$adalahAdminUnduh) {
        $query = db()->prepare("SELECT 1 FROM pendaftaran WHERE pengguna_id = ? AND mata_kuliah_id = ? AND status = 'disetujui'");
        $query->execute([$pengguna['id'], $data['mata_kuliah_id']]);
        if (!$query->fetchColumn()) {
            tampilkanGalat(403, 'Akses ditolak', 'Lampiran ini hanya untuk anggota mata kuliah yang sudah disetujui.');
        }
    }
} else {
    $idMahasiswa = ambilId(ambilGet('mhs'));
    // Mahasiswa hanya boleh mengunduh hasil kerjanya sendiri (ID dibandingkan dengan session).
    if (!$adalahAdminUnduh && $idMahasiswa !== $pengguna['id']) {
        tampilkanGalat(403, 'Akses ditolak', 'Hasil kerja hanya dapat diunduh oleh pemiliknya dan admin.');
    }
    $query = db()->prepare('SELECT t.mata_kuliah_id, tm.hasil_file AS file, tm.hasil_nama AS nama, tm.hasil_mime AS mime
        FROM tugas_mahasiswa tm JOIN tugas t ON t.id = tm.tugas_id
        WHERE tm.tugas_id = ? AND tm.pengguna_id = ?');
    $query->execute([$idTugas, $idMahasiswa]);
    $data = $query->fetch();
    if (!$data || !$data['file']) {
        tidakDitemukan('File hasil kerja tidak ditemukan.');
    }
    // F-24: mahasiswa yang dikeluarkan tidak dapat lagi mengakses hasil kerjanya.
    if (!$adalahAdminUnduh) {
        $query = db()->prepare("SELECT 1 FROM pendaftaran WHERE pengguna_id = ? AND mata_kuliah_id = ? AND status = 'disetujui'");
        $query->execute([$pengguna['id'], $data['mata_kuliah_id']]);
        if (!$query->fetchColumn()) {
            tampilkanGalat(403, 'Akses ditolak', 'Anda tidak lagi menjadi anggota mata kuliah ini.');
        }
    }
}

$path = pathFileUnggahan($jenis, $data['file']);
if ($path === null) {
    tidakDitemukan('File fisik tidak ditemukan di server.');
}

// ===== BAGIAN 3: KIRIM FILE =====
// Content-Type dari database (hasil finfo saat unggah, bukan dari nama file),
// nosniff (sudah dikirim keamanan.php), dan Content-Disposition attachment dengan nama asli.
$mime = array_key_exists($data['mime'], MIME_DOKUMEN) ? $data['mime'] : 'application/octet-stream';
$namaAscii = preg_replace('/[^A-Za-z0-9._ -]/', '_', $data['nama']) ?: 'file';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $namaAscii . '"; filename*=UTF-8\'\'' . rawurlencode($data['nama']));
header('Cache-Control: private, no-store');
readfile($path);
exit;
