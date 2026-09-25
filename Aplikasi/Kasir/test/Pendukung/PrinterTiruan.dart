import 'dart:convert';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';

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
