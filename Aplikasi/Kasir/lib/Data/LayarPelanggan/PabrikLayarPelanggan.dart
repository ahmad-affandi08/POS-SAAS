import 'dart:io';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';

import '../../Domain/Perangkat/LayananLayarPelanggan.dart';
import '../Printer/PortComWindows.dart';
import 'LayarPelangganAndroid.dart';

/// Membuat adaptor layar pelanggan sesuai platform (PRD §17.2.5a, v2.01): layar kedua di Android, VFD lewat COM port di
/// Windows. Platform lain / mode mati → tanpa layar pelanggan.
abstract final class PabrikLayarPelanggan {
  static List<String> AmbilModeTersedia() => [
    ModeLayarPelanggan.mati,
    if (Platform.isAndroid) ModeLayarPelanggan.layarKedua,
    if (Platform.isWindows) ModeLayarPelanggan.vfd,
  ];

  static PortLayarPelanggan Buat(PengaturanLayarPelanggan pengaturan, Map<String, int> warna) =>
      switch (pengaturan.mode) {
        ModeLayarPelanggan.layarKedua when Platform.isAndroid => LayarPelangganAndroid(warna: warna),
        ModeLayarPelanggan.vfd when Platform.isWindows && (pengaturan.portVfd ?? '').isNotEmpty => LayarPelangganVfd(
          TransportComWindows(pengaturan.portVfd!),
          nama: 'Layar VFD ${pengaturan.portVfd}',
        ),
        _ => const LayarPelangganTidakAda(),
      };
}
