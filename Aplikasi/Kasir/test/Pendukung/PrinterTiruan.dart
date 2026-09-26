import 'dart:convert';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:kasir/Domain/Struk/PemindaiPrinter.dart';
import 'package:kasir/Domain/Struk/ProfilPrinter.dart';

/// Printer tiruan untuk test: menyimpan setiap kiriman byte; [galat] diisi = kiriman berikutnya gagal dengan pesan itu.
class PrinterTiruan implements TransportPrinter {
  final List<List<int>> kiriman = [];
  String? galat;

  @override
  Future<void> Kirim(List<int> data) async {
    final pesan = galat;
    if (pesan != null) {
      throw GalatPrinter(pesan);
    }
    kiriman.add(List.of(data));
  }

  /// Teks kiriman ke-[indeks] (byte perintah ESC/POS dibuang, baris dipisah `\n`).
  String AmbilTeks([int indeks = -1]) {
    final data = kiriman[indeks < 0 ? kiriman.length + indeks : indeks];
    return ascii.decode([
      for (final b in data)
        if (b == 0x0A || (b >= 0x20 && b < 0x7F)) b,
    ]);
  }

  /// Apakah kiriman ke-[indeks] memuat pulsa buka laci `ESC p`.
  bool CekBukaLaci([int indeks = -1]) {
    final data = kiriman[indeks < 0 ? kiriman.length + indeks : indeks];
    for (var i = 0; i + 1 < data.length; i++) {
      if (data[i] == 0x1B && data[i + 1] == 0x70) {
        return true;
      }
    }
    return false;
  }
}

/// Pemindai printer tiruan: semua jenis sambungan didukung, hasil cari & galat izin bisa diatur test; transport =
/// [printer] (bukan radio sungguhan).
/// Printer sistem tiruan (v1.97): mencatat dokumen utuh yang dikirim ke dialog cetak.
class PrinterSistemTiruan implements TransportDokumen {
  final List<(DokumenStruk, LebarKertas)> dokumen = [];

  @override
  Future<void> CetakDokumen(DokumenStruk dokumen, LebarKertas lebar) async => this.dokumen.add((dokumen, lebar));

  @override
  Future<void> Kirim(List<int> data) => throw UnimplementedError('Printer sistem tidak menerima ESC/POS.');
}

class PemindaiTiruan implements PemindaiPrinter {
  PemindaiTiruan(this.printer);

  final PrinterTiruan printer;
  final PrinterSistemTiruan sistem = PrinterSistemTiruan();
  final Map<JenisTransport, List<PrinterDitemukan>> hasil = {};
  String? galatSiapkan;
  final List<ProfilPrinter> transportDibuat = [];

  @override
  List<JenisTransport> AmbilJenisDidukung() => const [
    JenisTransport.Jaringan,
    JenisTransport.BluetoothKlasik,
    JenisTransport.Ble,
  ];

  @override
  Future<String?> SiapkanBluetooth(JenisTransport jenis) async => galatSiapkan;

  @override
  Future<List<PrinterDitemukan>> CariPrinter(JenisTransport jenis) async => hasil[jenis] ?? const [];

  @override
  TransportPrinter BuatTransport(ProfilPrinter profil) {
    transportDibuat.add(profil);
    return profil.jenis == JenisTransport.CetakSistem ? sistem : printer;
  }
}

/// Layar pelanggan tiruan (v2.01): mencatat setiap isi yang ditampilkan.
class LayarPelangganTiruan implements PortLayarPelanggan {
  final List<IsiLayarPelanggan> isi = [];
  var ditutup = 0;

  @override
  String get nama => 'Layar tiruan';

  @override
  Future<bool> CekTersedia() async => true;

  @override
  Future<void> Tampilkan(IsiLayarPelanggan baru) async => isi.add(baru);

  @override
  Future<void> Tutup() async => ditutup++;
}
