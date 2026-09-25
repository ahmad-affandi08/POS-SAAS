import 'dart:async';
import 'dart:io';

/// Jenis sambungan printer (PRD §17.2.5). Fase ini: `Jaringan`. Lainnya menyusul bersama adaptor native.
enum JenisTransport {
  Jaringan('LAN / Wi-Fi'),
  BluetoothKlasik('Bluetooth'),
  Ble('Bluetooth LE'),
  Usb('USB'),
  SdkVendor('Printer bawaan'),
  CetakSistem('Printer sistem');

  const JenisTransport(this.label);

  final String label;
}

/// Galat printer dengan pesan untuk kasir: apa yang terjadi + apa yang bisa dilakukan (§17.6.6).
class GalatPrinter implements Exception {
  const GalatPrinter(this.pesan);

  final String pesan;

  @override
  String toString() => pesan;
}

/// Mengirim byte mentah ke printer. Implementasi per sambungan; kode fitur hanya mengenal antarmuka ini.
abstract interface class TransportPrinter {
  Future<void> Kirim(List<int> data);
}

/// Printer LAN/Wi-Fi mentah (RAW) di port 9100. Satu sambungan per pekerjaan cetak.
class TransportJaringan implements TransportPrinter {
  TransportJaringan(this.alamat, {this.port = portBawaan, this.batasWaktu = const Duration(seconds: 5)});

  static const int portBawaan = 9100;

  final String alamat;
  final int port;
  final Duration batasWaktu;

  @override
  Future<void> Kirim(List<int> data) async {
    Socket? soket;
    try {
      soket = await Socket.connect(alamat, port, timeout: batasWaktu);
      soket.add(data);
      await soket.flush().timeout(batasWaktu);
    } on SocketException {
      throw GalatPrinter(
        'Printer di $alamat:$port tidak tersambung. Pastikan printer menyala dan satu jaringan, lalu coba lagi.',
      );
    } on TimeoutException {
      throw GalatPrinter('Printer di $alamat:$port tidak menjawab. Cek kabel/Wi-Fi printer, lalu coba lagi.');
    } finally {
      await soket?.close().timeout(batasWaktu, onTimeout: () => null);
      soket?.destroy();
    }
  }
}
