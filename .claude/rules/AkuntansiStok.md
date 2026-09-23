---
paths:
  - "Aplikasi/Web/app/Domain/Akuntansi/**"
  - "Aplikasi/Web/app/Domain/Persediaan/**"
  - "Aplikasi/Web/app/Domain/Penjualan/**"
  - "Aplikasi/Web/app/Domain/Pembelian/**"
  - "Aplikasi/Web/app/Domain/Kasir/**"
  - "Aplikasi/Web/app/Domain/Pajak/**"
---

# Aturan akuntansi, stok & pajak (PRD §8 F-04–F-15, §11, §12)

- Setiap peristiwa berdampak keuangan menghasilkan `Jurnal` lewat `AturanPosting` sesuai tabel pemetaan §11.3. Jurnal **wajib seimbang**; tolak jika tidak.
- Jurnal otomatis tidak bisa diedit. Koreksi = batalkan dokumen sumber → jurnal pembalik otomatis.
- Stok hanya berubah lewat baris `MutasiStok` (append-only) dengan `JenisMutasi` dari enum §8 F-05. `SaldoStok` adalah cache yang bisa dibangun ulang dari ledger.
- HPP Moving Average dihitung ulang saat penerimaan barang (BR-04.2/04.3). HPP, harga, pajak, dan promo di-*snapshot* ke baris transaksi.
- Update `SaldoStok` memakai `SELECT ... FOR UPDATE` dengan urutan kunci konsisten (urut `IdProduk`).
- Urutan kalkulasi penjualan mengikuti F-07 (BrutoBaris → ... → TotalAkhir). Pajak dihitung per baris, dibulatkan per dokumen per jenis pajak. PPN memakai `PengaliDpp`.
- Periode terkunci (`KunciPeriode`) menolak transaksi bertanggal di periode itu (kecuali alur review Akuntan).
- Wajib invariant test: Σ Debit = Σ Kredit, `SaldoStok` = Σ `MutasiStok`, nilai persediaan neraca = Σ nilai stok, kas shift = ekspektasi.
