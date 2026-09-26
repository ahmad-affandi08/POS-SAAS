import 'dart:convert';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:flutter/services.dart';

/// Layar kedua Android (PRD §17.2.5a `PortLayar`, v2.01) lewat `KanalLayarPelanggan.kt`: POS all-in-one dua layar
/// (Sunmi T2/D2s, iMin D4, dll.) atau monitor HDMI kedua sebagai *presentation display*. [warna] berisi nilai ARGB
/// token desain (Latar, Teks, TeksSekunder, Aksen) agar layar kedua mengikuti palet PAYOU.
class LayarPelangganAndroid implements PortLayarPelanggan {
  LayarPelangganAndroid({required this.warna, this.kanal = const MethodChannel(namaKanal)});

  static const String namaKanal = 'id.payou.kasir/layar-pelanggan';

  final Map<String, int> warna;
  final MethodChannel kanal;
  String? _terakhir;

  @override
  String get nama => 'Layar kedua';

  @override
  Future<bool> CekTersedia() async {
    try {
      return await kanal.invokeMethod<bool>('CekTersedia') ?? false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return false;
    }
  }

  @override
  Future<void> Tampilkan(IsiLayarPelanggan isi) async {
    final sidik = isi.AmbilSidik();
    if (sidik == _terakhir) {
      return;
    }
    try {
      await kanal.invokeMethod<bool>('Tampilkan', {
        'Isi': jsonEncode({...isi.KeJson(), 'Warna': warna}),
      });
      _terakhir = sidik;
    } on PlatformException catch (galat) {
      throw GalatPrinter(galat.message ?? 'Layar pelanggan tidak bisa ditampilkan.');
    } on MissingPluginException {
      throw const GalatPrinter('Layar kedua hanya didukung di Android.');
    }
  }

  @override
  Future<void> Tutup() async {
    _terakhir = null;
    try {
      await kanal.invokeMethod<void>('Tutup');
    } on PlatformException {
      // Sudah tertutup.
    } on MissingPluginException {
      // Bukan Android.
    }
  }
}
