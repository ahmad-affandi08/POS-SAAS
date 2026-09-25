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

**Rincian F-16b bagian 1 (v1.60, CRM-02/03 tier & poin; keputusan pemilik produk v1.60: tier otomatis + bisa dikunci, penukaran poin sebagai diskon; rincian lain diputuskan agen atas mandat D-12):**
- Berlaku untuk tenant yang paketnya punya fitur `pelanggan.loyalti` (Pro ke atas) **dan** mengaktifkannya di **Pengaturan loyalti** (`/kelola/pelanggan/loyalti`, tabel `PengaturanLoyalti`): Rp belanja per poin (bawaan Rp 10.000, min. Rp 100), masa berlaku poin (bawaan 12 bulan, 1–60), periode evaluasi tier (bawaan 12 bulan, 1–24). Tanpa fitur: halaman tetap bisa diatur, perolehan & evaluasi tidak berjalan.
- **Tier pelanggan** (`TierPelanggan`, `/kelola/pelanggan/tier`, maks. 10 aktif): Kode (unik per tenant, huruf besar, tidak bisa diubah karena dirujuk `DaftarHarga.TierPelanggan`), Nama, MinimalBelanja (ambang belanja periode evaluasi), PengaliPoin (0,1–10), Urutan, Status Aktif/Diarsipkan. Form daftar harga memilih tier dari daftar ini (kode lama tetap diterima). `Pelanggan.IdTier`, `TierTetap` (dikunci manual, misal reseller), `TierDievaluasiPada`.
- **Buku poin** `MutasiPoin` (append-only; saldo = Σ Poin; baris positif punya `Sisa` untuk FIFO): **Perolehan** saat `Penjualan.Buat` berpelanggan diterima, di transaksi DB yang sama (idempoten per penjualan) = ⌊TotalAkhir ÷ BelanjaPerPoin × PengaliPoin tier⌋, berlaku sampai tanggal bisnis + masa berlaku. **Void** membalik sisa poin bersih penjualan; **retur** membalik proporsional: poin bersih = ⌊perolehan × (TotalAkhir − Σ TotalRefund) ÷ TotalAkhir⌋ (pembalikan tetap berjalan walau loyalti sudah dinonaktifkan; saldo boleh minus bila poinnya sudah terpakai). Pembalikan memakai lot penjualan asal lebih dulu, lalu yang paling cepat kedaluwarsa. **Penyesuaian manual** di detail pelanggan (±, alasan min. 5 karakter, maks. 100.000, tidak boleh membuat saldo minus). Perolehan poin tidak berjurnal (§11: poin menjadi diskon saat ditukar).
- **Proses malam** `pelanggan:proses-loyalti` (03.00 WIB): hanguskan sisa lot yang kedaluwarsa (baris `Kedaluwarsa`, idempoten per lot), lalu evaluasi tier: tier aktif tertinggi yang `MinimalBelanja` ≤ total belanja (tanpa void) sejak hari ini − periode; pelanggan `TierTetap` & diarsipkan dilewati; belanja di bawah semua ambang = tanpa tier. Audit `pelanggan.tier-otomatis`.
- Back-office: menu Pelanggan menjadi grup (Daftar pelanggan, Tier pelanggan, Pengaturan loyalti); daftar pelanggan menampilkan tier & poin dan bisa disaring per tier; detail pelanggan menampilkan tier & saldo poin, **Atur tier** (termasuk kunci), **Sesuaikan poin**, dan riwayat 100 mutasi poin. Audit `tier-pelanggan.*`, `pelanggan.tier`, `pelanggan.poin-sesuaikan`, `loyalti.pengaturan`.
- **POS:** hasil `GET /api/pos/v1/pelanggan` menambah `KodeTier`, `NamaTier`, `SaldoPoin` (tambahan kompatibel mundur). Memilih pelanggan menghitung ulang harga item baru di keranjang dengan `PenentuHarga` + tier (test vector `HRG-KANAL-TIER-001` sudah mencakup); melepas pelanggan kembali ke harga umum; baris pesanan meja yang sudah tersimpan memakai harga saat dipesan. Tier ikut disimpan di `PelangganLokal` (skema lokal 8) sehingga harga tier tetap berlaku offline; saldo poin hanya tampil saat online.
- **Bagian 2 (menyusul):** penukaran poin sebagai diskon pesanan sebelum pajak (J-16.4) dengan perluasan mesin kalkulasi PHP & Dart + test vector baru, wajib online (§18.4).
