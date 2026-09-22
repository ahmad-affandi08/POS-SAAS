<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 20. Kebutuhan Non-Fungsional

### 20.1 Kinerja

| Metrik | Target |
|---|---|
| TTFB halaman Inertia (p95) | < 600 ms |
| Endpoint API POS (p95) | < 400 ms |
| Sync push 50 transaksi | < 3 detik |
| Laporan harian satu outlet | < 1 detik |
| Laporan bulanan multi-outlet (dari tabel ringkasan) | < 3 detik |
| Cold start aplikasi POS Flutter sampai layar PIN | < 2,5 detik (detail §17.2.4) |

### 20.2 Keamanan

- HTTPS wajib (HSTS), cookie `Secure`/`HttpOnly`/`SameSite=Lax`, CSRF untuk semua mutasi sesi.
- Password di-hash dengan Argon2id/bcrypt. PIN kasir di-hash dan di-rate-limit (kunci 5 menit setelah 5 kali gagal).
- 2FA TOTP untuk Owner/Admin/Akuntan (wajib di paket Bisnis ke atas).
- Enkripsi field sensitif (`encrypted` cast): NIK, token integrasi, secret webhook, kredensial gateway.
- Tenant isolation test otomatis di CI (§13.4).
- Security headers: CSP ketat (nonce untuk Vite), `X-Frame-Options` (kecuali embed yang diizinkan), `Referrer-Policy`.
- Validasi upload (MIME, ukuran, re-encode gambar), file privat disajikan lewat signed URL.
- Rate limit login, OTP, registrasi, dan API.
- Audit log append-only dan tidak bisa diubah dari UI.
- Signature verification untuk semua webhook masuk. IP allowlist opsional.
- Device token per perangkat dengan *abilities* sesuai tipe perangkat (kasir tidak bisa memanggil endpoint gudang, dsb.), bisa dicabut kapan saja dari back-office.
- Keamanan aplikasi Flutter: secure storage, SQLCipher, obfuscation, lihat §17.2.6.
- Dependabot + `composer audit` + `npm audit` + `dart pub outdated`/audit dependensi di CI.
- Platform Pengelola: 2FA wajib semua akun, sesi 30 menit, allowlist IP opsional, akses lintas tenant hanya lewat `KonteksPengelola` yang diaudit, akses dukungan berizin & berbatas waktu (§13.8, P-09).
- Pentest eksternal sebelum GA (termasuk Platform Pengelola dan uji eskalasi hak dari tenant ke pengelola).

### 20.3 Keandalan & Observabilitas

- Health check `/sehat`, uptime monitor eksternal (ping tiap 1 menit).
- Error tracking (Sentry) dengan konteks `IdTenant`, `IdPerangkat`, dan tanpa PII berlebih.
- Log terstruktur harian, retensi 14 hari.
- Metrik bisnis internal: transaksi/menit, antrean outbox global, job gagal, keterlambatan queue (umur job tertua). Alert jika job tertua > 5 menit (indikasi cron macet).

### 20.4 Skalabilitas

- Semua driver infrastruktur (cache, queue, session, filesystem, broadcast) dikonfigurasi via `.env`, sehingga migrasi Hostinger shared/cloud → VPS tidak butuh perubahan kode.
- Stateless web tier sehingga bisa horizontal di VPS/load balancer kelak.
- Tabel ringkasan & cursor pagination mencegah query berat.

### 20.5 Kompatibilitas

**Aplikasi POS (Flutter)**

| Platform | Versi minimum (usulan, sesuaikan dengan dukungan Flutter stable saat rilis) | Status |
|---|---|---|
| Android (tablet, HP, POS all-in-one) | Android 7.0 (API 24), arsitektur arm64-v8a & armeabi-v7a | Utama (rilis pertama) |
| Windows | Windows 10 64-bit | Utama (rilis pertama) |
| iPadOS / iOS | iOS/iPadOS 15 | Utama (akhir Fase 1) |
| Windows POS all-in-one | Windows 10 64-bit | Didukung via jalur Windows (driver/COM) |
| macOS, Linux | — | **Tidak ditargetkan** (keputusan D-01). Bisa ditambah kelak |

**Aplikasi Owner (Flutter)**

| Platform | Versi minimum (usulan) |
|---|---|
| Android | Android 8.0 (API 26) |
| iOS | iOS 15 |

Resolusi: HP 360 dp s.d. desktop 1920 px. Dioptimalkan untuk tablet 8–11" dan layar POS 15". Mendukung orientasi lanskap & potret (tablet).

**Back-office & Web Publik**

| Browser | Dukungan |
|---|---|
| Chrome/Edge (2 versi terakhir) | Penuh |
| Safari (macOS, iPadOS/iOS 16+) | Penuh |
| Firefox (2 versi terakhir) | Penuh |

### 20.6 Lokalisasi

- Bahasa Indonesia default, English opsional.
- Format tanggal `dd/MM/yyyy`, mata uang Rupiah, pemisah ribuan titik.
- Zona waktu per outlet (WIB/WITA/WIT).
- Hari libur nasional & cuti bersama (untuk forecast & jadwal).

### 20.7 Privasi & Data

- Data tenant milik tenant: export penuh (Excel/CSV/JSON) kapan saja.
- Persetujuan pemasaran pelanggan akhir tercatat (UU PDP).
- Kebijakan retensi dan penghapusan terdokumentasi. Anonimisasi pelanggan atas permintaan, tanpa merusak integritas transaksi (nama diganti "Pelanggan Terhapus").
