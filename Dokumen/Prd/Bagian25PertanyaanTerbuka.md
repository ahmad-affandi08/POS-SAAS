<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 25. Pertanyaan Terbuka

1. **Nama sistem & domain.** Pilih dari kandidat di Lampiran A. Cek ketersediaan domain `.id`/`.com` dan merek di PDKI.
2. **Sektor prioritas MVP.** Usulan: Retail Umum, Kafe, QSR. Apakah ada sektor lain yang lebih strategis (misal laundry atau salon) berdasarkan jaringan pasar Anda?
3. **Payment gateway pertama** yang akan dikerjasamakan (Midtrans, Xendit, DOKU, atau lainnya), dan apakah {{APP}} akan menjadi agregator/sub-merchant.
4. **Provider WhatsApp** (BSP resmi vs gateway non-resmi). Rekomendasi: BSP resmi untuk kepatuhan dan stabilitas.
5. **Paket Hostinger** untuk produksi awal: Cloud Hosting (disarankan) atau Web Hosting Business.
6. **Kebijakan harga** final dan apakah ada paket gratis permanen.
7. **Model posting jurnal default**: per transaksi atau ringkasan per shift?
8. **Target launch** dan ukuran tim riil. Roadmap §22 menyesuaikan.
9. Apakah dibutuhkan **white-label** untuk reseller/franchise sejak awal?
10. Apakah perlu dukungan **multi-mata uang** (turis/perbatasan)? Default: tidak.
11. Apakah ada rencana **bundel hardware** (perangkat all-in-one + langganan) bersama distributor?
12. **Kanal distribusi Windows** (D-02): diputuskan setelah sistem stabil (lihat tabel keputusan di bawah).
13. **Allowlist IP Platform Pengelola** (BR-P01.2): per peran atau per pengguna? Sampai diputuskan, fitur ini tidak dibangun dan kolom `DaftarIpDiizinkan` tidak dibuat.

### 25.1 Keputusan yang Sudah Diambil

| ID | Keputusan | Tanggal | Dampak di PRD |
|---|---|---|---|
| D-01 | Platform Aplikasi POS: **Android, iOS/iPadOS, Windows**. **Linux tidak.** macOS tidak ditargetkan | 22/09/2026 | §1, §4.2, §13, §14.6, §17.2, §20.5, §22 |
| D-02 | Kanal distribusi & mekanisme update aplikasi **Windows ditentukan setelah semua sistem jadi dan berjalan stabil**. Selama pengembangan & beta: unduhan langsung terbatas untuk tester | 22/09/2026 | §14.6, §22 |
| D-03 | **Semua perangkat POS all-in-one** didukung melalui lapisan adaptor vendor + adaptor generik + Wizard Uji Perangkat + HCL | 22/09/2026 | §10.2 (POS-22), §17.2.5a, §22, §23 |
| D-04 | **Aplikasi Mobile Owner tersendiri** (Flutter, Android & iOS) | 22/09/2026 | §1, §3.3 (X19), §10.2a, §13, §16.1, §17.3, §22 |
| D-05 | **Database, folder, file, dan function memakai Bahasa Indonesia + PascalCase.** Turunan yang diputuskan untuk konsistensi: class/enum PascalCase, key JSON API = nama kolom (PascalCase), variabel lokal camelCase Indonesia. Pengecualian hanya untuk nama yang diwajibkan framework/alat (§13.7.4) | 22/09/2026 | §8 (status/enum), §12.2, §13.0–§13.4, §13.7, §14.3, §15, §16.2, §17, §18, §23, Lampiran D |
| D-06 | **URL/endpoint memakai Bahasa Indonesia**, huruf kecil kebab-case, kata benda tunggal (§13.7.1). Prefix API Owner menjadi `/api/pemilik/v1`. Diperluas ke nama event webhook, header HTTP kustom, dan nama permission | 22/09/2026 | §11–§13.6, §13.7, §14, §16, §17.3.4, §17.4, §18, §20, Lampiran C |
| D-07 | **Platform Pengelola** dibangun sebagai lapisan pertama (Fase 0) sebelum modul tenant: flow P-01 s.d. P-12, subdomain `pengelola.`, akun & guard terpisah, akses dukungan berizin | 22/09/2026 | §5.2, §6.2, §7, §8 Bagian A, §10.0, §13.6, §13.8, §15.3, §19.3, §20.2, §22, §24 |
| D-08 | Font resmi: **Atkinson Hyperlegible Next** untuk UI dan **Atkinson Hyperlegible Mono** untuk kode, di semua klien | 22/09/2026 | §17.5, `Spesifikasi/TokenDesain` |
| D-09 | Pedoman UI/UX & Design System: prinsip alat kerja, aturan warna 90/10, token warna, dua mode kepadatan, keadaan wajib, microcopy Indonesia, checklist anti-slop | 22/09/2026 | §17.6, `Spesifikasi/TokenDesain`, §23.3 |
| D-10 | Tata kelola AI agent tiga lapis: konteks (`CLAUDE.md`, `.claude/rules/`, `Dokumen/`), penjaga otomatis (hook, `Alat/CekKonvensi.py`, CI, CODEOWNERS), alur kerja (`/mulai-flow`, `/cek-dod`, subagent peninjau, template PR) | 22/09/2026 | §23.4, §13.7.4 |
