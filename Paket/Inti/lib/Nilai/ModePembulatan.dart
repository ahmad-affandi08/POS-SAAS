import 'package:decimal/decimal.dart';

/// Mode pembulatan eksplisit (padanan `Brick\Math\RoundingMode` di Backend, PRD §8 F-07).
///
/// Setiap operasi yang bisa kehilangan presisi wajib menyebut mode ini agar hasil PHP dan Dart identik.
enum ModePembulatan {
  /// Padanan `RoundingMode::Down`: dipotong menuju nol.
  MenujuNol,

  /// Padanan `RoundingMode::Up`: menjauhi nol.
  MenjauhiNol,

  /// Padanan `RoundingMode::Floor`: menuju minus tak hingga.
  KeBawah,

  /// Padanan `RoundingMode::Ceiling`: menuju plus tak hingga.
  KeAtas,

  /// Padanan `RoundingMode::HalfUp`: ke tetangga terdekat, jika tepat di tengah menjauhi nol.
  SetengahMenjauhiNol,

  /// Padanan `RoundingMode::HalfDown`: ke tetangga terdekat, jika tepat di tengah menuju nol.
  SetengahMenujuNol,

  /// Padanan `RoundingMode::HalfEven`: ke tetangga terdekat, jika tepat di tengah ke angka genap (banker's rounding).
  SetengahGenap,
}

/// Membagi bilangan bulat dengan pembulatan sesuai [mode]. [penyebut] wajib positif.
BigInt BagiBulat(BigInt pembilang, BigInt penyebut, ModePembulatan mode) {
  if (penyebut <= BigInt.zero) {
    throw ArgumentError.value(penyebut, 'penyebut', 'harus lebih dari 0');
  }
  final hasilBagi = pembilang ~/ penyebut;
  final sisa = pembilang.remainder(penyebut);
  if (sisa == BigInt.zero) {
    return hasilBagi;
  }
  final negatif = pembilang.isNegative;
  final langkah = negatif ? -BigInt.one : BigInt.one;
  final posisiTengah = (sisa.abs() * BigInt.two).compareTo(penyebut);
  final menjauh = switch (mode) {
    ModePembulatan.MenujuNol => false,
    ModePembulatan.MenjauhiNol => true,
    ModePembulatan.KeBawah => negatif,
    ModePembulatan.KeAtas => !negatif,
    ModePembulatan.SetengahMenjauhiNol => posisiTengah >= 0,
    ModePembulatan.SetengahMenujuNol => posisiTengah > 0,
    ModePembulatan.SetengahGenap => posisiTengah > 0 || (posisiTengah == 0 && hasilBagi.isOdd),
  };
  return menjauh ? hasilBagi + langkah : hasilBagi;
}

/// Membulatkan [nilai] ke [skala] angka desimal dengan [mode].
Decimal BulatkanKeSkala(Decimal nilai, int skala, ModePembulatan mode) {
  if (nilai.scale <= skala) {
    return nilai;
  }
  final signifikan = nilai.shift(nilai.scale).toBigInt();
  final penyebut = BigInt.from(10).pow(nilai.scale - skala);
  return Decimal.fromBigInt(BagiBulat(signifikan, penyebut, mode)).shift(-skala);
}
