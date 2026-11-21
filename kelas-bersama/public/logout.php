<?php
// =====================================================================
// public/logout.php — keluar HANYA lewat POST + token CSRF (aturan keamanan no. 7, F-04)
// Tautan dari situs lain tidak dapat memaksa pengguna keluar.
// =====================================================================
require __DIR__ . '/../app/keamanan.php'; // token CSRF sudah diperiksa di sini
require __DIR__ . '/../app/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect303('index.php');
}

keluarkanPengguna(); // hapus token "Ingat saya", kosongkan session, hapus cookie, hancurkan session
redirect303('login.php?keluar=1');
