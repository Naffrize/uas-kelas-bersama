<?php
// =====================================================================
// app/tampilan.php — memisahkan LOGIKA dan TAMPILAN (HTML)
//
//   public/<halaman>.php          → LOGIKA saja: keamanan, guard, validasi, query
//   app/tampilan/<halaman>.html   → HTML murni + class Tailwind CSS, TANPA kode PHP
//   app/tampilan/layout/*.html    → bingkai bersama: kepala, sidebar, topbar, flash, modal
//   app/tampilan/komponen/*.html  → potongan kecil: avatar, lencana, panah pilihan
//
// Di akhir file logika, data dikirim ke tampilan:
//     tampilkanHalaman('mahasiswa/dashboard', [
//         'judulHalaman' => 'Dashboard',
//         'menuAktif'    => 'dashboard',
//         'pengguna'     => $pengguna,
//         'statistik'    => $statistik,
//     ]);
// Di file .html, data itu dipanggil dengan {{statistik.total}}, {{#daftar}}...{{/daftar}},
// dan seterusnya (lihat app/templat.php). Semua teks otomatis di-escape.
// =====================================================================
require_once __DIR__ . '/templat.php';

// ===== BAGIAN 1: MERAKIT HALAMAN =====
// $data['tataLetak']: 'aplikasi' (sidebar + topbar, bawaan) atau 'tamu' (halaman masuk & daftar).
function tampilkanHalaman(string $namaTampilan, array $data): void
{
    $jenisTataLetak = $data['tataLetak'] ?? 'aplikasi';

    // Data bingkai disiapkan di sini agar file .html tetap bebas query.
    $data += $jenisTataLetak === 'aplikasi'
        ? dataTataLetak($data['pengguna'], $data['menuAktif'] ?? '')
        : ['ringkasPanel' => ringkasPanelTamu()];
    $data += dataUmum($data['judulHalaman'] ?? 'Kelas Bersama');
    $data['denganNavigasi'] = $jenisTataLetak === 'aplikasi';
    $data['flash'] = dataFlash(ambilFlash());

    // Isi halaman dibuat dulu, lalu dimasukkan ke kerangka layout/halaman.html.
    $data['isiHalaman'] = new HtmlAman(renderTemplat($namaTampilan, $data));
    echo renderTemplat('layout/halaman', $data);
}

// ===== BAGIAN 2: DATA YANG DIPAKAI SEMUA HALAMAN =====
function dataUmum(string $judulHalaman): array
{
    return [
        'judulHalaman' => $judulHalaman,
        'kelasBody' => 'min-h-screen',
        'dasar' => urlDasar(),                 // awal alamat: {{dasar}}admin/tugas.php
        'csrf' => $_SESSION['csrf'] ?? '',     // token CSRF untuk setiap form POST
        'aset' => [
            'favicon' => aset('favicon.svg'),
            'appJs' => aset('js/app.js'),
            'tailwindCadangan' => aset('js/tailwind-cadangan.js'),
            'tailwindLokal' => aset('js/vendor/tailwindcss-browser-4.3.5.js'),
        ],
        // Tema Tailwind (warna, font, komponen) dibaca dari file CSS milik kita sendiri.
        'temaTailwind' => new HtmlAman((string) file_get_contents(__DIR__ . '/../public/assets/css/tema.css')),
    ];
}

// ===== BAGIAN 3: DATA BINGKAI HALAMAN APLIKASI =====
// Menu sidebar & navigasi bawah, badge pengajuan menunggu, dan isi topbar.
function dataTataLetak(array $pengguna, string $menuAktif): array
{
    $adalahAdmin = $pengguna['peran'] === 'admin';
    $daftarMk = $adalahAdmin ? semuaMataKuliah() : mataKuliahDisetujui($pengguna['id']);
    $jumlahMenunggu = $adalahAdmin ? jumlahPengajuanMenunggu() : 0;

    // Pilihan mata kuliah di topbar mengikuti ?mk= pada alamat halaman.
    $idMkTerpilih = ambilId(ambilGet('mk'));
    $mkAktif = null;
    $opsiMk = ['' => $daftarMk ? 'Semua mata kuliah' : 'Belum ada mata kuliah'];
    foreach ($daftarMk as $mk) {
        $opsiMk[$mk['id']] = $mk['nama'];
        if ((int) $mk['id'] === $idMkTerpilih) {
            $mkAktif = $mk;
        }
    }

    // Setiap menu: [kunci, label sidebar, label navigasi bawah (HP), alamat]
    $daftarMenu = $adalahAdmin
        ? [
            ['dashboard', 'Dashboard', 'Beranda', 'admin/dashboard.php'],
            ['tugas', 'Daftar tugas', 'Tugas', 'admin/tugas.php'],
            ['matkul', 'Mata kuliah', 'Matkul', 'admin/matkul.php'],
            ['anggota', 'Anggota', 'Anggota', 'admin/anggota.php'],
        ]
        : [
            ['dashboard', 'Dashboard', 'Beranda', 'mahasiswa/dashboard.php'],
            ['tugas', 'Daftar tugas', 'Tugas', 'mahasiswa/tugas.php'],
            ['matkul', 'Pilih mata kuliah', 'Gabung', 'mahasiswa/matkul.php'],
        ];
    $menuUtama = [];
    foreach ($daftarMenu as [$kunci, $labelSidebar, $labelBawah, $alamat]) {
        $menuUtama[] = [
            'alamat' => url($alamat),
            'labelSidebar' => $labelSidebar,
            'labelBawah' => $labelBawah,
            'aktif' => $menuAktif === $kunci,
            'jumlahMenunggu' => $kunci === 'anggota' ? $jumlahMenunggu : 0,
        ];
    }

    return [
        'adalahAdmin' => $adalahAdmin,
        'menuUtama' => $menuUtama,
        'profilAktif' => $menuAktif === 'profil',
        'avatarPengguna' => dataAvatar($pengguna),
        'alamatDashboard' => url(dashboardUntuk($pengguna['peran'])),
        'aksiTopbar' => url($adalahAdmin ? 'admin/tugas.php' : 'mahasiswa/tugas.php'),
        'opsiMkTopbar' => dataOpsi($opsiMk, $mkAktif ? (int) $mkAktif['id'] : ''),
        'adaMkTopbar' => (bool) $daftarMk,
        'infoTopbar' => $mkAktif
            ? jumlahAnggotaDisetujui((int) $mkAktif['id']) . ' / ' . $mkAktif['kapasitas'] . ' anggota'
            : count($daftarMk) . ($adalahAdmin ? ' mata kuliah' : ' mata kuliah diikuti'),
        'labelPeran' => $adalahAdmin ? 'Admin' : 'Mahasiswa',
        'semester' => semesterSekarang(),
    ];
}

// ===== BAGIAN 4: PESAN FLASH =====
// Dipisah menjadi toast (hilang sendiri) dan dialog (desain m12/m13/m02).
function dataFlash(array $daftarFlash): array
{
    $toast = [];
    $dialog = [];
    foreach ($daftarFlash as $nomor => $flash) {
        $isi = [
            'nomor' => $nomor,
            'adalahGalat' => $flash['jenis'] === 'galat',
            'judul' => $flash['judul'],
            'pesan' => $flash['pesan'],
        ];
        if (empty($flash['modal'])) {
            $toast[] = $isi;
        } else {
            $dialog[] = $isi;
        }
    }
    return ['adaToast' => (bool) $toast, 'toast' => $toast, 'dialog' => $dialog];
}
