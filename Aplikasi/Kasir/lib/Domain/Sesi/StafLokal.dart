import 'dart:convert';

import 'package:klien_api/KlienApi.dart';

import '../../Data/BasisData/BasisDataKasir.dart';

/// Izin tenant yang dipakai aplikasi kasir (sama dengan `IzinTenant` server).
abstract final class IzinKasir {
  static const String penjualanBuat = 'penjualan.buat';
  static const String kasKeluarSetujui = 'kas.keluar.setujui';
  static const String penjualanDiskonManual = 'penjualan.diskon.manual';
  static const String penjualanDiskonSetujui = 'penjualan.diskon.setujui';
}

/// Staf dari data awal (tabel `Staf` lokal).
class StafLokal {
  const StafLokal({required this.uuid, required this.nama, required this.pemilik, required this.izin, this.pin});

  final String uuid;
  final String nama;
  final bool pemilik;
  final List<String> izin;
  final PinTerbungkus? pin;

  bool PunyaIzin(String kunci) => pemilik || izin.contains(kunci);

  static StafLokal DariBaris(BarisStaf baris) {
    final izin = jsonDecode(baris.Izin);
    return StafLokal(
      uuid: baris.Uuid,
      nama: baris.Nama,
      pemilik: baris.Pemilik,
      izin: izin is List<Object?> ? izin.whereType<String>().toList() : const [],
      pin: baris.PinGaram == null || baris.PinNonce == null || baris.PinSandi == null
          ? null
          : PinTerbungkus(garam: baris.PinGaram!, nonce: baris.PinNonce!, sandi: baris.PinSandi!),
    );
  }
}
