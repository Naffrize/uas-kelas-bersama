# PRD — Kelas Bersama (K/B)

| | |
|---|---|
| Produk | Kelas Bersama — ruang kolaborasi akademik berbasis web |
| Konteks | Proyek akhir pengganti UAS, Pemrograman Web, Teknik Informatika UMKT |
| Disusun oleh | Nafarie Hanif (2511102441049) untuk kelompok |
| Versi | 1.0 — 20 November 2026 |
| Stack | PHP 8 native + PDO, MySQL/MariaDB (XAMPP), Tailwind CSS v4, JS vanilla |
| Desain | Figma "Kelas Bersama" → `docs/design/` |

---

## 1. Latar belakang

Mahasiswa umumnya menerima informasi tugas dari banyak kanal (grup chat, pengumuman lisan,
LMS), sehingga tugas mudah terlewat dan progres pribadi sulit dipantau. Kelas Bersama
memusatkan to-do list tugas per mata kuliah dalam satu tempat: admin (pengelola kelas)
menyusun daftar tugas, sedangkan mahasiswa yang sudah disetujui dapat membaca tugas,
mencentang progresnya, dan mengunggah hasil kerja.

## 2. Tujuan

| Kode | Tujuan | Ukuran keberhasilan |
|---|---|---|
| T1 | Admin dapat mengelola mata kuliah dan tugas secara penuh (CRUD) | Semua operasi tambah/lihat/ubah/hapus berjalan tanpa error |
| T2 | Akses mata kuliah terkendali | Mahasiswa hanya melihat tugas dari mata kuliah berstatus *disetujui* |
| T3 | Mahasiswa memantau progres pribadi | Status per tugas tersimpan per mahasiswa; dashboard menampilkan jumlah per status |
| T4 | Hasil kerja terkumpul di satu tempat | Mahasiswa dapat mengunggah file/foto; admin dapat mengunduhnya |
| T5 | Aman sesuai materi praktikum | Lulus semua uji negatif di bagian 11 (CSRF, IDOR, upload palsu, akses tanpa login) |

## 3. Ruang lingkup

**Termasuk (In scope)**
- Registrasi mahasiswa, login, logout, profil + foto profil.
- Admin: CRUD mata kuliah (kategori), persetujuan pendaftaran, keluarkan anggota, CRUD tugas + lampiran referensi, melihat & mengunduh hasil kerja.
- Mahasiswa: memilih mata kuliah & mengajukan gabung, melihat to-do list, mencentang progres (Belum mulai / Sedang dikerjakan / Selesai), mengunggah hasil kerja.
- Dashboard dengan statistik, pencarian, dan filter tugas.
- Tampilan responsif desktop (1440px) dan mobile (390px) sesuai Figma.

**Tidak termasuk (Out of scope)**
- Rolling kelompok & riwayat pembagian (ada di Figma, dikeluarkan dari lingkup).
- Kode undangan kelas (diganti alur pengajuan + persetujuan).
- Mahasiswa menambah/mengedit tugas.
- Penilaian/nilai, chat, absensi, notifikasi email, reset password via email.

## 4. Peran pengguna

| Peran | Cara mendapat akun | Hak akses |
|---|---|---|
| **Admin** (pengelola/"sipen") | Dibuat lewat `tools/buat-admin.php` atau seed; tidak bisa daftar sendiri | CRUD mata kuliah, CRUD tugas, setujui/tolak pendaftaran, keluarkan anggota, lihat & unduh semua hasil kerja |
| **Mahasiswa** | Registrasi mandiri | Ajukan gabung mata kuliah, lihat tugas dari mata kuliah *disetujui*, ubah progres pribadi, unggah/ganti/hapus hasil kerja milik sendiri |
| **Tamu** | — | Hanya halaman login & registrasi |

## 5. User stories

**Mahasiswa**
- MHS-01 Sebagai mahasiswa, saya bisa mendaftar dengan nama, email, dan kata sandi agar punya akun.
- MHS-02 Saya bisa melihat daftar mata kuliah yang tersedia beserta status saya (belum gabung / menunggu / disetujui / ditolak).
- MHS-03 Saya bisa mengajukan gabung ke mata kuliah, dan membatalkan pengajuan yang masih menunggu.
- MHS-04 Saya bisa mengajukan ulang jika pengajuan sebelumnya ditolak.
- MHS-05 Saya hanya melihat to-do list dari mata kuliah yang sudah disetujui.
- MHS-06 Saya bisa mencentang tugas (selesai/belum) langsung dari daftar, dan memilih 3 status di halaman detail.
- MHS-07 Saya bisa mengunggah hasil kerja (PDF/DOC/DOCX/JPG/PNG/WEBP, maks. 5 MB), melihat pratinjau sebelum menyimpan, mengganti, atau menghapusnya.
- MHS-08 Saya bisa mengunduh lampiran referensi dari admin.
- MHS-09 Saya bisa mencari tugas dan memfilter berdasarkan mata kuliah, status, dan deadline.
- MHS-10 Saya bisa melihat ringkasan progres dan tugas dengan deadline terdekat di dashboard.

**Admin**
- ADM-01 Saya bisa menambah, mengedit, dan menghapus mata kuliah (kode, nama, deskripsi, kapasitas).
- ADM-02 Saya bisa melihat pengajuan yang menunggu dan menyetujui/menolaknya.
- ADM-03 Saya bisa mengeluarkan anggota dari mata kuliah (dengan konfirmasi).
- ADM-04 Saya bisa menambah, mengedit, dan menghapus tugas, termasuk lampiran referensi.
- ADM-05 Saya bisa melihat siapa saja yang sudah selesai/mengunggah per tugas dan mengunduh file mereka.
- ADM-06 Saya bisa melihat statistik: total tugas, mahasiswa aktif, jumlah mata kuliah, pengajuan menunggu.

## 6. Kebutuhan fungsional

Prioritas: **W** = wajib (Must), **S** = sebaiknya (Should), **O** = opsional (Could).

### 6.1 Autentikasi & akun
| ID | Kebutuhan | Prio |
|---|---|---|
| F-01 | Registrasi mahasiswa: nama lengkap (wajib, ≤100), email (format valid, unik, ≤120), kata sandi 8–72 karakter, konfirmasi harus sama. Peran otomatis `mahasiswa`. Setelah sukses → login otomatis → halaman "Pilih mata kuliah". | W |
| F-02 | Login dengan email + kata sandi. Satu tombol "Masuk"; sistem mengarahkan ke dashboard sesuai peran (Figma punya 2 tombol, digabung). | W |
| F-03 | Pesan login gagal generik. `session_regenerate_id(true)` setelah sukses. | W |
| F-04 | Logout via POST + CSRF, menampilkan modal konfirmasi "Keluar dari akun?". | W |
| F-05 | Profil: ubah nama; email & peran hanya-baca. Ganti kata sandi (lama + baru + konfirmasi). | S |
| F-06 | Foto profil JPG/PNG/WEBP maks. 5 MB dengan modal pratinjau; avatar inisial jika kosong. | S |
| F-07 | "Ingat saya selama 30 hari" (token acak di cookie, hash di DB). | O |

### 6.2 Mata kuliah (admin)
| ID | Kebutuhan | Prio |
|---|---|---|
| F-10 | Daftar mata kuliah berbentuk kartu: kode, nama, jumlah anggota disetujui / kapasitas, jumlah pengajuan menunggu, tombol Lihat anggota / Edit / Hapus. | W |
| F-11 | Tambah mata kuliah (modal "Buat mata kuliah"): kode (unik, huruf besar/angka/strip, ≤20), nama (≤100), deskripsi (opsional), kapasitas bilangan bulat 1–500 (default 40). | W |
| F-12 | Edit mata kuliah; kapasitas tidak boleh lebih kecil dari jumlah anggota disetujui. | W |
| F-13 | Hapus mata kuliah dengan modal konfirmasi; ikut menghapus tugas, pendaftaran, progres, dan file terkait (file dihapus setelah transaksi DB sukses). | W |

### 6.3 Pendaftaran mata kuliah & persetujuan
| ID | Kebutuhan | Prio |
|---|---|---|
| F-20 | Halaman "Pilih mata kuliah" (mahasiswa): kartu tiap mata kuliah dengan status & sisa kuota; tombol sesuai status: *Ajukan gabung* / *Batalkan pengajuan* / *Ajukan ulang* / label *Disetujui*. | W |
| F-21 | Pengajuan ditolak sistem jika kuota penuh atau sudah ada pengajuan aktif (menunggu/disetujui). | W |
| F-22 | Halaman "Anggota" (admin) dengan tab **Menunggu** dan **Disetujui**, filter per mata kuliah, pencarian nama/email. | W |
| F-23 | Admin *Setujui* (gagal jika kuota penuh) / *Tolak* pengajuan. Catat waktu & admin yang memutuskan. | W |
| F-24 | Admin *Keluarkan* anggota disetujui (modal konfirmasi). Status menjadi `dikeluarkan`; data progres & hasil kerja tetap tersimpan tetapi tidak dapat diakses mahasiswa. Mahasiswa dapat mengajukan ulang. | W |
| F-25 | Mahasiswa tanpa mata kuliah disetujui melihat *empty state* di dashboard: "Belum ada mata kuliah aktif" + tombol ke "Pilih mata kuliah". | W |

### 6.4 Tugas / to-do list
| ID | Kebutuhan | Prio |
|---|---|---|
| F-30 | Admin tambah tugas: judul (≤150), mata kuliah (select), deskripsi, "yang perlu disiapkan" (satu poin per baris, opsional), tanggal + waktu deadline, lampiran referensi opsional (1 file). | W |
| F-31 | Admin edit tugas; lampiran dapat diganti atau dihapus. | W |
| F-32 | Admin hapus tugas (modal "Hapus tugas ini?"); ikut menghapus progres, hasil kerja, dan file. | W |
| F-33 | Daftar tugas: tabel (desktop) / kartu (mobile): judul, mata kuliah, deadline, status. Admin: kolom pengumpulan "x/y selesai" + aksi Edit/Hapus. Mahasiswa: checkbox ceklis + badge status. | W |
| F-34 | Pencarian judul/mata kuliah; filter mata kuliah, status (mahasiswa), deadline (semua / minggu ini / terlewat); paginasi 10 per halaman. Parameter lewat GET. | S |
| F-35 | Detail tugas: badge mata kuliah, judul, "Ditambahkan oleh … · tanggal", deadline, deskripsi, daftar persiapan bernomor, lampiran bersama (unduh). | W |
| F-36 | Deadline terlewat ditandai visual (badge "Terlewat") bila status belum selesai. | S |

### 6.5 Progres & hasil kerja (mahasiswa)
| ID | Kebutuhan | Prio |
|---|---|---|
| F-40 | Status progres per mahasiswa per tugas: `belum_mulai` (default bila belum ada baris), `dikerjakan`, `selesai`. Ditampilkan sebagai badge + bar (0% / 60% / 100%, sesuai Figma). | W |
| F-41 | Ceklis di daftar tugas: centang → `selesai`; hapus centang → `belum_mulai`. Kirim via form POST kecil (bisa auto-submit dengan JS). | W |
| F-42 | Kartu "Progres saya" di detail: tombol pindah ke dua status lainnya (pola Figma: "Tandai selesai", "Sedang dikerjakan", "Belum mulai"). | W |
| F-43 | Kartu "Hasil kerja saya": unggah 1 file (PDF, DOC, DOCX, JPG, PNG, WEBP; maks. 5 MB) → modal pratinjau (nama, ukuran, jenis; gambar ditampilkan) → *Gunakan file* / *Pilih file lain*. Error → modal "File belum dapat diunggah". | W |
| F-44 | Setelah ada hasil: tampil nama file, ukuran, waktu unggah, tombol Unduh / Ganti / Hapus. Mengganti file menghapus file lama setelah UPDATE sukses. | W |
| F-45 | Mengunggah hasil saat status `belum_mulai` otomatis menaikkan status ke `dikerjakan` (tidak otomatis `selesai`). | S |
| F-46 | Hanya pemilik dan admin yang dapat mengunduh hasil kerja (`unduh.php` memeriksa hak). | W |

### 6.6 Pantauan admin
| ID | Kebutuhan | Prio |
|---|---|---|
| F-50 | Detail tugas admin menampilkan tabel "Pengumpulan": nama mahasiswa (anggota disetujui), status progres, file hasil (Unduh) atau "—", waktu unggah. Ringkasan "x dari y selesai · z mengunggah". | W |
| F-51 | Dashboard admin: kartu statistik (Total tugas, Mahasiswa aktif, Mata kuliah, Pengajuan menunggu), banner navy "N pengajuan menunggu persetujuan" + tombol *Tinjau pengajuan*, daftar tugas, kartu "Jangan terlewat". | W |

### 6.7 Dashboard mahasiswa
| ID | Kebutuhan | Prio |
|---|---|---|
| F-60 | Salam sesuai jam ("Selamat pagi/siang/sore/malam, {nama depan}."). | S |
| F-61 | Kartu statistik: Total tugas, Sedang dikerjakan, Selesai, Mata kuliah diikuti. | W |
| F-62 | Banner "Progres pribadi": "X dari Y tugas sudah selesai." + rincian + progress bar. | W |
| F-63 | Kartu "Jangan terlewat": tugas belum selesai dengan deadline terdekat + tombol *Lihat detail tugas*. | W |

## 7. Aturan bisnis

| Kode | Aturan |
|---|---|
| R-01 | Satu email = satu akun. Peran tidak dapat diubah dari aplikasi. |
| R-02 | Pasangan (mahasiswa, mata kuliah) unik di tabel `pendaftaran`; status berubah, baris tidak diduplikasi. |
| R-03 | Transisi status pendaftaran: `menunggu → disetujui / ditolak / dibatalkan(dihapus)`, `disetujui → dikeluarkan`, `ditolak / dikeluarkan → menunggu` (ajukan ulang). |
| R-04 | Jumlah `disetujui` ≤ kapasitas. Dicek saat mahasiswa mengajukan **dan** saat admin menyetujui. |
| R-05 | Mahasiswa hanya dapat membaca tugas dan mengubah progres/hasil pada mata kuliah berstatus `disetujui`. Semua cek dilakukan di server berdasarkan `$_SESSION`, bukan dari input form. |
| R-06 | Progres bersifat pribadi: perubahan satu mahasiswa tidak memengaruhi yang lain. |
| R-07 | Satu hasil kerja per mahasiswa per tugas (unggahan baru menggantikan yang lama). |
| R-08 | Unggah hasil tetap diizinkan setelah deadline, tetapi ditandai "Terlambat" (waktu unggah > deadline). |
| R-09 | Zona waktu aplikasi `Asia/Makassar` (WITA). Teks "WIB" di Figma diganti "WITA". |
| R-10 | Hapus data induk (mata kuliah/tugas) memakai konfirmasi modal dan transaksi DB; file fisik dihapus setelah `COMMIT`. |

## 8. Desain data

### 8.1 Tabel

```sql
-- database/schema.sql (ringkas; Claude Code melengkapi index & komentar)
CREATE TABLE pengguna (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama          VARCHAR(100) NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  peran         ENUM('admin','mahasiswa') NOT NULL DEFAULT 'mahasiswa',
  foto          VARCHAR(64)  NULL,               -- nama acak, file di public/uploads/foto
  dibuat        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE mata_kuliah (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode        VARCHAR(20)  NOT NULL UNIQUE,
  nama        VARCHAR(100) NOT NULL,
  deskripsi   TEXT NULL,
  kapasitas   SMALLINT UNSIGNED NOT NULL DEFAULT 40,   -- 1..500 (validasi di PHP)
  dibuat_oleh INT UNSIGNED NOT NULL,
  dibuat      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id)
) ENGINE=InnoDB;

CREATE TABLE pendaftaran (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengguna_id     INT UNSIGNED NOT NULL,
  mata_kuliah_id  INT UNSIGNED NOT NULL,
  status          ENUM('menunggu','disetujui','ditolak','dikeluarkan') NOT NULL DEFAULT 'menunggu',
  diajukan        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diputuskan      DATETIME NULL,
  diputuskan_oleh INT UNSIGNED NULL,
  UNIQUE KEY uq_daftar (pengguna_id, mata_kuliah_id),
  FOREIGN KEY (pengguna_id)     REFERENCES pengguna(id)    ON DELETE CASCADE,
  FOREIGN KEY (mata_kuliah_id)  REFERENCES mata_kuliah(id) ON DELETE CASCADE,
  FOREIGN KEY (diputuskan_oleh) REFERENCES pengguna(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tugas (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mata_kuliah_id   INT UNSIGNED NOT NULL,
  judul            VARCHAR(150) NOT NULL,
  deskripsi        TEXT NOT NULL,
  persiapan        TEXT NULL,                 -- satu poin per baris
  deadline         DATETIME NOT NULL,
  lampiran_file    VARCHAR(64)  NULL,         -- nama acak di storage/uploads/lampiran
  lampiran_nama    VARCHAR(255) NULL,         -- nama asli untuk ditampilkan
  lampiran_mime    VARCHAR(100) NULL,
  lampiran_ukuran  INT UNSIGNED NULL,
  dibuat_oleh      INT UNSIGNED NOT NULL,
  dibuat           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diubah           DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (mata_kuliah_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE,
  FOREIGN KEY (dibuat_oleh)    REFERENCES pengguna(id)
) ENGINE=InnoDB;

-- progres + hasil kerja disatukan: satu baris per (tugas, mahasiswa)
CREATE TABLE tugas_mahasiswa (
  tugas_id     INT UNSIGNED NOT NULL,
  pengguna_id  INT UNSIGNED NOT NULL,
  status       ENUM('belum_mulai','dikerjakan','selesai') NOT NULL DEFAULT 'belum_mulai',
  hasil_file   VARCHAR(64)  NULL,             -- nama acak di storage/uploads/hasil
  hasil_nama   VARCHAR(255) NULL,
  hasil_mime   VARCHAR(100) NULL,
  hasil_ukuran INT UNSIGNED NULL,
  diunggah     DATETIME NULL,
  diubah       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tugas_id, pengguna_id),
  FOREIGN KEY (tugas_id)    REFERENCES tugas(id)    ON DELETE CASCADE,
  FOREIGN KEY (pengguna_id) REFERENCES pengguna(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

Catatan: simpan progres memakai `INSERT ... ON DUPLICATE KEY UPDATE status = VALUES(status)`
sehingga baris dibuat saat pertama kali mahasiswa mengubah status.

### 8.2 Query kunci (acuan)
- Tugas yang boleh dilihat mahasiswa:
  `tugas t JOIN pendaftaran p ON p.mata_kuliah_id = t.mata_kuliah_id AND p.pengguna_id = :uid AND p.status = 'disetujui' LEFT JOIN tugas_mahasiswa tm ON tm.tugas_id = t.id AND tm.pengguna_id = :uid` → status = `COALESCE(tm.status, 'belum_mulai')`.
- Ringkasan pengumpulan admin per tugas: anggota `disetujui` LEFT JOIN `tugas_mahasiswa`.

## 9. Peta halaman & pemetaan desain

| Halaman (file) | Peran | Acuan desain `docs/design/desktop/` | Penyesuaian |
|---|---|---|---|
| `public/login.php` | tamu | `01-login.png` | 1 tombol "Masuk"; checkbox "Ingat saya" (O) |
| `public/register.php` | tamu | `02-register.png` | Tombol "Daftar & pilih mata kuliah"; panel kiri tampilkan jumlah mata kuliah |
| `public/mahasiswa/matkul.php` | mhs | `03-gabung-ref-pilih-matkul.png` | Input kode → grid kartu mata kuliah + status + tombol ajukan |
| `public/mahasiswa/dashboard.php` | mhs | `10-dashboard-mahasiswa.png` | Hapus tombol "Tambah tugas"; topbar: dropdown filter mata kuliah |
| `public/mahasiswa/tugas.php` | mhs | `18-ref-daftar-tugas-tabel-*.png`, `11`, `12` | Gaya warna mengikuti desain final; kolom aksi → checkbox |
| `public/mahasiswa/tugas-detail.php` | mhs | `13`–`16` | Hapus "Edit tugas"; tambah kartu "Hasil kerja saya" |
| `public/admin/dashboard.php` | admin | `20-dashboard-admin.png` | Kartu "Kelas dikelola" → "Pengajuan menunggu"; tombol banner → "Tinjau pengajuan" |
| `public/admin/tugas.php` | admin | `29-ref-daftar-tugas-tabel-admin*.png`, `21`, `22` | Kolom "Dibuat oleh" → "Pengumpulan x/y" |
| `public/admin/tugas-form.php` | admin | `24-tambah-tugas-admin.png`, `25-edit-tugas-admin.png` | Satu file untuk tambah & edit (`?id=`) |
| `public/admin/tugas-detail.php` | admin | `23-detail-tugas-admin.png` | Kartu kanan → tabel "Pengumpulan" |
| `public/admin/matkul.php` | admin | `26-kelola-matkul-ref-kelola-kelas.png` | "Kelas" → "Mata kuliah", "Kode undangan" → "Kode MK" |
| `public/admin/anggota.php` | admin | `27-anggota-dan-persetujuan.png` | Tambah tab Menunggu/Disetujui, tombol Setujui/Tolak |
| `public/profil.php` | semua | `17-profil-mahasiswa.png`, `28-profil-admin.png` | — |
| `public/unduh.php` | login | — | Endpoint unduh file berproteksi |

Modal (`docs/design/desktop-modal/`): `m01` pratinjau lampiran/hasil, `m02` file gagal, `m03–m05` foto profil,
`m06–m08` buat/edit/hapus mata kuliah, `m09` hapus tugas, `m10` keluarkan anggota, `m11` logout,
`m12–m13` notifikasi tersimpan (dipakai sebagai flash/toast), `m14–m15` filter (mobile: bottom sheet).

**Sidebar**
- Admin: Dashboard · Daftar tugas · Mata kuliah · Anggota (badge jumlah menunggu) · [Profil saya] · Keluar
- Mahasiswa: Dashboard · Daftar tugas · Pilih mata kuliah · [Profil saya] · Keluar
- Mobile: header ringkas (logo, badge peran, dropdown mata kuliah, Profil) + navigasi bawah tetap.

## 10. Kebutuhan non-fungsional

| Kode | Kategori | Kebutuhan |
|---|---|---|
| N-01 | Keamanan | Prepared statement, `password_hash`, regenerasi session, cookie `httponly` + `samesite=Lax`, CSRF di semua POST, escaping output, logout POST (pola P09). |
| N-02 | Keamanan upload | Validasi `error`, ukuran ≤ 5 MB, MIME via `finfo` dengan whitelist, nama acak, folder privat diblokir `.htaccess`, unduh via `unduh.php` dengan header `X-Content-Type-Options: nosniff` dan `Content-Disposition` (pola P10 + OWASP). |
| N-03 | Otorisasi | Guard peran di setiap halaman + cek kepemilikan/keanggotaan untuk setiap `id` dari URL/form (cegah IDOR). |
| N-04 | Responsif | Mobile-first; diuji di 390px dan 1440px; target sentuh ≥ 44px. |
| N-05 | Aksesibilitas | Label pada setiap input, kontras teks memadai, fokus terlihat (`ring-2`), modal dapat ditutup dengan Esc. |
| N-06 | Kinerja | Query daftar memakai index (`mata_kuliah_id`, `deadline`); paginasi 10 baris. |
| N-07 | Portabilitas | Berjalan di XAMPP standar tanpa instalasi tambahan; Tailwind dimuat dari CDN resmi + salinan lokal di-commit untuk offline (keputusan kelompok, menggantikan "CSS hasil build di-commit"); `config/koneksi.example.php` sebagai templat. |
| N-08 | Keterbacaan kode | Komentar berbahasa Indonesia per bagian (gaya jobsheet), satu tanggung jawab per file. |

### 10.1 Whitelist MIME upload
| Jenis | MIME (`finfo`) | Ekstensi simpan |
|---|---|---|
| PDF | `application/pdf` | pdf |
| DOC | `application/msword` | doc |
| DOCX | `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | docx |
| JPG | `image/jpeg` | jpg |
| PNG | `image/png` | png |
| WEBP | `image/webp` | webp |

Catatan teknis: pada sebagian versi libmagic, file DOCX terdeteksi sebagai `application/zip`.
Bila itu terjadi, terima hanya jika ekstensi asli `.docx` **dan** arsip memuat `word/document.xml`
(cek dengan `ZipArchive`). Gambar diverifikasi tambahan dengan `getimagesize()`.
Pastikan `upload_max_filesize` dan `post_max_size` di `php.ini` XAMPP ≥ 8M.

## 11. Rencana uji (acuan laporan)

| No | Skenario | Langkah | Harapan |
|---|---|---|---|
| U-01 | Registrasi normal | Isi data valid | Akun tersimpan, hash `$2y$…`, diarahkan ke Pilih mata kuliah |
| U-02 | Email ganda (negatif) | Daftar dengan email terdaftar | Ditolak dengan pesan |
| U-03 | Login salah (negatif) | Kata sandi salah | "Email atau kata sandi salah." |
| U-04 | Akses tanpa login (negatif) | Buka `/admin/dashboard.php` di incognito | Redirect ke login |
| U-05 | Mahasiswa buka halaman admin (negatif) | Login mahasiswa, buka URL admin | 403 / redirect ke dashboard mahasiswa |
| U-06 | Ajukan & setujui | Mahasiswa ajukan, admin setujui | Tugas mata kuliah tampil di dashboard mahasiswa |
| U-07 | Kuota penuh (negatif) | Kapasitas 1, dua mahasiswa disetujui | Persetujuan kedua ditolak |
| U-08 | IDOR tugas (negatif) | Mahasiswa buka `tugas-detail.php?id=` milik mata kuliah lain | 404 "Tugas tidak ditemukan" |
| U-09 | Ceklis progres | Centang tugas | Status `selesai`, statistik dashboard berubah; mahasiswa lain tidak terpengaruh |
| U-10 | Upload valid | PDF 1 MB | Tersimpan nama acak, tampil di kartu & tabel admin |
| U-11 | Upload palsu (negatif) | File `.txt` diganti nama `.pdf` | Ditolak, tidak ada baris/ file tersimpan |
| U-12 | Upload > 5 MB (negatif) | File 6 MB | Modal "File belum dapat diunggah" |
| U-13 | Unduh hasil orang lain (negatif) | Mahasiswa A akses `unduh.php` untuk file B | 403 |
| U-14 | Ganti hasil | Unggah file kedua | File lama terhapus dari folder |
| U-15 | CSRF (negatif) | POST tanpa token (DevTools) | 400 |
| U-16 | Hapus mata kuliah | Hapus MK berisi tugas & file | Data & file terkait hilang, tidak ada file yatim |
| U-17 | Logout | Keluar lalu tekan Back dan kirim form | Diarahkan ke login |
| U-18 | Responsif | Buka di 390px | Tabel jadi kartu, tidak ada scroll horizontal |

## 12. Alur kerja tim (GitHub)

- Branch `main` selalu bisa dijalankan. Fitur dikerjakan di branch `fitur/<nama>` (mis. `fitur/auth`, `fitur/tugas-admin`), digabung lewat Pull Request yang di-review minimal 1 anggota.
- Pesan commit: `feat: …`, `fix: …`, `docs: …`, `style: …` (Conventional Commits).
- `.gitignore`: `config/koneksi.php`, `storage/uploads/**` (kecuali `.gitkeep` & `.htaccess`), `public/uploads/foto/*`, `node_modules/`.
- Perubahan skema DB ditulis ulang di `database/schema.sql` + catatan di README agar anggota lain mengimpor ulang.
- Pembagian modul yang disarankan: (1) auth + profil, (2) mata kuliah + persetujuan, (3) tugas admin, (4) to-do mahasiswa + upload, (5) layout/Tailwind + QA. Fase 0–2 di `ROADMAP.md` dikerjakan lebih dulu oleh satu orang agar fondasi seragam.

## 13. Risiko & mitigasi

| Risiko | Mitigasi |
|---|---|
| Konflik merge pada file layout | Fondasi layout (Fase 2) diselesaikan & di-merge sebelum fitur lain dimulai |
| Port MySQL berbeda (3306/3307) | Nilai di `config/koneksi.php` lokal, tidak di-commit |
| Upload gagal karena batas `php.ini` | Dokumentasikan nilai minimum di README; tampilkan pesan dari kode `UPLOAD_ERR_INI_SIZE` |
| Kode buatan AI tidak dipahami anggota | Setiap fase diakhiri penjelasan alur oleh pembuat PR; catat penggunaan AI di laporan (sesuai panduan transparansi jobsheet P10) |

## 14. Referensi

1. PHP Manual — *password_hash*, https://www.php.net/manual/en/function.password-hash.php
2. PHP Manual — *Handling file uploads*, https://www.php.net/manual/en/features.file-upload.php
3. PHP Manual — *session_regenerate_id*, https://www.php.net/manual/en/function.session-regenerate-id.php
4. PHP Manual — *PDO prepared statements*, https://www.php.net/manual/en/pdo.prepared-statements.php
5. OWASP Cheat Sheet Series — *File Upload*, https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
6. OWASP Cheat Sheet Series — *Cross-Site Request Forgery Prevention*, https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html
7. OWASP Cheat Sheet Series — *Session Management*, https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html
8. OWASP Cheat Sheet Series — *Authorization* (IDOR), https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html
9. Tailwind CSS — *Installation: Tailwind CLI*, https://tailwindcss.com/docs/installation/tailwind-cli ;
   *Play CDN* (yang dipakai aplikasi), https://tailwindcss.com/docs/installation/play-cdn
10. GitHub Docs — *Creating diagrams* (Mermaid), https://docs.github.com/en/get-started/writing-on-github/working-with-advanced-formatting/creating-diagrams
11. Anthropic — *Claude Code: How Claude remembers your project* (CLAUDE.md), https://code.claude.com/docs/en/memory
12. Jobsheet Praktikum Pemrograman Web P09 (Login & Session) dan P10 (Upload Foto), UMKT 2026.
