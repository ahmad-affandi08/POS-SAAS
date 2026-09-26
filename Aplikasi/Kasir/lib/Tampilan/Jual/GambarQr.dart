import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Data/LayarPelanggan/ModulQr.dart';

/// Kode QR dari teks (QRIS dinamis, v2.05) digambar langsung di perangkat. Modul memakai token `teksUtama` di atas
/// `permukaan` dengan zona tenang 4 modul agar mudah dipindai kamera ponsel/aplikasi bank.
class GambarQr extends StatelessWidget {
  const GambarQr({super.key, required this.data, this.ukuran = 240, required this.label});

  final String data;
  final double ukuran;

  /// Label pembaca layar (misal "Kode QRIS Rp 25.000").
  final String label;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    return Semantics(
      label: label,
      image: true,
      child: Container(
        color: warna.permukaan,
        width: ukuran,
        height: ukuran,
        child: CustomPaint(
          painter: _PelukisQr(modul: SusunModulQr(data), warnaModul: warna.teksUtama),
        ),
      ),
    );
  }
}

class _PelukisQr extends CustomPainter {
  _PelukisQr({required this.modul, required this.warnaModul});

  final List<List<bool>> modul;
  final Color warnaModul;

  static const int zonaTenang = 4;

  @override
  void paint(Canvas canvas, Size size) {
    final jumlah = modul.length + zonaTenang * 2;
    final sisi = size.shortestSide / jumlah;
    final kuas = Paint()..color = warnaModul;
    for (var r = 0; r < modul.length; r++) {
      for (var c = 0; c < modul[r].length; c++) {
        if (modul[r][c]) {
          // Dilebarkan sedikit agar tidak ada garis tipis antar modul karena pembulatan piksel.
          canvas.drawRect(
            Rect.fromLTWH((c + zonaTenang) * sisi, (r + zonaTenang) * sisi, sisi + 0.5, sisi + 0.5),
            kuas,
          );
        }
      }
    }
  }

  @override
  bool shouldRepaint(_PelukisQr lama) => lama.modul != modul || lama.warnaModul != warnaModul;
}
