# Memasang PAYOU di Hosting (hPanel, SSH)

Panduan untuk hosting bersama (Hostinger/Niagahoster hPanel) dengan tiga domain (D-20):

| Domain | Isi | Kunci `.env` |
|---|---|---|
| `payou.id` | situs pemasaran (D-21) | `DOMAIN_PEMASARAN=payou.id` |
| `dashboard.payou.id` | back-office tenant, API aplikasi kasir & pemilik, struk digital | `DOMAIN_TENANT=dashboard.payou.id`, `APP_URL=https://dashboard.payou.id` |
| `console.payou.id` | Platform Pengelola (tim internal) | `PENGELOLA_DOMAIN=console.payou.id` |

Ketiganya dilayani **satu** aplikasi Laravel (`Aplikasi/Web`). Yang boleh terlihat dari internet hanya folder `Aplikasi/Web/public`.

Contoh jalur di bawah memakai akun `u704813174`. Ganti bila berbeda.

---

## 0. PENTING: keluarkan kode dari `public_html` sekarang

Kalau seluruh repo ada di `public_html`, siapa pun bisa mengunduh PRD, kode, dan folder `.git`. Pindahkan dulu sebelum langkah lain:

```bash
mkdir -p ~/domains/payou.id/aplikasi
cd ~/domains/payou.id/public_html
ls -la                       # catat isinya, termasuk file tersembunyi (.git, .github, .claude, .gitignore)
shopt -s dotglob
for f in *; do
  case "$f" in
    dashboard|console) ;;            # folder subdomain dibiarkan dulu
    *) mv "$f" ../aplikasi/ ;;
  esac
done
shopt -u dotglob
ls -la                       # sekarang hanya tersisa dashboard dan console
```

## 1. Cek kemampuan server

Hasil di hosting payou.id (26/09/2026): PHP 8.3.33, Composer 2.9.8, **MariaDB 11.8** (migrasi & seed berhasil), **tanpa Node**, fungsi `exec()` dimatikan, ekstensi `sodium` perlu diaktifkan manual di hPanel.

```bash
php -v                  # wajib 8.3 atau lebih baru
php -m | grep -Ei 'intl|bcmath|gd|zip|sodium|pdo_mysql|mbstring|fileinfo'
composer -V             # Composer 2
node -v; npm -v         # Node 20+ (untuk build tampilan). Boleh tidak ada, lihat langkah 5
mysql --version         # catat: MySQL 8 atau MariaDB
```

- Versi PHP untuk web & SSH diatur di **hPanel → Lanjutan → Konfigurasi PHP** → pilih **8.3**, lalu aktifkan ekstensi di atas.
- Kalau `php -v` di SSH masih versi lama, pakai jalur lengkap, misal `/opt/alt/php83/usr/bin/php`, di semua perintah `php` di bawah.

## 2. Buat database

hPanel → **Database → MySQL** → buat database, pengguna, dan kata sandi. Contoh nama: `u704813174_payou`, pengguna `u704813174_payou`. Simpan kata sandinya di tempat aman.

## 3. Pasang dependensi & `.env`

```bash
cd ~/domains/payou.id/aplikasi/Aplikasi/Web
composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env
```

Ubah nilai berikut (sisanya biarkan):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dashboard.payou.id
LOG_LEVEL=error

DOMAIN_PEMASARAN=payou.id
DOMAIN_TENANT=dashboard.payou.id
PENGELOLA_DOMAIN=console.payou.id

DB_HOST=localhost
DB_DATABASE=u704813174_payou
DB_USERNAME=u704813174_payou
DB_PASSWORD=(kata sandi dari langkah 2)
```

Lalu:

```bash
php artisan key:generate
```

> Jangan pernah mengirim isi `.env` ke siapa pun atau memasukkannya ke Git.

## 4. Isi database

```bash
php artisan migrate --force
php artisan db:seed --force                  # peran, satuan, wilayah, pajak, katalog paket, template sektor
php artisan panduan-awal:siapkan-bawaan
php artisan storage:link                     # bila galat "undefined function exec()": ln -s ../storage/app/public public/storage
php artisan pengelola:buat-super-admin --nama="Nama Anda" --email="email@anda"   # akun pertama konsol; kata sandi ditanyakan
```

Kalau `migrate` gagal (terutama di MariaDB), salin pesan galatnya dan kirimkan. Jangan menjalankan `migrate:fresh`.

## 5. Build tampilan (CSS/JS)

Kalau `node -v` ada (20+):

```bash
npm ci
npm run build
```

Kalau Node tidak tersedia di server (kasus hosting payou.id), jalankan `npm ci && npm run build` di komputer lain dari **commit yang sama** dengan kode di server, zip folder `public/build` (±360 file, ±3 MB), unggah ke `~/domains/payou.id/aplikasi/Aplikasi/Web/public/`, lalu:

```bash
cd ~/domains/payou.id/aplikasi/Aplikasi/Web/public
rm -rf build && unzip -q build-payou.zip && rm build-payou.zip
```

## 6. Hubungkan tiga domain ke folder `public`

```bash
W=~/domains/payou.id/aplikasi/Aplikasi/Web/public
cd ~/domains/payou.id/public_html

ls -la dashboard console         # pastikan kosong / hanya file bawaan hosting
rm -rf dashboard console
rm -f default.php index.html .htaccess

# domain utama: isi public_html = tautan ke isi folder public
for f in index.php .htaccess favicon.ico apple-touch-icon.png robots.txt build storage; do
  ln -s "$W/$f" "$f"
done

# subdomain: folder subdomain = tautan ke folder public
ln -s "$W" dashboard
ln -s "$W" console
ls -la
```

Semua domain sekarang menjalankan `index.php` yang sama. Laravel membedakan tampilannya dari nama domain (D-20).

## 7. SSL

hPanel → **Keamanan → SSL** → pasang SSL gratis untuk `payou.id`, `www.payou.id`, `dashboard.payou.id`, `console.payou.id`. Aktifkan **Force HTTPS**. Arahkan `www.payou.id` ke `payou.id` (hPanel → Domain → Pengalihan).

## 8. Cron (jadwal & antrean)

hPanel → **Tingkat Lanjut → Cron Job** → pilih **Kustom** (mode "PHP" memakai `/usr/bin/php` yang belum tentu 8.3), jadwal sekali per menit (`* * * * *`):

```
/opt/alt/php83/usr/bin/php /home/u704813174/domains/payou.id/aplikasi/Aplikasi/Web/artisan schedule:run >> /dev/null 2>&1
```

Periksa dengan `php artisan schedule:list` (jam tampil dalam UTC) dan dasbor Operasional di konsol.

Scheduler juga menjalankan antrean (`queue:work --stop-when-empty`), jadi tidak perlu proses lain.

## 9. Percepat

```bash
cd ~/domains/payou.id/aplikasi/Aplikasi/Web
php artisan optimize          # cache config, rute, view
```

Setiap kali `.env` diubah, jalankan lagi `php artisan optimize`. Kalau ada galat aneh setelah perubahan, jalankan `php artisan optimize:clear`.

## 10. Periksa

| Buka | Hasil yang diharapkan |
|---|---|
| `https://payou.id` | beranda situs pemasaran PAYOU |
| `https://payou.id/harga` | halaman harga |
| `https://dashboard.payou.id/masuk` | halaman masuk tenant |
| `https://console.payou.id/masuk` | halaman masuk Platform Pengelola |
| `https://dashboard.payou.id/sehat` | status OK |

Kalau muncul "500 Server Error": `tail -50 storage/logs/laravel.log` lalu kirimkan pesannya (tanpa kata sandi).

## 11. Langkah pertama di konsol (`console.payou.id`)

1. Masuk dengan akun Super Admin, aktifkan 2FA (wajib, aplikasi authenticator).
2. **Tim internal:** undang minimal satu orang lagi. Tarif pajak dan harga paket memakai persetujuan dua orang (pengaju ≠ peninjau).
3. **Legal:** terbitkan Syarat & Ketentuan dan Kebijakan Privasi. Pendaftaran tenant baru tertutup sampai keduanya berlaku.
4. **Katalog → Harga paket:** ajukan lalu tinjau harga. Paket baru tampil di `payou.id/harga` setelah harganya terbit.
5. **Integrasi:** atur penyedia email (verifikasi email & reset kata sandi), CAPTCHA pendaftaran, lalu WhatsApp bila dipakai.
6. **Situs pemasaran → Pengaturan:** nomor WhatsApp, email, media sosial, tautan unduh aplikasi, kode verifikasi Google.
7. Daftarkan `https://payou.id/peta-situs` di Google Search Console.

## 12. Memperbarui ke versi terbaru

```bash
cd ~/domains/payou.id/aplikasi
git pull origin main                       # bila folder .git & akses GitHub tersedia; kalau tidak, unggah ulang kode
cd Aplikasi/Web
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build                    # atau unggah public/build hasil build di komputer sendiri
php artisan optimize
```

Selama pembaruan, pengunjung bisa diberi halaman perawatan: `php artisan down`, lalu `php artisan up` setelah selesai.

## Aplikasi Kasir & Pemilik (Flutter)

Build dengan alamat server produksi:

```bash
flutter build apk --dart-define=ALAMAT_SERVER=https://dashboard.payou.id/
```

Tautan unduh hasilnya diisi di konsol → Situs pemasaran → Pengaturan → Tautan unduh aplikasi.
