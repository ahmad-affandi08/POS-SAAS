import 'package:flutter/material.dart';

/// Nama keluarga font yang di-bundle paket ini (PRD §17.5, D-08). Tidak memakai paket `google_fonts`.
const String paketFont = 'sistem_desain';
const String fontUtama = 'AtkinsonHyperlegibleNext';
const String fontMono = 'AtkinsonHyperlegibleMono';

/// Mode kepadatan tipografi (PRD §17.5): Nyaman untuk POS/KDS/Owner, Ringkas untuk back-office.
enum KepadatanTipografi { nyaman, ringkas }

/// Satu token skala tipografi: ukuran & tinggi baris dalam logical pixel.
@immutable
final class TokenTeks {
  const TokenTeks(this.ukuran, this.tinggiBaris, this.ketebalan);

  final int ukuran;
  final int tinggiBaris;
  final int ketebalan;

  /// Font variable butuh sumbu `wght` eksplisit agar ketebalan tampil sama di semua platform.
  TextStyle KeGaya({String keluarga = fontUtama, List<FontFeature> fitur = const []}) => TextStyle(
    fontFamily: keluarga,
    package: paketFont,
    fontSize: ukuran.toDouble(),
    height: tinggiBaris / ukuran,
    fontWeight: FontWeight.values.firstWhere((bobot) => bobot.value == ketebalan),
    fontVariations: [FontVariation.weight(ketebalan.toDouble())],
    fontFeatures: fitur,
    letterSpacing: 0,
  );
}

/// Enam token skala tipografi (PRD §17.5). Tidak boleh membuat ukuran baru di luar token ini.
@immutable
final class SkalaTipografi {
  const SkalaTipografi._({
    required this.tampilan,
    required this.judul,
    required this.subjudul,
    required this.isi,
    required this.label,
    required this.keterangan,
  });

  static const SkalaTipografi nyaman = SkalaTipografi._(
    tampilan: TokenTeks(36, 44, 700),
    judul: TokenTeks(24, 32, 700),
    subjudul: TokenTeks(18, 26, 600),
    isi: TokenTeks(16, 24, 400),
    label: TokenTeks(14, 20, 600),
    keterangan: TokenTeks(13, 18, 400),
  );

  static const SkalaTipografi ringkas = SkalaTipografi._(
    tampilan: TokenTeks(30, 38, 700),
    judul: TokenTeks(20, 28, 700),
    subjudul: TokenTeks(16, 24, 600),
    isi: TokenTeks(14, 20, 400),
    label: TokenTeks(13, 18, 600),
    keterangan: TokenTeks(12, 16, 400),
  );

  final TokenTeks tampilan;
  final TokenTeks judul;
  final TokenTeks subjudul;
  final TokenTeks isi;
  final TokenTeks label;
  final TokenTeks keterangan;

  static SkalaTipografi Untuk(KepadatanTipografi kepadatan) => switch (kepadatan) {
    KepadatanTipografi.nyaman => nyaman,
    KepadatanTipografi.ringkas => ringkas,
  };

  /// Memetakan token ke slot Material agar widget bawaan Flutter ikut memakai skala ini.
  TextTheme KeTemaTeks(Color warnaUtama, Color warnaSekunder) {
    TextStyle Gaya(TokenTeks token, Color warna) => token.KeGaya().copyWith(color: warna);
    return TextTheme(
      displayLarge: Gaya(tampilan, warnaUtama),
      displayMedium: Gaya(tampilan, warnaUtama),
      displaySmall: Gaya(tampilan, warnaUtama),
      headlineLarge: Gaya(judul, warnaUtama),
      headlineMedium: Gaya(judul, warnaUtama),
      headlineSmall: Gaya(judul, warnaUtama),
      titleLarge: Gaya(judul, warnaUtama),
      titleMedium: Gaya(subjudul, warnaUtama),
      titleSmall: Gaya(label, warnaUtama),
      bodyLarge: Gaya(isi, warnaUtama),
      bodyMedium: Gaya(isi, warnaUtama),
      bodySmall: Gaya(keterangan, warnaSekunder),
      labelLarge: Gaya(label, warnaUtama),
      labelMedium: Gaya(label, warnaUtama),
      labelSmall: Gaya(keterangan, warnaSekunder),
    );
  }
}
