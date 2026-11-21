# CLAUDE.md — Kelas Bersama (K/B)

Proyek akhir pengganti UAS mata kuliah Pemrograman Web, Teknik Informatika UMKT.
Aplikasi web "Kelas Bersama": admin mengelola mata kuliah dan to-do list tugas,
mahasiswa mendaftar mata kuliah (menunggu persetujuan admin), mencentang progres,
dan mengunggah hasil kerja.

Dokumen acuan (baca sesuai kebutuhan, jangan dimuat semua sekaligus):
- Kebutuhan lengkap: `docs/PRD.md`
- Alur & ERD (Mermaid): `docs/FLOWCHART.md`
- Urutan pengerjaan per fase: `docs/ROADMAP.md`
- Desain Figma (PNG): `docs/design/` — lihat `docs/design/README.md` untuk pemetaan layar

## Stack (WAJIB, jangan diganti)
- PHP 8 native (tanpa framework, tanpa Composer), PDO + MySQL/MariaDB via XAMPP.
- Tailwind CSS v4 lewat **CDN resmi** (`@tailwindcss/browser@4.3.5` di jsDelivr, versi dipatok
  + hash `integrity`): tanpa instalasi Node, tanpa build. Tema di `public/assets/css/tema.css`.
  Salinan lokal `public/assets/js/vendor/` dipakai otomatis bila CDN gagal (offline).
- JavaScript vanilla seperlunya (modal, pratinjau file, konfirmasi). Tanpa jQuery/React.
- Bahasa antarmuka, nama variabel, nama tabel, dan komentar: **Bahasa Indonesia**.

## Perintah
- Jalankan: start Apache + MySQL di XAMPP, buka `http://localhost/kelas-bersama/public/`
- Import DB: `database/schema.sql` lalu `database/seed.sql` (phpMyAdmin); data presentasi opsional: `database/demo.sql`
  (database `uas_kelas_bersama`; akun demo di README)
- Buat akun admin: `php tools/buat-admin.php` (CLI, minta email/nama/password)
- CSS: tidak ada build. Ubah class di file `.html` / tema di `tema.css`, lalu muat ulang browser.
- Cek sintaks PHP: `php -l <file>` untuk setiap file yang diubah

## Struktur folder
```
config/        koneksi.php (gitignored), koneksi.example.php
app/           kode non-publik: keamanan.php, auth.php, helper.php, upload.php,
               kueri.php (query bersama: filter, paginasi, statistik), komponen.php (data lencana/avatar/opsi),
               templat.php (mesin templat {{ }}), tampilan.php (tampilkanHalaman, data bingkai)
               (folder ini diblokir .htaccess "Require all denied")
  tampilan/    HTML MURNI (.html, tanpa PHP): <nama>.html, admin/, mahasiswa/,
               layout/ (kepala, sidebar, topbar, flash, ...), komponen/ (avatar, lencana, ...)
database/      schema.sql, seed.sql, demo.sql
storage/       uploads/lampiran, uploads/hasil  (privat, diblokir .htaccess, gitignored)
public/        satu-satunya folder yang diakses browser
  admin/       halaman khusus peran admin
  mahasiswa/   halaman khusus peran mahasiswa
  uploads/foto foto profil (pola P10: .htaccess tolak eksekusi skrip)
  assets/      css/tema.css (tema Tailwind), js/app.js, js/tailwind-cadangan.js, js/vendor/ (Tailwind offline)
tools/         skrip CLI (buat-admin.php)
```

## Aturan keamanan (dari materi P09–P10, tidak boleh dilonggarkan)
1. Semua query memakai prepared statement PDO. Dilarang menyisipkan variabel ke string SQL.
2. Password: `password_hash()` / `password_verify()`. Setelah login: `session_regenerate_id(true)`.
3. Setiap halaman yang memproses POST memanggil `app/keamanan.php` (cek token CSRF, tolak 400).
4. Setiap halaman terlindungi memanggil guard di baris awal:
   `wajibLogin()`, `wajibAdmin()`, atau `wajibMahasiswa()` dari `app/auth.php`.
5. Otorisasi per data (cegah IDOR): mahasiswa hanya boleh melihat tugas dari mata kuliah
   yang statusnya `disetujui`, dan hanya boleh mengubah progres/hasil miliknya sendiri.
6. Semua output ke HTML di-escape (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`): di file `.html`
   lewat `{{nama}}` (otomatis); `{{{ }}}` hanya mencetak objek `HtmlAman` buatan kode sendiri.
7. Logout hanya via POST + CSRF.
8. Upload: cek `$_FILES[...]['error']`, ukuran, MIME dari `finfo` (bukan ekstensi/nama),
   nama file acak `bin2hex(random_bytes(16))`, simpan nama asli hanya di DB.
   File lama dihapus **setelah** UPDATE/DELETE DB berhasil.
9. File di `storage/` hanya dapat diunduh lewat `public/unduh.php` yang memeriksa hak akses.
10. Pesan login gagal selalu sama: "Email atau kata sandi salah."

## Pola kode
- Logika dan tampilan dipisah:
  - `public/<halaman>.php` = LOGIKA (guard, baca input, query, validasi), tanpa HTML; diakhiri
    `tampilkanHalaman('<nama>', [...data])`. Teks/angka/tanggal disiapkan di sini (sudah diformat).
  - `app/tampilan/<nama>.html` = TAMPILAN, file HTML murni + class Tailwind, **tanpa kode PHP**.
    Sintaks (app/templat.php, gaya Mustache): `{{data}}`, `{{data.kunci}}`, `{{#daftar}}...{{/daftar}}`
    (ulang / tampil bila ya), `{{^data}}...{{/data}}` (bila kosong/tidak), `{{> layout/nama}}`, `{{! komentar }}`.
    Komentar `{{! }}` tidak boleh berisi kurung kurawal ganda.
  - Form POST diarahkan ke file aksi tanpa HTML (`*-simpan.php`, `*-hapus.php`, `*-aksi.php`),
    lalu redirect 303 (pola Post/Redirect/Get) + pesan flash di session.
  - Setiap form POST di .html: `<input type="hidden" name="csrf" value="{{csrf}}">`; alamat: `{{dasar}}admin/...`.
- Input dibaca dengan pola `is_string($_POST['x'] ?? null) ? trim($_POST['x']) : ''`.
- Validasi di server wajib; atribut HTML (`required`, `maxlength`) hanya pelengkap.
- Bingkai halaman (kepala, sidebar admin/mahasiswa, topbar, nav-bawah, flash, modal konfirmasi)
  dirakit `tampilkanHalaman()` lewat `app/tampilan/layout/halaman.html`. Jangan menyalin markup sidebar.
- Gaya hanya lewat class Tailwind (+ komponen `@apply` di `tema.css`): tanpa atribut `style`,
  tanpa `<script>` inline (dilarang CSP). Teks antarmuka & class Tailwind ditulis di file `.html`.
- Zona waktu: `date_default_timezone_set('Asia/Makassar')`, label "WITA".
- Tanggal tampil format Indonesia: "19 November 2026, 23.59 WITA".

## Design token (dari `docs/design/00-panduan-ui-tailwind.png`)
Definisikan di `public/assets/css/tema.css` lewat `@theme`:
- navy `#17243A` (sidebar, teks utama, banner) · slate `#526783` (teks sekunder)
- warm `#F7F8FA` (latar halaman) · gold `#C5A46D` (tombol utama, aksen, menu aktif)
- border `#E2E7ED` 1px · font Inter (400/500/600/700)
- Sudut: input & tombol `rounded-lg` (8px), kartu `rounded-xl` (12px)
- Tombol `min-h-11` (44px). Fokus `focus:ring-2 focus:ring-slate`.
- Mobile-first: `px-6 gap-4 grid-cols-1`; di `lg` (≥1024px) sidebar 232px tetap, konten `lg:ml-[232px] lg:px-10`.
- Tabel tugas di desktop berubah jadi kartu di mobile; form dua kolom → satu kolom.

## Penyesuaian desain vs Figma (keputusan kelompok)
- Figma memakai "Kelas + kode undangan"; aplikasi memakai **Mata kuliah + pengajuan + persetujuan admin**.
- Hanya admin yang membuat/mengedit/menghapus tugas. Tombol "Tambah tugas"/"Edit tugas"
  di layar mahasiswa **dihapus**.
- Fitur Rolling kelompok & Riwayat pembagian **tidak dikerjakan**.
- Ditambahkan kartu "Hasil kerja saya" (upload) di detail tugas mahasiswa dan
  tabel "Pengumpulan" di detail tugas admin.

## Cara kerja yang diharapkan dari Claude
- Kerjakan satu fase `docs/ROADMAP.md` per sesi; mulai dengan rencana singkat sebelum menulis kode.
- Sebelum membuat halaman, lihat PNG desain yang relevan di `docs/design/`.
- Setelah mengubah file PHP, jalankan `php -l`. Sebutkan langkah uji manual di browser.
- Jangan membuat fitur di luar PRD. Jika PRD ambigu, tanyakan.
- Jangan commit `config/koneksi.php`, `storage/uploads/*`, `public/uploads/foto/*`, `node_modules/`.
