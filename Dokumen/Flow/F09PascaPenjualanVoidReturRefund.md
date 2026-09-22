<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-09 · Pasca-Penjualan: Void, Retur, Refund

| Aksi | Kapan | Syarat | Efek |
|---|---|---|---|
| **Void item** (sebelum bayar) | Order terbuka, item sudah dikirim ke dapur | Alasan; PIN jika role butuh | Item ditandai void, masuk laporan void. Stok bahan tetap berkurang jika sudah diproduksi (opsi "waste"). |
| **Void transaksi** | Hari & shift yang sama, belum tutup shift | PIN supervisor + alasan | Dokumen `voided`. Stok & jurnal dibalik. Pembayaran dikembalikan (tunai keluar dari laci). |
| **Retur penjualan** | Setelah shift tutup / hari berbeda, dalam batas hari retur | Struk asli, alasan, kondisi barang (layak jual/rusak) | Dokumen retur terpisah. Stok kembali ke lokasi atau ke "Barang Rusak". Refund atau tukar barang atau jadi saldo/nota kredit. |
| **Tukar barang** | Retur + penjualan baru dalam satu layar | Sama dengan retur | Selisih harga dibayar/dikembalikan. |

**Aturan Bisnis:**
- BR-09.1 Dokumen yang sudah `paid` **tidak bisa diedit**, hanya di-void atau diretur.
- BR-09.2 Refund QRIS/kartu memakai refund gateway jika didukung. Jika tidak, dicatat sebagai refund manual (transfer).
- BR-09.3 Semua void/retur masuk **Laporan Anti-Fraud**: frekuensi per kasir, jam, nominal, dan pola (void segera setelah bayar tunai).
