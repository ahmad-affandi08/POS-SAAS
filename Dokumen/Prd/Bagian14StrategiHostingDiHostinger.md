<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 14. Strategi Hosting di Hostinger

### 14.1 Realita Shared/Cloud Hosting & Solusinya

Hostinger Web/Cloud Hosting (berbasis LiteSpeed, hPanel) **tidak** menyediakan proses latar belakang yang berjalan terus (tidak ada Supervisor/daemon), Redis, maupun WebSocket server. Arsitektur di atas sudah dirancang untuk batasan ini:

| Batasan | Dampak | Solusi di {{APP}} |
|---|---|---|
| Tidak ada Supervisor/daemon (`queue:work` permanen) | Queue tidak bisa berjalan terus | **Queue driver `database`** + **Cron setiap menit**: `php artisan schedule:run`. Scheduler menjalankan `queue:work --stop-when-empty --max-time=50 --tries=3` dengan `withoutOverlapping()`. Job kritis (stok & jurnal) **tidak** lewat queue (sinkron dalam transaksi). |
| Tidak ada Redis | Cache/session/lock tanpa Redis | `CACHE_STORE=database` (atau `file`), `SESSION_DRIVER=database`, atomic lock via database. Semua driver dari `.env` sehingga saat pindah VPS cukup ganti ke `redis`. |
| Tidak ada WebSocket (Reverb/Soketi) | Tidak ada push real-time dari server | Aplikasi Flutter: **polling delta** (KDS 5 detik, status QRIS 3 detik saat menunggu, master 60 detik) dengan endpoint ringan (`since` cursor, respons 304), ditambah **push FCM/APNs** (dikirim lewat HTTP keluar dari queue) sebagai pemicu tarik data segera. Back-office web: polling TanStack Query. Di fase 3, **Mode LAN** membuat komunikasi kasir↔KDS dalam outlet tidak bergantung server. |
| Tidak ada Node.js untuk build di server (atau tidak disarankan) | `npm run build` tidak di server | **Build di GitHub Actions**, upload hasil `public/build` via SSH/rsync. |
| Batas memori & waktu eksekusi PHP per request | Export/import besar gagal | Import/export **streaming (OpenSpout)** + **chunk** + diproses di queue per batch; PDF besar dipecah; laporan berat dari **tabel ringkasan** (`daily_*_summaries`). |
| Batas koneksi MySQL & entry process | Lonjakan trafik bisa error 503/508 | Query efisien (indeks tepat, tanpa N+1: `Model::preventLazyLoading()` di dev), cache props yang jarang berubah, polling adaptif (melambat saat tab tidak aktif), POS offline-first mengurangi request. |
| Batas inode/disk | Upload gambar menumpuk | Kompres & resize gambar saat upload (WebP), batas ukuran; opsi disk **S3-compatible** (Cloudflare R2/sejenis) via `FILESYSTEM_DISK`. |
| Document root = `public_html` | Struktur Laravel berbeda | Kode aplikasi di luar `public_html` (misal `~/Aplikasi/{{APP}}/Aktif`). `public_html` (nama wajib Hostinger) menjadi **symlink** ke `Aktif/public` (atau domain di-set ke folder tersebut di hPanel). |
| Cron minimal per menit | Scheduler granular menit | Cukup untuk queue, pengingat, tutup harian, dunning, forecast malam hari. |

### 14.2 Rekomendasi Paket

| Tahap | Paket Hostinger | Kapasitas perkiraan* |
|---|---|---|
| Pengembangan/Staging | Web Hosting Business | Tim internal & beta tester |
| Produksi awal (≤ ~300 tenant aktif) | **Cloud Hosting Startup/Professional** (sumber daya terdedikasi, IP khusus, lebih banyak RAM & proses) | Dengan offline-first + polling adaptif |
| Pertumbuhan (> ~300 tenant aktif / kebutuhan real-time) | **Hostinger VPS (KVM)** | Redis, Supervisor (queue permanen), Laravel Reverb (WebSocket), OPcache + tuning MySQL, opsional Octane |

\* Perkiraan kasar, **wajib divalidasi dengan load test** (§23). Spesifikasi paket Hostinger (RAM, CPU, entry process, SSH, cron, versi PHP) dapat berubah, jadi verifikasi di hPanel sebelum memilih.

**Syarat teknis paket:** akses SSH, PHP 8.3 dengan ekstensi `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `gd`/`imagick`, `zip`, `fileinfo`, `openssl`, `sodium`; Composer; Cron Job; SSL gratis; MySQL 8.

### 14.3 Pipeline Deploy (GitHub Actions → Hostinger via SSH)

```yaml
# .github/workflows/deploy.yml (ringkas)
on: { push: { branches: [main] } }
jobs:
  test:        # composer install, pint --test, larastan, pest (MySQL service), tsc, eslint, vitest
  build:
    needs: test
    steps:
      - composer install --no-dev --optimize-autoloader --classmap-authoritative
      - npm ci && npm run build            # hasil: public/build
      - tar artefak rilis (tanpa node_modules, tests, .git)
  deploy:
    needs: build
    steps:
      - upload artefak via scp/rsync ke ~/Aplikasi/{{APP}}/Rilis/{Stempel}
      - ssh: symlink Bersama/ (.env, storage) ke rilis baru
      - ssh: php artisan migrate --force   # migrasi harus backward-compatible (expand → contract)
      - ssh: php artisan optimize          # config, route, view, event cache
      - ssh: ln -sfn Rilis/{Stempel} Aktif   # switch atomik
      - ssh: php artisan queue:restart
      - ssh: hapus rilis lama (simpan 5 terakhir)
      - smoke test: curl /sehat (health check) → gagal = rollback symlink
```

- **Zero-downtime:** switch symlink atomik + migrasi *expand/contract* (kolom baru nullable dulu, hapus kolom lama di rilis berikutnya).
- **Kompatibilitas mundur API POS wajib.** Aplikasi Flutter versi lama masih beredar di perangkat selama berminggu-minggu. Backend harus melayani minimal **2 versi minor aplikasi terakhir**. Perubahan yang merusak kontrak hanya lewat `/api/pos/v2` atau pemaksaan update lewat `min_supported_version` (§14.6).
- **Rollback:** arahkan symlink `Aktif` ke rilis sebelumnya.
- **Environment:** `production`, `staging` (subdomain `staging.`), dengan database terpisah.
- **Secret** disimpan di GitHub Secrets (SSH key, host). `.env` produksi hanya ada di server.

### 14.4 Crontab di hPanel

```
* * * * * cd ~/Aplikasi/{{APP}}/Aktif && php artisan schedule:run >> /dev/null 2>&1
```

Isi `routes/console.php` (contoh):

| Jadwal | Tugas |
|---|---|
| Setiap menit | `queue:work --stop-when-empty --max-time=50` (withoutOverlapping) |
| Setiap 5 menit | Cek status pembayaran QRIS pending (fallback webhook), kirim webhook keluar yang gagal (retry) |
| Setiap jam | Notifikasi stok kritis, pengingat booking |
| 00:30 WIB | Tutup harian otomatis (ringkasan), expire poin, cek expired batch |
| 02:00 WIB | Forecast restock, rekalkulasi tier member, pembersihan sesi/token |
| 03:00 WIB | Backup database (mysqldump terkompresi → storage eksternal), prune log |
| Harian 08:00 | Dunning langganan, pengingat piutang pelanggan (WA) |

> Catatan zona waktu: scheduler memakai `Asia/Jakarta` untuk tugas global. Tugas per outlet (misal tutup harian) dijalankan sesuai zona waktu outlet (WIB/WITA/WIT).

### 14.5 Backup & Disaster Recovery

- Backup harian otomatis Hostinger **ditambah** backup mandiri (mysqldump + `storage/app`) ke penyimpanan eksternal terenkripsi, retensi 30 hari harian + 12 bulanan.
- **RPO** ≤ 24 jam (server). Untuk transaksi POS, RPO praktis ≈ 0 karena data juga ada di outbox perangkat sampai dikonfirmasi server.
- **RTO** ≤ 4 jam (restore ke paket baru dengan skrip provisioning terdokumentasi).
- Uji restore setiap bulan.

### 14.6 Build, Distribusi & Update Aplikasi Flutter

Aplikasi POS dan Aplikasi Owner **tidak di-hosting di Hostinger**. Hostinger hanya menjadi backend API dan halaman unduh.

| Platform | Build (CI) | Distribusi | Update |
|---|---|---|---|
| Android — POS | GitHub Actions (runner Linux) → **AAB** (Play) + **APK** (perangkat all-in-one tanpa Play Store) | Google Play (track internal → closed → production), APK di halaman `/unduh` dan **app store vendor** perangkat all-in-one (misal Sunmi Store) bila tersedia | Play in-app update. APK: cek versi via API + unduh |
| Android — Owner | Runner Linux → AAB | Google Play | Play in-app update |
| iOS / iPadOS — POS & Owner | Runner **macOS** (GitHub Actions atau Codemagic) → IPA, code signing via fastlane match | **App Store** (TestFlight untuk beta) | App Store. Paksa update lewat `min_supported_version` |
| Windows — POS | Runner **Windows** → installer bertanda tangan (MSIX dan/atau `.exe`) | **Selama pengembangan & beta:** unduhan langsung terbatas untuk tester. **Kanal produksi (Microsoft Store, unduhan langsung, atau keduanya) diputuskan setelah sistem stabil** | Selama beta: cek versi via `konfigurasi-aplikasi` + unduh installer baru. Mekanisme final mengikuti kanal yang dipilih |

> **Keputusan tertunda (D-01):** kanal distribusi & mekanisme update Windows ditetapkan setelah Fase 1 berjalan stabil. Arsitektur tidak bergantung pada pilihan ini: aplikasi hanya membaca `konfigurasi-aplikasi` (versi terbaru, `min_supported_version`, `download_url`).

**Kebijakan versi:**
- Versi semantik `MAJOR.MINOR.PATCH+BUILD`. Setiap rilis membawa `VersiSkemaSinkron`.
- Endpoint `GET /api/pos/v1/konfigurasi-aplikasi` mengembalikan `latest_version`, `min_supported_version`, dan feature flag remote per platform.
- Aplikasi di bawah `min_supported_version` **tetap boleh mengirim outbox yang tertunda** (agar tidak kehilangan transaksi), lalu mengunci layar jual sampai diperbarui.
- **Update tidak boleh dipasang saat ada shift terbuka dengan outbox belum terkirim** (aplikasi menunda dan mengingatkan).
- Rilis bertahap (staged rollout) 10% → 50% → 100% di Play Store. Di Windows, lewat kanal `Beta`/`Stabil` pada tabel `RilisAplikasi` (mekanisme final mengikuti D-02).

**Biaya & akun yang perlu disiapkan:** Google Play Console (sekali bayar), Apple Developer Program (tahunan), sertifikat code signing Windows (tahunan), proyek Firebase (FCM), runner macOS di CI (menit berbayar, dibutuhkan untuk build iOS), serta akun developer di app store vendor perangkat all-in-one bila dipakai.

Binary installer (puluhan MB) sebaiknya disimpan di **GitHub Releases** atau object storage (R2/S3-compatible), bukan di disk Hostinger, agar tidak menghabiskan kuota inode/bandwidth. Halaman `/unduh` dan `konfigurasi-aplikasi` cukup menautkannya.
