import 'package:mesin_kasir/MesinKasir.dart';

/// Format angka non-uang untuk tampilan Indonesia (koma desimal, tanpa nol berlebih). Uang memakai
/// `Uang.FormatRupiah()`.
abstract final class FormatAngka {
  /// `2.0000` → `2`; `1.5000` → `1,5`.
  static String FormatJumlah(Kuantitas jumlah) => FormatDesimal(jumlah.KeDesimal());

  /// `12.00` → `12`; `2.5` → `2,5`.
  static String FormatDesimal(Decimal nilai) {
    var teks = nilai.toString();
    if (teks.contains('.')) {
      teks = teks.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
    }
    return teks.replaceAll('.', ',');
  }

  static String FormatPersen(String nilai) => '${FormatDesimal(Decimal.tryParse(nilai) ?? Decimal.zero)}%';

  /// Masukan kasir (`10`, `12,5`, `12.5`) → desimal; null bila kosong/tidak valid.
  static Decimal? UraiDesimal(String teks) {
    final rapi = teks.trim().replaceAll(',', '.');
    return rapi.isEmpty ? null : Decimal.tryParse(rapi);
  }
}
