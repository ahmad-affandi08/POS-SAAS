<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 1. Ringkasan Eksekutif

**{{APP}}** adalah platform Point of Sale (POS) dan manajemen usaha berbasis SaaS untuk UMKM hingga usaha menengah multi-outlet di Indonesia. Satu platform melayani banyak sektor (F&B, retail, jasa, grosir, apotek, laundry, bengkel, dan lainnya) melalui **Template Sektor**: saat tenant mendaftar dan memilih jenis usaha, sistem menyalakan modul, alur kasir, bagan akun (COA), satuan, pajak, dan laporan yang sesuai. Tenant tidak perlu mengatur semuanya dari nol.

Pengembangan dimulai dari **flow bisnis**, bukan dari layar atau tabel. Setiap fitur harus bisa ditelusuri ke satu langkah dalam alur bisnis induk:

```
Daftar → Setup Usaha → Master Data → Pembelian & Stok Masuk → Buka Shift
      → Penjualan → Pembayaran → Pasca-Penjualan → Tutup Shift
      → Akuntansi Otomatis → Laporan & Insight → Tutup Buku
```

Setiap transaksi operasional (jual, beli, mutasi stok, kas) **otomatis menghasilkan jurnal akuntansi**. Dengan begitu laporan keuangan (Laba Rugi, Neraca, Arus Kas) selalu siap tanpa input ganda.

**Empat komponen produk:**

| Komponen | Teknologi | Pengguna | Platform |
|---|---|---|---|
| **Aplikasi POS {{APP}}** (kasir, KDS, operasional gudang, absensi) | **Flutter** | Kasir, pelayan, dapur, gudang, supervisor | Android (tablet, HP, semua perangkat POS all-in-one), iOS/iPadOS, Windows |
| **Aplikasi {{APP}} Owner** (dashboard, laporan, approval, notifikasi, kontrol outlet) | **Flutter** | Owner, manajer area, manajer outlet | Android & iOS (HP) |
| **Back-office Web** (produk, stok, pembelian, laporan, akuntansi, pengaturan) | Laravel 13 + Inertia React + TypeScript + Tailwind 4 + TanStack Query | Owner, manajer, akuntan, admin | Browser desktop & mobile |
| **Web Publik** (self-order QR meja, toko online, struk digital, booking) | Laravel + React (ringan) | Pelanggan akhir | Browser HP |

Keempatnya memakai satu backend Laravel dan satu database MySQL di Hostinger.

**Pembeda utama dibanding majoo dan pemain lain** (rinci di §3):

1. **Offline-first sungguhan.** Aplikasi POS Flutter native dengan database SQLite lokal. Kasir tetap bisa berjualan penuh saat internet mati, lalu sinkron otomatis tanpa transaksi ganda.
2. **Template Sektor + Feature Toggle.** Satu produk bisa disetel untuk 12+ jenis usaha, dan satu tenant boleh punya beberapa sektor sekaligus (misalnya kafe + retail merchandise).
3. **Promo Engine berbasis aturan.** Buy X Get Y, bundling, happy hour, tier member, voucher, dan stacking rules bisa dikonfigurasi tanpa kode.
4. **Akuntansi dan pajak Indonesia bawaan**, siap untuk PPN (termasuk DPP nilai lain), PB1/PBJT, e-Faktur/Coretax export, dan SAK EMKM/SAK EP.
5. **Audit trail dan approval berlapis.** Void, diskon manual, refund, dan penyesuaian stok wajib beralasan, bisa perlu PIN supervisor, dan tercatat permanen.
6. **Open API dan webhook** untuk integrasi ERP, marketplace, dan akuntansi pihak ketiga.
7. **Insight cerdas**: saran restock berbasis prediksi, deteksi anomali kasir, menu engineering (F&B), dan analisis ABC (retail).
8. **Satu aplikasi POS untuk semua perangkat.** Satu basis kode Flutter berjalan di Android, iPad/iPhone, dan Windows, termasuk **semua perangkat POS Android all-in-one** (printer & layar pelanggan bawaan) dan printer LAN dapur tanpa aplikasi tambahan.
9. **Aplikasi Mobile Owner.** Owner memantau omzet, laba, stok, dan kasir semua outlet dari HP, menyetujui void/diskon dari jarak jauh, dan menerima notifikasi anomali secara real-time.
10. **Biaya infrastruktur rendah.** Arsitektur dirancang agar bisa berjalan di shared/cloud hosting Hostinger, sehingga harga langganan bisa lebih kompetitif.
