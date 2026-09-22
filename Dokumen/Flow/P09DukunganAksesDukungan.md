<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-09 · Dukungan & Akses Dukungan

**Tujuan:** Masalah tenant cepat selesai, tanpa mengorbankan privasi dan keamanan data tenant.
**Aktor:** Dukungan (L1/L2), Teknis, Super Admin, Owner tenant.

**Tiket dukungan:**
- Kanal: in-app (tombol Bantuan di back-office & aplikasi), email, WhatsApp. Tiket dari aplikasi otomatis melampirkan konteks: tenant, outlet, perangkat, versi aplikasi, jumlah outbox tertunda, log singkat.
- Prioritas & **SLA per paket** (misal respons pertama: Gratis 2 hari kerja, Starter 1 hari, Pro 8 jam, Bisnis 4 jam).
- Eskalasi L1 → L2 → Teknis.
- **State Machine `TiketDukungan.Status`:** `Baru → Ditangani → MenungguPelanggan → Selesai → Ditutup`. `Selesai` bisa dibuka lagi dalam 7 hari.

**Akses dukungan ("Masuk sebagai tenant"):**
1. Owner memberi izin dari back-office: *"Izinkan tim dukungan mengakses akun saya"* dengan pilihan durasi (1/24/72 jam) dan cakupan (**Baca saja** atau **Baca & Ubah**). Izin juga bisa diberikan melalui tiket.
2. Petugas dukungan membuka sesi akses. Layar menampilkan **banner merah** "Anda mengakses akun {NamaUsaha} sebagai Dukungan".
3. Setiap aksi dalam sesi tercatat di `LogAudit` tenant dengan penanda pelaku pengelola, dan di `LogAuditPengelola`.
4. Owner dapat melihat riwayat akses dan mencabut izin kapan saja.
5. **Akses darurat tanpa izin** hanya untuk Super Admin, dengan alasan insiden keamanan atau permintaan hukum. Owner diberi notifikasi setelahnya.

**Alat bantu dukungan:** cabut/reset perangkat, minta perangkat mengunggah log sinkron, lihat status outbox per perangkat, **bangun ulang `SaldoStok` dari `MutasiStok`**, jalankan ulang ringkasan harian, kirim ulang email/WA, bantu import data.

**Aturan Bisnis:**
- BR-P09.1 Pengelola tidak pernah bisa melihat kata sandi, PIN, atau kredensial integrasi tenant. Data pribadi pelanggan tenant ditampilkan tersamar (masked) secara default.
- BR-P09.2 Aksi ubah dalam sesi dukungan hanya jika cakupan izin "Baca & Ubah".
- BR-P09.3 Alat bantu yang mengubah data (bangun ulang saldo, ringkasan) mencatat hasil sebelum/sesudah.

**AC:**
```gherkin
Given Owner tenant memberi izin akses dukungan "Baca saja" selama 24 jam
When petugas dukungan mencoba mengubah harga produk dalam sesi akses
Then aksi ditolak dengan pesan "Izin akses hanya baca"
And percobaan tersebut tercatat di log audit tenant dan log audit pengelola
```
