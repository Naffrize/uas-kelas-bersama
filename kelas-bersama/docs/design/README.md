# Desain Figma — pemetaan layar

Sumber: ekspor Figma "Kelas Bersama" (desktop 1440px, mobile 390px). Nama file sudah
dirapikan. File bertanda `ref` adalah acuan tata letak yang istilahnya harus disesuaikan;
file bertanda `draf-lama` memakai gaya lama (tombol navy) — ambil strukturnya saja,
warna & komponen ikut desain final.

| File | Dipakai untuk | Penyesuaian |
|---|---|---|
| `00-panduan-ui-tailwind.png` | Design token & aturan responsif | Kalimat "tidak ada upload tugas" diabaikan — upload hasil kerja termasuk lingkup |
| `00-komponen-tombol.png`, `00-komponen-checkbox.png` | Varian tombol (gold, putih, navy) & checkbox | — |
| `01-login` | `login.php` | 2 tombol masuk → 1 tombol "Masuk" |
| `02-register` | `register.php` | Tombol → "Daftar & pilih mata kuliah" |
| `03-gabung-ref-pilih-matkul` | `mahasiswa/matkul.php` | Input kode undangan diganti grid kartu mata kuliah + status pengajuan |
| `10-dashboard-mahasiswa` | `mahasiswa/dashboard.php` | Hapus tombol "Tambah tugas" |
| `11`, `12` | cari & filter mahasiswa | — |
| `13`–`16` | `mahasiswa/tugas-detail.php` | Hapus "Edit tugas"; tambah kartu "Hasil kerja saya" |
| `17-profil-mahasiswa` | `profil.php` | "Kelas aktif" → "Mata kuliah diikuti" |
| `18-ref-daftar-tugas-*-draf-lama` | `mahasiswa/tugas.php` | Kolom aksi → checkbox ceklis |
| `20-dashboard-admin` | `admin/dashboard.php` | "Kelas dikelola" → "Pengajuan menunggu"; tombol banner → "Tinjau pengajuan" |
| `21`, `22` | cari & filter admin | — |
| `23-detail-tugas-admin` | `admin/tugas-detail.php` | Kartu kanan → tabel "Pengumpulan" |
| `24`, `25` | `admin/tugas-form.php` | — |
| `26-kelola-matkul-ref-kelola-kelas` | `admin/matkul.php` | Kelas → Mata kuliah, Kode undangan → Kode MK |
| `27-anggota-dan-persetujuan` | `admin/anggota.php` | Tambah tab Menunggu/Disetujui + tombol Setujui/Tolak |
| `28-profil-admin` | `profil.php` | — |
| `29-ref-daftar-tugas-tabel-admin-draf-lama` | `admin/tugas.php` | "Dibuat oleh" → "Pengumpulan x/y" |
| `m01`–`m16` (folder `*-modal`) | Modal & toast | `m16` hanya acuan gaya pesan error |

Layar yang **tidak dipakai** (di luar lingkup): Rolling kelompok, Hasil pembagian,
Riwayat pembagian, Form/Edit tugas mahasiswa, Detail tugas anggota lain.
