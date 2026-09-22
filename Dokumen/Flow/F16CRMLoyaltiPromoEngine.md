<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-16 · CRM, Loyalti & Promo Engine

**Pelanggan:** nama, HP (kunci utama), email, tanggal lahir, alamat, tag, tier (Regular/Silver/Gold), level harga (retail/reseller/grosir), limit kredit, riwayat transaksi, saldo deposit, poin.

**Loyalti:**
- Perolehan poin: per Rp X belanja = 1 poin, pengali per tier/kategori/hari.
- Penukaran: poin → potongan atau hadiah produk.
- Poin kadaluarsa (misal 12 bulan, FIFO).
- Naik/turun tier otomatis berdasarkan belanja N bulan terakhir.
- **Membership/paket sesi** (salon, gym, laundry langganan): beli paket 10× potong rambut, sisa sesi terpakai per kunjungan (pendapatan diakui per sesi).

**Promo Engine (X3):**

```yaml
promo:
  name: "Happy Hour Kopi 2 Rp 30rb"
  period: { start: 2026-10-01, end: 2026-12-31, days: [mon,tue,wed,thu,fri], time: "14:00-17:00" }
  scope: { outlets: [JKT1, JKT2], channels: [dine_in, takeaway] }
  eligibility: { member_tiers: [any], min_subtotal: 0 }
  conditions:
    - type: buy_items          # beli item dari kategori
      category: "Kopi"
      qty: 2
  actions:
    - type: fixed_price_bundle # 2 item tersebut jadi Rp 30.000
      amount: 30000
  limits: { per_transaction: 3, per_customer_per_day: null, total_quota: 1000 }
  stacking: { priority: 10, exclusive: false, combinable_with: ["member_discount"] }
  funding: { cost_center: "Marketing", supplier_share_pct: 0 }
```

- **Tipe kondisi:** item/kategori/brand tertentu, min subtotal, min qty, tier member, ulang tahun, transaksi pertama, channel, metode bayar (promo bank/QRIS), kode voucher.
- **Tipe aksi:** diskon % / nominal (item atau order), harga spesial, Buy X Get Y (gratis/diskon), bundling harga tetap, gratis ongkir, poin berlipat.
- **Resolusi konflik:** urut prioritas; `exclusive` menghentikan evaluasi; algoritma memilih kombinasi yang **paling menguntungkan pelanggan** dalam batas aturan stacking (opsi tenant: "best for customer" atau "prioritas ketat").
- Promo dievaluasi **di aplikasi POS (engine Dart, bisa offline) dan divalidasi ulang di server (PHP)** memakai spesifikasi dan test vector yang sama.
- Laporan efektivitas promo: jumlah pakai, nilai diskon, uplift penjualan.

**Voucher:** kode tunggal/massal, sekali pakai/berulang, masa berlaku, distribusi via WA/broadcast (fase 3).
