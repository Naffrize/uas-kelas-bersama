<?php
// =====================================================================
// public/index.php — pintu masuk aplikasi
// Belum login → login.php; admin → admin/dashboard.php; mahasiswa → mahasiswa/dashboard.php.
// (Pemeriksaan instalasi tetap tersedia di cek-koneksi.php, khusus localhost.)
// =====================================================================
require __DIR__ . '/../app/keamanan.php';
require __DIR__ . '/../app/auth.php';

$pengguna = penggunaSaatIni() ?? masukDariCookieIngat();
redirect303($pengguna === null ? 'login.php' : dashboardUntuk($pengguna['peran']));
