<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 4. Tujuan, Non-Tujuan & Metrik Keberhasilan

### 4.1 Tujuan Produk

| ID | Tujuan |
|---|---|
| G1 | Tenant baru bisa melakukan transaksi pertama dalam **≤ 15 menit** setelah daftar (onboarding wizard + template sektor). |
| G2 | Kasir bisa menyelesaikan transaksi retail 5 item dalam **≤ 20 detik** di aplikasi POS (Android, iOS, Windows), dan tetap bisa beroperasi offline. |
| G3 | Laporan keuangan (L/R, Neraca) tersedia **real-time** tanpa input akuntansi manual. |
| G4 | Selisih stok sistem vs fisik turun (target tenant aktif: selisih opname < 2% nilai persediaan). |
| G5 | Biaya infra per tenant cukup rendah untuk paket mikro ≤ Rp 99.000/bulan. |

### 4.2 Non-Tujuan (di luar lingkup v1)

- ERP manufaktur penuh (MRP, routing, work center). Hanya produksi sederhana/rakitan (BOM 1 level + multi-level terbatas).
- Payroll lengkap dengan PPh 21 dan BPJS otomatis. v1 hanya rekap gaji, komisi, dan absensi; payroll penuh di fase 4.
- Back-office lengkap sebagai aplikasi native. Pengaturan & master data lengkap tetap di **web responsif**. Aplikasi Flutter dibagi dua: **Aplikasi POS** (operasional outlet) dan **Aplikasi Owner** (pemantauan, approval, aksi cepat). Aplikasi Owner tidak menggantikan back-office untuk pekerjaan berat seperti import, akuntansi, dan pengaturan pajak.
- POS berbasis browser (PWA). Kasir hanya lewat aplikasi Flutter. Web hanya untuk back-office dan halaman publik pelanggan.
- Aplikasi POS untuk **Linux dan macOS**. Tidak ditargetkan. Pengguna Mac dapat memakai iPad atau back-office web. Karena Flutter mendukung keduanya, platform ini bisa ditambahkan kelak jika ada permintaan pasar.
- Rumah sakit/klinik dengan rekam medis (butuh regulasi SATUSEHAT). Hanya apotek/toko obat ringan.
- Hotel dengan channel manager OTA.

### 4.3 KPI / Metrik

| Kategori | Metrik | Target 12 bulan setelah launch |
|---|---|---|
| Akuisisi | Tenant terdaftar | 3.000 |
| Aktivasi | % tenant yang transaksi pertama ≤ 24 jam | ≥ 60% |
| Retensi | Retensi tenant berbayar bulan ke-3 | ≥ 75% |
| Monetisasi | Konversi trial → berbayar | ≥ 20% |
| Keandalan | Uptime aplikasi (di luar mode offline) | ≥ 99,5% |
| Keandalan | Transaksi offline gagal sinkron | < 0,01% |
| Kinerja | p95 respons API kasir | < 400 ms |
| Kualitas aplikasi | Crash-free sessions aplikasi POS (semua platform) | ≥ 99,5% |
| Kualitas aplikasi | Rating Google Play / App Store | ≥ 4,5 |
| Kualitas | Bug kritikal di produksi per bulan | < 2 |
| Kepuasan | NPS tenant | ≥ 40 |
