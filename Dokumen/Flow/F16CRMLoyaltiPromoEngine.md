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

**Rincian F-16a (v1.59, CRM-01 data pelanggan; diputuskan agen atas mandat D-12):** F-16 dipecah: **F-16a** data pelanggan (P0), **F-16b** tier, level harga & poin loyalti (CRM-02/03), **F-16c** promo engine & voucher (CRM-05/06, test vector PHP & Dart bersama), **F-16d** deposit & paket sesi (CRM-04).
- **Pelanggan** (domain Pelanggan): Nama, **NoHp** (wajib; disimpan angka saja berawalan kode negara: `0812…`/`+62 812…`/`812…` → `62812…`, 10–15 digit; unik per tenant = kunci pelanggan), Email, TanggalLahir, Alamat, Tag (maks. 10), Catatan, SetujuPemasaran (persetujuan menerima promo, UU PDP), Status `Aktif`/`Diarsipkan` (pelanggan tidak dihapus karena dirujuk penjualan; diarsipkan tidak muncul di pencarian kasir). Nomor HP ditampilkan `0812-3456-7890` di back-office dan **tersamar** (`0812****7890`) di POS & log audit.
- **Back-office** `/kelola/pelanggan` (menu Pelanggan): `TabelData` (cari nama/nomor HP/email, saring Status & Tag, ringkasan jumlah transaksi, total belanja tanpa void & sebelum retur, terakhir belanja), tambah/ubah (`NoHpSudahTerdaftar`, `NoHpTidakValid`), arsipkan/pulihkan, dan detail dengan 50 transaksi terakhir (tautan ke detail penjualan bila ber-izin `laporan.penjualan.lihat`). Detail penjualan menampilkan pelanggannya. Izin baru `pelanggan.lihat` (bawaan Pemilik, Admin, Manajer Outlet, Supervisor, Akuntan) & `pelanggan.kelola` (Pemilik, Admin, Manajer Outlet); tenant lama lewat `organisasi:siapkan-peran`. Audit `pelanggan.tambah|ubah|arsipkan|pulihkan`.
- **POS:** kasir (cukup `penjualan.buat`) memilih pelanggan lewat baris pelanggan di keranjang atau **F2**: cari online `GET /api/pos/v1/pelanggan?kata=` (min. 3 karakter, nama atau nomor HP, maks. 20, nomor tersamar); offline = cari di pelanggan yang pernah dipakai perangkat (tabel lokal `PelangganLokal`, nomor tersamar saja). Pelanggan baru (nama + nomor HP) bisa dibuat offline: item outbox **`Pelanggan.Buat`** `{Nama, NoHp, Email?, UuidPengguna, DibuatPada}` (Uuid item = Uuid pelanggan) dikirim sebelum penjualannya (FIFO). Idempoten per Uuid; nomor HP yang ternyata sudah terdaftar (dibuat perangkat lain selagi offline) **tidak ditolak**: Uuid perangkat dicatat sebagai alias (`PelangganAlias`) pelanggan lama. `Penjualan.Buat` menerima `UuidPelanggan?` (Uuid atau alias → `Penjualan.IdPelanggan`); tidak dikenal = tetap diterima tanpa pelanggan + `PerluTinjauan` `PelangganTidakDikenal`. Pelanggan ikut tersimpan di pesanan tertahan dan pesanan meja; transaksi baru kembali ke pelanggan umum.
- **Belum di F-16a:** tier & level harga pelanggan di penentu harga, poin, limit kredit (F-12), ulang tahun & broadcast (CRM-07), impor/ekspor pelanggan, gabung pelanggan ganda, struk bernama pelanggan (menunggu cetak struk).
