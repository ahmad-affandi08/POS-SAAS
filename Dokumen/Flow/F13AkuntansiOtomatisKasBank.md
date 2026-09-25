<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-13 · Akuntansi Otomatis & Kas/Bank

- Setiap domain event yang punya dampak keuangan menghasilkan **Jurnal** melalui layanan `LayananPostingJurnal` memakai **Aturan Posting** (pemetaan event → akun) yang dapat dikonfigurasi per tenant (§11.3).
- **Kas & Bank:** akun kas per outlet, rekening bank, transfer antar akun, penerimaan/pengeluaran lain, **rekonsiliasi bank** (import mutasi CSV, fase 3).
- **Biaya operasional:** input pengeluaran (listrik, sewa, gaji) dengan kategori beban & lampiran.
- **Jurnal manual/umum** hanya untuk role Akuntan/Owner, wajib seimbang.
- **Aset tetap & penyusutan** (fase 3): garis lurus, jurnal penyusutan bulanan otomatis.

**Rincian F-13a (v1.48, fase 1; diputuskan agen atas mandat D-12):**
- Jurnal otomatis penjualan, void, retur, kas shift, dan selisih tutup shift sudah diposting oleh flow masing-masing (F-06–F-11) di transaksi yang sama (aturan #10). F-13a menambah pengelolaan dan laporannya di back-office; izin `akuntansi.kelola` (ubah) dan `laporan.keuangan.lihat` (lihat).
- **Bagan akun** `/kelola/akuntansi/akun`: daftar pohon (`TabelData` mode lokal/server) dengan kode, nama, tipe, saldo normal, status; tambah akun anak & ubah nama/status. Kode unik per tenant; akun yang sudah punya jurnal atau dipakai pemetaan/metode bayar/kategori kas tidak bisa dihapus (hanya dinonaktifkan); tipe akun tidak bisa diubah setelah ada jurnal.
- **Pemetaan akun** `/kelola/akuntansi/pemetaan`: setiap `PeranAkun` → akun (per tenant, override per outlet), dengan validasi tipe akun yang sama seperti BR-P03.3; perubahan dicatat di log audit dan hanya berlaku untuk jurnal berikutnya.
- **Transaksi kas & bank** `/kelola/akuntansi/kas-bank` (dokumen `TransaksiKasBank`, nomor `KB/{YYYY}/{MM}/{SEQ4}`, append-only; koreksi dengan dokumen pembalik): `Pengeluaran` (akun kas/bank sumber → akun beban/aset, kategori beban, keterangan, lampiran opsional), `Penerimaan` (akun pendapatan lain/ekuitas/lainnya → akun kas/bank), `Transfer` (kas/bank → kas/bank, termasuk setoran brankas ke bank). Jurnal diposting saat simpan (`JenisSumberJurnal::TransaksiKasBank`); periode terkunci ditolak. Akun kas/bank = akun bertipe Kas/Bank (aset lancar) di bagan akun. Daftar saldo per akun kas/bank.
- **Laporan keuangan** (dari `JurnalDetail`, saring periode & outlet, ekspor CSV): **Buku besar** per akun (saldo awal, mutasi, saldo berjalan, tautan ke dokumen sumber), **Neraca saldo** (per akun: saldo awal, debit, kredit, saldo akhir; Σ debit = Σ kredit ditampilkan), **Laba rugi** (pendapatan − HPP = laba kotor − beban = laba bersih, per kelompok tipe akun, perbandingan periode sebelumnya). Neraca & arus kas menyusul (P1).
