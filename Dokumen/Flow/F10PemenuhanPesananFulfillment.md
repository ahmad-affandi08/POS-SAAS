<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-10 · Pemenuhan Pesanan (Fulfillment)

- **F&B Dine-in/Takeaway:** order dikirim ke **KDS** atau **printer dapur** per *station* (Dapur, Bar, Pastry) berdasarkan kategori produk. Status item: `Antre → Dimasak → Siap → Disajikan`. Tampilkan timer & warna (hijau < 10 menit, kuning, merah).
- **Nomor antrian / pager:** layar *Customer Display* menampilkan nomor siap.
- **Pengiriman (retail/grosir):** status `SiapKemas → Dikemas → Dikirim → Diterima`, surat jalan, kurir internal/pihak ketiga, bukti foto.
- **Pre-order & Pesanan kustom (bakery, percetakan):** DP, tanggal ambil, status produksi, pelunasan saat ambil.
- **Laundry:** status `Diterima → Dicuci → Dikeringkan → Disetrika → Siap → Diambil`, notifikasi WA saat `Siap`.
- **Bengkel:** Perintah Kerja `Masuk → Diagnosis → MenungguPersetujuan → Dikerjakan → Qc → Selesai → Diambil`.
