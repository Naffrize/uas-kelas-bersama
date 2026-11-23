# Panduan Pengembang

Catatan teknis untuk anggota kelompok: cara menyiapkan lingkungan kerja, pola kode, tampilan,
keamanan, dan masalah yang sering muncul. Cara menjalankan aplikasi ada di README di akar repo.

## Menyiapkan lingkungan kerja

Supaya repo tetap di folder kuliah tetapi bisa dibuka lewat XAMPP, buat *junction* di Command Prompt:

```bat
mklink /J C:\xampp\htdocs\kelas-bersama "D:\lokasi\repo\uas-kelas-bersama\kelas-bersama"
```

Aplikasi lalu bisa dibuka di http://localhost/kelas-bersama/public/. Alamat dasar aplikasi dihitung
otomatis, jadi nama folder di `htdocs` bebas.

Untuk melepas junction cukup `rmdir C:\xampp\htdocs\kelas-bersama`. Yang terhapus hanya tautannya,
isi repo tetap aman. Jangan menghapus isi folder junction, karena itu sama dengan menghapus file repo.

Akun admin tanpa data demo dibuat lewat skrip:

```bat
C:\xampp\php\php.exe tools\buat-admin.php
```

## Struktur folder

```text
kelas-bersama/
├── app/                kode PHP bersama (diblokir .htaccess, 403 dari browser)
│   ├── keamanan.php    session, token CSRF, header keamanan, zona waktu
│   ├── auth.php        guard login/peran dan "ingat saya"
│   ├── helper.php      db(), url(), e(), redirect303(), flash, format tanggal WITA
│   ├── kueri.php       query bersama: filter, paginasi, statistik, tugas per peran
│   ├── komponen.php    data untuk komponen tampilan: lencana, avatar, opsi select, paginasi
│   ├── upload.php      validasi dan simpan unggahan (finfo, nama acak)
│   ├── templat.php     mesin templat yang mengisi {{ }} di file .html
│   ├── tampilan.php    tampilkanHalaman(): merakit halaman dari file .html
│   └── tampilan/       HTML setiap halaman, tanpa kode PHP
│       ├── admin/, mahasiswa/, login.html, register.html, profil.html, ...
│       ├── layout/     kerangka halaman: kepala, sidebar, topbar, flash, modal, paginasi
│       └── komponen/   potongan kecil: avatar, lencana, panah select
├── config/             koneksi.example.php (salin menjadi koneksi.php)
├── database/           schema.sql, seed.sql, demo.sql, migrasi-warna-matkul.sql
├── docs/               PRD, flowchart, roadmap, desain Figma, hasil uji, tangkapan layar
├── public/             satu-satunya folder yang dibuka browser
│   ├── admin/, mahasiswa/   halaman per peran dan file aksinya
│   ├── assets/         css/tema.css, js/app.js, js/tailwind-cadangan.js, js/vendor/, img/
│   └── uploads/foto/   foto profil (.htaccess menolak eksekusi skrip)
├── storage/            uploads/lampiran, uploads/hasil (privat, 403)
└── tools/              buat-admin.php
```

## Logika dan tampilan

Setiap halaman terdiri dari dua file:

| File | Isi |
|---|---|
| `public/<halaman>.php` | guard login/peran, membaca input, query, validasi. Tanpa HTML. |
| `app/tampilan/<halaman>.html` | HTML dan class Tailwind. Tanpa kode PHP. |

File logika selalu diakhiri dengan `tampilkanHalaman()`, yang mengisi file `.html` dengan data lalu
memasangnya di `layout/halaman.html` bersama sidebar dan topbar:

```php
// public/admin/matkul.php
$pengguna = wajibAdmin();
$daftarMataKuliah = db()->query('SELECT ... FROM mata_kuliah ...')->fetchAll();
tampilkanHalaman('admin/matkul', [
    'judulHalaman' => 'Mata kuliah',
    'menuAktif' => 'matkul',
    'pengguna' => $pengguna,
    'daftarMataKuliah' => $daftarMataKuliah,
]);
```

```html
<!-- app/tampilan/admin/matkul.html -->
<ul class="mt-6 grid gap-4 lg:grid-cols-2">
    {{#daftarMataKuliah}}
        <li class="kartu p-6">
            <h3 class="text-2xl font-bold">{{nama}}</h3>
            <p>Kode MK: {{kode}}</p>
        </li>
    {{/daftarMataKuliah}}
</ul>
{{^daftarMataKuliah}}
    <p>Belum ada mata kuliah.</p>
{{/daftarMataKuliah}}
```

Sintaks yang bisa dipakai di file `.html` (gaya [Mustache](https://mustache.github.io)):

| Tanda | Arti |
|---|---|
| `{{nama}}` | cetak data sebagai teks, otomatis di-escape |
| `{{tugas.judul}}` | ambil isi data bertingkat |
| `{{#daftar}}...{{/daftar}}` | ulangi untuk setiap isi daftar, atau tampilkan bila nilainya ya |
| `{{^daftar}}...{{/daftar}}` | tampilkan bila daftar kosong atau nilainya tidak |
| `{{> layout/sidebar}}` | sisipkan file `.html` lain |
| `{{! catatan }}` | komentar, tidak dikirim ke browser (jangan berisi kurung kurawal ganda) |

Beberapa aturan:

- File `.html` tidak berisi query atau logika. Tanggal yang sudah diformat dan tanda ya/tidak
  (misalnya `terlewat`) disiapkan di file logika.
- Nama data yang salah ketik langsung memunculkan galat, tidak diam-diam kosong.
- Setiap form POST wajib berisi `<input type="hidden" name="csrf" value="{{csrf}}">`.
  Alamat ditulis dengan `{{dasar}}`, misalnya `{{dasar}}admin/tugas.php`.
- File aksi (`*-simpan.php`, `*-hapus.php`, `*-aksi.php`) tidak punya tampilan: memproses POST,
  lalu redirect 303 dengan pesan flash.

## Tampilan dan Tailwind

Tailwind dimuat dari CDN di `app/tampilan/layout/kepala.html`, versinya dipatok (4.3.5) dan
dicek `integrity`. Tidak ada proses build. Ubah class di file `.html`, lalu muat ulang browser.

Tema ada di `public/assets/css/tema.css`. File itu tidak dipasang dengan `<link>`, melainkan
disisipkan ke `<style type="text/tailwindcss">` lalu dibaca Tailwind di browser. Isinya:

- warna: `navy`, `navy-muda`, `slate`, `warm`, `gold`, `gold-muda`, `garis`, `hijau`, `hijau-muda`
- komponen: `tombol` (`tombol-emas`, `tombol-putih`, `tombol-navy`), `kartu`, `masukan`, `label`,
  `lencana`, `progres`, `modal`, dan lainnya

Bila CDN gagal dimuat (tanpa internet), `public/assets/js/tailwind-cadangan.js` memuat salinan lokal
dari `public/assets/js/vendor/`. Tampilan tetap sama, hanya font Inter diganti font sistem.

### Warna mata kuliah

Admin memilih warna setiap mata kuliah (kolom `mata_kuliah.warna`). Pilihannya: emas, navy, hijau,
toska, biru, plum, terakota, dan abu. Nilai warnanya diatur di satu tempat, `tema.css`.

Di HTML cukup beri atribut `data-warna` pada elemen pembungkus, lalu pakai class `mk-*`:

```html
<li class="kartu" data-warna="{{warna}}">
    <div class="bg-mk-latar text-mk-teks">...</div>
    <span class="size-2 rounded-full bg-mk-aksen"></span>
</li>
```

| Class | Dipakai untuk |
|---|---|
| `bg-mk-latar` | latar lembut (kepala kartu, label) |
| `bg-mk-aksen`, `border-mk-aksen` | titik penanda, garis kiri/atas |
| `text-mk-teks` | teks di atas latar lembut (sudah lolos kontras WCAG AA) |

Untuk menambah warna baru: tambahkan di `ENUM` kolom `warna` (`schema.sql`), di konstanta
`WARNA_MATKUL` (`app/helper.php`), dan satu baris `[data-warna="..."]` di `tema.css`.

## Keamanan

| Aturan | Penerapan |
|---|---|
| Prepared statement | Semua query lewat `prepare()`/`execute()` atau `jalankan()`; filter dinamis hanya menggabung potongan SQL tetap (`app/kueri.php`) |
| Password | `password_hash()`/`password_verify()`; `session_regenerate_id(true)` di `masukkanPengguna()` |
| CSRF | `app/keamanan.php` memeriksa setiap POST (gagal → 400) |
| Guard | `wajibLogin()`, `wajibAdmin()`, `wajibMahasiswa()` di awal setiap halaman |
| IDOR | `tugasUntukMahasiswa()` selalu JOIN pendaftaran berstatus `disetujui`; ID mahasiswa diambil dari session |
| Escaping | `{{data}}` di file `.html` selalu lewat `htmlspecialchars`; `{{{ }}}` hanya mencetak objek `HtmlAman` |
| Logout | Hanya lewat POST dengan token CSRF |
| Upload | Cek kode error, ukuran ≤ 5 MB, MIME dari `finfo`, nama file acak; file lama dihapus setelah database berhasil diubah |
| Unduh | File di `storage/` hanya lewat `public/unduh.php` yang memeriksa hak akses |
| Login gagal | Pesan selalu "Email atau kata sandi salah." |
| Lainnya | CSP (skrip hanya dari server sendiri dan jsDelivr), `X-Frame-Options`, `nosniff`, `no-store`, kuota dikunci `FOR UPDATE`, alamat `kembali` hanya internal |

## Alur kerja tim

- `main` harus selalu bisa dijalankan. Tarik perubahan teman dulu (`git pull`) sebelum mulai bekerja.
- Pesan commit singkat dengan awalan `feat:`, `fix:`, `docs:`, atau `style:`.
- Jangan commit `config/koneksi.php`, isi `storage/uploads/`, dan isi `public/uploads/foto/`.
- Kalau skema berubah, perbarui `database/schema.sql` dan sediakan file migrasi agar anggota lain
  tidak perlu mengimpor ulang dari nol.
- Setelah mengubah file PHP, cek sintaksnya: `C:\xampp\php\php.exe -l nama-file.php`.

Pembagian modul (PRD bagian 12):

| Modul | File utama |
|---|---|
| Autentikasi dan profil | `app/keamanan.php`, `app/auth.php`, `login.php`, `register.php`, `logout.php`, `profil*.php` |
| Mata kuliah dan persetujuan | `admin/matkul*.php`, `admin/anggota*.php`, `mahasiswa/matkul*.php` |
| Tugas (admin) | `admin/tugas*.php`, `app/upload.php`, `unduh.php` |
| To-do dan unggah hasil | `mahasiswa/tugas*.php`, `progres-simpan.php`, `hasil-*.php`, dashboard |
| Tampilan dan pengujian | `app/templat.php`, `app/tampilan.php`, `app/tampilan/`, `assets/css/tema.css`, `docs/HASIL-UJI.md` |

Setiap modul juga memegang file `.html`-nya di `app/tampilan/`.

## Masalah umum

| Gejala | Solusi |
|---|---|
| "Koneksi database gagal. MySQL belum berjalan atau port salah." | Nyalakan MySQL; samakan port di XAMPP dengan `$dbPort` di `config/koneksi.php` |
| "Koneksi database gagal. Database belum ada." | Impor `database/schema.sql` |
| "config/koneksi.php belum ada." | Salin `config/koneksi.example.php` menjadi `config/koneksi.php` |
| Selalu "Email atau kata sandi salah" padahal akun demo diketik benar | Akun demo belum ada; impor `database/demo.sql`. Mengimpor ulang `schema.sql` menghapus semua akun |
| Galat "Unknown column 'mk.warna'" | Database dari versi lama; impor `database/migrasi-warna-matkul.sql` |
| Galat "data {{...}} tidak dikirim oleh file logika" | Nama data di `.html` salah ketik, atau belum dikirim lewat `tampilkanHalaman()` |
| Halaman tampil tanpa gaya | CDN tidak terjangkau dan salinan lokal di `public/assets/js/vendor/` hilang; ambil ulang dari repo |
| `'php' is not recognized` | Pakai `C:\xampp\php\php.exe` atau tambahkan `C:\xampp\php` ke PATH |
| Unggahan gagal / "Kiriman terlalu besar" | Naikkan `upload_max_filesize` dan `post_max_size` di `php.ini` minimal `8M`, lalu restart Apache |
