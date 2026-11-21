# Flowchart & Diagram — Kelas Bersama

Semua diagram memakai Mermaid sehingga tampil langsung di GitHub dan di VS Code
(ekstensi *Markdown Preview Mermaid Support*). Versi gambar ada di `docs/flowchart-png/`.

Daftar isi:
1. Peta situs (sitemap)
2. Alur umum sistem
3. Registrasi & login
4. Pengajuan mata kuliah & persetujuan admin
5. Admin mengelola mata kuliah (CRUD)
6. Admin mengelola tugas (CRUD + lampiran)
7. Mahasiswa mengerjakan tugas (ceklis + upload)
8. Validasi upload di server
9. Otorisasi unduh file
10. State diagram status
11. ERD

---

## 1. Peta situs

```mermaid
flowchart LR
    root["index.php<br/>arahkan sesuai sesi"] --> login["login.php"]
    root --> reg["register.php"]
    login --> A["admin/dashboard.php"]
    login --> M["mahasiswa/dashboard.php"]
    reg --> MK["mahasiswa/matkul.php<br/>Pilih mata kuliah"]

    subgraph ADMIN["Area Admin"]
        A --> AT["admin/tugas.php<br/>Daftar tugas"]
        AT --> AF["admin/tugas-form.php<br/>Tambah / Edit"]
        AT --> AD["admin/tugas-detail.php<br/>Detail + Pengumpulan"]
        A --> AM["admin/matkul.php<br/>Mata kuliah"]
        A --> AA["admin/anggota.php<br/>Menunggu / Disetujui"]
    end

    subgraph MHS["Area Mahasiswa"]
        M --> MT["mahasiswa/tugas.php<br/>To-do list"]
        MT --> MD["mahasiswa/tugas-detail.php<br/>Progres + Hasil kerja"]
        M --> MK
    end

    A --> P["profil.php"]
    M --> P
    AD --> U["unduh.php"]
    MD --> U
```

## 2. Alur umum sistem

```mermaid
flowchart TD
    S([Mulai]) --> O[Buka aplikasi]
    O --> L{Sudah login?}
    L -- Tidak --> PunyaAkun{Punya akun?}
    PunyaAkun -- Tidak --> R[Registrasi mahasiswa]
    R --> Login
    PunyaAkun -- Ya --> Login[Login]
    Login --> L
    L -- Ya --> Peran{Peran?}
    Peran -- admin --> DA[Dashboard admin]
    Peran -- mahasiswa --> Ada{Punya mata kuliah<br/>disetujui?}
    Ada -- Tidak --> PMK[Pilih mata kuliah<br/>dan ajukan gabung]
    PMK --> Tunggu[/Menunggu persetujuan admin/]
    Tunggu --> Ada
    Ada -- Ya --> DM[Dashboard mahasiswa]
    DA --> KelolaMK[Kelola mata kuliah]
    DA --> Setujui[Setujui / tolak pengajuan]
    DA --> KelolaT[Kelola tugas]
    DA --> Pantau[Pantau progres & unduh hasil]
    DM --> Lihat[Lihat to-do list]
    Lihat --> Ceklis[Ceklis progres]
    Lihat --> Unggah[Unggah hasil kerja]
    DA --> Out[Logout]
    DM --> Out
    Out --> E([Selesai])
```

## 3. Registrasi & login

```mermaid
flowchart TD
    subgraph REG["Registrasi (register.php)"]
        R1[Isi nama, email, kata sandi, konfirmasi] --> R2{Token CSRF valid?}
        R2 -- Tidak --> R0[HTTP 400]
        R2 -- Ya --> R3{Nama 1-100, email valid,<br/>sandi 8-72, konfirmasi sama?}
        R3 -- Tidak --> R4[Tampilkan pesan validasi] --> R1
        R3 -- Ya --> R5{Email sudah terdaftar?}
        R5 -- Ya --> R4
        R5 -- Tidak --> R6["INSERT pengguna<br/>password_hash, peran = mahasiswa"]
        R6 --> R7["session_regenerate_id(true)<br/>simpan sesi"]
        R7 --> R8[Redirect ke Pilih mata kuliah]
    end

    subgraph LOG["Login (login.php)"]
        L1[Isi email & kata sandi] --> L2{Token CSRF valid?}
        L2 -- Tidak --> L0[HTTP 400]
        L2 -- Ya --> L3[SELECT pengguna WHERE email = ?]
        L3 --> L4{"Akun ada dan<br/>password_verify benar?"}
        L4 -- Tidak --> L5["Pesan: Email atau kata sandi salah."] --> L1
        L4 -- Ya --> L6["session_regenerate_id(true)<br/>SESSION pengguna = id, nama, peran"]
        L6 --> L7{peran}
        L7 -- admin --> L8[admin/dashboard.php]
        L7 -- mahasiswa --> L9[mahasiswa/dashboard.php]
    end
```

## 4. Pengajuan mata kuliah & persetujuan admin

```mermaid
flowchart TD
    subgraph MAHASISWA
        M1[Buka Pilih mata kuliah] --> M2[Lihat kartu MK + status saya]
        M2 --> M3{Status saya?}
        M3 -- Belum gabung / ditolak / dikeluarkan --> M4[Klik Ajukan gabung]
        M3 -- Menunggu --> M5[Klik Batalkan pengajuan]
        M3 -- Disetujui --> M6[Buka tugas MK]
        M5 --> M5a[DELETE pendaftaran] --> M2
    end

    M4 --> S1{Kuota masih ada?}
    S1 -- Tidak --> S2[Pesan: Mata kuliah penuh] --> M2
    S1 -- Ya --> S3["INSERT atau UPDATE pendaftaran<br/>status = menunggu"]
    S3 --> A1

    subgraph ADMIN
        A1[Tab Menunggu di halaman Anggota] --> A2{Keputusan}
        A2 -- Setujui --> A3{Kuota masih ada?}
        A3 -- Tidak --> A4[Pesan: Kapasitas penuh] --> A1
        A3 -- Ya --> A5["UPDATE status = disetujui<br/>diputuskan, diputuskan_oleh"]
        A2 -- Tolak --> A6[UPDATE status = ditolak]
        A7[Tab Disetujui] --> A8[Klik Keluarkan] --> A9{Konfirmasi modal?}
        A9 -- Ya --> A10[UPDATE status = dikeluarkan]
        A9 -- Batal --> A7
    end

    A5 --> M6
    A6 --> M2
    A10 --> M2
```

## 5. Admin mengelola mata kuliah (CRUD)

```mermaid
flowchart TD
    K0[Halaman Mata kuliah] --> K1{Aksi}
    K1 -- Buat --> K2[Modal Buat mata kuliah]
    K1 -- Edit --> K3[Modal Edit mata kuliah]
    K1 -- Hapus --> K4[Modal Hapus mata kuliah ini?]
    K1 -- Lihat anggota --> K5[admin/anggota.php?mk=id]

    K2 --> V{"Kode unik & format benar,<br/>nama 1-100, kapasitas 1-500?"}
    K3 --> V2{"Valid dan kapasitas >=<br/>jumlah anggota disetujui?"}
    V -- Tidak --> Err[Pesan validasi] --> K0
    V2 -- Tidak --> Err
    V -- Ya --> Ins[INSERT mata_kuliah] --> Ok[Flash: Mata kuliah disimpan] --> K0
    V2 -- Ya --> Upd[UPDATE mata_kuliah] --> Ok

    K4 --> C{Konfirmasi?}
    C -- Batal --> K0
    C -- Ya --> T1[Kumpulkan nama file lampiran & hasil dari semua tugas MK]
    T1 --> T2["BEGIN; DELETE mata_kuliah<br/>(CASCADE ke tugas, pendaftaran, tugas_mahasiswa); COMMIT"]
    T2 --> T3{Berhasil?}
    T3 -- Ya --> T4[Hapus file fisik] --> Ok
    T3 -- Tidak --> T5[ROLLBACK, file tidak disentuh] --> Err
```

## 6. Admin mengelola tugas (CRUD + lampiran)

```mermaid
flowchart TD
    D0[Daftar tugas admin] --> D1{Aksi}
    D1 -- Tambah --> F0["tugas-form.php"]
    D1 -- Edit --> F1["tugas-form.php?id=..."]
    D1 -- Detail --> DT[tugas-detail.php: info + tabel Pengumpulan]
    D1 -- Hapus --> H0[Modal Hapus tugas ini?]

    F0 --> F2[Isi judul, mata kuliah, deskripsi,<br/>persiapan, deadline, lampiran opsional]
    F1 --> F2
    F2 --> F3{Field wajib valid &<br/>mata kuliah ada?}
    F3 -- Tidak --> F4[Tampilkan error, isian tetap terisi] --> F2
    F3 -- Ya --> F5{Ada file lampiran baru?}
    F5 -- Ya --> UP[[Validasi upload - lihat diagram 8]]
    UP -- Gagal --> F4
    UP -- Lolos --> F6
    F5 -- Tidak --> F6{Tambah atau Edit?}
    F6 -- Tambah --> F7[INSERT tugas] --> F9
    F6 -- Edit --> F8[UPDATE tugas] --> F8a{Lampiran diganti / dihapus?}
    F8a -- Ya --> F8b[Hapus file lampiran lama] --> F9
    F8a -- Tidak --> F9[Flash: Tugas disimpan, redirect ke detail]

    H0 --> H1{Konfirmasi?}
    H1 -- Ya --> H2[Kumpulkan file lampiran + semua hasil] --> H3[DELETE tugas, CASCADE] --> H4[Hapus file fisik] --> D0
    H1 -- Batal --> D0
```

## 7. Mahasiswa mengerjakan tugas (ceklis + upload)

```mermaid
flowchart TD
    T0[Daftar tugas / Dashboard] --> T1{Tugas dari MK disetujui?}
    T1 -- Tidak --> T404[404 Tugas tidak ditemukan]
    T1 -- Ya --> T2{Aksi}

    T2 -- Centang checkbox --> C1["UPSERT tugas_mahasiswa<br/>status = selesai / belum_mulai"] --> T0
    T2 -- Buka detail --> D1[Detail tugas: deskripsi, persiapan, lampiran]

    D1 --> D2{Aksi di detail}
    D2 -- Ubah status --> C2["UPSERT status: belum_mulai / dikerjakan / selesai"] --> D1
    D2 -- Unduh lampiran --> UNDUH[[unduh.php - lihat diagram 9]]
    D2 -- Pilih file hasil --> P1[Modal pratinjau: nama, ukuran, jenis, gambar]
    P1 --> P2{Gunakan file?}
    P2 -- Pilih file lain --> D2
    P2 -- Ya --> V[[Validasi upload - diagram 8]]
    V -- Gagal --> E1[Modal: File belum dapat diunggah] --> D2
    V -- Lolos --> S1["UPSERT hasil_file, hasil_nama, diunggah = NOW()"]
    S1 --> S2{Status sebelumnya belum_mulai?}
    S2 -- Ya --> S3[Status jadi dikerjakan] --> S4
    S2 -- Tidak --> S4{Ada file hasil lama?}
    S4 -- Ya --> S5[Hapus file lama] --> S6
    S4 -- Tidak --> S6[Flash: Hasil kerja tersimpan] --> D1
    D2 -- Hapus hasil --> H1[UPDATE hasil_* = NULL] --> H2[Hapus file fisik] --> D1
```

## 8. Validasi upload di server (`app/upload.php`)

```mermaid
flowchart TD
    U0["simpanUnggahan(field, jenis)"] --> U1{"$_FILES[field] ada?"}
    U1 -- Tidak / UPLOAD_ERR_NO_FILE --> Null[Kembalikan null: tidak ada file]
    U1 -- Ya --> U2{error = UPLOAD_ERR_OK?}
    U2 -- Tidak --> X1["Gagal: pesan sesuai kode error<br/>(INI_SIZE, PARTIAL, dll.)"]
    U2 -- Ya --> U3{"size <= 5 MB?"}
    U3 -- Tidak --> X2[Gagal: ukuran melebihi 5 MB]
    U3 -- Ya --> U4["MIME = finfo(tmp_name)"]
    U4 --> U5{MIME ada di whitelist jenis ini?}
    U5 -- Tidak --> U5a{"application/zip + .docx<br/>+ berisi word/document.xml?"}
    U5a -- Tidak --> X3[Gagal: jenis file tidak didukung]
    U5a -- Ya --> U6
    U5 -- Ya --> U6{Gambar?}
    U6 -- Ya --> U7{getimagesize valid?}
    U7 -- Tidak --> X3
    U7 -- Ya --> U8
    U6 -- Tidak --> U8["nama = bin2hex(random_bytes(16)) . ekstensi dari MIME"]
    U8 --> U9{move_uploaded_file ke folder jenis?}
    U9 -- Tidak --> X4[Gagal: tidak dapat menyimpan]
    U9 -- Ya --> OK["Kembalikan: file, nama asli, mime, ukuran"]
```

## 9. Otorisasi unduh file (`public/unduh.php`)

```mermaid
flowchart TD
    A0["GET unduh.php?jenis=lampiran|hasil&tugas=ID&mhs=ID"] --> A1{Sudah login?}
    A1 -- Tidak --> R1[Redirect login]
    A1 -- Ya --> A2[Ambil data tugas + file dari DB]
    A2 --> A3{Data & file ada?}
    A3 -- Tidak --> R404[404]
    A3 -- Ya --> A4{Peran admin?}
    A4 -- Ya --> KIRIM
    A4 -- Tidak --> A5{jenis}
    A5 -- lampiran --> A6{Terdaftar disetujui<br/>di MK tugas ini?}
    A5 -- hasil --> A7{mhs = id pengguna sesi?}
    A6 -- Tidak --> R403[403]
    A7 -- Tidak --> R403
    A6 -- Ya --> KIRIM
    A7 -- Ya --> KIRIM["Kirim header Content-Type dari DB,<br/>nosniff, Content-Disposition nama asli,<br/>readfile()"]
```

## 10. State diagram

Status pendaftaran mata kuliah:

```mermaid
stateDiagram-v2
    [*] --> menunggu: mahasiswa ajukan
    menunggu --> disetujui: admin setujui (kuota ada)
    menunggu --> ditolak: admin tolak
    menunggu --> [*]: mahasiswa batalkan (baris dihapus)
    disetujui --> dikeluarkan: admin keluarkan
    ditolak --> menunggu: ajukan ulang
    dikeluarkan --> menunggu: ajukan ulang
```

Status progres tugas (per mahasiswa):

```mermaid
stateDiagram-v2
    [*] --> belum_mulai: default (belum ada baris)
    belum_mulai --> dikerjakan: pilih Sedang dikerjakan / unggah hasil
    belum_mulai --> selesai: centang / Tandai selesai
    dikerjakan --> selesai: Tandai selesai
    dikerjakan --> belum_mulai: Belum mulai
    selesai --> dikerjakan: Sedang dikerjakan
    selesai --> belum_mulai: hapus centang
```

## 11. ERD

```mermaid
erDiagram
    PENGGUNA ||--o{ PENDAFTARAN : mengajukan
    MATA_KULIAH ||--o{ PENDAFTARAN : menerima
    MATA_KULIAH ||--o{ TUGAS : memiliki
    PENGGUNA ||--o{ MATA_KULIAH : "membuat (admin)"
    PENGGUNA ||--o{ TUGAS : "membuat (admin)"
    TUGAS ||--o{ TUGAS_MAHASISWA : dikerjakan
    PENGGUNA ||--o{ TUGAS_MAHASISWA : mengerjakan
    PENGGUNA ||--o{ TOKEN_INGAT : "diingat (F-07)"

    PENGGUNA {
        int id PK
        varchar nama
        varchar email UK
        varchar password_hash
        enum peran "admin | mahasiswa"
        varchar foto
        datetime dibuat
    }
    MATA_KULIAH {
        int id PK
        varchar kode UK
        varchar nama
        text deskripsi
        smallint kapasitas
        int dibuat_oleh FK
        datetime dibuat
    }
    PENDAFTARAN {
        int id PK
        int pengguna_id FK
        int mata_kuliah_id FK
        enum status "menunggu | disetujui | ditolak | dikeluarkan"
        datetime diajukan
        datetime diputuskan
        int diputuskan_oleh FK
    }
    TUGAS {
        int id PK
        int mata_kuliah_id FK
        varchar judul
        text deskripsi
        text persiapan
        datetime deadline
        varchar lampiran_file
        varchar lampiran_nama
        varchar lampiran_mime
        int lampiran_ukuran
        int dibuat_oleh FK
    }
    TUGAS_MAHASISWA {
        int tugas_id PK
        int pengguna_id PK
        enum status "belum_mulai | dikerjakan | selesai"
        varchar hasil_file
        varchar hasil_nama
        varchar hasil_mime
        int hasil_ukuran
        datetime diunggah
    }
    TOKEN_INGAT {
        int id PK
        int pengguna_id FK
        char pemilih UK
        char validator_hash
        datetime kedaluwarsa
    }
```

Catatan implementasi (sesuai `database/schema.sql`):
- `TOKEN_INGAT` menyimpan token "Ingat saya selama 30 hari" (F-07). Cookie berisi `pemilih:validator`;
  database hanya menyimpan hash SHA-256 validator, dan token dirotasi setiap kali dipakai.
- `MATA_KULIAH.dibuat_oleh` boleh `NULL` karena 4 mata kuliah contoh di `seed.sql` dibuat tanpa akun.
- Gambar `docs/flowchart-png/11-erd.png` dibuat sebelum tabel `TOKEN_INGAT` ditambahkan; diagram Mermaid di atas adalah versi terbaru.
