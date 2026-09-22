<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-18 · Karyawan: Jadwal, Absensi, Komisi

- **Jadwal shift kerja** mingguan per outlet.
- **Absensi:** clock-in/out dari aplikasi POS di perangkat outlet dengan PIN + **selfie** (kamera native), atau dari aplikasi {{APP}} di HP pribadi dengan geofence (GPS native, deteksi mock location di Android).
- **Komisi:** aturan per produk/kategori/layanan (persentase atau nominal), per staf yang ditugaskan di baris transaksi (salon, bengkel, sales grosir). Bisa dibagi ke beberapa staf.
- **Target penjualan** per karyawan/outlet + progres.
- **Rekap gaji** (fase 2): gaji pokok + komisi + lembur − potongan (kasbon, selisih kas yang dibebankan). Export ke Excel/transfer. PPh 21 & BPJS di fase 4.
- **Kasbon karyawan:** dicatat sebagai piutang karyawan, dipotong otomatis dari rekap gaji.
