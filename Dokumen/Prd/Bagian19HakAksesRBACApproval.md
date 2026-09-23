<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 19. Hak Akses (RBAC) & Approval

### 19.1 Role Default

| Role | Cakupan |
|---|---|
| **Owner** | Semua akses di semua outlet, termasuk langganan & hapus data |
| **Admin** | Semua kecuali langganan & kepemilikan |
| **Manajer Outlet** | Operasional outlet yang ditugaskan: produk (lihat/ubah harga jika diizinkan), stok, approval, laporan outlet |
| **Supervisor** | Approval di POS (void, diskon, refund, kas keluar), buka ulang shift |
| **Kasir** | POS: jual, bayar, hold, cetak, buka/tutup shift sendiri |
| **Pelayan** | Ambil order meja, kirim ke dapur, tanpa pembayaran |
| **Dapur/Barista** | KDS saja |
| **Gudang** | Penerimaan, transfer, opname, penyesuaian (butuh approval) |
| **Purchasing** | Supplier, PO |
| **Akuntan** | Keuangan, jurnal, pajak, tutup buku; baca semua laporan |
| **Apoteker** | Penjualan obat keras & input resep (RTL-PHR) |
| **Sales/Salesman** | Sales order, pelanggan miliknya, piutang pelanggan |

Owner dapat membuat role kustom dari daftar permission granular: `modul.aksi[.cakupan]`, misal `penjualan.void`, `penjualan.diskon.manual`, `persediaan.penyesuaian.setujui`, `laporan.keuangan.lihat`, `produk.harga.ubah`.

Implementasi F-02a: peran bawaan yang dibuat untuk setiap tenant adalah Owner (`Pemilik`), Admin, Manajer Outlet, Supervisor, Kasir, Gudang (`StafGudang`), Purchasing (`StafPembelian`), dan Akuntan. Pelayan, Dapur/Barista, Apoteker, dan Sales/Salesman bergantung sektor sehingga ditambahkan template sektor (F-01). Izin awal: `outlet.lihat`, `outlet.kelola`, `pengguna.lihat`, `pengguna.undang`, `pengguna.ubah`, `pengguna.nonaktifkan`, `peran.kelola`, `audit.lihat` (ditegakkan F-02), serta `produk.lihat`, `produk.kelola`, `produk.harga.ubah`, `persediaan.lihat`, `persediaan.kelola`, `persediaan.penyesuaian.setujui`, `pembelian.kelola`, `penjualan.buat`, `penjualan.void`, `penjualan.diskon.manual`, `laporan.penjualan.lihat`, `laporan.keuangan.lihat`, `akuntansi.kelola`, `langganan.kelola` (khusus Owner) yang penegakannya dibangun bersama flow masing-masing.

### 19.2 Batas & Approval yang Bisa Dikonfigurasi

| Aksi | Batas default | Approval |
|---|---|---|
| Diskon manual item/order | Kasir ≤ 10%, Supervisor ≤ 30% | PIN role lebih tinggi |
| Void item setelah kirim ke dapur | Selalu | PIN Supervisor |
| Void transaksi | Selalu | PIN Supervisor + alasan |
| Retur/refund | > Rp 0 | PIN Supervisor |
| Kas keluar | > Rp 200.000 | PIN Supervisor |
| Selisih tutup shift | > Rp 10.000 | Supervisor/Manajer |
| Penyesuaian stok | > Rp 500.000 nilai | Manajer |
| PO | > Rp 5.000.000 | Owner |
| Ubah harga jual | — | Permission `produk.harga.ubah` |
| Buka laci tanpa transaksi | Selalu dicatat | Opsional PIN |

**Approval jarak jauh (X4):** jika supervisor tidak di tempat, permintaan dikirim ke HP supervisor/owner lewat **push notification** di **Aplikasi {{APP}} Owner** (fallback: link WA) untuk disetujui dengan satu ketukan, lengkap dengan detail (kasir, item, nominal, alasan). Butuh online di kedua sisi.

### 19.3 Peran Internal Platform Pengelola

| Peran | Cakupan | Tidak boleh |
|---|---|---|
| **Super Admin** | Semua menu pengelola, akses darurat, kredensial integrasi produksi, tangguhkan tenant, persetujuan kedua | — (semua aksi tetap diaudit) |
| **Keuangan** | Paket & harga (usul), COA & pemetaan akun template sektor, tagihan, verifikasi pembayaran, refund (≤ batas), laporan MRR, komisi mitra | Akses dukungan ke data tenant, kredensial integrasi |
| **Dukungan** | Tiket, tampilan 360° tenant, perpanjang trial, override sementara, akses dukungan berizin, alat bantu | Mengubah harga paket, refund, data master pajak |
| **Teknis** | Monitoring, job gagal, rilis aplikasi, flag fitur, integrasi, menerbitkan template sektor, insiden, alat bantu teknis | Tagihan & refund |
| **Konten & Legal** | Data master regulasi (pengaju), template sektor (isi), dokumen legal, template pesan, help center | Tenant & tagihan |
| **Mitra & Penjualan** | Mitra, atribusi, perpanjang trial prospek, analitik funnel | Akses dukungan, tagihan |
| **Analis** | Baca saja: laporan platform & analitik (data agregat, tanpa data pribadi) | Semua aksi ubah |

Peran dapat digabung untuk tim kecil (misal satu orang Keuangan + Dukungan). Aturan *four-eyes* (P-02, P-08) tetap berlaku: pengaju dan penyetuju harus orang berbeda.
