<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-02 · Master Regulasi & Referensi

**Tujuan:** Data acuan nasional (wilayah, pajak, hari libur, bank) dikelola terpusat, bertanggal berlaku, dan otomatis dipakai semua tenant.
**Aktor:** Konten & Legal, Keuangan (peninjau), Super Admin.
**Pemicu:** Persiapan awal, perubahan regulasi (PMK, Perda PBJT), pergantian tahun (hari libur).

**Data yang dikelola:**

| Data | Isi | Dipakai oleh |
|---|---|---|
| Wilayah | Provinsi & kabupaten/kota (kode wilayah resmi, tanpa kecamatan/desa), zona waktu (WIB/WITA/WIT). Data awal wajib dari seeder (Kepmendagri 2025, v1.74) | Profil outlet, pendaftaran F-00, tarif PBJT, zona waktu laporan |
| Tarif pajak nasional | PPN (tarif + `PengaliDpp`), jenis pajak lain | Kalkulasi penjualan & pembelian (§12) |
| Tarif pajak daerah | PBJT makanan & minuman per kabupaten/kota, aturan service charge masuk DPP | Outlet F&B sesuai kota |
| Hari libur | Libur nasional & cuti bersama per tahun | Forecast restock, jadwal kerja, laporan |
| Referensi pembayaran | Bank, e-wallet, jaringan EDC, penerbit QRIS | Pilihan metode pembayaran tenant |
| Satuan standar | pcs, kg, liter, meter, dus, dll. | Template sektor & import produk |

**Langkah (perubahan tarif pajak):**
1. Konten & Legal membuat **draf tarif baru** dengan `BerlakuMulai` (tarif lama tidak diedit, hanya diberi `BerlakuSampai`).
2. Melampirkan dasar hukum (nomor PMK/Perda, tautan dokumen).
3. **Peninjau kedua** (Keuangan/Super Admin) menyetujui (*four-eyes principle*).
4. Tarif terbit. Sistem menghitung tenant/outlet terdampak dan mengirim pemberitahuan ("Tarif PBJT Kota X berubah menjadi 10% mulai 1 Januari 2027").
5. Aplikasi POS menerima tarif baru lewat delta sinkron sebelum tanggal berlaku, sehingga perpindahan tarif tetap benar walaupun perangkat offline pada hari H.

**State Machine data master bertanggal:** `Draf → MenungguTinjauan → Terbit → (Berakhir saat BerlakuSampai lewat)`. `Ditolak` kembali ke `Draf`. Khusus hari libur: `Terbit → Dibatalkan` lewat pengajuan pembatalan yang disetujui (BR-P02.6).

**Aturan Bisnis:**
- BR-P02.1 Tarif yang sudah terbit tidak pernah diedit atau dihapus. Koreksi = tarif baru.
- BR-P02.2 Perubahan tarif nasional butuh **2 penyetuju berbeda**. Perubahan tarif daerah dan hari libur butuh **1 penyetuju**. Siapa pun yang pernah membuat, mengubah, atau mengajukan draf (penyusun) tidak boleh menyetujuinya. Satu penolakan mengembalikan data ke `Draf`.
- BR-P02.5 `BerlakuMulai` tarif tidak boleh di masa lalu saat diajukan maupun saat terbit (tarif harus sempat terkirim ke POS sebelum berlaku). Nilai awal tarif (misal PPN) dimuat dari file data sebagai draf, tidak pernah ditulis di kode.
- BR-P02.6 Hari libur terbit tidak diubah. Pembatalan (misal cuti bersama dibatalkan pemerintah) diajukan dengan alasan dan ditinjau 1 penyetuju selain pengaju; bila disetujui statusnya `Dibatalkan` dan baris tetap tersimpan. Penggeseran = pembatalan + hari libur baru.
- BR-P02.3 Tenant boleh **override** tarif daerah untuk outletnya (misal Perda baru belum masuk ke master) dengan konfirmasi dan catatan. Override terlihat di Platform Pengelola sebagai sinyal untuk memperbarui master.
- BR-P02.4 Hari libur tahun berikutnya wajib terbit paling lambat 1 Desember (pengingat otomatis ke Konten & Legal).
