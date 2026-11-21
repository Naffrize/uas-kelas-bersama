-- =====================================================================
-- Kelas Bersama — data DEMO untuk presentasi (ROADMAP Fase 9)
-- Impor SETELAH schema.sql dan seed.sql (butuh 4 mata kuliah contoh).
--
-- JANGAN diimpor di server publik: kata sandi akun demo tercantum di README.
--   Admin     : admin@demo.kelasbersama.test  / admin12345
--   Mahasiswa : nadia@demo.kelasbersama.test  / mahasiswa123
--               (aditya, salsabila, raka, dimas, intan — sandi sama)
--
-- Aman dijalankan ulang: data demo lama dihapus lebih dulu.
-- Deadline dihitung relatif terhadap tanggal impor agar dashboard selalu "hidup".
-- Tidak ada file unggahan di data demo (file asli tidak ikut di-commit).
-- =====================================================================

USE uas_kelas_bersama;
SET time_zone = '+08:00'; -- WITA, sama dengan aplikasi

-- ---------------------------------------------------------------------
-- 0. Hapus data demo lama. Tugas buatan admin demo dihapus dulu karena
--    tugas.dibuat_oleh tidak boleh menunjuk akun yang hilang (RESTRICT).
-- ---------------------------------------------------------------------
DELETE t FROM tugas t JOIN pengguna p ON p.id = t.dibuat_oleh WHERE p.email LIKE '%@demo.kelasbersama.test';
DELETE FROM pengguna WHERE email LIKE '%@demo.kelasbersama.test';

-- ---------------------------------------------------------------------
-- 1. Akun demo (hash bcrypt dari password_hash())
-- ---------------------------------------------------------------------
INSERT INTO pengguna (nama, email, password_hash, peran) VALUES
    ('Rizky Pratama',   'admin@demo.kelasbersama.test',     '$2y$10$GFkBldW9H55raDTG7Yj3jeyZ9LQHog21tB/ALu0LZbOGdFqP1lX9q', 'admin'),
    ('Nadia Putri',     'nadia@demo.kelasbersama.test',     '$2y$10$V.bW7hp6HnG/.oG3nTZqeOhG/gcQ20aMv2fVULvf7WSpNXl9nkVV6', 'mahasiswa'),
    ('Aditya Nugraha',  'aditya@demo.kelasbersama.test',    '$2y$10$V.bW7hp6HnG/.oG3nTZqeOhG/gcQ20aMv2fVULvf7WSpNXl9nkVV6', 'mahasiswa'),
    ('Salsabila Zahra', 'salsabila@demo.kelasbersama.test', '$2y$10$V.bW7hp6HnG/.oG3nTZqeOhG/gcQ20aMv2fVULvf7WSpNXl9nkVV6', 'mahasiswa'),
    ('Raka Pratama',    'raka@demo.kelasbersama.test',      '$2y$10$V.bW7hp6HnG/.oG3nTZqeOhG/gcQ20aMv2fVULvf7WSpNXl9nkVV6', 'mahasiswa'),
    ('Dimas Saputra',   'dimas@demo.kelasbersama.test',     '$2y$10$V.bW7hp6HnG/.oG3nTZqeOhG/gcQ20aMv2fVULvf7WSpNXl9nkVV6', 'mahasiswa'),
    ('Intan Maharani',  'intan@demo.kelasbersama.test',     '$2y$10$V.bW7hp6HnG/.oG3nTZqeOhG/gcQ20aMv2fVULvf7WSpNXl9nkVV6', 'mahasiswa');

SET @admin    = (SELECT id FROM pengguna WHERE email = 'admin@demo.kelasbersama.test');
SET @nadia    = (SELECT id FROM pengguna WHERE email = 'nadia@demo.kelasbersama.test');
SET @aditya   = (SELECT id FROM pengguna WHERE email = 'aditya@demo.kelasbersama.test');
SET @salsa    = (SELECT id FROM pengguna WHERE email = 'salsabila@demo.kelasbersama.test');
SET @raka     = (SELECT id FROM pengguna WHERE email = 'raka@demo.kelasbersama.test');
SET @dimas    = (SELECT id FROM pengguna WHERE email = 'dimas@demo.kelasbersama.test');
SET @intan    = (SELECT id FROM pengguna WHERE email = 'intan@demo.kelasbersama.test');

SET @rpl      = (SELECT id FROM mata_kuliah WHERE kode = 'IF-RPL');
SET @basdat   = (SELECT id FROM mata_kuliah WHERE kode = 'IF-BASDAT');
SET @strukdat = (SELECT id FROM mata_kuliah WHERE kode = 'IF-STRUKDAT');
SET @pweb     = (SELECT id FROM mata_kuliah WHERE kode = 'IF-PWEB');

-- ---------------------------------------------------------------------
-- 2. Pendaftaran dengan berbagai status (menunggu, disetujui, ditolak, dikeluarkan)
--    Intan hanya punya pengajuan menunggu → contoh empty state dashboard (F-25).
-- ---------------------------------------------------------------------
INSERT INTO pendaftaran (pengguna_id, mata_kuliah_id, status, diajukan, diputuskan, diputuskan_oleh) VALUES
    (@nadia,  @rpl,      'disetujui',   NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 13 DAY, @admin),
    (@nadia,  @basdat,   'disetujui',   NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 13 DAY, @admin),
    (@nadia,  @pweb,     'disetujui',   NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 12 DAY, @admin),
    (@nadia,  @strukdat, 'menunggu',    NOW() - INTERVAL 1 DAY,  NULL, NULL),
    (@aditya, @rpl,      'disetujui',   NOW() - INTERVAL 13 DAY, NOW() - INTERVAL 12 DAY, @admin),
    (@aditya, @basdat,   'disetujui',   NOW() - INTERVAL 13 DAY, NOW() - INTERVAL 12 DAY, @admin),
    (@aditya, @strukdat, 'disetujui',   NOW() - INTERVAL 13 DAY, NOW() - INTERVAL 11 DAY, @admin),
    (@salsa,  @rpl,      'disetujui',   NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 9 DAY,  @admin),
    (@salsa,  @pweb,     'menunggu',    NOW() - INTERVAL 2 DAY,  NULL, NULL),
    (@raka,   @basdat,   'disetujui',   NOW() - INTERVAL 11 DAY, NOW() - INTERVAL 10 DAY, @admin),
    (@raka,   @strukdat, 'ditolak',     NOW() - INTERVAL 6 DAY,  NOW() - INTERVAL 5 DAY,  @admin),
    (@dimas,  @pweb,     'menunggu',    NOW() - INTERVAL 3 HOUR, NULL, NULL),
    (@dimas,  @rpl,      'dikeluarkan', NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 2 DAY,  @admin),
    (@intan,  @rpl,      'menunggu',    NOW() - INTERVAL 5 HOUR, NULL, NULL);

-- ---------------------------------------------------------------------
-- 3. Sepuluh tugas (dua sudah lewat deadline, satu deadline hari ini)
--    Kolom persiapan: satu poin per baris (\n).
-- ---------------------------------------------------------------------
INSERT INTO tugas (mata_kuliah_id, judul, deskripsi, persiapan, deadline, dibuat_oleh, dibuat) VALUES
    (@rpl, 'Analisis kebutuhan sistem',
     'Susun analisis kebutuhan untuk aplikasi perpustakaan kampus. Identifikasi kebutuhan fungsional, nonfungsional, dan aktor utama.',
     'Daftar kebutuhan pengguna\nDiagram use case dan skenario\nRingkasan hasil diskusi kelompok',
     TIMESTAMP(CURDATE(), '23:59:00'), @admin, NOW() - INTERVAL 2 DAY),
    (@basdat, 'Normalisasi basis data',
     'Normalisasikan skema basis data perpustakaan hingga bentuk normal ketiga. Jelaskan dependensi fungsional pada setiap tahap.',
     'Skema awal dan dependensi fungsional\nTahapan 1NF, 2NF, dan 3NF\nRingkasan perubahan skema',
     TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '23:59:00'), @admin, NOW() - INTERVAL 2 DAY),
    (@strukdat, 'Implementasi linked list',
     'Implementasikan single linked list dengan operasi tambah, hapus, dan cari. Sertakan analisis kompleksitas setiap operasi.',
     'Kode program dengan komentar\nTangkapan layar hasil uji\nTabel kompleksitas waktu',
     TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '23:59:00'), @admin, NOW() - INTERVAL 9 DAY),
    (@rpl, 'Diagram arsitektur aplikasi',
     'Gambarkan arsitektur aplikasi perpustakaan: lapisan presentasi, logika bisnis, dan data, beserta alur permintaan utama.',
     'Diagram komponen\nPenjelasan tiap lapisan',
     TIMESTAMP(CURDATE() + INTERVAL 3 DAY, '23:59:00'), @admin, NOW() - INTERVAL 1 DAY),
    (@basdat, 'Query JOIN dan agregasi',
     'Tulis sepuluh query SQL yang memakai JOIN, GROUP BY, dan HAVING untuk basis data perpustakaan.',
     'File .sql berisi sepuluh query\nHasil eksekusi setiap query',
     TIMESTAMP(CURDATE() + INTERVAL 5 DAY, '23:59:00'), @admin, NOW() - INTERVAL 1 DAY),
    (@pweb, 'Form login dengan session',
     'Buat halaman login PHP dengan password_hash, session_regenerate_id, dan token CSRF seperti jobsheet P09.',
     'Kode login.php dan logout.php\nTangkapan layar uji login gagal dan berhasil',
     TIMESTAMP(CURDATE() + INTERVAL 4 DAY, '21:00:00'), @admin, NOW() - INTERVAL 3 DAY),
    (@pweb, 'Upload foto profil aman',
     'Tambahkan fitur upload foto profil dengan validasi finfo, batas ukuran, dan nama file acak seperti jobsheet P10.',
     'Kode upload\n.htaccess folder uploads\nHasil uji file palsu',
     TIMESTAMP(CURDATE() + INTERVAL 9 DAY, '23:59:00'), @admin, NOW()),
    (@rpl, 'Rencana pengujian (test plan)',
     'Susun rencana pengujian black-box untuk fitur peminjaman buku, lengkap dengan skenario positif dan negatif.',
     'Tabel skenario uji\nData uji\nHasil yang diharapkan',
     TIMESTAMP(CURDATE() + INTERVAL 12 DAY, '23:59:00'), @admin, NOW()),
    (@strukdat, 'Stack dan queue',
     'Implementasikan stack dan queue berbasis array, lalu bandingkan dengan versi linked list.',
     'Kode program\nTabel perbandingan',
     TIMESTAMP(CURDATE() + INTERVAL 6 DAY, '23:59:00'), @admin, NOW() - INTERVAL 1 DAY),
    (@basdat, 'ERD sistem akademik',
     'Rancang ERD sistem akademik sederhana: mahasiswa, mata kuliah, dosen, dan KRS.',
     'Gambar ERD\nPenjelasan relasi dan kardinalitas',
     TIMESTAMP(CURDATE() - INTERVAL 4 DAY, '23:59:00'), @admin, NOW() - INTERVAL 12 DAY);

SET @t_analisis    = (SELECT id FROM tugas WHERE dibuat_oleh = @admin AND judul = 'Analisis kebutuhan sistem');
SET @t_normalisasi = (SELECT id FROM tugas WHERE dibuat_oleh = @admin AND judul = 'Normalisasi basis data');
SET @t_linkedlist  = (SELECT id FROM tugas WHERE dibuat_oleh = @admin AND judul = 'Implementasi linked list');
SET @t_arsitektur  = (SELECT id FROM tugas WHERE dibuat_oleh = @admin AND judul = 'Diagram arsitektur aplikasi');
SET @t_query       = (SELECT id FROM tugas WHERE dibuat_oleh = @admin AND judul = 'Query JOIN dan agregasi');
SET @t_login       = (SELECT id FROM tugas WHERE dibuat_oleh = @admin AND judul = 'Form login dengan session');
SET @t_erd         = (SELECT id FROM tugas WHERE dibuat_oleh = @admin AND judul = 'ERD sistem akademik');

-- ---------------------------------------------------------------------
-- 4. Progres pribadi (tanpa baris = belum mulai)
--    Progres Dimas tetap tersimpan walau ia dikeluarkan dari RPL (F-24).
-- ---------------------------------------------------------------------
INSERT INTO tugas_mahasiswa (tugas_id, pengguna_id, status) VALUES
    (@t_analisis,    @nadia,  'dikerjakan'),
    (@t_normalisasi, @nadia,  'selesai'),
    (@t_arsitektur,  @nadia,  'selesai'),
    (@t_login,       @nadia,  'dikerjakan'),
    (@t_erd,         @nadia,  'selesai'),
    (@t_analisis,    @aditya, 'selesai'),
    (@t_normalisasi, @aditya, 'dikerjakan'),
    (@t_linkedlist,  @aditya, 'selesai'),
    (@t_analisis,    @salsa,  'selesai'),
    (@t_arsitektur,  @salsa,  'dikerjakan'),
    (@t_normalisasi, @raka,   'selesai'),
    (@t_query,       @raka,   'dikerjakan'),
    (@t_erd,         @raka,   'selesai'),
    (@t_analisis,    @dimas,  'selesai');
