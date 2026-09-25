import 'package:adaptor_perangkat/AdaptorPerangkat.dart';

import 'ProfilPrinter.dart';

/// Printer yang bisa dipilih kasir: printer Bluetooth yang sudah di-pair, COM port Bluetooth (Windows), atau hasil
/// pindai BLE.
class PrinterDitemukan {
  const PrinterDitemukan({required this.jenis, required this.alamat, required this.nama});

  final JenisTransport jenis;
  final String alamat;
  final String nama;
}

/// Kemampuan printer platform ini (PRD §17.2.5, v1.80): jenis sambungan yang didukung, izin & keadaan Bluetooth,
/// pencarian printer, dan pembuatan transport. Implementasi nyata di `Data/Printer/`; test memakai tiruan.
abstract interface class PemindaiPrinter {
  /// Jenis sambungan yang didukung platform ini, urut tampil (LAN selalu ada).
  List<JenisTransport> AmbilJenisDidukung();

  /// Minta izin & periksa Bluetooth menyala. null = siap; selain itu pesan untuk kasir (apa yang terjadi + apa yang
  /// dilakukan).
  Future<String?> SiapkanBluetooth(JenisTransport jenis);

  /// Printer Bluetooth terpasang (Classic) atau hasil pindai BLE beberapa detik. Melempar [GalatPrinter] berpesan.
  Future<List<PrinterDitemukan>> CariPrinter(JenisTransport jenis);

  TransportPrinter BuatTransport(ProfilPrinter profil);
}
