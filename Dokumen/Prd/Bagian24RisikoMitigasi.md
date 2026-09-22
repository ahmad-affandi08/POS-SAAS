<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 24. Risiko & Mitigasi

| # | Risiko | Kemungkinan | Dampak | Mitigasi |
|---|---|---|---|---|
| R1 | Batas sumber daya shared hosting tercapai saat tenant bertambah | Tinggi | Tinggi | Mulai di Cloud Hosting. Offline-first & polling adaptif. Monitoring entry process/CPU. Driver via `.env`. Rencana migrasi VPS di Fase 3 dengan pemicu terukur (CPU > 70% berkelanjutan, error 503/508). |
| R2 | Cron/queue macet sehingga notifikasi & webhook tertunda | Sedang | Sedang | Operasi kritis sinkron. Alert umur job. `withoutOverlapping` + `max-time`. |
| R3 | Konflik/duplikasi data offline | Sedang | Tinggi | ULID klien, idempotency key, outbox FIFO, test E2E offline, dashboard outbox per perangkat. |
| R4 | Selisih kalkulasi klien vs server | Sedang | Tinggi | Test vector bersama, server re-kalkulasi dan menyimpan selisih (jika ada) untuk investigasi. |
| R5 | Perubahan regulasi pajak | Tinggi | Sedang | Tarif berbasis tanggal efektif, konsultan pajak sebagai reviewer, fitur tax rate dikelola Super Admin. |
| R6 | Fragmentasi printer & hardware (merek, protokol, Bluetooth Classic tidak didukung iOS) | Tinggi | Sedang | Abstraksi `TransportPrinter`, Hardware Compatibility List, rekomendasi printer LAN/BLE untuk iPad, fallback printer sistem, lab uji perangkat. |
| R13 | Review App Store/Play memperlambat rilis perbaikan kritis | Sedang | Tinggi | Feature flag remote (`konfigurasi-aplikasi`), perbaikan logika bisnis sebisa mungkin di server, TestFlight/track internal untuk hotfix, rilis desktop via auto-updater lebih cepat. |
| R14 | Aplikasi versi lama masih beredar & tidak kompatibel dengan API baru | Tinggi | Tinggi | API POS berversi, kompatibel mundur 2 versi minor, `min_supported_version` + pengiriman outbox tetap diizinkan sebelum update wajib. |
| R15 | Batasan background di iOS (sinkron tertunda saat aplikasi di latar) | Sedang | Sedang | Sinkron saat aplikasi aktif, anjuran kiosk/Guided Access untuk iPad kasir, indikator outbox tertunda, push sebagai pemicu. |
| R17 | Penyalahgunaan akses internal (pengelola melihat/mengubah data tenant tanpa hak) | Rendah | Sangat tinggi | Akses dukungan berizin tenant & berbatas waktu, `LogAuditPengelola` append-only, 2FA wajib, peran minimum, akses darurat hanya Super Admin + notifikasi Owner, test arsitektur, tinjauan log berkala. |
| R18 | Data master salah (tarif pajak, template) berdampak ke banyak tenant sekaligus | Sedang | Tinggi | Alur tinjauan dua orang (P-02), validasi otomatis & sandbox template (P-03), tanggal berlaku ke depan, override tenant tercatat sebagai sinyal koreksi. |
| R16 | Beban tim lebih besar (dua basis kode klien: Flutter & React) | Sedang | Sedang | Back-office React fokus CRUD/laporan dengan shadcn/ui. Kalkulasi hanya di PHP & Dart (web publik hitung via server). Design token & OpenAPI bersama. |
| R7 | Kebocoran data antar tenant | Rendah | Sangat tinggi | Global scope + test isolasi otomatis + ULID + code review checklist + pentest. |
| R8 | Scope creep karena banyak sektor | Tinggi | Tinggi | Flow-first + prioritas P0–P3 + template sektor bertahap (3 sektor di MVP). |
| R9 | Persaingan harga dengan pemain besar | Tinggi | Sedang | Diferensiasi offline, multi-sektor, akuntansi; paket gratis; biaya infra rendah. |
| R10 | Ketergantungan pada payment gateway/WA BSP | Sedang | Sedang | Abstraksi interface, minimal 2 provider yang bisa dipilih. |
| R11 | Performa MySQL pada tabel transaksi besar | Sedang | Tinggi | Indeks komposit, tabel ringkasan, arsip, query review, `EXPLAIN` di PR yang menyentuh laporan. |
| R12 | Kehilangan data server | Rendah | Sangat tinggi | Backup ganda, uji restore bulanan, outbox perangkat sebagai sumber pemulihan transaksi terakhir. |
