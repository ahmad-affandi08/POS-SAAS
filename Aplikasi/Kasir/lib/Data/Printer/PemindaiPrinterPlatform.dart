import 'dart:io';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';

import '../../Domain/Struk/PemindaiPrinter.dart';
import '../../Domain/Struk/ProfilPrinter.dart';
import 'KanalBluetoothKlasik.dart';
import 'PortComWindows.dart';
import 'TransportBle.dart';

/// Printer yang didukung per platform (PRD §17.2.5, v1.80):
/// - Android: LAN/Wi-Fi, Bluetooth Classic (SPP, printer yang sudah di-pair), Bluetooth LE;
/// - iOS/iPadOS: LAN/Wi-Fi dan Bluetooth LE (iOS tidak mengizinkan Bluetooth Classic tanpa sertifikasi MFi);
/// - Windows: LAN/Wi-Fi, Bluetooth Classic (COM port virtual), Bluetooth LE;
/// - lainnya: LAN/Wi-Fi.
class PemindaiPrinterPlatform implements PemindaiPrinter {
  const PemindaiPrinterPlatform({
    this.klasik = const KanalBluetoothKlasik(),
    this.ble = const KlienUniversalBle(),
    this.lamaPindai = const Duration(seconds: 5),
  });

  final KanalBluetoothKlasik klasik;
  final KlienBle ble;
  final Duration lamaPindai;

  @override
  List<JenisTransport> AmbilJenisDidukung() => [
    JenisTransport.Jaringan,
    if (Platform.isAndroid || Platform.isWindows) JenisTransport.BluetoothKlasik,
    if (Platform.isAndroid || Platform.isIOS || Platform.isWindows || Platform.isMacOS) JenisTransport.Ble,
  ];

  @override
  Future<String?> SiapkanBluetooth(JenisTransport jenis) async => switch (jenis) {
    JenisTransport.BluetoothKlasik when Platform.isAndroid => klasik.Siapkan(),
    JenisTransport.BluetoothKlasik => null,
    JenisTransport.Ble => ble.Siapkan(),
    _ => null,
  };

  @override
  Future<List<PrinterDitemukan>> CariPrinter(JenisTransport jenis) async => switch (jenis) {
    JenisTransport.BluetoothKlasik when Platform.isAndroid => klasik.DaftarTerpasang(),
    JenisTransport.BluetoothKlasik when Platform.isWindows => PortComWindows.DaftarPortBluetooth(),
    JenisTransport.Ble => ble.Pindai(lamaPindai),
    _ => const <PrinterDitemukan>[],
  };

  @override
  TransportPrinter BuatTransport(ProfilPrinter profil) => switch (profil.jenis) {
    JenisTransport.BluetoothKlasik when Platform.isWindows => TransportComWindows(profil.alamat),
    JenisTransport.BluetoothKlasik => TransportBluetoothKlasikAndroid(profil.alamat, klasik),
    JenisTransport.Ble => TransportBle(profil.alamat, ble),
    _ => TransportJaringan(profil.alamat, port: profil.port),
  };
}
