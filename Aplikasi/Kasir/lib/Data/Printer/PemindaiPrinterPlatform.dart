import 'dart:io';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:flutter/foundation.dart';

import '../../Domain/Struk/PemindaiPrinter.dart';
import '../../Domain/Struk/ProfilPrinter.dart';
import 'KanalBluetoothKlasik.dart';
import 'KanalUsbPrinter.dart';
import 'PortComWindows.dart';
import 'SpoolerWindows.dart';
import 'TransportBle.dart';

/// Printer yang didukung per platform (PRD §17.2.5, v1.80, v1.96):
/// - Android: LAN/Wi-Fi, Bluetooth Classic (SPP, printer yang sudah di-pair), Bluetooth LE, USB (USB host/OTG), dan
///   printer bawaan POS all-in-one (Sunmi lewat printer Bluetooth virtual "InnerPrinter", iMin & merek lain lewat USB
///   internal);
/// - iOS/iPadOS: LAN/Wi-Fi dan Bluetooth LE (iOS tidak mengizinkan Bluetooth Classic tanpa sertifikasi MFi);
/// - Windows: LAN/Wi-Fi, Bluetooth Classic (COM port virtual), Bluetooth LE, USB (printer terpasang, spooler RAW);
/// - lainnya: LAN/Wi-Fi.
class PemindaiPrinterPlatform implements PemindaiPrinter {
  const PemindaiPrinterPlatform({
    this.klasik = const KanalBluetoothKlasik(),
    this.usb = const KanalUsbPrinter(),
    this.ble = const KlienUniversalBle(),
    this.lamaPindai = const Duration(seconds: 5),
  });

  /// Alamat `ProfilPrinter` untuk printer bawaan Sunmi.
  static const String alamatSunmi = 'Sunmi';

  /// Printer bawaan Sunmi terdaftar sebagai perangkat Bluetooth ter-pair bernama "InnerPrinter" dengan alamat tetap.
  static const String alamatInnerPrinterSunmi = '00:11:22:33:44:55';

  /// Awalan alamat printer bawaan yang tersambung lewat USB internal (`usb:VID:PID`).
  static const String awalanUsb = 'usb:';

  final KanalBluetoothKlasik klasik;
  final KanalUsbPrinter usb;
  final KlienBle ble;
  final Duration lamaPindai;

  @override
  List<JenisTransport> AmbilJenisDidukung() => [
    if (Platform.isAndroid) JenisTransport.SdkVendor,
    JenisTransport.Jaringan,
    if (Platform.isAndroid || Platform.isWindows) JenisTransport.BluetoothKlasik,
    if (Platform.isAndroid || Platform.isIOS || Platform.isWindows || Platform.isMacOS) JenisTransport.Ble,
    if (Platform.isAndroid || Platform.isWindows) JenisTransport.Usb,
  ];

  @override
  Future<String?> SiapkanBluetooth(JenisTransport jenis) async => switch (jenis) {
    JenisTransport.BluetoothKlasik when Platform.isAndroid => klasik.Siapkan(),
    JenisTransport.SdkVendor when Platform.isAndroid && (await usb.AmbilInfo()).sunmi => klasik.Siapkan(),
    JenisTransport.Ble => ble.Siapkan(),
    _ => null,
  };

  @override
  Future<List<PrinterDitemukan>> CariPrinter(JenisTransport jenis) async => switch (jenis) {
    JenisTransport.BluetoothKlasik when Platform.isAndroid => klasik.DaftarTerpasang(),
    JenisTransport.BluetoothKlasik when Platform.isWindows => PortComWindows.DaftarPortBluetooth(),
    JenisTransport.Ble => ble.Pindai(lamaPindai),
    JenisTransport.Usb when Platform.isAndroid => usb.Daftar(),
    JenisTransport.Usb when Platform.isWindows => SpoolerWindows.DaftarPrinter(),
    JenisTransport.SdkVendor when Platform.isAndroid => CariPrinterBawaan(),
    _ => const <PrinterDitemukan>[],
  };

  /// Deteksi otomatis printer bawaan all-in-one (PRD §17.2.5a) dari merek perangkat: Sunmi = InnerPrinter Bluetooth;
  /// merek lain (iMin, dll.) = perangkat printer USB internal. Tidak dikenali = kosong (pakai jenis lain + wizard uji).
  @visibleForTesting
  Future<List<PrinterDitemukan>> CariPrinterBawaan() async {
    final info = await usb.AmbilInfo();
    if (info.sunmi) {
      return [
        PrinterDitemukan(
          jenis: JenisTransport.SdkVendor,
          alamat: alamatSunmi,
          nama: 'Printer bawaan Sunmi ${info.model}'.trim(),
        ),
      ];
    }
    final merek = info.imin ? 'iMin' : info.produsen;
    return [
      for (final p in await usb.Daftar(jenis: JenisTransport.SdkVendor))
        PrinterDitemukan(
          jenis: JenisTransport.SdkVendor,
          alamat: '$awalanUsb${p.alamat}',
          nama: 'Printer bawaan $merek ${info.model} (${p.nama})'.replaceAll(RegExp(r'\s+'), ' '),
        ),
    ];
  }

  @override
  TransportPrinter BuatTransport(ProfilPrinter profil) => switch (profil.jenis) {
    JenisTransport.BluetoothKlasik when Platform.isWindows => TransportComWindows(profil.alamat),
    JenisTransport.BluetoothKlasik => TransportBluetoothKlasikAndroid(profil.alamat, klasik),
    JenisTransport.Ble => TransportBle(profil.alamat, ble),
    JenisTransport.Usb when Platform.isWindows => TransportSpoolerWindows(profil.alamat),
    JenisTransport.Usb => TransportUsbAndroid(profil.alamat, usb),
    JenisTransport.SdkVendor when profil.alamat == alamatSunmi => TransportBluetoothKlasikAndroid(
      alamatInnerPrinterSunmi,
      klasik,
    ),
    JenisTransport.SdkVendor => TransportUsbAndroid(profil.alamat.replaceFirst(awalanUsb, ''), usb),
    _ => TransportJaringan(profil.alamat, port: profil.port),
  };
}
