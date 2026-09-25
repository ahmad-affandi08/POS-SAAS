import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';

/// Lebar logo di struk dalam titik (≈ 32 mm di 203 dpi): cukup jelas tanpa memakan banyak kertas.
const int lebarLogoStruk = 256;

/// Dekode PNG/JPEG logo (mesin gambar Flutter) lalu ubah ke 1 bit untuk printer thermal. Gambar rusak = null.
Future<GambarMonokrom?> UbahLogoKeMonokrom(Uint8List byte) async {
  try {
    final codec = await ui.instantiateImageCodec(byte);
    final bingkai = await codec.getNextFrame();
    final gambar = bingkai.image;
    final rgba = await gambar.toByteData(format: ui.ImageByteFormat.rawStraightRgba);
    final hasil = rgba == null
        ? null
        : GambarMonokrom.DariRgba(
            gambar.width,
            gambar.height,
            rgba.buffer.asUint8List(),
            lebarMaksimal: lebarLogoStruk,
          );
    gambar.dispose();
    codec.dispose();
    return hasil;
  } on Exception {
    return null;
  }
}
