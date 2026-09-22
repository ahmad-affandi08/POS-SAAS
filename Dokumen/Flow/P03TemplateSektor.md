<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-03 · Template Sektor

**Tujuan:** Setiap sektor usaha (§5.1) punya paket konfigurasi siap pakai yang dipelihara terpusat dan berversi.
**Aktor:** Konten & Legal (isi bisnis), Keuangan (COA & pemetaan akun), Teknis (validasi).
**Pemicu:** Persiapan awal (3 template MVP: RTL-GEN, FNB-CAF, FNB-QSR), penambahan sektor baru, perbaikan template.

**Isi satu template:**
- Modul/fitur aktif (kunci fitur) dan **mode kasir** default
- **COA** (inti + ekstensi sektor) dan **pemetaan akun** untuk semua jenis peristiwa (§11.3)
- Kategori contoh, produk contoh (opsional), satuan, kelompok pajak default
- Pengaturan default: pembulatan, service charge, stok boleh minus, metode HPP
- Stasiun dapur default (F&B), alasan void/penyesuaian default, laporan unggulan di dasbor

**Langkah:**
1. Buat template baru atau **duplikasi** versi terbit untuk membuat versi baru (status `Draf`).
2. Ubah isi melalui editor terstruktur (bukan JSON mentah).
3. **Validasi otomatis:** COA seimbang & tanpa kode ganda, setiap kunci `PemetaanAkun` terisi, kelompok pajak merujuk tarif yang ada di P-02, fitur yang diaktifkan ada di katalog P-04.
4. **Pratinjau sandbox:** sistem membuat tenant uji sementara, menjalankan onboarding F-01 dengan template ini, dan membuat beberapa transaksi contoh untuk memeriksa jurnal & laporan.
5. Terbitkan. Versi sebelumnya menjadi `Usang` untuk tenant baru.
6. (Opsional) **Tawarkan pembaruan** ke tenant yang memakai versi lama. Tenant memilih "Terapkan", dan penerapannya bersifat aditif (BR-01.1).

**State Machine `TemplateSektorVersi.Status`:** `Draf → Terbit → Usang`.

**Aturan Bisnis:**
- BR-P03.1 Tenant menyimpan versi template yang diterapkan. Versi baru **tidak pernah** mengubah data tenant tanpa persetujuan tenant.
- BR-P03.2 Versi yang sedang dipakai tenant tidak bisa dihapus.
- BR-P03.3 Template tidak bisa terbit jika validasi otomatis gagal.
