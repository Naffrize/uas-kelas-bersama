<?php
// =====================================================================
// public/admin/tugas-simpan.php — simpan tugas baru / perubahan tugas (F-30, F-31)
// Diagram 6: validasi → (unggah lampiran) → INSERT/UPDATE → hapus lampiran lama → detail.
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php'; // POST tanpa token CSRF → 400
require __DIR__ . '/../../app/auth.php';
require __DIR__ . '/../../app/upload.php';
$pengguna = wajibAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('admin/tugas.php');
}

// ===== BAGIAN 2: DATA LAMA (mode edit) =====
$idTugas = ambilId(ambilPost('id'));
$lama = null;
if ($idTugas > 0) {
    $lama = tugasUntukAdmin($idTugas);
    if ($lama === null) {
        tidakDitemukan('Tugas yang ingin disimpan tidak ditemukan.');
    }
}
$namaForm = $lama ? 'tugas-' . $idTugas : 'tugas-baru';
$alamatForm = 'admin/tugas-form.php' . ($lama ? '?id=' . $idTugas : '');

// ===== BAGIAN 3: BACA & VALIDASI ISIAN (server wajib, atribut HTML hanya pelengkap) =====
$nilai = [
    'judul' => ambilPost('judul'),
    'mata_kuliah_id' => ambilPost('mata_kuliah_id'),
    'deskripsi' => ambilPost('deskripsi'),
    'persiapan' => str_replace("\r\n", "\n", ambilPost('persiapan')),
    'tanggal' => ambilPost('tanggal'),
    'waktu' => ambilPost('waktu'),
];
$galat = [];

if ($nilai['judul'] === '' || mb_strlen($nilai['judul']) > 150) {
    $galat['judul'] = 'Judul tugas wajib diisi, maksimal 150 karakter.';
}
$idMataKuliah = ambilId($nilai['mata_kuliah_id']);
$query = db()->prepare('SELECT id FROM mata_kuliah WHERE id = ?');
$query->execute([$idMataKuliah]);
if (!$query->fetch()) {
    $galat['mata_kuliah_id'] = 'Pilih mata kuliah yang tersedia.';
}
if ($nilai['deskripsi'] === '' || mb_strlen($nilai['deskripsi']) > 5000) {
    $galat['deskripsi'] = 'Deskripsi wajib diisi, maksimal 5.000 karakter.';
}
if (mb_strlen($nilai['persiapan']) > 2000) {
    $galat['persiapan'] = 'Daftar persiapan maksimal 2.000 karakter.';
}
// Tanggal + waktu harus benar-benar valid (misal 31 Februari ditolak).
$deadline = DateTime::createFromFormat('!Y-m-d H:i', $nilai['tanggal'] . ' ' . $nilai['waktu']);
if (!$deadline || $deadline->format('Y-m-d H:i') !== $nilai['tanggal'] . ' ' . $nilai['waktu']) {
    $galat['deadline'] = 'Isi tanggal dan waktu deadline yang valid.';
}
$adaFileBaru = ($_FILES['lampiran']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

if ($galat) {
    if ($adaFileBaru) {
        $galat['lampiran'] = 'Pilih ulang file lampiran (file belum disimpan karena isian lain belum valid).';
    }
    simpanFormLama($namaForm, $nilai, $galat);
    redirect303($alamatForm);
}

// ===== BAGIAN 4: UNGGAH LAMPIRAN (setelah isian lain valid → tidak ada file yatim) =====
try {
    $file = simpanUnggahan('lampiran', 'lampiran');
} catch (UnggahanGagal $e) {
    simpanFormLama($namaForm, $nilai, ['lampiran' => $e->getMessage()]);
    flash('galat', 'File belum dapat diunggah', $e->getMessage(), true); // desain m02
    redirect303($alamatForm);
}
$hapusLampiran = ambilPost('hapus_lampiran') === '1';
$persiapan = trim($nilai['persiapan']) === '' ? null : $nilai['persiapan'];
$waktuDeadline = $deadline->format('Y-m-d H:i:00');

// ===== BAGIAN 5: SIMPAN KE DATABASE =====
try {
    if ($lama) {
        // Lampiran: diganti file baru, dihapus, atau tetap seperti semula.
        $lampiran = [$lama['lampiran_file'], $lama['lampiran_nama'], $lama['lampiran_mime'], $lama['lampiran_ukuran']];
        if ($file) {
            $lampiran = [$file['file'], $file['nama'], $file['mime'], $file['ukuran']];
        } elseif ($hapusLampiran) {
            $lampiran = [null, null, null, null];
        }
        $query = db()->prepare('UPDATE tugas SET mata_kuliah_id = ?, judul = ?, deskripsi = ?, persiapan = ?, deadline = ?,
                lampiran_file = ?, lampiran_nama = ?, lampiran_mime = ?, lampiran_ukuran = ?
            WHERE id = ?');
        $query->execute(array_merge([$idMataKuliah, $nilai['judul'], $nilai['deskripsi'], $persiapan, $waktuDeadline], $lampiran, [$idTugas]));
    } else {
        $query = db()->prepare('INSERT INTO tugas (mata_kuliah_id, judul, deskripsi, persiapan, deadline,
                lampiran_file, lampiran_nama, lampiran_mime, lampiran_ukuran, dibuat_oleh)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $query->execute([$idMataKuliah, $nilai['judul'], $nilai['deskripsi'], $persiapan, $waktuDeadline,
            $file['file'] ?? null, $file['nama'] ?? null, $file['mime'] ?? null, $file['ukuran'] ?? null, $pengguna['id']]);
        $idTugas = (int) db()->lastInsertId();
    }
} catch (Throwable $e) {
    if ($file) {
        hapusFile('lampiran', $file['file']); // DB gagal → file baru dihapus agar tidak yatim
    }
    throw $e;
}

// File lampiran lama dihapus SETELAH UPDATE berhasil (aturan keamanan no. 8).
if ($lama && $lama['lampiran_file'] && ($file || $hapusLampiran)) {
    hapusFile('lampiran', $lama['lampiran_file']);
}

flash('sukses', 'Tugas disimpan', '"' . $nilai['judul'] . '" sudah tersedia untuk anggota mata kuliah.', true);
redirect303('admin/tugas-detail.php?id=' . $idTugas);
