# Memasang PAYOU Edisi Lisensi di Server Pembeli

Panduan untuk pembeli lisensi PAYOU (D-35, PRD §13.10): dashboard toko dipasang di **server dan domain milik
pembeli sendiri**. Satu lisensi = satu usaha, semua fitur, berlaku selamanya. Tidak ada konsol pengelola, situs
pemasaran, pendaftaran publik, maupun tagihan langganan.

Langkah server (PHP, basis data, domain, SSL, cron) sama dengan `Panduan/PasangDiHosting.md`, tetapi **cukup satu
domain**. Bagian di bawah hanya menuliskan yang berbeda.

## Yang dibutuhkan

| | |
|---|---|
| Server | PHP 8.3+ dengan ekstensi `gd`, `sodium`, `pdo_mysql`, `mbstring`, `intl`, `zip`; Composer; cron tiap menit |
| Basis data | MySQL 8 (disarankan) atau MariaDB 11.8 |
| Domain | Satu domain/subdomain dengan SSL, misal `kasir.tokoanda.com`, **sama persis** dengan yang tertulis di berkas lisensi |
| Dari PAYOU | Berkas lisensi `*.lisensi` (nomor, nama pemegang, domain, batas outlet/perangkat/pengguna) dan paket rilis |

## 1. Pasang kode & `.env`

Ikuti `PasangDiHosting.md` langkah 0–3 (kode di luar `public_html`, buat database, `composer install --no-dev`,
`.env`). Bedanya di `.env`:

```
APP_URL=https://kasir.tokoanda.com      # domain di berkas lisensi
EDISI=Lisensi
DOMAIN_PEMASARAN=                        # kosongkan
DOMAIN_TENANT=                           # kosongkan
```

`PENGELOLA_DOMAIN` tidak dipakai. Pengirim email & WhatsApp **tidak** diisi di `.env`, tetapi lewat langkah 4.

## 2. Isi basis data & tampilan

```
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
npm ci && npm run build                  # atau unggah public/build dari paket rilis
```

## 3. Pasang lisensi (sekali)

Unggah berkas lisensi ke server (di luar folder `public`), lalu:

```
php artisan lisensi:pasang /path/ke/tokoanda.lisensi
```

Perintah ini:
- memeriksa tanda tangan lisensi (tanpa internet);
- memuat & menerbitkan data master rilis (pajak, satuan, wilayah, katalog fitur, template sektor);
- menanyakan nama usaha dan data Owner (nama, email, nomor WhatsApp, kata sandi yang diketik tersembunyi);
- membuat usaha, Owner, outlet & gudang bawaan, metode Tunai.

Sesudahnya buka `https://kasir.tokoanda.com/masuk`, masuk sebagai Owner, aktifkan verifikasi dua langkah, lalu ikuti
panduan awal. Cek kapan saja dengan `php artisan lisensi:info`.

Tanpa lisensi sah, atau bila dibuka dari domain lain, semua halaman menjawab 503 dengan petunjuk.

## 4. Email, WhatsApp, dan penyimpanan berkas

```
php artisan lisensi:atur-integrasi Email         # SMTP hosting, Gmail, Brevo, Mailgun, …
php artisan lisensi:atur-integrasi Whatsapp      # WhatsApp Cloud API resmi, Fonnte, Wablas, …
php artisan lisensi:atur-integrasi Penyimpanan   # opsional: S3/R2 untuk foto produk & bukti
```

Perintah menanyakan penyedia, pengaturan, dan kredensial (tersembunyi), menyimpannya terenkripsi, menguji koneksi,
lalu mengaktifkannya bila berhasil. Jalankan lagi untuk mengganti token/kata sandi. Gerbang pembayaran QRIS diatur
Owner sendiri di back-office (Pengaturan › Gerbang pembayaran), sama seperti edisi SaaS.

## 5. Cron

Sama dengan `PasangDiHosting.md` langkah 8 (`php artisan schedule:run` tiap menit). Di edisi Lisensi, jadwal tagihan,
trial, dan Platform Pengelola tidak berjalan; tutup harian, draf PO, penyusutan, pengingat, dan antrean tetap jalan.

## 6. Aplikasi Kasir & Pemilik

Pakai aplikasi PAYOU yang sama (Play Store / berkas instalasi dari PAYOU), lalu arahkan ke server toko:

- **Kasir:** di back-office menu Perangkat, buat kode aktivasi. QR-nya sudah membawa alamat server
  (`https://kasir.tokoanda.com/aktivasi-perangkat?kode=…`), jadi cukup dipindai. Tanpa kamera (Windows): ketuk
  "Toko memakai server sendiri?", isi alamat server, lalu ketik kodenya.
- **Pemilik:** di layar masuk ketuk "Toko memakai server sendiri?", isi alamat server, lalu email & kata sandi.

Alamat server tersimpan di perangkat (tetap ada saat keluar atau saat perangkat dicabut, supaya transaksi offline
tetap terkirim ke server yang sama).

## 7. Memperbarui ke rilis baru

```
php artisan down
# ganti kode dengan paket rilis baru (jangan menimpa .env dan storage/)
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan lisensi:siapkan-data         # template & fitur baru dari rilis ikut aktif
npm ci && npm run build                  # atau salin public/build dari paket rilis
php artisan optimize
php artisan up
```

## 8. Menambah outlet/perangkat atau pindah domain

Minta berkas lisensi baru ke PAYOU, lalu jalankan `php artisan lisensi:pasang /path/ke/berkas-baru.lisensi`. Data
usaha tidak disentuh; hanya batas/domain yang berubah. Untuk pindah domain, ganti juga `APP_URL`.

## Catatan keamanan

- Simpan berkas lisensi & cadangan basis data di luar folder `public`.
- Bila server di belakang Cloudflare/reverse proxy, isi `PROKSI_TEPERCAYA` dengan alamat proksi yang benar (jangan
  `*`), lihat `PasangDiHosting.md` langkah 7a.
- Pembeli adalah penyelenggara sistem elektronik untuk tokonya sendiri: kebijakan privasi, persetujuan pelanggan
  (UU PDP), dan kewajiban pajak (e-Faktur/Coretax) menjadi tanggung jawab pembeli.

## Untuk PAYOU: menerbitkan lisensi

Sekali saja, di komputer pemilik produk (bukan server): `php artisan lisensi:buat-kunci ~/payou-lisensi.kunci`,
tempel kunci publik yang dicetak ke `Aplikasi/Web/config/lisensi.php`, commit, dan cadangkan berkas kunci privat.
Per pembeli:

```
php artisan lisensi:terbitkan --nomor=PAYOU-L-2026-0001 --pemegang="PT Toko Anda" --domain=kasir.tokoanda.com \
  --batas-outlet=3 --batas-perangkat=5 --kunci-privat=~/payou-lisensi.kunci --keluaran=tokoanda.lisensi
```
