<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 16. Desain API & Integrasi

### 16.1 Tiga Lapisan API

| Lapisan | Prefix | Auth | Konsumen | Versi |
|---|---|---|---|---|
| Internal | `/internal/*` | Sesi + CSRF (Sanctum stateful) | Back-office web & web publik {{APP}} (TanStack Query) | Tidak diversi, berubah bersama frontend |
| POS | `/api/pos/v1/*` | **Device token** (Sanctum, abilities per tipe perangkat) + `X-Id-Kasir` + `Idempotency-Key` + `X-Versi-Aplikasi` | Aplikasi Flutter | Berversi URL (`v1`) + versi skema sinkron (`X-Skema-Sinkron`). Wajib kompatibel mundur untuk 2 versi minor aplikasi |
| Owner | `/api/pemilik/v1/*` | **User token** (Sanctum, berumur terbatas + refresh) | Aplikasi Owner Flutter | Berversi URL, kompatibel mundur 2 versi minor aplikasi (§17.3.4) |
| Publik | `/api/v1/*` | Sanctum Personal Access Token dengan scope (`produk:baca`, `penjualan:baca`, `stok:tulis`, ...) | Integrasi pihak ketiga | Semantic, deprecation ≥ 6 bulan |

### 16.2 Konvensi

- Key JSON **PascalCase Bahasa Indonesia, sama dengan nama kolom** (§13.7), tanggal ISO-8601 UTC, uang sebagai **string desimal** (`"15000.00"`) agar tidak kehilangan presisi.
- Pagination berbasis cursor untuk list besar. Filter mengikuti gaya `saring[Status]=Lunas&urut=-DibuatPada&sertakan=Detail` (spatie/laravel-query-builder, nama parameter `filter`/`sort`/`include` diganti lewat `config/query-builder.php`).
- Error format seragam:
  ```json
  { "Galat": { "Kode": "StokTidakCukup", "Pesan": "Stok Kopi Susu tidak cukup", "Detail": { "UuidProduk": "...", "Tersedia": "2.0000" } } }
  ```
- Rate limit per token/tenant (misal 120 req/menit untuk paket standar).

### 16.3 Endpoint API POS (Aplikasi Flutter)

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/pos/v1/perangkat/aktivasi` | Tukar kode aktivasi → device token, kode perangkat (`Perangkat.Kode`), info outlet |
| GET | `/api/pos/v1/konfigurasi-aplikasi` | Versi terbaru, `min_supported_version`, feature flag remote, konfigurasi outlet |
| GET | `/api/pos/v1/data-awal` | Paket data awal (dapat berupa file JSON terkompresi gzip untuk katalog besar): produk, harga, modifier, pajak, promo aktif, metode bayar, meja, pengaturan, staf + hash PIN, pelanggan yang sering datang (terbatas) |
| GET | `/api/pos/v1/perubahan?sejak={kursor}` | Delta perubahan master sejak cursor (produk/harga/promo/stok ringkas/86/staf) |
| POST | `/api/pos/v1/sinkron/kirim` | Kirim batch outbox (shift, sale, payment, cash movement, void, retur, approval). Respons per item: `accepted` / `duplicate` / `rejected` + alasan |
| POST | `/api/pos/v1/detak` | Status perangkat, versi app, platform, jumlah outbox tertunda, status printer |
| POST | `/api/pos/v1/token-notifikasi` | Daftarkan/perbarui token FCM perangkat |
| GET | `/api/pos/v1/pelanggan/cari?kata=` | Cari pelanggan di server (online) |
| POST | `/api/pos/v1/pembayaran/qris` · `GET /api/pos/v1/pembayaran/qris/{id}` | Buat QRIS dinamis & cek status |
| POST | `/api/pos/v1/persetujuan/jarak-jauh` | Minta approval jarak jauh (dikirim ke HP supervisor/owner via push) |
| GET | `/api/pos/v1/kds/tiket?stasiun=&sejak=` | Antrean tiket dapur (mode KDS) |
| POST | `/api/pos/v1/gudang/penerimaan-barang`, `/gudang/transfer-stok`, `/gudang/stok-opname` | Operasi gudang dari aplikasi |

**Kontrak API** didokumentasikan otomatis dalam OpenAPI (`Spesifikasi/OpenApi/PosV1.yaml`, dihasilkan Scramble di CI). Model DTO Dart di-generate/diverifikasi dari spesifikasi tersebut. CI gagal jika kontrak berubah tanpa kenaikan versi.

### 16.4 Webhook Keluar (X7)

Event: `penjualan.selesai`, `penjualan.divoid`, `penjualan.diretur`, `pembayaran.diterima`, `stok.menipis`, `stok.disesuaikan`, `produk.diubah`, `pelanggan.dibuat`, `pesanan-pembelian.disetujui`, `penerimaan-barang.diposting`, `shift.ditutup`.

- Payload ditandatangani HMAC-SHA256 (`X-Tanda-Tangan`), berisi `IdPeristiwa` unik untuk dedup di sisi penerima.
- Retry eksponensial (1m, 5m, 30m, 2j, 12j), dikirim oleh queue via cron.
- Log pengiriman terlihat oleh tenant, tersedia tombol "kirim ulang".

### 16.5 Integrasi Pihak Ketiga

| Integrasi | Tujuan | Pola | Fase |
|---|---|---|---|
| Payment Gateway (Midtrans / Xendit / DOKU / sejenis, **abstraksi antarmuka `GerbangPembayaran`**) | QRIS dinamis, VA, e-wallet, kartu, refund, billing SaaS | Create charge → webhook (verifikasi signature) + polling fallback | 2 |
| WhatsApp (WA Business API via BSP resmi; abstraksi `KanalPesan`) | Struk, OTP, pengingat piutang/booking, broadcast (dengan opt-in) | Queue + template pesan | 2 |
| Email (SMTP Hostinger / layanan transaksional) | Verifikasi, invoice, laporan terjadwal | Queue | 1 |
| Firebase Cloud Messaging (FCM, termasuk APNs untuk iOS) | Push ke aplikasi POS: approval jarak jauh, order online/self-order baru, stok kritis, pemicu sinkron | HTTP v1 API dari queue Laravel | 2 |
| Printer thermal | Struk, dapur | Lihat §17.6 | 1 |
| Ojol/Marketplace | Menu, stok, order | Tergantung API mitra. Awalnya input manual per channel | 3–4 |
| Software akuntansi eksternal | Export jurnal | CSV/Excel terformat, API di fase lanjut | 3 |
| Coretax/e-Faktur | Faktur pajak | Export format impor. Integrasi via PJAP di fase 4 | 3–4 |
| Ekspedisi | Ongkir & resi | Agregator ongkir | 4 |
