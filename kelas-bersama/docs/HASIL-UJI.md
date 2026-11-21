# Hasil Uji — Kelas Bersama

| | |
|---|---|
| Tanggal uji | 20 November 2026 |
| Lingkungan | Windows 11 · XAMPP (Apache 2.4, PHP 8.2.12, MariaDB 10.4.32) · Microsoft Edge (Chromium) |
| Data awal | `database/schema.sql` + `seed.sql` + `demo.sql` |
| Metode | (1) Skrip otomatis `curl` yang menjalankan alur nyata lewat Apache — lengkap dengan cookie session & token CSRF — lalu memeriksa kode HTTP, isi halaman, isi database, dan file di folder unggahan. (2) Uji perilaku JavaScript di browser sungguhan (Edge headless): klik, pilih file, kirim form. (3) Tangkapan layar 1440 px & 390 px untuk tampilan. |
| Hasil | **97 pemeriksaan server lulus · 24 pemeriksaan JavaScript lulus (online & offline) · 0 gagal** |
| Uji ulang | Dijalankan lagi setelah tampilan diubah menjadi file **HTML murni** (`app/tampilan/*.html`) dan Tailwind dimuat dari **CDN**. HTML 77 keadaan halaman (semua peran, galat validasi, flash, paginasi, 403/404/400) direkam sebelum & sesudah lalu dibandingkan: isinya sama. Perbedaan hanya yang disengaja: `<head>` (CDN + tema), deskripsi tugas memakai `whitespace-pre-line` sebagai ganti `<br>`, dan tautan "Lewati ke konten" di cek-koneksi. Tangkapan layar desktop & HP identik dengan versi sebelumnya. |

## 1. Skenario PRD bagian 11

| No | Skenario | Input / langkah | Harapan | Hasil aktual | Status |
|---|---|---|---|---|---|
| U-01 | Registrasi normal | Nama "Uji Satu", email `Uji1@Contoh.test`, sandi `rahasia123` + konfirmasi | Akun tersimpan, hash `$2y$…`, diarahkan ke Pilih mata kuliah | 303 → `mahasiswa/matkul.php`; hash diawali `$2y$`; email disimpan huruf kecil; modal "Akun berhasil dibuat" | Lulus |
| U-02 | Email ganda (negatif) | Daftar lagi dengan `uji1@contoh.test` | Ditolak dengan pesan | Tetap di form, pesan "Email sudah terdaftar. Silakan masuk." | Lulus |
| U-03 | Login salah (negatif) | Sandi salah; juga email yang tidak terdaftar | "Email atau kata sandi salah." | Pesan identik untuk kedua kasus | Lulus |
| U-04 | Akses tanpa login (negatif) | Buka `admin/dashboard.php` tanpa cookie | Redirect ke login | 303 → `login.php` | Lulus |
| U-05 | Mahasiswa buka halaman admin (negatif) | Login mahasiswa → `admin/dashboard.php` | 403 / redirect | 403 "Akses ditolak" (admin → halaman mahasiswa juga 403) | Lulus |
| U-06 | Ajukan & setujui | Mahasiswa ajukan IF-PWEB → admin klik Setujui | Tugas mata kuliah tampil di dashboard mahasiswa | Status `disetujui`, `diputuskan` & `diputuskan_oleh` terisi; tugas "Form login dengan session" tampil | Lulus |
| U-07 | Kuota penuh (negatif) | Mata kuliah kapasitas 1, dua pengajuan, admin setujui keduanya | Persetujuan kedua ditolak | Pengajuan kedua tetap `menunggu`, modal "Kapasitas penuh"; pengajuan baru saat kuota penuh juga ditolak (F-21) | Lulus |
| U-08 | IDOR tugas (negatif) | Mahasiswa membuka `tugas-detail.php?id=` dari mata kuliah yang belum disetujui | 404 "Tugas tidak ditemukan" | 404 | Lulus |
| U-09 | Ceklis progres | Centang tugas di daftar | Status `selesai`; mahasiswa lain tidak terpengaruh | Baris `selesai` hanya milik mahasiswa itu; hapus centang → `belum_mulai`; statistik dashboard ikut berubah | Lulus |
| U-10 | Upload valid | PDF ±1 MB | Tersimpan nama acak, tampil di kartu & tabel admin | Nama `^[a-f0-9]{32}\.pdf$` di `storage/uploads/hasil`; tampil di kartu "Hasil kerja saya" dan tabel Pengumpulan; status naik ke `dikerjakan` (F-45) | Lulus |
| U-11 | Upload palsu (negatif) | File teks diganti nama `.pdf` | Ditolak, tidak ada baris/file | Ditolak (`finfo` = `text/plain`); jumlah baris & file tidak berubah. Juga berlaku untuk lampiran admin dan foto profil (skrip berkedok `.png`) | Lulus |
| U-12 | Upload > 5 MB (negatif) | File 6 MB | Modal "File belum dapat diunggah" | Modal tampil: "Ukuran besar.pdf adalah 6,0 MB. Batas maksimal 5 MB per file…"; tidak ada file baru. Browser sudah menolak sebelum dikirim | Lulus |
| U-13 | Unduh hasil orang lain (negatif) | Mahasiswa B membuka `unduh.php?jenis=hasil&tugas=…&mhs=<A>` | 403 | 403. Lampiran mata kuliah yang belum disetujui juga 403 | Lulus |
| U-14 | Ganti hasil | Unggah file kedua | File lama terhapus dari folder | File lama hilang, file baru tersimpan | Lulus |
| U-15 | CSRF (negatif) | POST tanpa token / token salah | 400 | 400 (tanpa sesi → diarahkan ke login) | Lulus |
| U-16 | Hapus mata kuliah | Hapus MK berisi tugas, lampiran, dan hasil kerja | Data & file hilang, tidak ada file yatim | Tugas, pendaftaran, progres terhapus (CASCADE); file lampiran & hasil dihapus setelah COMMIT | Lulus |
| U-17 | Logout | Keluar, lalu buka halaman / kirim form lama | Diarahkan ke login | 303 → `login.php`; form lama → `login.php?sesi=habis`; `GET logout.php` tidak mengeluarkan | Lulus |
| U-18 | Responsif | Buka di 390 px | Tabel jadi kartu, tanpa scroll horizontal | Diperiksa lewat tangkapan layar 390 px (`tangkapan-layar/10`–`13`): tabel → kartu, navigasi bawah tetap, tanpa scroll horizontal | Lulus (visual) |

## 2. Pemeriksaan keamanan tambahan

| Pemeriksaan | Hasil |
|---|---|
| XSS: nama berisi `<script>alert(1)</script>` | Tampil ter-escape (`&lt;script&gt;`) |
| Open redirect pada parameter `kembali` (`//evil.example/x`) | Diabaikan, kembali ke halaman bawaan |
| IDOR: ubah progres tugas mata kuliah lain lewat form | 404 |
| Status progres tidak dikenal | 400 |
| Header | `Content-Security-Policy`, `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store` |
| Cookie session | `HttpOnly`, `SameSite=Lax`, nama khusus `KELASBERSAMA` |
| Akses langsung `storage/`, `app/` (termasuk file tampilan `app/tampilan/*.html`), `config/`, `database/`, `tools/` | 403 |
| Mesin templat (`app/templat.php`) | 26 uji unit lulus: `{{ }}` selalu escape (termasuk `<script>`, tanda kutip), `{{{ }}}` hanya mencetak objek `HtmlAman`, nama templat aneh (`../config/koneksi`) ditolak, data salah ketik → galat |
| Tailwind CDN | Versi dipatok 4.3.5 + `integrity` sha384 (sama dengan salinan lokal); CSP hanya menambah `https://cdn.jsdelivr.net` untuk skrip, skrip inline tetap ditolak |
| `.php` di `public/uploads/foto/` | 403, tidak dieksekusi |
| Unduh file | `Content-Disposition: attachment`, Content-Type dari database, isi file identik |
| DOCX | Dikenali; ZIP tanpa `word/document.xml` ditolak; parser ZIP manual (tanpa ekstensi zip) lulus uji |
| "Ingat saya" | Cookie berisi validator acak; database hanya menyimpan hash SHA-256; token dirotasi saat masuk otomatis dan dihapus saat logout |
| Ganti kata sandi | Sandi lama salah ditolak; setelah diganti, sandi baru bisa dipakai dan sandi lama tidak |

## 3. Pemeriksaan fungsi tambahan

Validasi tugas (judul kosong, tanggal 31 Februari) dengan isian tetap terisi · kode MK duplikat & kapasitas 501 ditolak (modal terbuka lagi) · edit mata kuliah · ajukan dua kali tetap satu baris (R-02) · batalkan pengajuan · ditolak → ajukan ulang memakai baris yang sama · keluarkan anggota (tugas → 404, unduh hasil → 403, data tetap tersimpan) · paginasi · cari & filter (status, mata kuliah) · judul daftar dashboard mengikuti filter · ubah nama & foto profil (foto tampil di sidebar) · semua halaman admin & mahasiswa terbuka tanpa warning PHP.

## 4. Perilaku JavaScript (Edge, CSP aktif)

| Pemeriksaan | Hasil |
|---|---|
| Tailwind CDN aktif: latar body = warna tema `warm`, komponen `.kartu` (sudut 12px, border `garis`) diterapkan | Lulus |
| Tanpa internet (CDN & Google Fonts diblokir): salinan lokal Tailwind dimuat otomatis, semua pemeriksaan tetap lulus | Lulus |
| Form ber-`data-konfirmasi` membuka modal dulu, tidak langsung terkirim | Lulus |
| Judul & tombol modal mengikuti `data-judul` / `data-tombol` | Lulus |
| Tombol Batal & klik latar gelap menutup modal | Lulus |
| Pilih PDF → modal pratinjau (nama, ukuran, jenis) | Lulus |
| Pilih PNG → pratinjau gambar (`blob:`) | Lulus |
| "Pilih file lain" / menutup pratinjau membatalkan pilihan | Lulus |
| File 6 MB / `.exe` → modal galat sebelum dikirim | Lulus |
| "Gunakan file" → unggah terkirim, modal flash terbuka otomatis | Lulus |
| Centang checkbox → tersimpan otomatis + toast, toast hilang ±6 detik | Lulus |
| Tombol filter membuka modal "Filter tugas" | Lulus |
| "Ya, keluar" → logout lewat POST → halaman masuk | Lulus |
| Admin Mata kuliah: Edit membuka modal berisi data MK; Batal menutup; "Buat mata kuliah" membuka form kosong (kapasitas 40) | Lulus |
| Admin Mata kuliah: Hapus → modal konfirmasi dulu, belum menghapus | Lulus |
| Admin Anggota: Tolak → modal konfirmasi; Batal → pengajuan tetap ada | Lulus |

## 5. Uji manual singkat (untuk presentasi)

1. Impor `schema.sql`, `seed.sql`, `demo.sql`, lalu buka `http://localhost/kelas-bersama/public/`.
2. Masuk sebagai **nadia@demo.kelasbersama.test / mahasiswa123** → centang satu tugas, buka detail tugas, unggah PDF.
3. Keluar, masuk sebagai **admin@demo.kelasbersama.test / admin12345** → Anggota → Setujui pengajuan; buka detail tugas → unduh hasil Nadia.
4. Uji negatif: ganti `id` di URL detail tugas mahasiswa ke tugas mata kuliah lain (harus 404); hapus input `csrf` lewat DevTools lalu kirim form (harus 400).
