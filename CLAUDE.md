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

Status: **Fase 0 berjalan** (§22). Sudah ada: fondasi `Aplikasi/Web` (Laravel + back-office) dan Flutter, Platform Pengelola P-01 s.d. P-09 & P-11 (dasar), F-00 (registrasi & autentikasi), F-02 (organisasi, peran, perangkat, PIN), F-01 (panduan awal & template sektor), F-03 (katalog, harga & pajak, impor/ekspor), F-05a (stok awal, ledger stok & HPP, jurnal inti), F-06 (shift & kas: server, back-office, aplikasi kasir Flutter dengan PIN offline & outbox). Berikutnya: utang D-16 (§25 no. 22: `TabelData`, audit responsif, bingkai Ruang Kerja Kasir), lalu F-07 (penjualan).
