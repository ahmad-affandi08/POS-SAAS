import 'dart:math';

/// Pembuat ULID (26 karakter Crockford base32: 48 bit milidetik + 80 bit acak) untuk ID yang dibuat di perangkat
/// (PRD §18: shift, penjualan, mutasi kas). Server memakainya sebagai `Uuid` idempoten dan memvalidasi format ULID.
/// ULID dalam milidetik yang sama tetap unik karena bagian acaknya 80 bit dari `Random.secure()`.
class PembuatUlid {
  PembuatUlid({Random? acak, DateTime Function()? jam}) : _acak = acak ?? Random.secure(), _jam = jam ?? DateTime.now;

  static const String _abjad = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

  final Random _acak;
  final DateTime Function() _jam;

  String Buat() {
    final teks = StringBuffer();
    var waktu = _jam().toUtc().millisecondsSinceEpoch;
    final bagianWaktu = List<String>.filled(10, '0');
    for (var indeks = 9; indeks >= 0; indeks--) {
      bagianWaktu[indeks] = _abjad[waktu % 32];
      waktu ~/= 32;
    }
    teks.writeAll(bagianWaktu);
    for (var indeks = 0; indeks < 16; indeks++) {
      teks.write(_abjad[_acak.nextInt(32)]);
    }
    return teks.toString();
  }

  /// Format ULID yang diterima server (`ulid` Laravel): 26 karakter Crockford base32, karakter pertama 0–7.
  static bool CekValid(String nilai) => RegExp(r'^[0-7][0-9A-HJKMNP-TV-Z]{25}$').hasMatch(nilai);
}
