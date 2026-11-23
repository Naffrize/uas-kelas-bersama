<?php
// =====================================================================
// public/admin/matkul-simpan.php — buat / edit mata kuliah (F-11, F-12; diagram 5)
// Kode unik (huruf besar/angka/strip, ≤20), nama 1–100, kapasitas 1–500,
// warna dari daftar WARNA_MATKUL, dan saat edit kapasitas ≥ jumlah anggota disetujui.
// =====================================================================

// ===== BAGIAN 1: KEAMANAN & GUARD =====
require __DIR__ . '/../../app/keamanan.php'; // POST tanpa token CSRF → 400
require __DIR__ . '/../../app/auth.php';
$pengguna = wajibAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('admin/matkul.php');
}

$idMataKuliah = ambilId(ambilPost('id'));
$namaForm = $idMataKuliah > 0 ? 'matkul-' . $idMataKuliah : 'matkul-baru';

// ===== BAGIAN 2: BACA & VALIDASI ISIAN =====
$nilai = [
    'nama' => ambilPost('nama'),
    'kode' => strtoupper(ambilPost('kode')),
    'deskripsi' => str_replace("\r\n", "\n", ambilPost('deskripsi')),
    'kapasitas' => ambilPost('kapasitas'),
    'warna' => ambilPost('warna'),
];
$galat = [];
// Warna hanya boleh dari daftar pilihan (kosong = pakai warna lama / emas, lihat di bawah).
if ($nilai['warna'] !== '' && !array_key_exists($nilai['warna'], WARNA_MATKUL)) {
    $galat['warna'] = 'Pilih salah satu warna yang tersedia.';
}
if ($nilai['nama'] === '' || mb_strlen($nilai['nama']) > 100) {
    $galat['nama'] = 'Nama mata kuliah wajib diisi, maksimal 100 karakter.';
}
if (!preg_match('/^[A-Z0-9]+(-[A-Z0-9]+)*$/', $nilai['kode']) || strlen($nilai['kode']) > 20) {
    $galat['kode'] = 'Gunakan huruf besar, angka, atau strip (contoh IF-PWEB), maksimal 20 karakter.';
}
if (mb_strlen($nilai['deskripsi']) > 1000) {
    $galat['deskripsi'] = 'Deskripsi maksimal 1.000 karakter.';
}
$kapasitas = filter_var($nilai['kapasitas'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
if ($kapasitas === false) {
    $galat['kapasitas'] = 'Kapasitas wajib bilangan bulat 1–500.';
}

$pdo = db();
try {
    $pdo->beginTransaction();

    // Mode edit: kunci baris mata kuliah agar tidak bersamaan dengan persetujuan anggota.
    if ($idMataKuliah > 0) {
        $query = $pdo->prepare('SELECT id, warna FROM mata_kuliah WHERE id = ? FOR UPDATE');
        $query->execute([$idMataKuliah]);
        $lama = $query->fetch();
        if (!$lama) {
            $pdo->rollBack();
            tidakDitemukan('Mata kuliah tidak ditemukan.');
        }
        if ($nilai['warna'] === '') {
            $nilai['warna'] = $lama['warna'];
        }
        $anggota = jumlahAnggotaDisetujui($idMataKuliah);
        if ($kapasitas !== false && $kapasitas < $anggota) {
            $galat['kapasitas'] = "Kapasitas tidak boleh kurang dari jumlah anggota disetujui ($anggota orang).";
        }
    }

    // Kode harus unik (selain mata kuliah ini sendiri).
    if (!isset($galat['kode'])) {
        $query = $pdo->prepare('SELECT id FROM mata_kuliah WHERE kode = ? AND id <> ?');
        $query->execute([$nilai['kode'], $idMataKuliah]);
        if ($query->fetch()) {
            $galat['kode'] = 'Kode MK sudah dipakai mata kuliah lain.';
        }
    }

    if ($galat) {
        $pdo->rollBack();
        simpanFormLama($namaForm, $nilai, $galat);
        flash('galat', 'Mata kuliah belum disimpan', 'Periksa kembali isian yang ditandai.');
        redirect303('admin/matkul.php');
    }

    // ===== BAGIAN 3: SIMPAN =====
    $deskripsi = trim($nilai['deskripsi']) === '' ? null : $nilai['deskripsi'];
    $warna = $nilai['warna'] === '' ? 'emas' : $nilai['warna'];
    if ($idMataKuliah > 0) {
        $query = $pdo->prepare('UPDATE mata_kuliah SET kode = ?, nama = ?, deskripsi = ?, kapasitas = ?, warna = ? WHERE id = ?');
        $query->execute([$nilai['kode'], $nilai['nama'], $deskripsi, $kapasitas, $warna, $idMataKuliah]);
    } else {
        $query = $pdo->prepare('INSERT INTO mata_kuliah (kode, nama, deskripsi, kapasitas, warna, dibuat_oleh) VALUES (?, ?, ?, ?, ?, ?)');
        $query->execute([$nilai['kode'], $nilai['nama'], $deskripsi, $kapasitas, $warna, $pengguna['id']]);
    }
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() !== '23000') {
        throw $e;
    }
    // Kode didaftarkan bersamaan oleh admin lain (UNIQUE di database).
    simpanFormLama($namaForm, $nilai, ['kode' => 'Kode MK sudah dipakai mata kuliah lain.']);
    redirect303('admin/matkul.php');
}

// Desain m13 "Pengaturan disimpan".
flash('sukses', 'Mata kuliah disimpan', $nilai['nama'] . ' (' . $nilai['kode'] . ') berhasil ' . ($idMataKuliah > 0 ? 'diperbarui.' : 'dibuat.'), true);
redirect303('admin/matkul.php');
