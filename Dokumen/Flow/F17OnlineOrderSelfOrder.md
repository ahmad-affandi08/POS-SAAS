<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-17 · Online Order & Self-Order

- **Self-Order QR Meja (X12):** QR unik per meja → web ringan (tanpa login) → menu (stok & ketersediaan real-time) → keranjang → catatan → bayar QRIS dinamis **atau** "bayar di kasir" → order masuk ke POS (status `MenungguKonfirmasi` jika belum bayar) dan KDS.
- **Toko Online (Web Store):** `/{slugTenant}` katalog, keranjang, checkout, pilih ambil sendiri/kirim, pembayaran gateway, status pesanan. SEO dasar.
- **Integrasi Ojol & Marketplace (fase 3+):** sinkron menu & stok, order masuk otomatis (bergantung ketersediaan API mitra). Sebelum API tersedia: input manual sebagai channel dengan harga channel (X8) + laporan settlement.
- BR-17.1 Order online memakai "shift virtual" harian per outlet. Pembayaran online masuk ke akun clearing gateway.
- BR-17.2 Menu dapat ditandai habis (86) langsung dari aplikasi POS/KDS, segera tercermin di self-order web (TanStack Query polling 15–30 detik).
- BR-17.3 Order online/self-order yang masuk diteruskan ke aplikasi POS & KDS lewat delta sync (polling 5–10 detik) dan **push notification** (FCM/APNs) sebagai pemicu tarik data segera.
