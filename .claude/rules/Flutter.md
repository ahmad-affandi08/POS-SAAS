---
paths:
  - "Aplikasi/**"
  - "Paket/**"
---

# Aturan aplikasi Flutter (PRD §17.2, §17.3, §18)

- Folder & file di `lib/` PascalCase (`Fitur/Keranjang/KalkulatorKeranjang.dart`). Nama paket di `pubspec.yaml` huruf kecil (`mesin_kasir`) karena syarat Dart. Method PascalCase, kecuali override Flutter (`build`, `initState`, `dispose`, ...).
- Arsitektur feature-first berlapis: Tampilan → Aplikasi → Domain → Data. State & DI dengan Riverpod.
- Uang & jumlah dengan paket `decimal` (value object `Uang`/`Kuantitas`). **Dilarang `double`** untuk uang/kuantitas.
- `Paket/MesinKasir` adalah Dart murni (tanpa import Flutter) dan wajib lolos test vector `Spesifikasi/VektorUjiKalkulasi/`.
- Aplikasi POS **offline-first**: simpan Penjualan + detail + pembayaran + entri Outbox dalam **satu transaksi SQLite (Drift)**. ID dibuat di perangkat (ULID) sebagai `UuidKlien`. Nomor dokumen memakai kode perangkat.
- Sinkron hanya lewat `/api/pos/v1` (device token). Migrasi skema Drift **tidak boleh** menghapus outbox yang belum terkirim.
- Rahasia di secure storage; DB lokal terenkripsi SQLCipher bila disyaratkan (§17.2.6). Jangan log PIN, token, atau data pelanggan.
- Font di-bundle (bukan paket `google_fonts`). Ukuran & warna dari token `SistemDesain`. Target sentuh ≥ 48dp.
- Hardware lewat abstraksi `Paket/AdaptorPerangkat` (`TransportPrinter`, `KemampuanPerangkat`). Kode fitur tidak memanggil SDK vendor langsung.
- Test: unit (MesinKasir, repositori, sinkron), widget/golden, integration (alur jual online & offline).
- File test berakhiran `_test.dart` (syarat `flutter test`), misal `test/Nilai/Uang_test.dart`. Pengecekan: `melos run periksa` dari akar repo (sekali: `dart pub global activate melos 7.8.2`).
