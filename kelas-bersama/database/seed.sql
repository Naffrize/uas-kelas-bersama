-- =====================================================================
-- Kelas Bersama — data contoh (seed)
-- Impor SETELAH database/schema.sql.
--
-- Berisi 4 mata kuliah contoh TANPA akun (ROADMAP Fase 0):
--   - akun admin dibuat lewat: php tools/buat-admin.php
--   - akun mahasiswa dibuat lewat halaman registrasi
-- dibuat_oleh = NULL menandakan data berasal dari seed, bukan dari admin.
--
-- Aman dijalankan ulang: kode yang sudah ada tidak digandakan, hanya
-- nama & deskripsinya diperbarui (kapasitas buatan admin tidak diubah).
-- =====================================================================

USE uas_kelas_bersama;

INSERT INTO mata_kuliah (kode, nama, deskripsi, kapasitas, warna, dibuat_oleh) VALUES
    ('IF-RPL',      'Rekayasa Perangkat Lunak',
     'Analisis kebutuhan, perancangan, implementasi, dan pengujian perangkat lunak secara terstruktur.',
     40, 'hijau', NULL),
    ('IF-BASDAT',   'Basis Data',
     'Perancangan ERD, normalisasi, dan query SQL untuk mengelola data aplikasi.',
     40, 'biru', NULL),
    ('IF-STRUKDAT', 'Struktur Data',
     'Array, linked list, stack, queue, tree, dan graph beserta penerapannya.',
     40, 'terakota', NULL),
    ('IF-PWEB',     'Pemrograman Web',
     'Membangun aplikasi web dengan HTML, CSS, PHP, dan MySQL yang aman.',
     40, 'emas', NULL)
ON DUPLICATE KEY UPDATE
    nama      = VALUES(nama),
    deskripsi = VALUES(deskripsi);
