-- =====================================================================
-- Kelas Bersama — menambah kolom warna mata kuliah ke database yang SUDAH ADA
-- Impor sekali lewat phpMyAdmin (tab Import). Data lama tidak dihapus.
-- Tidak perlu bila database dibuat ulang dari schema.sql terbaru.
-- Aman dijalankan ulang.
-- =====================================================================

USE uas_kelas_bersama;

ALTER TABLE mata_kuliah
    ADD COLUMN IF NOT EXISTS warna ENUM('emas','navy','hijau','toska','biru','plum','terakota','abu')
        NOT NULL DEFAULT 'emas' AFTER kapasitas;

-- Warna awal mata kuliah contoh (hanya yang masih memakai warna bawaan).
UPDATE mata_kuliah SET warna = 'hijau'    WHERE kode = 'IF-RPL'      AND warna = 'emas';
UPDATE mata_kuliah SET warna = 'biru'     WHERE kode = 'IF-BASDAT'   AND warna = 'emas';
UPDATE mata_kuliah SET warna = 'terakota' WHERE kode = 'IF-STRUKDAT' AND warna = 'emas';
