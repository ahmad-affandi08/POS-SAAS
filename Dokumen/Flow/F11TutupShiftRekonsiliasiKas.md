<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-11 · Tutup Shift & Rekonsiliasi Kas

**Langkah:**
1. Kasir menekan "Tutup Shift". Sistem **tidak menampilkan** jumlah kas seharusnya (blind close, bisa dikonfigurasi).
2. Kasir menghitung uang per pecahan dan input total non-tunai (opsional, untuk cocokkan slip EDC).
3. Sistem menghitung **selisih** = kas aktual − (modal awal + penjualan tunai + kas masuk − kas keluar − refund tunai).
4. Selisih melebihi toleransi → wajib alasan + approval supervisor.
5. Cetak **Laporan Shift (X/Z report)**: ringkasan penjualan, per metode bayar, void, diskon, pajak, kas.
6. (Opsional) **Setoran**: kas disetor ke brankas/bank. Dokumen setoran memindahkan saldo Kas Laci → Kas Brankas/Bank.

**Dampak Jurnal:** selisih kurang → Dr Beban Selisih Kas, Cr Kas; selisih lebih → Dr Kas, Cr Pendapatan Lain (Selisih Kas).

**Rincian F-11 (v1.45, diputuskan agen atas mandat D-12):**
- Aplikasi POS: "Tutup shift" di layar Shift. Tutup buta bawaan (`TutupShiftButa`, pengaturan kasir) — kas seharusnya tidak ditampilkan sebelum kasir menyimpan hitungan. Kasir mengisi hitungan pecahan (Rp 100.000 s.d. Rp 100) atau total kas aktual, dan opsional total non-tunai per metode (pencocokan slip EDC/QRIS). Pesanan tertahan harus diselesaikan atau dibatalkan dulu.
- Kas seharusnya = kas awal + penjualan tunai bersih (tunai diterima − kembalian) + kas masuk − kas keluar − setoran − refund tunai (void & retur). Selisih = kas aktual − kas seharusnya. |Selisih| > `ToleransiSelisihKas` (pengaturan kasir, bawaan Rp 10.000, §19.2) → wajib alasan + PIN penyetuju ber-izin baru `shift.selisih.setujui` (bawaan Supervisor, Manajer Outlet, Admin).
- Item outbox `Shift.Tutup` `{UuidShift, UuidPengguna, DitutupPada, KasAktual, PecahanKasAkhir|null, NonTunai [{UuidMetodePembayaran, Jumlah}], Alasan|null, UuidPenyetuju|null, Ringkasan {KasSeharusnya, Selisih}}`; dikirim setelah semua penjualan/kas shift itu (outbox FIFO). Server menghitung ulang kas seharusnya dari datanya; beda dengan perangkat → tetap diterima dengan angka server + `PerluTinjauan` (`KasSeharusnyaBerbeda`). Shift menjadi `Tertutup` (`DitutupOleh`, `DitutupPada`, `KasSeharusnya`, `KasAktual`, `Selisih`, `PecahanKasAkhir`, `RingkasanNonTunai` JSON, `AlasanSelisih`, `IdPenyetujuSelisih`). Jurnal selisih di transaksi yang sama: kurang J-11.1 (Dr `BebanSelisihKas`, Cr `KasOutlet`), lebih J-11.2 (Dr `KasOutlet`, Cr `PendapatanLain`). Penjualan/kas yang tiba setelah shift ditutup tetap diterima dengan `PerluTinjauan` (`ShiftSudahDitutup`).
- Laporan shift: **X** (ringkasan berjalan, dapat dibuka kapan saja di aplikasi) dan **Z** (setelah tutup): jumlah transaksi, penjualan kotor/diskon/bersih, pajak, per metode bayar, void & retur, kas masuk/keluar/setoran, kas seharusnya/aktual/selisih. Back-office detail shift menampilkan ringkasan yang sama. Buka ulang shift oleh supervisor menyusul.
