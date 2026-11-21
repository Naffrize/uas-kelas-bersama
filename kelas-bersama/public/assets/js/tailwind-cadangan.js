// =====================================================================
// public/assets/js/tailwind-cadangan.js — cadangan bila CDN Tailwind gagal dimuat
//
// Tailwind dimuat dari CDN jsDelivr (lihat app/tampilan/layout/kepala.html).
// Bila gagal (tidak ada internet, CDN diblokir, atau isi file tidak cocok dengan
// hash integrity), skrip ini memuat salinan lokal versi yang sama dari
// public/assets/js/vendor/, sehingga tampilan tetap rapi saat presentasi offline.
// File ini harus dimuat SEBELUM tag skrip CDN agar kegagalannya tertangkap.
// =====================================================================
(function () {
    'use strict';
    var sudahDimuat = false;

    // Galat memuat <script> tidak "naik" ke window, jadi ditangkap pada fase capture (true).
    window.addEventListener('error', function (event) {
        var elemen = event.target;
        if (sudahDimuat || !elemen || elemen.tagName !== 'SCRIPT' || !elemen.hasAttribute('data-tailwind-cdn')) {
            return;
        }
        sudahDimuat = true;
        var cadangan = document.createElement('script');
        cadangan.src = elemen.getAttribute('data-cadangan');
        document.head.appendChild(cadangan);
    }, true);
})();
