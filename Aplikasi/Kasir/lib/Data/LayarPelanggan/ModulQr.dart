import 'package:qr/qr.dart';

/// Matriks modul QR (baris × kolom, true = gelap) dari [data], tingkat koreksi M (cukup untuk QRIS di layar).
/// Dipakai `GambarQr` dan layar pelanggan Android (dikirim sebagai baris "0/1" agar Kotlin tidak butuh pustaka QR).
List<List<bool>> SusunModulQr(String data) {
  final gambar = QrImage(QrCode.fromData(data: data, errorCorrectLevel: QrErrorCorrectLevel.M));
  return [
    for (var r = 0; r < gambar.moduleCount; r++) [for (var c = 0; c < gambar.moduleCount; c++) gambar.isDark(r, c)],
  ];
}
