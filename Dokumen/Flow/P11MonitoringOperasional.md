<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-11 · Monitoring Operasional

**Tujuan:** Tim Teknis tahu lebih dulu sebelum tenant mengeluh.
**Aktor:** Teknis.

**Dasbor operasional:**
- Detak scheduler (cron terakhir berjalan), umur job antrean tertua, job gagal (lihat detail, coba ulang, buang)
- Error rate & p95 API (POS, Owner, internal), endpoint terlambat
- Perangkat dengan outbox macet > 2 jam (semua tenant), galat sinkron terbanyak
- Webhook keluar gagal, status integrasi (P-05)
- Backup terakhir yang berhasil & hasil uji restore terakhir
- Ukuran database per tenant, tenant dengan beban tertinggi, pemakaian disk/inode hosting
- Crash-free sessions per platform & versi aplikasi

**Alert:** ambang batas per metrik → email/WA/Telegram ke Teknis yang bertugas, dengan tautan ke *runbook*.

**Manajemen insiden:** catat insiden (tingkat, dampak, kronologi), perbarui **halaman status publik** (`status.{{app}}.id`, di-hosting terpisah dari Hostinger agar tetap hidup saat server bermasalah), post-mortem setelah selesai.

**Aturan Bisnis:**
- BR-P11.1 Umur job tertua > 5 menit atau scheduler tidak berdetak > 3 menit = alert kritis (§20.3).
- BR-P11.2 Insiden yang berdampak ke > 10% tenant aktif wajib diumumkan di halaman status dalam 15 menit.
