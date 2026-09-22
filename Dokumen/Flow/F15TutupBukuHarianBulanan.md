<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-15 · Tutup Buku (Harian & Bulanan)

- **Tutup Harian (End of Day)** per outlet: memastikan semua shift tertutup, sinkron offline tuntas, lalu membuat ringkasan harian (tabel agregat `RingkasanPenjualanHarian` untuk laporan cepat).
- **Tutup Bulan:** kunci periode (tabel `KunciPeriode`). Transaksi dengan tanggal di periode terkunci ditolak, kecuali oleh Akuntan dengan *reopen* yang dicatat audit. Jurnal penyesuaian (penyusutan, akrual) diposting.
- **Tutup Tahun:** jurnal penutup: saldo pendapatan & beban → Laba Ditahan.
