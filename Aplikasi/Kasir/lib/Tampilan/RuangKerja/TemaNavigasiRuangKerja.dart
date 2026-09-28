import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Tema navigasi bingkai Ruang Kerja (PRD §17.2.7, D-16): rel kiri dan bilah bawah memakai warna merek yang sama
/// dengan bilah atas, sehingga bingkai kasir terbaca sebagai satu kerangka — bukan tiga potongan terpisah.
///
/// Dipasang lokal di bingkai ini, bukan di `TemaDasar`: tema dasar dipakai bersama Aplikasi Pemilik, yang
/// navigasinya tetap berada di atas permukaan putih.
abstract final class TemaNavigasiRuangKerja {
  /// Opasitas item non-aktif di atas latar merek. Putih 75% di atas `brandGelap` masih berkontras ±5,9:1
  /// (lolos WCAG AA), tetapi cukup redup sehingga item aktif tetap yang paling menonjol.
  static const double opasitasPasif = 0.75;

  /// Opasitas penanda item aktif: putih tipis di atas merek. Ikon putih di atasnya berkontras ±6,6:1.
  static const double opasitasPenanda = 0.18;

  /// Bentuk penanda item aktif, sama dengan radius kontrol lain di aplikasi.
  static final RoundedRectangleBorder _bentukPenanda = RoundedRectangleBorder(
    borderRadius: BorderRadius.circular(TokenJarak.radiusKontrol),
  );

  static TextStyle _GayaLabel(TokenWarna warna, TextTheme teks, {required bool aktif}) =>
      (teks.labelMedium ?? const TextStyle()).copyWith(
        color: aktif ? warna.permukaan : warna.permukaan.withValues(alpha: opasitasPasif),
        fontWeight: aktif ? FontWeight.w600 : FontWeight.w500,
      );

  static IconThemeData _GayaIkon(TokenWarna warna, {required bool aktif}) => IconThemeData(
    color: aktif ? warna.permukaan : warna.permukaan.withValues(alpha: opasitasPasif),
    size: TokenJarak.ikonBesar,
  );

  /// Tema rel navigasi kiri (tablet & desktop).
  static NavigationRailThemeData BuatTemaRel(TokenWarna warna, TextTheme teks) => NavigationRailThemeData(
    backgroundColor: warna.brandGelap,
    elevation: 0,
    indicatorColor: warna.permukaan.withValues(alpha: opasitasPenanda),
    indicatorShape: _bentukPenanda,
    useIndicator: true,
    selectedIconTheme: _GayaIkon(warna, aktif: true),
    unselectedIconTheme: _GayaIkon(warna, aktif: false),
    selectedLabelTextStyle: _GayaLabel(warna, teks, aktif: true),
    unselectedLabelTextStyle: _GayaLabel(warna, teks, aktif: false),
  );

  /// Tema bilah navigasi bawah (HP).
  static NavigationBarThemeData BuatTemaBilah(TokenWarna warna, TextTheme teks) => NavigationBarThemeData(
    backgroundColor: warna.brandGelap,
    elevation: 0,
    indicatorColor: warna.permukaan.withValues(alpha: opasitasPenanda),
    indicatorShape: _bentukPenanda,
    iconTheme: WidgetStateProperty.resolveWith(
      (keadaan) => _GayaIkon(warna, aktif: keadaan.contains(WidgetState.selected)),
    ),
    labelTextStyle: WidgetStateProperty.resolveWith(
      (keadaan) => _GayaLabel(warna, teks, aktif: keadaan.contains(WidgetState.selected)),
    ),
  );
}
