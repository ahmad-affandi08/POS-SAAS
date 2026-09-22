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
