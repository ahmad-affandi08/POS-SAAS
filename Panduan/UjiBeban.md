# Uji Beban API POS

Audit PAY-P1-09 meminta kapasitas diukur, bukan diperkirakan. Skrip di repo ini menyiapkan data sungguhan lewat pembantu
test yang sama dengan suite Fitur lalu menekan API POS dengan [k6](https://k6.io).

## Isi

| Berkas | Fungsi |
|---|---|
| `Aplikasi/Web/tests/Beban/SiapkanDataBebanTes.php` | Membuat N tenant (tiap tenant satu perangkat kasir aktif, shift terbuka, tiga produk berstok 1.000.000) dan **men-commit** datanya, lalu menulis `storage/beban/data.json` (token perangkat, kode outlet/perangkat, uuid). Bukan bagian suite biasa. |
| `Spesifikasi/UjiBeban/Beban.k6.js` | Empat skenario: `checkout` (penjualan tunai per detik), `outbox` (20 penjualan offline sekaligus), `polling` (konfigurasi, katalog delta, data awal), `webhook` (notifikasi billing palsu harus 401). |
| `Aplikasi/Web/tests/Beban/PeriksaInvarianBebanTes.php` | Sesudah beban: `SaldoStok = Σ MutasiStok`, rantai mutasi, jurnal seimbang, akun persediaan, per tenant. |
| `.github/workflows/UjiBeban.yml` | Menjalankan semuanya di runner (MySQL 8, server PHP 4 pekerja, worker antrean database). Dipicu manual: **Actions → Uji Beban → Run workflow**. |

## Cara membaca hasil

- Ambang bawaan (bisa diubah di `Beban.k6.js`): p95 checkout < 1,5 dtk, p95 outbox < 5 dtk, p95 polling < 1 dtk,
  p95 webhook < 0,5 dtk, galat checkout < 1%, **tidak ada penjualan ditolak**.
- `permintaan_dibatasi_429` = permintaan yang ditahan pembatas laju per perangkat (`pos-120` per menit untuk sinkron).
  Itu perilaku yang diharapkan, bukan galat; angkanya menunjukkan apakah laju uji melewati batas perangkat.
- Deadlock: ringkasan mencetak `lock_deadlocks` & `lock_timeouts` InnoDB sejak penghitung dinyalakan, plus dump deadlock
  terakhir. Deadlock yang diserap percobaan ulang tidak menjadi HTTP 500, tetapi tetap membuang waktu; angkanya dipakai
  untuk membandingkan perubahan kode (acuan 1 Okt 2026: 2 tenant, 25 penjualan/detik, 90 detik = ±40–75 setelah v3.27 (tiga run: 42, 75, 97 untuk
  varian lain), 220 sebelumnya; variasi antar-run besar, jadi ulangi 2–3 kali sebelum menyimpulkan). Untuk membandingkan dua versi sekaligus, dorong keduanya ke branch sementara dan jalankan
  workflow di masing-masing (**Run workflow → Use workflow from**), lalu hapus branchnya.
- Langkah terakhir menjalankan `tests/Konkurensi/KonkurensiTes.php` (dua proses PHP mengirim item yang sama). Suite ini
  **tidak** ada di `phpunit.xml`, jadi panggil per berkas dan jalankan terakhir: ia menyisakan data uji di basis data.
- Ringkasan Actions menampilkan sisa antrean (`jobs`), pekerjaan gagal, jumlah penjualan tersimpan, dan jumlah galat log.
- Hasil di runner adalah **pembanding antar versi**, bukan kapasitas hosting bersama: CPU, I/O, dan jumlah pekerja PHP
  berbeda. Untuk angka hosting, jalankan skrip yang sama terhadap staging: siapkan data di basis data staging
  (`pest tests/Beban/SiapkanDataBebanTes.php` dengan env basis data staging, **bukan produksi**), lalu
  `k6 run -e BASE_URL=https://staging.… -e BEBAN_DATA=storage/beban/data.json Spesifikasi/UjiBeban/Beban.k6.js`.

## Belum tercakup

Laporan besar dan impor (butuh sesi back-office), antrean webhook QRIS tenant bertanda tangan sah, dan polling KDS/Owner.
Tambahkan sebagai skenario baru di `Beban.k6.js` bila hasil pertama menunjukkan titik lemah di sana.
