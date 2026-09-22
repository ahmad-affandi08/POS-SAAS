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
