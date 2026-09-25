import 'dart:ffi';
import 'dart:isolate';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:ffi/ffi.dart';
import 'package:win32/win32.dart';

import '../../Domain/Struk/PemindaiPrinter.dart';

/// Printer Bluetooth Classic di Windows (PRD §17.2.5, v1.80): setelah printer di-pair di Pengaturan Windows › Bluetooth,
/// Windows membuat COM port virtual "Standard Serial over Bluetooth link". Port Bluetooth dikenali dari registry
/// `HKLM\HARDWARE\DEVICEMAP\SERIALCOMM` (nama nilai `\Device\BthModemN`); data ESC/POS ditulis ke port itu.
abstract final class PortComWindows {
  /// COM port Bluetooth yang terdaftar, misal `COM5`. Registry tidak terbaca = daftar kosong.
  static List<PrinterDitemukan> DaftarPortBluetooth() => using((arena) {
    final kunci = arena<Pointer>();
    if (RegOpenKeyEx(HKEY_LOCAL_MACHINE, arena.pcwstr(r'HARDWARE\DEVICEMAP\SERIALCOMM'), 0, KEY_READ, kunci) !=
        ERROR_SUCCESS) {
      return const <PrinterDitemukan>[];
    }
    final hkey = HKEY(kunci.value);
    final hasil = <PrinterDitemukan>[];
    try {
      const panjangNama = 256;
      const panjangData = 512;
      final nama = arena.pwstrBuffer(panjangNama);
      final data = arena<Uint8>(panjangData);
      final ukuranNama = arena<Uint32>();
      final ukuranData = arena<Uint32>();
      for (var i = 0; ; i++) {
        ukuranNama.value = panjangNama;
        ukuranData.value = panjangData;
        final galat = RegEnumValue(hkey, i, nama, ukuranNama, null, data, ukuranData);
        if (galat != ERROR_SUCCESS) {
          break;
        }
        final perangkat = nama.toDartString();
        final port = data.cast<Utf16>().toDartString();
        if (perangkat.toLowerCase().contains('bth') && port.toUpperCase().startsWith('COM')) {
          hasil.add(PrinterDitemukan(jenis: JenisTransport.BluetoothKlasik, alamat: port, nama: '$port (Bluetooth)'));
        }
      }
    } finally {
      RegCloseKey(hkey);
    }
    return hasil..sort((a, b) => a.alamat.compareTo(b.alamat));
  });
}

/// Menulis byte ESC/POS ke COM port Bluetooth Windows. Port dibuka per pekerjaan cetak lalu ditutup; penulisan
/// (blocking, bisa beberapa detik lewat Bluetooth) berjalan di isolate lain agar layar kasir tidak macet.
class TransportComWindows implements TransportPrinter {
  const TransportComWindows(this.port);

  final String port;

  @override
  Future<void> Kirim(List<int> data) async {
    final port = this.port;
    final salinan = List<int>.of(data);
    final galat = await Isolate.run(() => _Tulis(port, salinan));
    if (galat != null) {
      throw GalatPrinter(galat);
    }
  }

  /// null = berhasil; selain itu pesan untuk kasir.
  static String? _Tulis(String port, List<int> data) => using((arena) {
    final hasilBuka = CreateFile(
      arena.pcwstr('\\\\.\\$port'),
      GENERIC_WRITE,
      FILE_SHARE_NONE,
      null,
      OPEN_EXISTING,
      FILE_ATTRIBUTE_NORMAL,
      null,
    );
    final pegangan = hasilBuka.value;
    if (pegangan == INVALID_HANDLE_VALUE) {
      return 'Printer Bluetooth di $port tidak tersambung. Nyalakan printer dan pastikan sudah di-pair di Windows, '
          'lalu coba lagi.';
    }
    try {
      final batas = arena<COMMTIMEOUTS>()
        ..ref.WriteTotalTimeoutConstant = 5000
        ..ref.WriteTotalTimeoutMultiplier = 2;
      SetCommTimeouts(pegangan, batas);
      final penyangga = arena<Uint8>(data.length);
      penyangga.asTypedList(data.length).setAll(0, data);
      final tertulis = arena<Uint32>();
      var terkirim = 0;
      while (terkirim < data.length) {
        final hasil = WriteFile(pegangan, penyangga + terkirim, data.length - terkirim, tertulis, null);
        if (!hasil.value || tertulis.value == 0) {
          return 'Struk gagal terkirim ke printer Bluetooth di $port. Coba cetak lagi.';
        }
        terkirim += tertulis.value;
      }
      FlushFileBuffers(pegangan);
      return null;
    } finally {
      CloseHandle(pegangan);
    }
  });
}
