# CLAUDE.md — Aturan Kerja AI Agent

POS SaaS multi-sektor Indonesia bernama **PAYOU** (D-15; placeholder `{{APP}}` di PRD = PAYOU). Aset merek di `Spesifikasi/Merek/`.
Sumber kebenaran: `PRD.md`. **Jangan membaca PRD.md utuh.** Baca potongannya lewat `Dokumen/Indeks.md`:
flow di `Dokumen/Flow/`, bagian PRD di `Dokumen/Prd/`, keputusan di `Dokumen/Keputusan.md`.

Aturan di file ini dan di `.claude/rules/` **wajib**. Pelanggaran dicegat oleh hook, `Alat/CekKonvensi.py`, test, dan CI.
Jangan mencoba melewati penjaga tersebut. Kalau penjaga menolak, perbaiki kodenya, bukan penjaganya.

## Cara kerja wajib

1. **Setiap tugas terikat ke ID flow** (`P-xx` Platform Pengelola, `F-xx` Tenant) dan aturan bisnisnya (`BR-xx`).
   Tidak ada ID flow? Tanyakan dulu, jangan menebak cakupan.
2. Mulai dengan `/mulai-flow <ID>`: baca dokumen flow, susun rencana yang menyebut BR-xx dan D-xx terkait, minta persetujuan untuk pekerjaan besar.
3. Kerjakan **sekecil mungkin** sesuai tugas. Jangan memperluas cakupan, jangan refactor di luar tugas.
4. Urutan implementasi mengikuti dependensi flow (`Dokumen/Prd/Bagian07...`). P-01 s.d. P-06 sebelum F-00.
5. Sebelum menyatakan selesai: jalankan `/cek-dod`. Semua pengecekan harus hijau.
6. PR memakai template `.github/pull_request_template.md` dan menyebut Flow, BR, dan D-xx.

## Aturan emas (ringkas; detail di PRD)

**Penamaan (D-05, D-06, §13.7)**
1. Tabel, kolom, folder, file, class, function/method: **Bahasa Indonesia PascalCase**. Tabel tunggal (`Penjualan`, `PenjualanDetail`), PK `Id`, FK `Id{Tabel}`, waktu `DibuatPada`/`DiubahPada`/`DihapusPada`.
2. Function diawali kata kerja: `HitungTotal()`, `SimpanPenjualan()`. Variabel lokal camelCase Indonesia.
3. URL huruf kecil kebab-case Indonesia (`/api/pos/v1/sinkron/kirim`), parameter route `{camelCase}`, nama route `kelola.produk.daftar`.
4. Key JSON API = nama kolom (PascalCase). Header kustom `X-Id-Kasir`, event webhook `penjualan.selesai`, permission `produk.harga.ubah`.
5. Pakai istilah dari kamus §13.7.1 (Pemasok, bukan Supplier/Vendor). Istilah baru → usulkan, jangan mengarang.
6. Pengecualian hanya yang tercantum di §13.7.4 dan `Alat/KonvensiPengecualian.json`.

**Data & domain**
7. **Uang dan kuantitas tidak pernah float/double.** PHP: `Uang`/`Kuantitas` (brick/math). Dart: paket `decimal`. DB: `DECIMAL`.
8. **Dokumen transaksi yang sudah diposting tidak boleh diedit/dihapus.** Koreksi lewat dokumen pembalik (void, retur, penyesuaian).
9. **Stok dan jurnal adalah turunan peristiwa.** Jangan menulis langsung ke `SaldoStok` atau saldo akun. Semua lewat `MutasiStok` dan `Jurnal` yang seimbang.
10. Penangan stok & jurnal berjalan **sinkron di transaksi DB yang sama**. Hanya efek non-kritis (notifikasi, webhook) yang lewat queue.
11. **Setiap query data tenant lewat scope `MilikTenant`.** Melewati scope hanya di `Domain/Pengelola` via `KonteksPengelola::JalankanLintasTenant()`.
12. **Tarif pajak tidak pernah di-hard-code.** Ambil dari `TarifPajak` bertanggal berlaku.
13. Mutasi dari POS **idempoten**: `UuidKlien` + header `Idempotency-Key`. Nomor dokumen offline memakai kode perangkat.
14. Modul domain hanya saling memanggil lewat **Aksi/Layanan publik atau Peristiwa**, bukan query tabel domain lain.
15. Migrasi yang sudah di-merge **tidak boleh diubah**. Buat migrasi baru (pola expand → contract).
16. API POS & Owner wajib **kompatibel mundur** 2 versi minor aplikasi. Perubahan kontrak = versi baru.

**Kualitas**
17. Setiap perubahan perilaku disertai test. Flow keuangan/stok wajib invariant test (Σ debit = Σ kredit, `SaldoStok` = Σ `MutasiStok`).
18. Kalkulasi harga/pajak/promo harus lolos test vector bersama di `Spesifikasi/VektorUjiKalkulasi/` (PHP & Dart).
19. **Dilarang** melemahkan, men-skip, atau menghapus test, aturan lint, test arsitektur, atau konfigurasi CI supaya lolos.
20. UI mengikuti §17.5 (font Atkinson Hyperlegible) dan §17.6 (token, keadaan wajib, microcopy Indonesia). Palet final PAYOU (D-15) ada di token: pakai token, jangan hex lepas.
21. **Semua tabel web memakai `TabelData`** (TanStack Table + TanStack Query, fitur lengkap §17.4.3); jangan merakit `<table>` sendiri. **Web responsif** 360px s.d. layar lebar (§17.4.4), diuji di 360/768/1280px (D-16).
22. **Aplikasi POS adalah Ruang Kerja Kasir** (§17.2.7, D-16): semua layar setelah masuk berada di bingkai `RuangKerja`; elegan, tenang, dan mudah untuk kerja berjam-jam.

## Yang dilarang keras

- Melemahkan file penjaga (`CLAUDE.md`, `.claude/**`, `Alat/**`, `.github/**`, `PRD.md`, konfigurasi lint/test, test arsitektur). Sejak **D-17** agent boleh mengubah file penjaga **tanpa meminta izin** (memperbarui PRD, aturan, dokumen), asalkan tidak melemahkan test/lint/CI/test arsitektur (aturan #19), dicatat di PRD (riwayat versi/keputusan), dan dilaporkan ke pengguna. Test vector boleh ditambah kasus oleh agent, tetapi kasus lama tidak boleh dihapus/dilemahkan (lihat `.claude/rules/Pengujian.md`).
- Mengedit `Dokumen/` langsung. Itu hasil generate dari `PRD.md` lewat `python3 Alat/PecahPrd.py`.
- `git push --force`, `--no-verify`, menonaktifkan hook, `migrate:fresh`/`db:wipe` di luar lingkungan test.
- Membaca atau menulis `.env` dan rahasia apa pun. Kredensial tidak pernah masuk kode, log, atau commit.
- Menyimpang dari PRD diam-diam. Jika aturan bertabrakan, tidak jelas, atau terasa salah:
  **berhenti, jelaskan ke pengguna, atau tulis usulan di PR** (bagian "Usulan perubahan keputusan"). Keputusan baru atau penyesuaian D-xx boleh dicatat agent (D-12, D-17) dan dilaporkan; membalik keputusan pemilik produk tetap harus ditanyakan dulu.

## Perintah pengecekan

```bash
python3 Alat/CekKonvensi.py --berubah   # konvensi penamaan & pola terlarang (file yang berubah)
python3 Alat/CekKonvensi.py --semua     # seluruh repo
python3 Alat/PecahPrd.py --cek          # Dokumen/ sinkron dengan PRD.md
```

Perintah per stack (aktif setelah scaffolding Fase 0; lihat `.claude/rules/`):
- `Aplikasi/Web` (PHP): `composer tes:cepat`, `composer analisis` (Pint, Larastan, Pest termasuk `arch()`)
- `Aplikasi/Web` (frontend): `npm run periksa` (tsc, ESLint, Vitest)
- Flutter: `melos run periksa` dari akar (dart format, flutter analyze, test; sekali: `dart pub global activate melos 7.8.2`)

## Peta repo (target, lihat §13.0)

```
Aplikasi/Web/       Laravel 13 (API, back-office Inertia React, web publik, Platform Pengelola)
Aplikasi/Kasir/     Aplikasi POS Flutter (Android, iOS/iPadOS, Windows)
Aplikasi/Pemilik/   Aplikasi Owner Flutter (Android, iOS)
Paket/              Paket Dart bersama (MesinKasir, Inti, KlienApi, SistemDesain, AdaptorPerangkat)
Spesifikasi/        Test vector, OpenAPI, token desain
Alat/               Penjaga & skrip proyek (terlindungi)
Dokumen/            Potongan PRD hasil generate (terlindungi)
```

Status: **Fase 1 inti selesai** (§22). Sudah ada: fondasi `Aplikasi/Web` (Laravel + back-office) dan Flutter, Platform Pengelola P-01 s.d. P-09 & P-11 (dasar), F-00, F-01, F-02, F-03, F-04 fase 1 (pembelian & hutang), F-05a, F-05b (transfer, opname, penyesuaian), F-06, F-07 (penjualan: mesin kalkulasi PHP & Dart, sinkron, layar Jual & Bayar), F-08 fase 1, F-09 fase 1 (void & retur), F-11 (tutup shift), F-13a (keuangan dasar + neraca & arus kas), F-14a (laporan inti), audit responsif D-16c, F-10a (meja, area, stasiun dapur), F-07 mode meja + F-10b fase 1 (pesanan terbuka tersinkron, tiket dapur, layar Meja & layar dapur KDS di aplikasi), F-16a (data pelanggan: back-office, cari & buat di POS), F-16b (tier, harga tier, perolehan, kedaluwarsa & penukaran poin sebagai diskon), F-16c bagian 1–4 (promo otomatis offline; voucher & kode promo wajib online, kode massal + ekspor CSV; promo metode bayar, ulang tahun, transaksi pertama, batas per pelanggan; poin berlipat, pendanaan promo pemasok + klaim J-16.5, laporan efektivitas & uplift; void mengembalikan jatah promo), F-12 bagian 1 & 2 (limit kredit, penjualan tempo dengan PIN penyetuju, piutang & umur, pelunasan; pre-order dengan uang muka), F-18 bagian 1 & 2 (karyawan, jadwal kerja, absensi PIN + swafoto di POS, komisi per staf pelayan), F-18 bagian 3 (kasbon J-18.1, rekap gaji bulanan J-18.2, target penjualan & progres), cetak struk bagian 1, 2, 3, 4 (struk digital `/s/{kodeStruk}`, bukti void, nota retur, laporan X/Z; pengaturan struk di back-office, `Paket/AdaptorPerangkat` ESC/POS, printer LAN + Bluetooth Classic Android/Windows + Bluetooth LE iOS/Android/Windows, cetak otomatis/ulang, laci; bukti uang muka pre-order, buka laci manual tercatat + PIN opsional, tiket dapur per stasiun di printer LAN/Bluetooth + penjualan langsung ke dapur), F-15 (tutup harian per outlet, tutup bulan & buka kunci, transaksi POS di periode terkunci dibukukan di periode terbuka berikutnya, tutup tahun J-15.1), P-10 (rilis aplikasi bertahap, versi minimum, flag fitur & kill switch, kunci layar jual saat wajib perbarui). F-16c bagian 4d/4e (klaim promo pemasok akrual J-16.6, potong klaim dari hutang J-16.7), poin di struk, cetak struk bagian 5 (printer USB Android/Windows, printer bawaan Sunmi/iMin, Wizard Uji Perangkat & `Perangkat.ProfilHardware`). cetak struk bagian 6 (printer sistem PDF/AirPrint/driver + cadangan saat printer thermal gagal). HCL otomatis dari Wizard Uji Perangkat (halaman pengelola + publik `/kompatibilitas-perangkat`). Berikutnya: gratis ongkir (menunggu flow pesan-antar/toko online), flow lanjutan sesuai urutan §22.
