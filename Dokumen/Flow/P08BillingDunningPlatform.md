<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-08 · Billing & Dunning Platform

**Tujuan:** Tagihan langganan terbit tepat waktu, pembayaran tercatat benar, dan tunggakan ditangani konsisten. Ini sisi pengelola dari F-19.
**Aktor:** Keuangan, Sistem (cron).

**Langkah:**
1. Cron harian membuat tagihan untuk langganan yang periodenya akan berakhir (H-7), termasuk add-on, kupon, proration, dan **PPN** atas jasa langganan (sesuai status PKP {{APP}}).
2. Tagihan dikirim via email & WA dan tampil di back-office tenant.
3. Pembayaran:
   - **Gateway** (VA/QRIS/e-wallet/kartu): webhook → verifikasi → `Lunas` → periode diperpanjang otomatis.
   - **Transfer manual** (Fase 0–1): tenant mengunggah bukti → Keuangan memverifikasi di antrean "Menunggu Verifikasi" → `Lunas`.
4. **Dunning:** pengingat H-7, H-3, H0, H+3. Setelah jatuh tempo status langganan `Tertunggak` (masa tenggang 7 hari), lalu `Ditangguhkan`.
5. **Refund/kredit:** nota kredit untuk tagihan berikutnya, atau refund ke rekening.
6. **Faktur pajak** untuk tenant PKP yang meminta (export format Coretax, fase 3).
7. **Laporan platform:** MRR, ARR, churn (logo & pendapatan), ARPA, piutang langganan & umurnya, pendapatan per paket, per sektor, dan per mitra.

**State Machine `TagihanLangganan.Status`:** `Draf → Terbit → Lunas`. `Terbit → JatuhTempo → Dihapuskan`. `Terbit → Dibatalkan`. `Lunas → Dikembalikan` (refund).

**Aturan Bisnis:**
- BR-P08.1 Nomor tagihan platform berurutan tanpa celah per tahun (kebutuhan pajak).
- BR-P08.2 Refund di atas Rp 1.000.000 butuh persetujuan kedua (Super Admin).
- BR-P08.3 Pembukuan pendapatan platform dapat dilakukan dengan menjadikan {{APP}} sendiri sebagai **tenant internal** (*dogfooding*), atau diexport ke software akuntansi.

**Rincian Fase 0 — tagihan manual & verifikasi bukti transfer** (keputusan agen atas mandat pemilik produk, 24/09/2026; menunggu konfirmasi):
- BR-P08.4 Owner membuat tagihan sendiri di back-office (`/kelola/langganan`): paket Aktif selain Gratis dan harga negosiasi, siklus Bulanan/Tahunan. Hanya **satu tagihan terbuka** (Terbit/JatuhTempo) per tenant. Saat langganan `Aktif` hanya perpanjangan paket berjalan (termasuk paket yang sudah diarsipkan, BR-P04.2); ganti paket saat Aktif menunggu proration F-19. Tagihan terbuka tanpa bukti yang sedang diverifikasi boleh dibatalkan Owner (nomor tetap terpakai).
- BR-P08.5 Nomor tagihan `INV/{Tahun}/{Bulan}/{Urut 6 digit}` (awalan konfigurasi); urut berjalan per tahun tanpa celah, penghitung dikunci di transaksi yang sama sehingga percobaan gagal tidak memakan nomor.
- BR-P08.6 Kalkulasi: Subtotal = harga siklus dari `HargaPaket` berlaku (grandfathering memakai tanggal mulai berlangganan paket yang sama dari tagihan lunas terakhir). Diskon kupon: Persen = Subtotal × Nilai% × BulanDiskon ÷ JumlahBulan; Nominal = Nilai per bulan × BulanDiskon; dibulatkan ke rupiah terdekat, maksimal Subtotal. DPP = ⌊(Subtotal − Diskon) × PengaliDpp⌋, PPN = ⌊DPP × Tarif⌋ (rupiah penuh, dibulatkan ke bawah), Total = Subtotal − Diskon + PPN. Tarif dari `TarifPajak` Ppn terbit; bila belum terbit tagihan tidak bisa dibuat. Status PKP {{APP}} dan rekening tujuan dari konfigurasi (`config/tagihan.php`), bukan kode.
- BR-P08.7 Kupon: `Kuota` = jumlah tenant berbeda yang boleh memakai; `DurasiBulan` = total bulan berdiskon per tenant (tagihan tahunan memakai sebagian bulannya). Pemakaian dicatat saat tagihan terbit dan dilepas saat tagihan dibatalkan.
- BR-P08.8 Jatuh tempo: aktivasi = terbit + 7 hari (konfigurasi); perpanjangan = akhir periode berjalan. Bukti transfer JPG/PNG/WEBP/PDF maks 5 MB (jenis diperiksa dari isi berkas), disimpan privat dan hanya disajikan ke Owner tenant itu serta Keuangan/Super Admin (setiap pembukaan oleh pengelola diaudit). Jumlah transfer harus sama dengan total (pembayaran sebagian belum didukung); satu bukti `Menunggu` per tagihan, bukti ditolak boleh diganti.
- BR-P08.9 Verifikasi oleh Keuangan/Super Admin tanpa persetujuan kedua (four-eyes P-08 hanya untuk refund, BR-P08.2); jumlah yang masuk di mutasi rekening wajib diisi dan harus sama dengan total. Diterima → tagihan `Lunas` → `Langganan` `Aktif` dengan paket & siklus tagihan; periode perpanjangan menyambung dari akhir periode berjalan (Aktif/Tertunggak), selain itu mulai saat diterima. Ditolak wajib beralasan. Owner diberi email untuk keduanya; semua tercatat di `LogAuditPengelola` dengan IdTenant. Penangguhan manual Super Admin (BR-P07.4: penipuan, permintaan hukum) tidak bisa dicabut lewat tagihan: selama itu Owner tidak bisa membuat tagihan dan Keuangan tidak bisa menerima pembayaran (v1.23). Tenant berpenanda `Uji`/`Demo`/`Internal` dikecualikan dari penjadwal tunggakan. Urutan kunci Langganan → Tagihan → Pembayaran berlaku untuk unggah bukti, verifikasi, dan penjadwal.
- BR-P08.10 Tagihan `JatuhTempo` tetap bisa dilunasi atau dibatalkan (`JatuhTempo → Lunas/Dibatalkan`). Penjadwal tiap jam: tagihan Terbit lewat jatuh tempo → `JatuhTempo`; langganan Aktif lewat `PeriodeSelesai` → `Tertunggak`; Tertunggak lewat masa tenggang 7 hari → `Ditangguhkan`, kecuali ada bukti transfer yang sedang diverifikasi.
- Ditunda: pengingat dunning (email/WA), tagihan otomatis H-7, gateway, add-on, proration, nota kredit & refund, faktur pajak, laporan MRR, tagihan Rp 0 (kupon 100%), pembayaran sebagian.
