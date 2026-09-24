/// Engine kalkulasi keranjang, promo, pajak, biaya layanan, dan pembulatan (PRD §8 F-07).
///
/// Dart murni (tanpa Flutter) agar bisa diuji cepat dan identik dengan engine PHP di Backend.
/// Implementasi engine keranjang dibangun bersama flow F-07. Sudah ada: penentu harga lapis 3–5 (F-03, `Harga/`) dan
/// mesin kalkulasi penjualan F-07a (`Kalkulasi/`).
library;

export 'package:inti/Inti.dart';

export 'package:rational/rational.dart' show Rational;

export 'Harga/Harga.dart';
export 'Kalkulasi/Kalkulasi.dart';
