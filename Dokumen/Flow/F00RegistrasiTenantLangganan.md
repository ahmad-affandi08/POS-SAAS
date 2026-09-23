<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-00 · Registrasi Tenant & Langganan

**Tujuan:** Calon pelanggan membuat akun usaha (tenant) dan memulai masa trial.
**Aktor:** Calon Owner, Sistem.
**Pemicu:** Klik "Daftar Gratis" di landing page (atau tautan mitra/referral, P-12).
**Prasyarat:** P-02 s.d. P-06 selesai: paket & batas tersedia (P-04), template sektor terbit (P-03), tarif pajak & wilayah (P-02), email & CAPTCHA aktif (P-05), S&K dan Kebijakan Privasi terbit (P-06).

**Langkah:**
1. Isi nama, email, no. WhatsApp, password, nama usaha, (opsional) kode mitra/referral, lalu **centang persetujuan S&K dan Kebijakan Privasi** versi yang berlaku (tercatat di `PersetujuanDokumenLegal`).
2. Verifikasi email (link) **atau** OTP WhatsApp.
3. Sistem membuat baris di tabel `Tenant`, `Pengguna` (peran Owner), `Langganan` (status `Trial`, durasi & paket sesuai konfigurasi P-04), `AtribusiMitra` bila ada kode mitra, outlet default "Outlet Utama", gudang default.
4. Redirect ke Onboarding Wizard (F-01).

**Aturan Bisnis:**
- BR-00.1 Email dan nomor WA unik per user. Satu user **boleh** menjadi anggota beberapa tenant (konsultan/akuntan), dengan pemilih tenant setelah login.
- BR-00.2 Slug tenant unik, dibuat otomatis dari nama usaha. Dipakai untuk URL toko online `/{slugTenant}`.
- BR-00.3 Trial tidak butuh kartu kredit. Di akhir trial, tenant turun ke paket Gratis (fitur terbatas), bukan dihapus.
- BR-00.4 Rate-limit registrasi per IP (anti-spam) + CAPTCHA (Cloudflare Turnstile). Default 5 percobaan per jam per IP. Di Produksi, registrasi ditutup bila CAPTCHA belum aktif (P-05); lingkungan non-produksi boleh tanpa CAPTCHA.
- BR-00.5 Tombol Daftar langsung membentuk tenant (AC di bawah) dan masuk sebagai Owner; email verifikasi (tautan bertanda tangan, berlaku 24 jam) dikirim bersamaan dan banner pengingat tampil sampai terverifikasi. OTP WhatsApp menyusul bersama integrasi WhatsApp BSP. Email yang sudah terdaftar tidak bisa dipakai mendaftar lagi; menambah usaha kedua untuk pengguna yang sama dibangun bersama F-02 (undangan & pemilih tenant).
- BR-00.6 Paket trial: paket yang dipilih di halaman harga (`?paket=KODE`) bila aktif dan bukan harga negosiasi, selain itu paket bawaan registrasi (konfigurasi, default `PRO`). Durasi trial = `Paket.MasaTrialHari`; paket tanpa masa trial (misal `GRATIS`) langsung berstatus `Gratis`. Registrasi ditolak bila S&K dan Kebijakan Privasi belum berlaku (BR-P06.2) atau paket tidak aktif.
- BR-00.7 Transisi `Langganan.Status` yang sah: Trial → Aktif/Gratis; Aktif → Tertunggak/Berhenti; Tertunggak → Aktif/Ditangguhkan; Ditangguhkan → Aktif/Gratis/Berhenti; Gratis → Aktif. Akhir trial diproses perintah harian: langganan pindah ke paket Gratis (konfigurasi, default `GRATIS`).

**State Machine `Langganan.Status`:**
```
Trial → Aktif → Tertunggak → Ditangguhkan → Berhenti
    ↘ Gratis (jika trial habis tanpa bayar)
```
- `Tertunggak`: grace period 7 hari. Semua fitur jalan, tampil banner.
- `Ditangguhkan`: hanya bisa login, melihat laporan, export data, dan membayar tagihan. POS terkunci, kecuali tenant memilih turun ke paket Gratis (maks 1 outlet, 1 perangkat). Data tenant **tidak pernah dihapus** selama 12 bulan setelah `Berhenti`, dan tenant selalu bisa export datanya.
- Transaksi offline yang dibuat sebelum status berubah tetap diterima saat sinkron.

**AC:**
```gherkin
Given calon pengguna mengisi form registrasi dengan data valid
When ia menekan "Daftar"
Then tenant, user owner, outlet "Outlet Utama", gudang default, dan langganan berstatus Trial 14 hari terbentuk
And ia diarahkan ke Onboarding Wizard
```
