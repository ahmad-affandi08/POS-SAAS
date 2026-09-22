<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 3. Analisis Kompetitor & Strategi "Lebih dari Majoo"

### 3.1 Lanskap

| Pemain | Kekuatan umum | Celah yang bisa dimanfaatkan |
|---|---|---|
| **majoo** | Ekosistem lengkap (POS, inventori, akuntansi, CRM, karyawan, toko online), banyak sektor, brand kuat | Mode offline terbatas, paket fitur lengkap relatif mahal untuk mikro, kustomisasi promo & approval terbatas, integrasi API terbuka terbatas |
| Moka (GoTo) | Kuat di F&B/retail, ekosistem GoBiz | Akuntansi dasar, fokus ekosistem sendiri |
| Pawoon | Mudah dipakai, F&B | Fitur back-office lebih ringan |
| Qasir | Gratis/murah untuk mikro | Fitur multi-outlet & akuntansi terbatas |
| iSeller / Olsera | Omnichannel retail | Kurang di F&B dan jasa |
| ESB | F&B enterprise, kuat di resto chain | Mahal, kompleks untuk UMKM |

> Catatan: Tabel adalah gambaran umum posisi pasar untuk arah produk, bukan klaim fitur spesifik. Tim wajib melakukan uji langsung (trial akun) setiap kompetitor sebelum finalisasi fitur di tiap fase.

### 3.2 Paritas Wajib (Table Stakes, setara majoo)

Fitur berikut **harus ada** agar {{APP}} layak dibandingkan:

- POS kasir (retail & F&B), multi-pembayaran, QRIS, struk cetak/digital
- Manajemen produk: varian, modifier/add-on, bundling, resep/bahan baku
- Inventori multi-outlet & multi-gudang, transfer stok, stock opname, PO & penerimaan
- Manajemen meja, split bill, kitchen printer/KDS (F&B)
- CRM pelanggan, poin/loyalti, voucher, promo
- Karyawan: shift, absensi, komisi, hak akses
- Akuntansi: jurnal otomatis, Laba Rugi, Neraca, Arus Kas
- Laporan penjualan, stok, kas, per outlet/karyawan/produk
- Toko online / pesan online, integrasi ojol (fase lanjut)
- Aplikasi owner (dashboard mobile) — di {{APP}} dibuat sebagai aplikasi Flutter tersendiri (§17.3)

### 3.3 Pembeda (Beyond Majoo)

| Kode | Pembeda | Deskripsi | Fase |
|---|---|---|---|
| X1 | **Offline-first POS** | Seluruh alur kasir (jual, bayar tunai/EDC manual, cetak struk, buka/tutup shift) berjalan tanpa internet di aplikasi Flutter (SQLite lokal). Sinkron idempoten via outbox. | 1 |
| X2 | **Multi-sektor per tenant** | Satu tenant bisa memakai beberapa template sektor per outlet (outlet A kafe, outlet B toko retail). Satu pelanggan dan satu laporan konsolidasi. | 1 |
| X3 | **Promo Engine (rule-based)** | Kondisi (produk, kategori, waktu, member tier, channel, min. belanja) × aksi (diskon %, nominal, gratis item, harga spesial) dengan prioritas & stacking. | 2 |
| X4 | **Approval Workflow & Anti-Fraud** | PIN/OTP supervisor untuk void, refund, diskon di atas batas, buka laci kas manual. Skor risiko kasir & notifikasi anomali ke owner. | 1–2 |
| X5 | **Akuntansi & Pajak Indonesia native** | COA per sektor, jurnal otomatis dari setiap event, PPN DPP nilai lain, PB1/PBJT, export e-Faktur/Coretax, SAK EMKM. | 1–3 |
| X6 | **Smart Restock & Forecast** | Prediksi kebutuhan stok (moving average + musiman, termasuk Ramadan/Lebaran), draft PO otomatis ke supplier. | 3 |
| X7 | **Open API + Webhook** | REST API v1 bertoken, webhook event (order.paid, stock.low, dll.), dokumentasi publik. | 3 |
| X8 | **Harga multi-level & per channel** | Harga berbeda per outlet, per channel (dine-in, takeaway, GoFood, GrabFood, Shopee), per tier pelanggan (grosir/reseller), per jumlah (tiered pricing). | 2 |
| X9 | **Konsinyasi & titip jual** | Barang titipan supplier: stok tidak masuk aset, hutang timbul hanya saat terjual, laporan settlement ke penitip. | 3 |
| X10 | **Franchise/Kemitraan** | Royalti otomatis per outlet mitra, master menu terpusat, harga terkunci, laporan royalti. | 4 |
| X11 | **Struk & notifikasi WhatsApp** | Kirim struk, invoice, pengingat piutang, dan notifikasi booking via WhatsApp (gateway pihak ketiga). | 2 |
| X12 | **Self-order QR Meja** | Pelanggan scan QR di meja, pesan dan bayar (QRIS) sendiri, order masuk ke KDS. | 2 |
| X13 | **Booking & Antrian (Jasa)** | Booking online salon/barbershop/bengkel, antrian digital, pemilihan staf, reminder otomatis. | 2 |
| X14 | **Audit Trail Permanen** | Setiap perubahan data penting tercatat (siapa, kapan, nilai lama/baru, perangkat, IP). Tidak bisa dihapus tenant. | 1 |
| X15 | **Multi-platform & hardware-agnostic** | Satu aplikasi Flutter untuk Android (tablet murah, HP, **semua perangkat POS all-in-one**: Sunmi, iMin, PAX, Telpo, dan merek lain), iPad/iPhone, dan Windows (PC bekas). Printer thermal via Bluetooth, USB, LAN, atau printer bawaan. Tidak mewajibkan beli hardware tertentu. | 1 |
| X16 | **Import massal & migrasi dari kompetitor** | Template Excel + importer yang memetakan export dari aplikasi lain agar pindah platform mudah. | 1 |
| X17 | **Mode LAN Lokal (Outlet Hub)** | Saat internet mati, perangkat dalam satu outlet (kasir, tablet pelayan, KDS) tetap saling bertukar order lewat Wi-Fi lokal. Satu perangkat bertindak sebagai hub, lalu hub menyinkronkan ke cloud saat online. | 3 |
| X18 | **Push notification native** | Approval jarak jauh, stok kritis, dan order online masuk dikirim sebagai push (FCM/APNs) ke aplikasi, tanpa bergantung pada WebSocket server. | 2 |
| X19 | **Aplikasi Mobile Owner** | Aplikasi Flutter khusus owner (Android & iOS): dashboard real-time multi-outlet, laporan ringkas, approval jarak jauh satu ketukan, notifikasi anomali kasir/stok/selisih kas, cek stok & harga, kelola promo cepat. | 2 |
