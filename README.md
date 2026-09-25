# PAYOU

POS SaaS multi-sektor untuk usaha di Indonesia: retail, F&B, jasa, dan lainnya. Satu sistem untuk kasir (online & offline), stok, pembelian, akuntansi otomatis, pajak, dan laporan, lengkap dengan Platform Pengelola untuk tim internal.

> Sumber kebenaran produk adalah [`PRD.md`](PRD.md). Potongannya (flow, bagian PRD, keputusan) ada di [`Dokumen/`](Dokumen/Indeks.md) dan **dibuat otomatis** dari `PRD.md`. Aturan kerja pengembang & AI agent ada di [`CLAUDE.md`](CLAUDE.md) dan [`.claude/rules/`](.claude/rules).

---

## Daftar isi

1. [Gambaran produk](#gambaran-produk)
2. [Arsitektur & struktur repo](#arsitektur--struktur-repo)
3. [Teknologi](#teknologi)
4. [Menyiapkan lingkungan pengembangan](#menyiapkan-lingkungan-pengembangan)
5. [Menjalankan aplikasi](#menjalankan-aplikasi)
6. [Pengujian & pengecekan kualitas](#pengujian--pengecekan-kualitas)
7. [Konvensi wajib](#konvensi-wajib)
8. [Alur kerja pengembangan](#alur-kerja-pengembangan)
9. [Status pengembangan](#status-pengembangan)
10. [Dokumentasi lanjutan](#dokumentasi-lanjutan)

---

## Gambaran produk

| Aplikasi | Pengguna | Platform |
|---|---|---|
| **Back-office web** (`/kelola`) | Pemilik, admin, manajer outlet, akuntan, staf gudang | Peramban, responsif 360px s.d. layar lebar |
| **Aplikasi Kasir (POS)** | Kasir, supervisor | Android, iOS/iPadOS, Windows. Offline-first, minimal 72 jam tanpa internet |
| **Aplikasi Pemilik** | Pemilik usaha | Android, iOS |
| **Platform Pengelola** (`pengelola.`) | Tim internal PAYOU | Peramban, akun & guard terpisah |
| **Web publik** | Calon pelanggan, pembeli (self-order/toko online, fase berikutnya) | Peramban |

Kemampuan utama:

- **Penjualan & pembayaran**: keranjang, harga bertingkat & daftar harga, pilihan/modifier, diskon manual dengan persetujuan PIN, pajak inklusif/eksklusif (PPN DPP 11/12, PB1/PBJT), biaya layanan, pembulatan tunai, split payment (tunai, QRIS statis, EDC, transfer, e-wallet).
- **Pasca-penjualan**: void di shift yang sama, retur dengan refund, daftar void & retur untuk anti-fraud.
- **Shift & kas**: buka/tutup shift, kas masuk/keluar/setoran, tutup buta, rekonsiliasi selisih, laporan X/Z.
- **Persediaan**: ledger `MutasiStok` append-only, HPP rata-rata bergerak/FIFO, batch & nomor seri, stok awal & impor, transfer, opname, penyesuaian.
- **Pembelian**: pemasok, PO dengan persetujuan, penerimaan barang, faktur & 3-way matching, hutang & pembayaran, retur pembelian.
- **Akuntansi otomatis**: setiap peristiwa keuangan menghasilkan jurnal seimbang di transaksi yang sama; bagan akun, pemetaan akun, kas & bank, buku besar, neraca saldo, laba rugi.
- **Laporan**: dashboard pemilik, laporan penjualan per dimensi, pajak, stok.
- **Platform Pengelola**: tim internal, master regulasi (tarif pajak bertanggal, wilayah, bank), template sektor, paket & fitur langganan, integrasi platform, dokumen legal, siklus hidup tenant, tagihan, tiket dukungan, monitoring.

---

## Arsitektur & struktur repo

Monorepo berisi server Laravel, aplikasi Flutter, dan paket Dart bersama.

```
Aplikasi/
  Web/          Laravel 13: API POS & Pemilik, back-office Inertia React, Platform Pengelola, web publik
  Kasir/        Aplikasi POS Flutter (Ruang Kerja Kasir, offline-first dengan Drift/SQLite + outbox)
  Pemilik/      Aplikasi Pemilik Flutter
Paket/
  Inti/         Nilai uang & kuantitas (Decimal), ULID
  KlienApi/     Klien HTTP API POS
  MesinKasir/   Mesin kalkulasi harga, pajak, diskon, pembulatan (Dart murni)
  SistemDesain/ Token & komponen UI bersama (ubin produk, keranjang, papan angka, dll.)
Spesifikasi/
  VektorUjiKalkulasi/  Test vector bersama kalkulasi (PHP & Dart wajib sama sampai sen)
  VektorUjiPin/        Test vector PIN offline
  Merek/               Aset merek PAYOU
Alat/           Penjaga & skrip proyek (CekKonvensi.py, PecahPrd.py)
Dokumen/        Potongan PRD hasil generate (jangan diedit langsung)
PRD.md          Product Requirements Document (sumber kebenaran)
CLAUDE.md       Aturan kerja wajib
```

Prinsip arsitektur:

- **Domain-driven, modular monolith.** Kode server ada di `Aplikasi/Web/app/Domain/{Domain}` (Penjualan, Kasir, Persediaan, Akuntansi, Katalog, Pajak, Organisasi, Pembelian, Laporan, Pengelola, …) dengan lapisan `Aksi`, `Kueri`, `Layanan`, `Model`, `Data`, `Enum`, `Peristiwa`. Antar-domain hanya lewat Aksi/Kueri/Layanan publik atau peristiwa, dan dijaga oleh test arsitektur.
- **Multi-tenant.** Setiap tabel tenant memakai scope `MilikTenant`; lintas tenant hanya di `Domain/Pengelola`.
- **Stok & jurnal adalah turunan peristiwa.** Ditulis hanya lewat `CatatMutasiStok` dan `PostingJurnal` di transaksi DB yang sama dengan dokumennya. Efek non-kritis (ringkasan laporan, notifikasi) lewat antrean.
- **Offline-first POS.** Dokumen dibuat di perangkat (ULID sebagai `UuidKlien`, nomor dokumen berkode perangkat), disimpan bersama entri outbox dalam satu transaksi SQLite, lalu dikirim ke `POST /api/pos/v1/sinkron/kirim` secara FIFO dan idempoten.
- **Satu algoritma, dua implementasi.** Kalkulasi penjualan ada di PHP (`App\Domain\Penjualan\Kalkulasi`) dan Dart (`Paket/MesinKasir`), dan keduanya wajib lolos test vector yang sama.

---

## Teknologi

| Lapisan | Teknologi |
|---|---|
| Server | PHP 8.3, Laravel 13, MySQL 8, Sanctum (token perangkat), antrean Laravel |
| Back-office | Inertia.js + React 19 + TypeScript strict, Tailwind CSS 4, shadcn/ui, TanStack Query & TanStack Table (`TabelData`), Vite |
| Aplikasi mobile/desktop | Flutter & Dart 3.13+, Riverpod, Drift (SQLite), paket `decimal` |
| Kualitas | Pest (termasuk `arch()`), Larastan/PHPStan, Pint, Vitest, ESLint, Prettier, `flutter analyze`, melos |
| Tipografi & merek | Atkinson Hyperlegible Next (UI) & Mono, palet PAYOU lewat token desain |

---

## Menyiapkan lingkungan pengembangan

### Prasyarat

- PHP 8.3 dengan ekstensi standar Laravel, Composer 2
- MySQL 8
- Node.js 22 + npm
- Flutter (Dart SDK ≥ 3.13) dan melos 7.8.2: `dart pub global activate melos 7.8.2`
- Python 3 untuk skrip `Alat/`

### Server & back-office (`Aplikasi/Web`)

```bash
cd Aplikasi/Web
composer setup
```

`composer setup` memasang dependensi PHP, membuat berkas lingkungan dari contoh bila belum ada, membuat kunci aplikasi, menjalankan migrasi, memasang dependensi npm, dan mem-build aset. Atur koneksi database lokal di berkas lingkungan Anda sendiri. **Jangan pernah meng-commit rahasia atau kredensial.**

### Aplikasi Flutter (akar repo)

```bash
dart pub get          # ruang kerja pub: satu pubspec.lock di akar untuk semua paket & aplikasi
```

---

## Menjalankan aplikasi

### Server & back-office

```bash
cd Aplikasi/Web
composer dev          # server Laravel + antrean + Vite dalam satu perintah
```

Back-office tersedia di `/kelola`, Platform Pengelola di subdomain `pengelola.`, dan API POS di `/api/pos/v1`.

### Aplikasi Kasir

```bash
cd Aplikasi/Kasir
flutter run -t lib/UtamaDev.dart        # juga: lib/UtamaStaging.dart, lib/UtamaProduksi.dart
```

Aktifkan perangkat dengan kode aktivasi dari back-office (menu Perangkat), lalu masuk dengan PIN kasir.

### Aplikasi Pemilik

```bash
cd Aplikasi/Pemilik
flutter run
```

---

## Pengujian & pengecekan kualitas

Semua pengecekan di bawah juga dijalankan CI (`.github/workflows/CekKepatuhan.yml`) dan harus hijau sebelum merge.

| Cakupan | Perintah (dari folder terkait) |
|---|---|
| Konvensi penamaan & pola terlarang | `python3 Alat/CekKonvensi.py --berubah` (atau `--semua`) dari akar |
| `Dokumen/` sinkron dengan `PRD.md` | `python3 Alat/PecahPrd.py --cek` dari akar |
| PHP: format & analisis statis | `composer analisis` (Pint + PHPStan) di `Aplikasi/Web` |
| PHP: test (Unit, Fitur di MySQL, Arsitektur) | `composer tes:cepat` di `Aplikasi/Web` |
| Frontend web | `npm run periksa` (tsc, ESLint, Prettier, Vitest) di `Aplikasi/Web` |
| Flutter & paket Dart | `melos run periksa` dari akar (format, analyze, test) |

Hal yang dijaga test:

- **Invariant keuangan & stok**: Σ debit = Σ kredit, `SaldoStok` = Σ `MutasiStok`, nilai persediaan = saldo akun persediaan.
- **Test vector kalkulasi** di `Spesifikasi/VektorUjiKalkulasi/` dijalankan oleh PHP dan Dart.
- **Isolasi tenant, izin, batas outlet, dan idempotensi** sinkron POS.
- **Test arsitektur**: batas antar-domain dan larangan pola tertentu.
- **UI**: semua tabel lewat `TabelData`, tanpa `<select>` bawaan, dan tanpa isian tanggal bawaan peramban.

---

## Konvensi wajib

Ringkasan dari [`CLAUDE.md`](CLAUDE.md); detailnya ada di PRD §13.7 dan `.claude/rules/`.

- **Bahasa Indonesia + PascalCase** untuk tabel, kolom, folder, file, class, dan function. Function diawali kata kerja (`HitungTotal`, `SimpanPenjualan`). Variabel lokal camelCase. PK `Id`, FK `Id{Tabel}`, waktu `DibuatPada`/`DiubahPada`/`DihapusPada`.
- **URL** huruf kecil kebab-case Indonesia (`/api/pos/v1/sinkron/kirim`). Key JSON API = nama kolom (PascalCase). Permission bergaya `penjualan.diskon.manual`.
- Istilah mengikuti kamus PRD §13.7.1 (misal **Pemasok**, bukan Supplier).
- **Uang & kuantitas tidak pernah float/double**: PHP `Uang`/`Kuantitas` (brick/math), Dart `decimal`, DB `DECIMAL`.
- **Dokumen terposting tidak diedit/dihapus**; koreksi lewat dokumen pembalik (void, retur, penyesuaian).
- **Tarif pajak tidak pernah di-hard-code**; selalu dari `TarifPajak` bertanggal berlaku.
- **Mutasi dari POS idempoten** (`UuidKlien` + `Idempotency-Key`).
- **Migrasi yang sudah di-merge tidak diubah**; buat migrasi baru.
- **UI**: token desain (tanpa hex lepas, gradien, efek kaca, atau emoji), `TabelData` untuk semua tabel, komponen `Komponen/Tanggal` & `PilihanCari`, microcopy Indonesia, responsif 360/768/1280px. Aplikasi POS berada di bingkai **Ruang Kerja Kasir**.
- **Dilarang** melemahkan/men-skip test, lint, atau CI; `git push --force`; `--no-verify`; serta membaca atau menulis rahasia.

---

## Alur kerja pengembangan

1. Setiap tugas terikat ke **ID flow** (`P-xx` Platform Pengelola, `F-xx` tenant) dan aturan bisnisnya (`BR-xx`). Mulai dengan `/mulai-flow <ID>` untuk membaca flow terkait di `Dokumen/Flow/`.
2. Kerjakan sekecil mungkin sesuai cakupan, ikuti urutan dependensi flow (PRD §7).
3. Setiap perubahan perilaku disertai test; flow keuangan/stok wajib invariant test.
4. Sebelum menyatakan selesai, jalankan `/cek-dod` (semua pengecekan di atas).
5. Perubahan PRD dilakukan di `PRD.md` lalu `python3 Alat/PecahPrd.py` untuk memperbarui `Dokumen/`. Keputusan dicatat di riwayat versi & tabel keputusan (D-xx).
6. PR memakai template `.github/pull_request_template.md` dan menyebut Flow, BR, dan D-xx.

---

## Status pengembangan

**Fase 0–1 berjalan** (PRD §22). Yang sudah dibangun:

| Area | Flow |
|---|---|
| Platform Pengelola | P-01 s.d. P-09 & P-11 (dasar) |
| Registrasi, autentikasi, organisasi | F-00, F-01 (panduan awal & template sektor), F-02 (outlet, peran, perangkat, PIN) |
| Katalog, harga & pajak | F-03 (termasuk impor/ekspor) |
| Persediaan | F-05a (stok awal, ledger & HPP, jurnal inti) |
| Kasir | F-06 (shift & kas), F-07 (penjualan: mesin kalkulasi PHP & Dart, sinkron, layar Jual & Bayar), F-08 fase 1 (pembayaran), F-09 fase 1 (void & retur), F-11 (tutup shift & rekonsiliasi) |
| Keuangan & laporan | F-13a (bagan akun, pemetaan, kas & bank, buku besar, neraca saldo, laba rugi), F-14a (dashboard, laporan penjualan/pajak/stok) |
| Fondasi UI | `TabelData` (TanStack), pemilih tanggal & pilihan ber-cari, bingkai Ruang Kerja Kasir |

Sedang dikerjakan: **F-04** (pembelian & hutang) dan **F-05b** (transfer, opname, penyesuaian). Daftar utang teknis & pertanyaan terbuka ada di PRD §25.

---

## Dokumentasi lanjutan

- [`PRD.md`](PRD.md): dokumen lengkap (baca per bagian lewat `Dokumen/Indeks.md`)
- [`Dokumen/Flow/`](Dokumen/Flow): spesifikasi per flow bisnis
- [`Dokumen/Keputusan.md`](Dokumen/Keputusan.md): keputusan produk (D-xx)
- [`CLAUDE.md`](CLAUDE.md) & [`.claude/rules/`](.claude/rules): aturan kerja per stack
- `Aplikasi/Kasir/README.md`: catatan aplikasi kasir
