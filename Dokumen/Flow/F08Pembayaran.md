<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-08 · Pembayaran

**Metode yang didukung:**

| Metode | Mekanisme | Offline? | Fase |
|---|---|---|---|
| Tunai | Input uang diterima → kembalian, tombol pecahan cepat (Rp 20rb, 50rb, 100rb, uang pas) | Ya | 1 |
| QRIS Statis | Tampilkan QR statis merchant, kasir konfirmasi manual (+ foto bukti opsional) | Ya | 1 |
| QRIS Dinamis | Dibuat via payment gateway (Midtrans/Xendit/DOKU/dll.), status otomatis via webhook + polling | Tidak | 2 |
| EDC (debit/kredit) | Kasir pilih bank/EDC, input no. approval/4 digit kartu | Ya | 1 |
| E-wallet / VA / Transfer | Konfirmasi manual atau via gateway | Manual: Ya | 1–2 |
| Piutang (Tempo) | Hanya untuk pelanggan terdaftar dengan limit kredit | Ya (cek limit dari cache) | 2 |
| Deposit / Saldo Member | Potong saldo prabayar pelanggan | Terbatas (cache saldo) | 2 |
| Poin Loyalti | Tukar poin sebagai potongan | Terbatas | 2 |
| Voucher / Gift Card | Kode voucher tervalidasi | Terbatas | 2 |
| Platform Ojol | GoFood/GrabFood/ShopeeFood sebagai metode (settlement dari platform) | Ya | 2 |

**Aturan Bisnis:**
- BR-08.1 **Split payment** diizinkan (misal Rp 50rb tunai + sisa QRIS).
- BR-08.2 **Split bill** (F&B): per item, per orang (bagi rata), atau per nominal. Menghasilkan beberapa dokumen pembayaran untuk satu order.
- BR-08.3 Setiap metode pembayaran terhubung ke **akun kas/bank/clearing** di COA. Contoh: QRIS → "Piutang Pencairan QRIS" sampai dana cair ke rekening.
- BR-08.4 MDR/biaya (QRIS, EDC, ojol) dicatat otomatis sebagai beban saat settlement (§11).
- BR-08.5 QRIS dinamis: timeout default 15 menit. Jika webhook terlambat, kasir bisa "Cek Status". Pembayaran ganda terdeteksi via kolom unik `PenjualanPembayaran.RefEksternal`.
- BR-08.6 Pembulatan tunai hanya untuk bagian tunai.

**Rincian QRIS dinamis (v2.05; rincian diputuskan agen atas mandat D-12, penyedia dipilih di konsol P-05 v2.04):**
- **Alur kasir (wajib online):** kasir memilih metode berjenis `QrisDinamis` → aplikasi membuat tagihan lewat `POST /api/pos/v1/qris` `{Uuid, UuidMetode, Jumlah, Keterangan?}` (Uuid ULID dibuat perangkat; permintaan ulang dengan Uuid sama = tagihan yang sama, tanpa memanggil gerbang lagi) → server memanggil gerbang aktif dan mengembalikan `{Uuid, NomorPesanan, IsiQr, HalamanBayar, KedaluwarsaPada, Status, Jumlah}` → QR digambar di perangkat (juga di layar pelanggan) dengan hitung mundur → aplikasi memantau `GET /api/pos/v1/qris/{uuid}` tiap 2 detik; server memanggil `CekStatus` gerbang paling sering sekali per 5 detik per tagihan (bila webhook terlambat) → `Lunas` → pembayaran dicatat dengan `Referensi` = Uuid tagihan. Kasir **tidak bisa** menandai lunas sendiri. Batal → `POST .../batal` (bila ternyata sudah lunas: 409 `SudahLunas`, pembayaran tetap dipakai). Offline/gerbang gagal → pesan jelas, saran QRIS statis/tunai.
- **Tabel `TagihanQris`:** IdTenant, IdOutlet, IdPerangkat, IdMetodePembayaran, Uuid, NomorPesanan (unik global, `PY{IdTenant basis-36}-{Uuid}`, dipakai untuk memulihkan tenant dari webhook), Jumlah, JumlahDiterima, Penyedia, IdReferensi gerbang, IsiQr, HalamanBayar, Status (`Menunggu/Lunas/Kedaluwarsa/Gagal/Dibatalkan`, riwayat di `RiwayatStatusDokumen`), KedaluwarsaPada (15 menit atau lebih cepat menurut gerbang), LunasPada, TerakhirDicekPada, UuidPenjualan (unik per tenant bila terisi).
- **Webhook** `POST /webhook/{midtrans|xendit|tripay|duitku|ipaymu|doku}` (tanpa CSRF, batas 300/menit): hanya penyedia yang aktif, tanda tangan diverifikasi (401 bila salah); notifikasi ulang idempoten; jumlah berbeda → tagihan tetap Menunggu + `LogAudit` `tagihan-qris.jumlah-berbeda`; pembayaran yang datang setelah status akhir tetap membuat `Lunas` (dengan peringatan log).
- **Penautan ke penjualan** (di transaksi `Penjualan.Buat`, baris tagihan dikunci): tagihan dikenal & belum dipakai → `TagihanQris.UuidPenjualan` diisi dan `PenjualanPembayaran.RefEksternal` = NomorPesanan (indeks unik mencegah pembayaran ganda, BR-08.5). Masalah tidak menolak penjualan (offline-first) tetapi menjadi alasan tinjauan: `QrisDinamisTidakDikenal`, `QrisDinamisDipakaiUlang`, `QrisDinamisBelumLunas`, `QrisDinamisJumlahBerbeda`. Jurnal sama dengan non-tunai lain (akun kliring metode / Piutang Pencairan, J-07.1).
- QRIS dinamis tidak dipakai untuk uang muka pre-order dan tamu self-order (menyusul). Pembayaran QRIS dinamis yang sudah lunas tidak bisa dihapus kasir dari daftar pembayaran; bila penyimpanan gagal, kasir menyelesaikan ulang tanpa QR baru.
- **Belum:** pencatatan MDR saat settlement & rekonsiliasi settlement (BR-08.4), refund/batal di sisi gerbang (BR-09.2 tetap refund manual), tugas terjadwal pengedaluwarsa tagihan (saat ini diterapkan ketika dibaca).
- **Catatan regulasi:** lihat catatan P-05 v2.04 (model akun gerbang platform vs sub-merchant vs akun milik tenant) — menunggu keputusan pemilik produk.
