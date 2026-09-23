<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-01 · Onboarding Wizard & Template Sektor

**Tujuan:** Tenant siap transaksi dalam ≤ 15 menit.
**Aktor:** Owner.

**Langkah (Wizard 6 langkah, bisa dilewati & dilanjutkan):**
1. **Profil usaha**: nama, alamat, provinsi/kota (untuk zona waktu & tarif PBJT), logo, NPWP (opsional), status PKP (ya/tidak).
2. **Pilih sektor** (bisa lebih dari satu), lalu pilih template untuk **Outlet Utama**. Yang ditampilkan hanya template berstatus `Terbit` versi terbaru dari P-03.
3. **Pajak**: sistem mengusulkan default sesuai sektor, status PKP, dan tarif kota outlet dari master P-02 (F&B: PB1/PBJT 10% + service charge opsional; Retail PKP: PPN). Owner mengonfirmasi atau mengubah.
4. **Produk awal** (pilih salah satu): (a) contoh produk dari template, (b) import Excel/CSV, (c) import dari export aplikasi lain (majoo/Moka/Pawoon/dll. via mapper kolom), (d) tambah manual cepat (nama + harga).
5. **Metode pembayaran**: tunai (default), QRIS (statis upload gambar dulu, dinamis via payment gateway nanti), EDC bank, transfer.
6. **Perangkat & printer**: daftarkan perangkat ini sebagai kasir, tes cetak struk.
7. Selesai. Tampil checklist "Langkah Berikutnya" (undang staf, stok awal, dll.) di dashboard.

**Aturan Bisnis:**
- BR-01.1 Menerapkan template bersifat **idempoten dan aditif**: menambah modul/COA/kategori yang belum ada, tidak menghapus data yang sudah ada.
- BR-01.2 COA dibuat dari gabungan COA inti + ekstensi sektor (§11.2).
- BR-01.3 Feature flag per outlet disimpan di tabel `OutletFitur` sehingga layar POS & menu menyesuaikan.

**Rincian F-01 (v1.28, diputuskan agen atas mandat pemilik produk D-12):**
- Wizard di `/kelola/panduan-awal`, izin tenant baru `panduan-awal.kelola` (bawaan Pemilik & Admin). Progres per tenant di `ProgresPanduanAwal`; setiap langkah bisa dilewati dan dilanjutkan, beranda menampilkan checklist "Langkah Berikutnya".
- Langkah 2 menerapkan template `Terbit` versi terbaru secara **idempoten & aditif** (BR-01.1): COA inti + ekstensi sektor ke `Akun` & `PemetaanAkun` (BR-01.2), `Kategori`, `Satuan` dari `SatuanStandar`, `KelompokPajak`, `OutletFitur`, dan pengaturan tenant yang belum ada. Data yang sudah ada (termasuk akun yang diganti nama tenant) tidak ditimpa atau dihapus. Mengganti template menambah, bukan membersihkan, dan UI memberi peringatan. Versi template yang diterapkan dicatat di `Outlet.IdTemplateSektorVersi` & `Outlet.TemplateSektorDiterapkanPada` (BR-P03.1). Penerapan mengunci baris tenant (urutan kunci Tenant → Langganan → Outlet → baris data) sehingga klik ganda aman.
- `OutletFitur` menyimpan **pilihan template**; fitur efektif = fitur paket ∩ `OutletFitur`, dihitung saat dibaca (`EvaluatorFitur`). Mode kasir disimpan di konfigurasi fitur `pos.retail`.
- Langkah 3 (pajak): usulan dari sektor, `Tenant.Pkp`, dan tarif PBJT kota outlet. `KelompokPajakDetail` merujuk `IdJenisPajak` (tarif efektif dicari `TarifPajakBerlaku` per kota & tanggal, tidak pernah dibekukan, CLAUDE.md #12); flag outlet di `Outlet.ProfilPajak`. Kota tanpa tarif PBJT di master boleh disimpan dengan peringatan; F-07 memperlakukannya sebagai "PBJT tidak dihitung + peringatan ke Owner", bukan galat penjualan.
- Langkah 4 (produk awal): (a) produk contoh dari kunci template `ProdukContoh` dan (d) tambah manual cepat (nama + harga + kategori opsional), dibatasi `BatasSku`. Impor Excel/CSV dan dari aplikasi lain dikerjakan di F-03 dan tidak ditampilkan di wizard. Produk cepat bertipe Stok untuk Retail/Grosir dan NonStok untuk F&B sampai ada resep; SKU boleh kosong.
- Langkah 5 (metode pembayaran): `MetodePembayaran` per tenant: Tunai (selalu ada, tidak bisa dinonaktifkan), QRIS statis (gambar di disk privat), EDC per bank (`ReferensiBank`), Transfer. `IdAkun` kosong = diturunkan dari `PemetaanAkun` sesuai jenis (Tunai → Kas Outlet, QRIS/EDC → Piutang Pencairan, Transfer → Bank). QRIS dinamis & pembacaan isi QR (NMID) menyusul F-08.
- Nama metode pembayaran unik per tenant (tanpa membedakan huruf besar/kecil) sehingga kirim ganda tidak membuat metode kembar. Langkah Profil usaha, Sektor, dan Pajak hanya berstatus Selesai lewat aksinya sendiri; tandai Selesai langsung hanya untuk Produk, Metode pembayaran, dan Perangkat. Sektor tenant (`Pengaturan.Sektor`) adalah gabungan semua template & sektor tambahan yang pernah dipilih.
- Keterbatasan diketahui: kategori/akun template yang diganti nama tenant akan ditambahkan lagi dengan nama asli bila template diterapkan ulang (aditif, bukan menimpa). Pencegahannya butuh kolom kunci asal template, dikerjakan bersama tawarkan pembaruan template (BR-P03.6).
- Langkah 6 (perangkat): memakai aktivasi F-02b; tes cetak dilakukan di Aplikasi Kasir.
- Logo usaha & gambar QRIS disimpan di disk privat dan diunduh aplikasi lewat API (F-06/F-07), tidak bergantung `storage:link`.
- Kas Outlet memakai satu akun `1-1100` dengan dimensi `JurnalDetail.IdOutlet` (BR-02.4); akun kas terpisah per outlet tetap bisa dibuat di F-13.
- Ditunda (utang tercatat): peran sektor (Pelayan, Dapur/Barista, Apoteker, Salesman) ke F-10/F-17; jam buka outlet ke halaman outlet (F-02); materialisasi StasiunDapur, AlasanVoid, AlasanPenyesuaian, LaporanUnggulan dibaca dari versi template terapan sampai flow masing-masing; "Stok awal" di checklist setelah F-05; pratinjau sandbox & tawarkan pembaruan template (BR-P03.6).
