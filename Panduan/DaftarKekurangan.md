# Daftar Kekurangan & Pekerjaan Tertunda PAYOU

Status per 26 September 2026 (PRD v2.10). Dokumen ini mencatat apa yang **belum ada**, **belum diuji di produksi**, atau **menunggu keputusan pemilik produk**, supaya sistem bisa dibuka ke publik lebih dulu dengan risiko yang diketahui. Perbarui setiap kali satu butir selesai.

## A. Wajib diperhatikan sebelum/tepat saat dibuka ke publik

| # | Kekurangan | Dampak | Yang perlu dilakukan |
|---|---|---|---|
| A1 | Baru dipasang di hosting produksi (26/09/2026): situs & halaman masuk sudah terbuka, cron aktif | Alur lengkap (daftar tenant, kasir sinkron, email) belum diuji di produksi | Uji alur ujung ke ujung setelah email, CAPTCHA, legal, dan harga paket diatur |
| A2 | Hosting memakai **MariaDB 11.8** (migrasi & seed berhasil), sedangkan PRD mensyaratkan MySQL 8 (§13.7.5) dan semua test berjalan di MySQL 8 | Migrasi atau kueri tertentu bisa gagal atau berperilaku beda | Cek `mysql --version`; bila MariaDB dan ada galat, laporkan untuk disesuaikan, atau pindah ke VPS/MySQL 8 |
| A3 | Belum ada **uji beban** (§23) | Kapasitas hosting bersama belum terukur | Batasi jumlah tenant awal (beta), pantau dasbor Operasional di konsol |
| A4 | Harga paket bawaan masih **Draf** | Halaman `payou.id/harga` hanya menampilkan paket harga negosiasi (Enterprise) | Ajukan & tinjau harga paket di konsol (butuh dua orang: pengaju ≠ peninjau) |
| A5 | Dokumen legal (S&K, Kebijakan Privasi) belum terbit di database produksi | Pendaftaran tenant tertutup | Terbitkan dari konsol → Legal. Isi hukumnya perlu ditinjau ahli hukum (UU PDP, UU ITE) |
| A6 | Penyedia email & CAPTCHA belum diatur | Verifikasi email, reset kata sandi, dan pendaftaran belum berfungsi penuh | Konsol → Integrasi |
| A7 | Situs pemasaran belum punya **persetujuan cookie** & kebijakan cookie | Wajib sebelum memasang analitik/iklan (UU PDP) | Jangan pasang Google Analytics/Meta Pixel manual dulu. Menunggu Situs pemasaran bagian B |
| A8 | Kontak, WhatsApp, media sosial, logo, dan tautan unduh situs masih kosong | Tombol WhatsApp & blok Kontak belum tampil | Konsol → Situs pemasaran → Pengaturan |
| A12 | Server tanpa Node: setiap pembaruan tampilan perlu build di tempat lain lalu unggah `public/build` | Pembaruan manual dan rawan beda versi | Buat alur build otomatis (GitHub Actions) yang menghasilkan paket siap unggah |
| A9 | Aplikasi Kasir & Pemilik belum dirilis ke Play Store/App Store/Windows | Pengguna belum bisa mengunduh aplikasi kasir | Build dengan `ALAMAT_SERVER=https://dashboard.payou.id/`, tanda tangan rilis, unggah ke toko aplikasi (akun developer) |
| A10 | Pencadangan otomatis database & file belum diatur di hosting | Risiko kehilangan data | Aktifkan backup harian hPanel + catat di konsol (`pengelola:catat-backup`) |
| A11 | Satu test (`EksporVarianTes` round-trip) kadang gagal saat seluruh suite dijalankan, lolos bila dijalankan sendiri | Indikasi test tidak stabil (urutan/data) | Selidiki penyebabnya, jangan dilewati |

## A2. Perbaikan teknis yang ditunda (diminta pemilik produk: dikerjakan setelah fitur C)

| # | Pekerjaan | Rincian |
|---|---|---|
| T1 | Kecilkan ukuran tampilan | Situs payou.id ±169 KB gzip, dashboard ±333 KB saat pertama dibuka. Rencana: CSS situs dipisah dari CSS dashboard, pustaka JS bersama dipecah (situs tidak memuat komponen tabel/kalender), grafik Beranda dashboard dimuat belakangan, logo PNG 60 KB → WebP/SVG, kompresi & cache panjang di `.htaccess`. Target ±80–100 KB (situs), ±200 KB (dashboard). |
| T2 | Tampilan Integrasi | Enkripsi tertulis "Ssl" → "SSL"; kata sandi SMTP/rahasia ditampilkan 4 karakter terakhir → sembunyikan penuh untuk kata sandi (kunci API boleh tetap 4 terakhir). |
| T3 | Build otomatis | Server hosting tanpa Node: buat GitHub Actions yang membangun `public/build` + `vendor` dan menghasilkan paket ZIP siap unggah per commit `main`, agar pembaruan tidak bergantung build manual. |
| T4 | Situs pemasaran bagian B | Persetujuan cookie + kebijakan cookie (UU PDP); formulir "Minta demo/Kontak" dengan kotak masuk prospek di konsol (data prospek dilindungi, tidak dicatat di log); ID Google Analytics & Meta Pixel diatur dari konsol dan hanya aktif setelah persetujuan; artikel/blog untuk SEO. |

## B. Fitur yang belum dibangun (urutan kerja berikutnya)

1. **Situs pemasaran bagian B:** artikel/blog, formulir kontak & minta demo dengan kotak masuk prospek di konsol, persetujuan cookie (UU PDP), ID Google Analytics/Meta Pixel dari konsol.
2. **F-16d bagian 2:** paket sesi (J-16.2/J-16.3).
3. **Mode jasa** (booking, staf), **laundry**, **wholesale** (SO/DO/invoice).
4. **Karyawan:** geofence absensi.
5. **Laporan anti-fraud** & persetujuan jarak jauh lengkap di Aplikasi Pemilik.
6. **Push notification** (FCM/APNs).
7. **Gratis ongkir**, menunggu flow pesan-antar/toko online.
8. Batch & kedaluwarsa, nomor seri, produksi.
9. Harga per kanal ojol (input manual).
10. Modul Gudang di aplikasi (pindai GRN, transfer, opname).
11. **Fase 3:** toko online `/{slugTenant}` & kurir, Open API + webhook + portal developer, konsinyasi, landed cost, rekonsiliasi bank, aset tetap, bengkel, template sektor lengkap, e-Faktur/Coretax, Salesman, smart restock/forecast, mode LAN, portal mitra (P-12).
12. Billing langganan semi-otomatis (gateway) di sisi platform (F-19 lanjutan).

## C. Keputusan yang menunggu pemilik produk

1. `robots.txt` & `sitemap.xml` masuk pengecualian konvensi URL? Saat ini peta situs di `/peta-situs` dan `robots.txt` statis.
2. Bonus saat isi deposit pelanggan: ada atau tidak?
3. Kedaluwarsa saldo deposit: ada atau tidak?
4. Sub-merchant gateway pembayaran di bawah platform (nanti)?
5. Tautan struk digital/QR: tetap di `dashboard.payou.id/s/…` atau pindah ke `payou.id`?
6. Pengalihan `www.payou.id` → `payou.id` (diatur di hPanel).
7. Nama subdomain konsol: panduan memakai `console.payou.id` sesuai folder hosting (PRD menulis `consol.`). Cukup samakan nilai `PENGELOLA_DOMAIN`.
8. Isi pemasaran (teks, foto produk, testimoni pelanggan nyata) diisi dari konsol.
