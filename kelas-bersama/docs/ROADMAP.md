# Roadmap Implementasi dengan Claude Code (VS Code)

Cara pakai: kerjakan **satu fase per sesi** Claude Code. Di awal setiap fase, aktifkan
*plan mode* (Shift+Tab sampai muncul "plan mode") lalu tempel prompt fase tersebut.
Setujui rencana, biarkan Claude menulis kode, uji di browser, commit, lalu `/clear`
sebelum fase berikutnya. `CLAUDE.md` di root otomatis dibaca setiap sesi.

Centang kotak setelah kriteria selesai terpenuhi.

---

## Fase 0 — Fondasi repo  *(1 orang, sebelum yang lain mulai)*

**Hasil:** struktur folder, Tailwind jalan, koneksi DB, skema, akun admin.

```text
Baca @CLAUDE.md dan bagian 8 @docs/PRD.md.
Kerjakan Fase 0 dari @docs/ROADMAP.md:
1. Buat struktur folder sesuai CLAUDE.md (config, app, database, storage, public, tools),
   termasuk .htaccess "Require all denied" di app/, config/, storage/, database/, tools/,
   dan .htaccess penolak eksekusi skrip di public/uploads/foto (pola P10).
2. database/schema.sql lengkap dengan index (tugas.mata_kuliah_id, tugas.deadline,
   pendaftaran.status) — aman diimpor ulang (DROP TABLE IF EXISTS urutan anak dulu).
3. database/seed.sql: 4 mata kuliah contoh (Rekayasa Perangkat Lunak, Basis Data,
   Struktur Data, Pemrograman Web) TANPA akun.
4. config/koneksi.example.php (PDO, ERRMODE_EXCEPTION, utf8mb4, port 3306) + .gitignore.
5. tools/buat-admin.php (CLI saja; tolak jika dijalankan lewat web).
6. package.json dengan script dev/build Tailwind CLI v4; public/assets/css/input.css
   berisi @import "tailwindcss" dan @theme design token dari CLAUDE.md + font Inter.
7. public/index.php sementara yang menampilkan "Koneksi OK" bila DB tersambung.
8. README.md: cara instal (XAMPP, import SQL, salin koneksi, buat admin, npm run build).
Jangan buat halaman fitur dulu.
```

- [x] `http://localhost/kelas-bersama/public/` menampilkan "Koneksi OK" *(sejak Fase 1 halaman ini pindah ke `public/cek-koneksi.php`; `index.php` menjadi pengarah sesuai peran)*
- [x] `php tools/buat-admin.php` membuat admin dengan hash `$2y$`
- [x] ~~`npm run build` menghasilkan `public/assets/css/app.css`~~ *(digantikan keputusan kelompok: Tailwind dimuat dari CDN tanpa build — lihat bagian "Setelah Fase 9" di bawah)*
- [x] Akses `http://localhost/kelas-bersama/app/` → 403

## Fase 1 — Keamanan inti & autentikasi

```text
Kerjakan Fase 1 dari @docs/ROADMAP.md. Acuan: F-01..F-04 di @docs/PRD.md,
diagram 3 di @docs/FLOWCHART.md, desain @docs/design/desktop/01-login.png dan
@docs/design/desktop/02-register.png (sementara pakai HTML polos rapi, layout final di Fase 2).
Buat:
- app/keamanan.php (session cookie httponly+samesite Lax, token CSRF, tolak POST tanpa token 400)
- app/helper.php (e(), redirect303(), flash set/get, formatTanggalIndo(), salam())
- app/auth.php (penggunaSaatIni(), wajibLogin(), wajibAdmin(), wajibMahasiswa())
- public/register.php, login.php, logout.php, index.php (redirect sesuai peran)
Ikuti pola kode jobsheet P09 (komentar per BAGIAN, validasi input string, pesan generik).
```

- [x] U-01, U-02, U-03, U-04, U-05, U-15, U-17 di PRD bagian 11 lulus

## Fase 2 — Layout & komponen UI

```text
Kerjakan Fase 2 dari @docs/ROADMAP.md. Lihat @docs/design/00-panduan-ui-tailwind.png,
@docs/design/desktop/20-dashboard-admin.png, @docs/design/mobile/10-dashboard-mahasiswa.png,
dan modal di @docs/design/desktop-modal/m11-logout.png.
Buat app/layout/: head.php, sidebar.php (menu berbeda admin/mahasiswa sesuai PRD bagian 9,
item aktif berwarna gold), topbar.php (badge peran, dropdown filter mata kuliah),
nav-bawah.php (mobile), flash.php (toast dari m12/m13), modal-konfirmasi.php (dipakai ulang
untuk logout/hapus/keluarkan), footer.php.
Buat public/assets/js/app.js: buka/tutup modal (Esc & klik backdrop), konfirmasi sebelum submit,
auto-submit checkbox ceklis.
Terapkan layout ke login & register (panel navy kiri, form kanan) dan buat dashboard kosong
untuk kedua peran. Pastikan responsif 390px dan 1440px.
```

- [x] Tampilan login/register/dashboard kosong mirip Figma di 390px & 1440px
- [x] Modal logout berjalan, logout via POST

## Fase 3 — Admin: CRUD mata kuliah

```text
Kerjakan Fase 3 dari @docs/ROADMAP.md: F-10..F-13 di @docs/PRD.md, diagram 5 di
@docs/FLOWCHART.md, desain @docs/design/desktop/26-kelola-matkul-ref-kelola-kelas.png
dan modal m06, m07, m08. Terapkan penyesuaian istilah di PRD bagian 9.
File: public/admin/matkul.php, matkul-simpan.php, matkul-hapus.php.
Hapus memakai transaksi; file fisik dihapus setelah COMMIT (fungsi hapusFile di app/upload.php
boleh dibuat sebagai stub dulu).
```

- [x] Tambah, edit (kapasitas < anggota ditolak), hapus dengan konfirmasi berjalan
- [x] Kode MK duplikat ditolak dengan pesan

## Fase 4 — Pendaftaran mata kuliah & persetujuan

```text
Kerjakan Fase 4 dari @docs/ROADMAP.md: F-20..F-25, R-02..R-04 di @docs/PRD.md,
diagram 4 dan state diagram pendaftaran di @docs/FLOWCHART.md.
Desain: @docs/design/desktop/03-gabung-ref-pilih-matkul.png (ubah jadi grid kartu MK),
@docs/design/desktop/27-anggota-dan-persetujuan.png (tambah tab Menunggu/Disetujui),
modal m10.
File: public/mahasiswa/matkul.php, matkul-aksi.php (ajukan/batal/ajukan ulang),
public/admin/anggota.php, anggota-aksi.php (setujui/tolak/keluarkan).
Cek kuota di dalam transaksi (SELECT ... FOR UPDATE) agar dua persetujuan bersamaan tidak melebihi kapasitas.
Tambahkan badge jumlah pengajuan menunggu di menu Anggota.
```

- [x] U-06, U-07 lulus; empty state dashboard mahasiswa (F-25) tampil

## Fase 5 — Admin: CRUD tugas + lampiran

```text
Kerjakan Fase 5 dari @docs/ROADMAP.md: F-30..F-36, bagian 10.1 di @docs/PRD.md,
diagram 6 dan 8 di @docs/FLOWCHART.md.
Desain: @docs/design/desktop/24-tambah-tugas-admin.png, 25-edit-tugas-admin.png,
23-detail-tugas-admin.png, 29-ref-daftar-tugas-tabel-admin-draf-lama.png (ambil struktur
tabelnya saja, warna ikut desain final), modal m01, m02, m09.
Lengkapi app/upload.php: simpanUnggahan(string $field, string $jenis): array|null yang
melempar UnggahanGagal (exception) berisi pesan ramah; hapusFile(string $jenis, ?string $nama).
Jenis: 'lampiran' dan 'hasil' → storage/uploads/{jenis}; 'foto' → public/uploads/foto.
Buat public/unduh.php sesuai diagram 9.
File: public/admin/tugas.php, tugas-form.php, tugas-simpan.php, tugas-hapus.php, tugas-detail.php
(tabel Pengumpulan boleh menampilkan data kosong dulu).
```

- [x] Tambah/edit/hapus tugas berjalan; lampiran tersimpan dengan nama acak di `storage/`
- [x] U-11, U-12 lulus untuk lampiran; akses langsung ke `storage/uploads/...` → 403

## Fase 6 — Mahasiswa: to-do list & ceklis progres

```text
Kerjakan Fase 6 dari @docs/ROADMAP.md: F-40..F-42, R-05, R-06 di @docs/PRD.md,
diagram 7 (bagian ceklis) dan state diagram progres di @docs/FLOWCHART.md.
Desain: @docs/design/desktop/18-ref-daftar-tugas-tabel-mahasiswa-draf-lama.png,
@docs/design/mobile/18-ref-daftar-tugas-kartu-mahasiswa-draf-lama.png,
13-detail-tugas-mahasiswa.png, 14/15/16 (tiga keadaan progres).
Hilangkan tombol Tambah tugas & Edit tugas. Semua query tugas mahasiswa WAJIB lewat JOIN
pendaftaran status 'disetujui' (lihat PRD 8.2). Progres disimpan dengan
INSERT ... ON DUPLICATE KEY UPDATE.
File: public/mahasiswa/tugas.php, tugas-detail.php, progres-simpan.php.
```

- [x] U-08, U-09 lulus

## Fase 7 — Upload hasil kerja & pantauan admin

```text
Kerjakan Fase 7 dari @docs/ROADMAP.md: F-43..F-46, F-50, R-07, R-08 di @docs/PRD.md,
diagram 7 (bagian upload) dan 9 di @docs/FLOWCHART.md, modal m01 & m02.
Tambahkan kartu "Hasil kerja saya" di public/mahasiswa/tugas-detail.php (di bawah kartu
Progres saya, gaya kartu sama). Pratinjau di sisi browser memakai FileReader/URL.createObjectURL
untuk gambar; untuk PDF/DOC tampilkan ikon + nama + ukuran. Validasi ukuran di JS hanya
untuk kenyamanan; validasi asli tetap di server.
File baru: public/mahasiswa/hasil-unggah.php, hasil-hapus.php.
Lengkapi tabel Pengumpulan di admin/tugas-detail.php (status, file, waktu, label Terlambat).
```

- [x] U-10, U-11, U-12, U-13, U-14 lulus

## Fase 8 — Dashboard, pencarian/filter, profil

```text
Kerjakan Fase 8 dari @docs/ROADMAP.md: F-05, F-06, F-34, F-51, F-60..F-63 di @docs/PRD.md.
Desain: 10-dashboard-mahasiswa.png, 20-dashboard-admin.png, 11/12/21/22 (cari & filter),
17-profil-mahasiswa.png, 28-profil-admin.png, modal m03, m04, m05, m12, m14, m15.
Filter memakai parameter GET (q, mk, status, deadline, hal) dan dipertahankan di link paginasi.
Di mobile, filter tampil sebagai bottom sheet (modal m14/m15 versi mobile).
```

- [x] Statistik dashboard cocok dengan data di DB
- [x] Filter + paginasi berjalan bersamaan; foto profil tersimpan & tampil di sidebar

## Fase 9 — QA, keamanan, dan serah terima

```text
Kerjakan Fase 9 dari @docs/ROADMAP.md.
1. Tinjau seluruh kode terhadap 10 aturan keamanan di @CLAUDE.md; laporkan temuan per file
   sebelum memperbaiki.
2. Cari setiap parameter id dari GET/POST dan pastikan ada cek kepemilikan/keanggotaan.
3. Jalankan php -l ke semua file PHP.
4. Buat database/demo.sql berisi data contoh (1 admin dummy dengan hash, 6 mahasiswa,
   pendaftaran berbagai status, 10 tugas) untuk demo presentasi.
5. Perbarui README: fitur, akun demo, tangkapan layar, pembagian tugas anggota.
```

- [x] Seluruh U-01..U-18 lulus dan dicatat (input, harapan, aktual) untuk laporan — lihat `docs/HASIL-UJI.md`
- [x] Demo end-to-end: daftar → ajukan → disetujui → ceklis → unggah → admin unduh

### Setelah Fase 9 — HTML terpisah & Tailwind CDN (permintaan kelompok)

- [x] Tampilan setiap halaman berupa file **HTML murni** `app/tampilan/<nama>.html` (tanpa kode PHP);
      `public/<nama>.php` hanya berisi logika dan diakhiri `tampilkanHalaman()` (`app/tampilan.php`)
- [x] Mesin templat sendiri `app/templat.php` (sintaks `{{ }}` gaya Mustache, escape otomatis, tanpa eval)
- [x] Layout & komponen juga `.html`: `app/tampilan/layout/`, `app/tampilan/komponen/`
- [x] Tailwind dari CDN jsDelivr (versi 4.3.5 dipatok + SRI), tema `public/assets/css/tema.css`,
      cadangan offline otomatis (`tailwind-cadangan.js` + `js/vendor/`); Node & build tidak diperlukan
- [x] HTML 77 keadaan halaman dibandingkan sebelum/sesudah: isinya sama (kecuali perubahan yang disengaja)
- [x] Uji ulang: 97 pemeriksaan server + 24 pemeriksaan JavaScript lulus (online & offline)

---

## Tips memakai Claude Code untuk proyek ini

- Mulai sesi dari folder root repo agar `CLAUDE.md` terbaca.
- Rujuk file dengan `@path` (dokumen, kode, atau PNG desain) supaya Claude membacanya.
- Minta Claude menjelaskan alur kode yang dibuatnya ("jelaskan alur request → validasi → SQL → output
  di file X") — ini berguna untuk presentasi dan rubrik penjelasan individual.
- Jika Claude menyimpang dari aturan, tambahkan aturan itu ke `CLAUDE.md` (lewat `/memory` atau minta Claude "ingat aturan ini").
- Commit setelah tiap fase lulus uji; jangan menumpuk banyak fase dalam satu commit.
- Catat penggunaan AI di laporan sesuai panduan transparansi pada jobsheet.
