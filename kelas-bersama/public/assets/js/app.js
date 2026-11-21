/* =====================================================================
   Kelas Bersama — JavaScript vanilla seperlunya (tanpa jQuery/React)

   1. Modal <dialog>: buka/tutup, Esc (bawaan browser), klik latar gelap
   2. Konfirmasi sebelum form dikirim (logout, hapus, keluarkan, tolak)
   3. Kirim otomatis (ceklis tugas, pilihan mata kuliah di topbar)
   4. Toast pesan yang hilang sendiri
   5. Pratinjau file sebelum diunggah (m01/m03) + galat di sisi browser (m02/m05)
      Validasi di sini hanya untuk kenyamanan; validasi asli tetap di server
      (app/upload.php).
   ===================================================================== */
(function () {
  'use strict';

  var BATAS_UNGGAH = 5 * 1024 * 1024; // 5 MB
  var EKSTENSI = {
    dokumen: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'],
    gambar: ['jpg', 'jpeg', 'png', 'webp']
  };

  // ===== Fungsi bantu =====
  function bukaModal(modal) {
    if (modal && typeof modal.showModal === 'function' && !modal.open) {
      modal.showModal();
    }
  }

  function tutupModal(modal) {
    if (modal && modal.open) {
      modal.close();
    }
  }

  // Mengisi teks elemen [data-isi="kunci"]; teks kosong → elemen disembunyikan.
  function isi(wadah, kunci, teks) {
    var el = wadah.querySelector('[data-isi="' + kunci + '"]');
    if (el) {
      el.textContent = teks;
      el.hidden = !teks;
    }
  }

  function formatUkuran(byte) {
    if (byte < 1024) {
      return byte + ' B';
    }
    if (byte < 1024 * 1024) {
      return Math.round(byte / 1024).toLocaleString('id-ID') + ' KB';
    }
    return (byte / 1024 / 1024).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' MB';
  }

  // ===== 1. MODAL =====
  document.addEventListener('click', function (e) {
    var pembuka = e.target.closest('[data-buka-modal]');
    if (pembuka) {
      e.preventDefault();
      bukaModal(document.getElementById(pembuka.getAttribute('data-buka-modal')));
      return;
    }
    var penutup = e.target.closest('[data-tutup-modal]');
    if (penutup) {
      e.preventDefault();
      tutupModal(penutup.closest('dialog'));
      return;
    }
    // Klik di latar gelap: target klik adalah elemen <dialog> itu sendiri
    // (isi modal dibungkus .modal-isi, jadi klik di dalam kotak tidak menutup).
    if (e.target.tagName === 'DIALOG') {
      tutupModal(e.target);
    }
  });

  // Modal yang harus langsung tampil (pesan flash, form yang gagal divalidasi).
  document.querySelectorAll('dialog[data-buka-saat-muat]').forEach(bukaModal);

  // ===== 2. KONFIRMASI SEBELUM KIRIM =====
  var modalKonfirmasi = document.getElementById('modal-konfirmasi');
  var formMenunggu = null;

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!modalKonfirmasi || !form.hasAttribute('data-konfirmasi')) {
      return;
    }
    e.preventDefault();
    formMenunggu = form;
    isi(modalKonfirmasi, 'judul', form.getAttribute('data-judul') || 'Lanjutkan tindakan ini?');
    isi(modalKonfirmasi, 'pesan', form.getAttribute('data-pesan') || '');
    isi(modalKonfirmasi, 'tombol', form.getAttribute('data-tombol') || 'Ya, lanjutkan');
    bukaModal(modalKonfirmasi);
  });

  if (modalKonfirmasi) {
    modalKonfirmasi.querySelector('[data-setuju]').addEventListener('click', function () {
      var form = formMenunggu;
      formMenunggu = null;
      tutupModal(modalKonfirmasi);
      if (form) {
        form.submit(); // submit() tidak memicu event submit lagi → tidak berulang
      }
    });
  }

  // ===== 3. KIRIM OTOMATIS =====
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el.matches && el.matches('[data-kirim-otomatis]') && el.form) {
      if (typeof el.form.requestSubmit === 'function') {
        el.form.requestSubmit();
      } else {
        el.form.submit();
      }
    }
  });

  // ===== 4. TOAST =====
  function hilangkanToast(toast) {
    toast.classList.add('opacity-0');
    setTimeout(function () {
      toast.remove();
    }, 300);
  }
  document.querySelectorAll('[data-toast]').forEach(function (toast) {
    setTimeout(function () {
      hilangkanToast(toast);
    }, 6000);
  });
  document.addEventListener('click', function (e) {
    var tombol = e.target.closest('[data-tutup-toast]');
    if (tombol) {
      hilangkanToast(tombol.closest('[data-toast]'));
    }
  });

  // ===== 5. PRATINJAU FILE =====
  // <input type="file" data-pratinjau="id-modal" data-galat="id-modal-galat" data-jenis="dokumen|gambar"
  //        data-maks="5242880" [data-kirim-saat-pakai] [data-tampil-nama="id-label"]>
  function tampilkanNamaTerpilih(input) {
    var target = document.getElementById(input.getAttribute('data-tampil-nama') || '');
    if (!target) {
      return;
    }
    var file = input.files && input.files[0];
    target.textContent = file ? 'Dipilih: ' + file.name + ' · ' + formatUkuran(file.size) : (target.getAttribute('data-kosong') || '');
  }

  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input.matches || !input.matches('input[type="file"][data-pratinjau]')) {
      return;
    }
    var file = input.files && input.files[0];
    if (!file) {
      tampilkanNamaTerpilih(input);
      return;
    }

    var jenis = input.getAttribute('data-jenis') === 'gambar' ? 'gambar' : 'dokumen';
    var maks = parseInt(input.getAttribute('data-maks'), 10) || BATAS_UNGGAH;
    var ekstensi = (file.name.split('.').pop() || '').toLowerCase();
    var pesan = '';
    if (EKSTENSI[jenis].indexOf(ekstensi) === -1) {
      pesan = 'Jenis file ' + file.name + ' tidak didukung. Gunakan ' +
        (jenis === 'gambar' ? 'JPG, PNG, atau WEBP.' : 'PDF, DOC, DOCX, JPG, PNG, atau WEBP.');
    } else if (file.size > maks) {
      pesan = 'Ukuran ' + file.name + ' adalah ' + formatUkuran(file.size) + '. ' +
        (jenis === 'gambar' ? 'Gunakan JPG, PNG, atau WEBP maksimal 5 MB.' : 'Batas maksimal 5 MB per file. Pilih file yang lebih kecil.');
    }

    if (pesan) {
      input.value = '';
      tampilkanNamaTerpilih(input);
      var modalGalat = document.getElementById(input.getAttribute('data-galat') || '');
      if (modalGalat) {
        isi(modalGalat, 'pesan', pesan);
        bukaModal(modalGalat);
      } else {
        window.alert(pesan);
      }
      return;
    }

    var modal = document.getElementById(input.getAttribute('data-pratinjau'));
    if (!modal) {
      tampilkanNamaTerpilih(input);
      return;
    }
    modal._input = input;
    modal._dipakai = false;
    var labelJenis = ekstensi === 'jpeg' ? 'JPG' : ekstensi.toUpperCase();
    isi(modal, 'nama', file.name);
    isi(modal, 'info', formatUkuran(file.size) + ' · ' + labelJenis + ' · Ukuran valid, di bawah 5 MB');
    isi(modal, 'jenis', labelJenis);

    var gambar = modal.querySelector('[data-isi-gambar]');
    var ikonDokumen = modal.querySelector('[data-isi-ikon]');
    if (gambar) {
      if (gambar.src && gambar.src.indexOf('blob:') === 0) {
        URL.revokeObjectURL(gambar.src);
      }
      if (/^(jpe?g|png|webp)$/.test(ekstensi)) {
        gambar.src = URL.createObjectURL(file);
        gambar.hidden = false;
        if (ikonDokumen) {
          ikonDokumen.hidden = true;
        }
      } else {
        gambar.removeAttribute('src');
        gambar.hidden = true;
        if (ikonDokumen) {
          ikonDokumen.hidden = false;
        }
      }
    }
    bukaModal(modal);
  });

  // Tombol di modal pratinjau: "Gunakan file" dan "Pilih file lain".
  document.addEventListener('click', function (e) {
    var tombol = e.target.closest('[data-pakai-file], [data-pilih-ulang]');
    if (!tombol) {
      return;
    }
    var modal = tombol.closest('dialog');
    var input = modal && modal._input;
    if (!input) {
      return;
    }
    e.preventDefault();
    if (tombol.hasAttribute('data-pakai-file')) {
      modal._dipakai = true;
      tutupModal(modal);
      if (input.hasAttribute('data-kirim-saat-pakai')) {
        input.form.submit();
      } else {
        tampilkanNamaTerpilih(input);
      }
    } else {
      input.value = '';
      tampilkanNamaTerpilih(input);
      tutupModal(modal);
      input.click();
    }
  });

  // Modal pratinjau ditutup tanpa "Gunakan file" (Esc / klik latar) → pilihan dibatalkan.
  document.querySelectorAll('dialog[data-modal-pratinjau]').forEach(function (modal) {
    modal.addEventListener('close', function () {
      if (!modal._dipakai && modal._input) {
        modal._input.value = '';
        tampilkanNamaTerpilih(modal._input);
      }
    });
  });
})();
