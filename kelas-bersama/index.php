<?php
// Akar proyek bukan halaman aplikasi: semua halaman yang boleh dibuka browser ada di public/.
// Contoh: http://localhost/kelas-bersama/ diarahkan ke http://localhost/kelas-bersama/public/
header('Location: public/', true, 302);
exit;
