-- =====================================================================
-- Kelas Bersama — skema database
-- MySQL / MariaDB (XAMPP) · engine InnoDB · charset utf8mb4
--
-- Cara impor : phpMyAdmin → tab Import → pilih file ini → Import.
-- Urutan     : 1) schema.sql  2) seed.sql  3) php tools/buat-admin.php
--
-- AMAN DIIMPOR ULANG: tabel lama dihapus dulu (urutan anak → induk),
-- lalu dibuat kembali. PERHATIAN: impor ulang MENGHAPUS SEMUA DATA,
-- termasuk akun admin — jalankan lagi tools/buat-admin.php sesudahnya.
--
-- Acuan: docs/PRD.md bagian 8 dan ERD di docs/FLOWCHART.md bagian 11.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS uas_kelas_bersama
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE uas_kelas_bersama;

-- ---------------------------------------------------------------------
-- 0. Hapus tabel lama.
--    Tabel ANAK (pemilik foreign key) dihapus lebih dulu, baru tabel
--    INDUK, supaya MySQL tidak menolak karena masih ada baris yang merujuk.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS token_ingat;
DROP TABLE IF EXISTS tugas_mahasiswa;
DROP TABLE IF EXISTS tugas;
DROP TABLE IF EXISTS pendaftaran;
DROP TABLE IF EXISTS mata_kuliah;
DROP TABLE IF EXISTS pengguna;

-- ---------------------------------------------------------------------
-- 1. pengguna — akun admin & mahasiswa
--    R-01: satu email = satu akun. Collation *_ci membuat
--    Nadia@kampus.ac.id dan nadia@kampus.ac.id dianggap email yang sama.
--    Peran tidak dapat diubah dari aplikasi; admin dibuat lewat CLI.
-- ---------------------------------------------------------------------
CREATE TABLE pengguna (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama          VARCHAR(100) NOT NULL,
    email         VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,                       -- hasil password_hash(), bukan password asli
    peran         ENUM('admin', 'mahasiswa') NOT NULL DEFAULT 'mahasiswa',
    foto          VARCHAR(64)  NULL,                           -- nama acak di public/uploads/foto; NULL = avatar inisial
    dibuat        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pengguna_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Akun admin dan mahasiswa';

-- ---------------------------------------------------------------------
-- 2. mata_kuliah — "kategori" tugas, dikelola admin (F-10..F-13)
--    dibuat_oleh boleh NULL: mata kuliah contoh di seed.sql dibuat tanpa
--    akun (ROADMAP Fase 0), dan bila akun pembuat terhapus datanya tetap ada.
--    Kapasitas 1–500 divalidasi di PHP; CHECK di bawah adalah lapisan kedua.
--    warna: dipilih admin; nilai warnanya diatur di public/assets/css/tema.css.
-- ---------------------------------------------------------------------
CREATE TABLE mata_kuliah (
    id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    kode        VARCHAR(20)       NOT NULL,                    -- huruf besar/angka/strip, unik (mis. IF-PWEB)
    nama        VARCHAR(100)      NOT NULL,
    deskripsi   TEXT              NULL,
    kapasitas   SMALLINT UNSIGNED NOT NULL DEFAULT 40,         -- batas anggota berstatus 'disetujui' (R-04)
    warna       ENUM('emas','navy','hijau','toska','biru','plum','terakota','abu')
                                  NOT NULL DEFAULT 'emas',     -- warna kartu & penanda mata kuliah
    dibuat_oleh INT UNSIGNED      NULL,                        -- admin pembuat; NULL = data contoh dari seed
    dibuat      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mata_kuliah_kode (kode),
    KEY idx_mata_kuliah_pembuat (dibuat_oleh),
    CONSTRAINT ck_mata_kuliah_kapasitas CHECK (kapasitas BETWEEN 1 AND 500),
    CONSTRAINT fk_mata_kuliah_pembuat FOREIGN KEY (dibuat_oleh)
        REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mata kuliah yang dapat diikuti mahasiswa';

-- ---------------------------------------------------------------------
-- 3. pendaftaran — pengajuan gabung mahasiswa ke mata kuliah (F-20..F-25)
--    R-02: pasangan (mahasiswa, mata kuliah) unik; saat ajukan ulang,
--          status baris yang sama diubah, bukan menambah baris baru.
--    R-03: menunggu → disetujui / ditolak / (dibatalkan = baris dihapus)
--          disetujui → dikeluarkan; ditolak / dikeluarkan → menunggu.
-- ---------------------------------------------------------------------
CREATE TABLE pendaftaran (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pengguna_id     INT UNSIGNED NOT NULL,                     -- mahasiswa yang mengajukan
    mata_kuliah_id  INT UNSIGNED NOT NULL,
    status          ENUM('menunggu', 'disetujui', 'ditolak', 'dikeluarkan') NOT NULL DEFAULT 'menunggu',
    diajukan        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diputuskan      DATETIME     NULL,                         -- waktu admin setujui / tolak / keluarkan
    diputuskan_oleh INT UNSIGNED NULL,                         -- admin yang memutuskan
    PRIMARY KEY (id),
    UNIQUE KEY uq_pendaftaran (pengguna_id, mata_kuliah_id),  -- juga dipakai JOIN "tugas yang boleh dilihat"
    KEY idx_pendaftaran_status (status),                       -- hitung pengajuan 'menunggu' (badge menu Anggota)
    KEY idx_pendaftaran_mk_status (mata_kuliah_id, status),    -- hitung anggota 'disetujui' per mata kuliah (kuota)
    KEY idx_pendaftaran_pemutus (diputuskan_oleh),
    CONSTRAINT fk_pendaftaran_pengguna FOREIGN KEY (pengguna_id)
        REFERENCES pengguna (id) ON DELETE CASCADE,
    CONSTRAINT fk_pendaftaran_mata_kuliah FOREIGN KEY (mata_kuliah_id)
        REFERENCES mata_kuliah (id) ON DELETE CASCADE,
    CONSTRAINT fk_pendaftaran_pemutus FOREIGN KEY (diputuskan_oleh)
        REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pengajuan gabung mata kuliah dan keputusan admin';

-- ---------------------------------------------------------------------
-- 4. tugas — to-do list per mata kuliah, hanya admin yang mengelola (F-30..F-36)
--    File lampiran disimpan di storage/uploads/lampiran dengan nama acak;
--    nama asli hanya disimpan di kolom lampiran_nama (aturan keamanan no. 8).
-- ---------------------------------------------------------------------
CREATE TABLE tugas (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mata_kuliah_id  INT UNSIGNED NOT NULL,
    judul           VARCHAR(150) NOT NULL,
    deskripsi       TEXT         NOT NULL,
    persiapan       TEXT         NULL,                         -- "yang perlu disiapkan", satu poin per baris
    deadline        DATETIME     NOT NULL,                     -- disimpan dalam WITA
    lampiran_file   VARCHAR(64)  NULL,                         -- nama acak: 32 heksadesimal + ekstensi
    lampiran_nama   VARCHAR(255) NULL,                         -- nama asli untuk ditampilkan & diunduh
    lampiran_mime   VARCHAR(100) NULL,                         -- hasil finfo, dipakai header Content-Type
    lampiran_ukuran INT UNSIGNED NULL,                         -- byte
    dibuat_oleh     INT UNSIGNED NOT NULL,                     -- admin pembuat ("Ditambahkan oleh ...")
    dibuat          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diubah          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tugas_mk_deadline (mata_kuliah_id, deadline),      -- daftar tugas per mata kuliah, urut deadline
    KEY idx_tugas_deadline (deadline),                         -- "Jangan terlewat" & filter minggu ini/terlewat
    KEY idx_tugas_pembuat (dibuat_oleh),
    CONSTRAINT fk_tugas_mata_kuliah FOREIGN KEY (mata_kuliah_id)
        REFERENCES mata_kuliah (id) ON DELETE CASCADE,
    CONSTRAINT fk_tugas_pembuat FOREIGN KEY (dibuat_oleh)
        REFERENCES pengguna (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tugas per mata kuliah';

-- ---------------------------------------------------------------------
-- 5. tugas_mahasiswa — progres + hasil kerja PRIBADI (F-40..F-46)
--    Satu baris per (tugas, mahasiswa). Belum ada baris = 'belum_mulai'.
--    Simpan progres: INSERT ... ON DUPLICATE KEY UPDATE status = VALUES(status)
--    R-07: satu hasil kerja per mahasiswa per tugas (unggahan baru menggantikan).
-- ---------------------------------------------------------------------
CREATE TABLE tugas_mahasiswa (
    tugas_id     INT UNSIGNED NOT NULL,
    pengguna_id  INT UNSIGNED NOT NULL,                        -- mahasiswa pemilik progres
    status       ENUM('belum_mulai', 'dikerjakan', 'selesai') NOT NULL DEFAULT 'belum_mulai',
    hasil_file   VARCHAR(64)  NULL,                            -- nama acak di storage/uploads/hasil
    hasil_nama   VARCHAR(255) NULL,                            -- nama asli file hasil kerja
    hasil_mime   VARCHAR(100) NULL,
    hasil_ukuran INT UNSIGNED NULL,                            -- byte
    diunggah     DATETIME     NULL,                            -- > deadline berarti "Terlambat" (R-08)
    diubah       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tugas_id, pengguna_id),
    KEY idx_tugas_mahasiswa_pengguna (pengguna_id),            -- statistik progres per mahasiswa
    CONSTRAINT fk_tugas_mahasiswa_tugas FOREIGN KEY (tugas_id)
        REFERENCES tugas (id) ON DELETE CASCADE,
    CONSTRAINT fk_tugas_mahasiswa_pengguna FOREIGN KEY (pengguna_id)
        REFERENCES pengguna (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Progres dan hasil kerja pribadi mahasiswa per tugas';

-- ---------------------------------------------------------------------
-- 6. token_ingat — "Ingat saya selama 30 hari" (PRD F-07)
--    Cookie berisi "pemilih:validator". Database hanya menyimpan HASH
--    SHA-256 dari validator, sehingga isi tabel yang bocor tidak bisa
--    dipakai untuk masuk. Token dipakai sekali lalu diganti (rotasi).
-- ---------------------------------------------------------------------
CREATE TABLE token_ingat (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pengguna_id    INT UNSIGNED NOT NULL,
    pemilih        CHAR(24)     NOT NULL,                      -- bagian publik cookie, untuk mencari baris
    validator_hash CHAR(64)     NOT NULL,                      -- hash('sha256', validator rahasia di cookie)
    kedaluwarsa    DATETIME     NOT NULL,
    dibuat         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token_ingat_pemilih (pemilih),
    KEY idx_token_ingat_pengguna (pengguna_id),
    CONSTRAINT fk_token_ingat_pengguna FOREIGN KEY (pengguna_id)
        REFERENCES pengguna (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Token login "Ingat saya" (hanya hash yang disimpan)';
