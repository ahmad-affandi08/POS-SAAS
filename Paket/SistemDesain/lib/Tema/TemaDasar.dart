import 'package:flutter/material.dart';

import '../Token/TokenTipografi.dart';
import '../Token/TokenWarna.dart';

/// Membuat [ThemeData] terang dari token (PRD §17.6). Aplikasi tidak menyusun tema sendiri.
///
/// Selalu terang: tidak ada mode gelap (D-14).
ThemeData BuatTema({KepadatanTipografi kepadatan = KepadatanTipografi.Nyaman}) {
  const warna = TokenWarna.bawaan;
  final skala = SkalaTipografi.AmbilUntuk(kepadatan);
  final skemaWarna = ColorScheme(
    brightness: Brightness.light,
    primary: warna.brand,
    onPrimary: warna.permukaan,
    secondary: warna.brand,
    onSecondary: warna.permukaan,
    error: warna.bahaya,
    onError: warna.permukaan,
    surface: warna.permukaan,
    onSurface: warna.teksUtama,
    onSurfaceVariant: warna.teksSekunder,
    outline: warna.garisInput,
    outlineVariant: warna.garis,
  );
  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    colorScheme: skemaWarna,
    scaffoldBackgroundColor: warna.latar,
    fontFamily: fontUtama,
    package: paketFont,
    textTheme: skala.KeTemaTeks(warna.teksUtama, warna.teksSekunder),
    dividerColor: warna.garis,
    // Target sentuh minimal 48dp (PRD §17.6, .claude/rules/Flutter.md).
    materialTapTargetSize: MaterialTapTargetSize.padded,
    visualDensity: VisualDensity.standard,
    extensions: [warna],
  );
}
