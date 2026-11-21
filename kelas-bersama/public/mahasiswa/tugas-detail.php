<?php
// =====================================================================
// public/mahasiswa/tugas-detail.php — LOGIKA detail tugas mahasiswa
// F-35 detail · F-42 "Progres saya" · F-43/F-44 "Hasil kerja saya".
// Tampilan (HTML): app/tampilan/mahasiswa/tugas-detail.html (desain 13–16).
// Tugas dari mata kuliah yang tidak disetujui → 404 (U-08, cegah IDOR).
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php';
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibMahasiswa();

// ===== BAGIAN 2: AMBIL TUGAS (JOIN pendaftaran 'disetujui' milik pengguna session) =====
$idTugas = ambilId(ambilGet('id'));
$tugas = $idTugas > 0 ? tugasUntukMahasiswa($idTugas, $pengguna['id']) : null;
if ($tugas === null) {
    tidakDitemukan('Tugas tidak ditemukan. Tugas hanya dapat dibuka dari mata kuliah yang pengajuannya sudah disetujui.');
}
$status = $tugas['status'];
$lewat = sudahLewat($tugas['deadline']);

// F-42: tombol untuk pindah ke dua status lainnya (pola Figma 14–16). Tombol pertama berwarna emas.
$tombolStatus = match ($status) {
    'belum_mulai' => [['selesai', 'Tandai selesai'], ['dikerjakan', 'Sedang dikerjakan']],
    'dikerjakan' => [['selesai', 'Tandai selesai'], ['belum_mulai', 'Belum mulai']],
    default => [['dikerjakan', 'Sedang dikerjakan'], ['belum_mulai', 'Belum mulai']],
};
$dataTombol = [];
foreach ($tombolStatus as $urutan => [$statusBaru, $label]) {
    $dataTombol[] = ['statusBaru' => $statusBaru, 'label' => $label, 'utama' => $urutan === 0];
}

// ===== BAGIAN 3: TAMPILKAN HTML =====
tampilkanHalaman('mahasiswa/tugas-detail', [
    'judulHalaman' => $tugas['judul'],
    'menuAktif' => 'tugas',
    'pengguna' => $pengguna,
    'tugas' => $tugas,
    'info' => [
        'pembuat' => $tugas['pembuat_nama'] ?? 'Admin',
        'dibuat' => formatTanggalPendek($tugas['dibuat']),
        'deadline' => formatTanggalIndo($tugas['deadline']),
        'terlewat' => $lewat && $status !== 'selesai',
    ],
    'tentang' => dataTentangTugas($tugas),
    'progres' => dataProgres($status, true),
    'tombolStatus' => $dataTombol,
    'alamatIni' => 'mahasiswa/tugas-detail.php?id=' . (int) $tugas['id'],
    'hasil' => $tugas['hasil_file'] ? [
        'jenis' => labelJenisFile($tugas['hasil_mime']),
        'nama' => $tugas['hasil_nama'],
        'ukuran' => formatUkuran($tugas['hasil_ukuran']),
        'diunggah' => formatTanggalIndo($tugas['diunggah']),
        'terlambat' => $tugas['diunggah'] !== null && strtotime($tugas['diunggah']) > strtotime($tugas['deadline']), // R-08
        'alamatUnduh' => url('unduh.php?jenis=hasil&tugas=' . (int) $tugas['id'] . '&mhs=' . $pengguna['id']),
    ] : null,
    'lewat' => $lewat,
]);
