import 'package:flutter/material.dart';

/// Token warna semantik (PRD §17.6.3).
///
/// **Sementara**: nilai masih usulan awal dan akan diganti saat identitas brand final (D-09).
/// Nilai akhir berasal dari `Spesifikasi/TokenDesain/Token.json` agar web dan Flutter selalu sama.
/// Kode fitur hanya memakai nama token, tidak pernah kode warna langsung.
@immutable
final class TokenWarna extends ThemeExtension<TokenWarna> {
  const TokenWarna({
    required this.latar,
    required this.permukaan,
    required this.garis,
    required this.garisInput,
    required this.teksUtama,
    required this.teksSekunder,
    required this.brand,
    required this.sukses,
    required this.peringatan,
    required this.bahaya,
    required this.info,
  });

  static const TokenWarna terang = TokenWarna(
    latar: Color(0xFFFAFAF7),
    permukaan: Color(0xFFFFFFFF),
    garis: Color(0xFFE4E2DC),
    garisInput: Color(0xFF8A877F),
    teksUtama: Color(0xFF1C1B19),
    teksSekunder: Color(0xFF5C5A55),
    brand: Color(0xFF0B6468),
    sukses: Color(0xFF2E7D32),
    peringatan: Color(0xFF9A5B00),
    bahaya: Color(0xFFB3261E),
    info: Color(0xFF1F5FAD),
  );

  static const TokenWarna gelap = TokenWarna(
    latar: Color(0xFF151514),
    permukaan: Color(0xFF1E1E1C),
    garis: Color(0xFF34332F),
    garisInput: Color(0xFF7A776F),
    teksUtama: Color(0xFFEDEBE6),
    teksSekunder: Color(0xFFA8A59E),
    brand: Color(0xFF5BB8BB),
    sukses: Color(0xFF6FBF73),
    peringatan: Color(0xFFE3A13B),
    bahaya: Color(0xFFF28B82),
    info: Color(0xFF8AB4F0),
  );

  final Color latar;
  final Color permukaan;
  final Color garis;
  final Color garisInput;
  final Color teksUtama;
  final Color teksSekunder;
  final Color brand;
  final Color sukses;
  final Color peringatan;
  final Color bahaya;
  final Color info;

  /// Token warna dari tema terdekat. Gagal keras bila tema belum memasang [TokenWarna].
  static TokenWarna Dari(BuildContext context) =>
      Theme.of(context).extension<TokenWarna>() ??
      (throw StateError('TokenWarna belum dipasang di ThemeData. Pakai BuatTema().'));

  @override
  TokenWarna copyWith({
    Color? latar,
    Color? permukaan,
    Color? garis,
    Color? garisInput,
    Color? teksUtama,
    Color? teksSekunder,
    Color? brand,
    Color? sukses,
    Color? peringatan,
    Color? bahaya,
    Color? info,
  }) => TokenWarna(
    latar: latar ?? this.latar,
    permukaan: permukaan ?? this.permukaan,
    garis: garis ?? this.garis,
    garisInput: garisInput ?? this.garisInput,
    teksUtama: teksUtama ?? this.teksUtama,
    teksSekunder: teksSekunder ?? this.teksSekunder,
    brand: brand ?? this.brand,
    sukses: sukses ?? this.sukses,
    peringatan: peringatan ?? this.peringatan,
    bahaya: bahaya ?? this.bahaya,
    info: info ?? this.info,
  );

  @override
  TokenWarna lerp(covariant TokenWarna? other, double t) {
    if (other == null) {
      return this;
    }
    return TokenWarna(
      latar: Color.lerp(latar, other.latar, t)!,
      permukaan: Color.lerp(permukaan, other.permukaan, t)!,
      garis: Color.lerp(garis, other.garis, t)!,
      garisInput: Color.lerp(garisInput, other.garisInput, t)!,
      teksUtama: Color.lerp(teksUtama, other.teksUtama, t)!,
      teksSekunder: Color.lerp(teksSekunder, other.teksSekunder, t)!,
      brand: Color.lerp(brand, other.brand, t)!,
      sukses: Color.lerp(sukses, other.sukses, t)!,
      peringatan: Color.lerp(peringatan, other.peringatan, t)!,
      bahaya: Color.lerp(bahaya, other.bahaya, t)!,
      info: Color.lerp(info, other.info, t)!,
    );
  }
}
