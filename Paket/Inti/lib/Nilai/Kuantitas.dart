import 'package:decimal/decimal.dart';

import 'ModePembulatan.dart';

/// Jumlah barang/bahan dengan presisi tetap 4 desimal (PRD §8 F-05, §15.1, CLAUDE.md #7).
///
/// Padanan `App\Domain\Bersama\Nilai\Kuantitas` di Backend.
final class Kuantitas implements Comparable<Kuantitas> {
  const Kuantitas._(this._nilai);

  static const int skala = 4;

  final Decimal _nilai;

  /// Dari string desimal, misal `"2"` atau `"0.125"`.
  static Kuantitas Dari(String nilai) => DariDesimal(Decimal.parse(nilai));

  static Kuantitas DariBulat(int jumlah) => Kuantitas._(Decimal.fromInt(jumlah));

  static Kuantitas DariDesimal(Decimal nilai) {
    if (nilai.scale > skala) {
      throw ArgumentError.value(nilai.toString(), 'nilai', 'Kuantitas maksimal $skala desimal');
    }
    return Kuantitas._(nilai);
  }

  static Kuantitas Nol() => Kuantitas._(Decimal.zero);

  Kuantitas Tambah(Kuantitas lain) => Kuantitas._(_nilai + lain._nilai);

  Kuantitas Kurangi(Kuantitas lain) => Kuantitas._(_nilai - lain._nilai);

  /// Mengalikan (misal konversi satuan) lalu membulatkan ke 4 desimal dengan mode yang disebut eksplisit.
  Kuantitas Kali(Decimal faktor, {ModePembulatan mode = ModePembulatan.SetengahMenjauhiNol}) =>
      Kuantitas._(BulatkanKeSkala(_nilai * faktor, skala, mode));

  Kuantitas Negasi() => Kuantitas._(-_nilai);

  /// Hasil selalu -1, 0, atau 1 (sama dengan `BigDecimal::compareTo` di Backend).
  int Bandingkan(Kuantitas lain) => _nilai.compareTo(lain._nilai).sign;

  bool SamaDengan(Kuantitas lain) => _nilai == lain._nilai;

  bool BernilaiNol() => _nilai == Decimal.zero;

  bool BernilaiNegatif() => _nilai < Decimal.zero;

  Decimal KeDesimal() => _nilai;

  /// Format penyimpanan & API: string desimal, misal `"2.0000"`.
  String KeString() => _nilai.toStringAsFixed(skala);

  String toJson() => KeString();

  @override
  int compareTo(Kuantitas other) => Bandingkan(other);

  @override
  bool operator ==(Object other) => other is Kuantitas && SamaDengan(other);

  @override
  int get hashCode => _nilai.hashCode;

  @override
  String toString() => KeString();
}
