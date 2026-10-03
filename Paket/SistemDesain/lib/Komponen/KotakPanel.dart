import 'package:flutter/material.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';

/// Kotak panel netral bergaris tipis (radius 8), pengganti kartu berbayangan.
class KotakPanel extends StatelessWidget {
  const KotakPanel({super.key, required this.anak, this.rapat = false});

  final Widget anak;

  /// Tanpa padding dalam: untuk daftar baris yang mengatur jaraknya sendiri (baris bergaris pemisah).
  final bool rapat;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    // Material (bukan DecoratedBox) supaya ListTile/InkWell di dalamnya tetap menampilkan efek sentuh.
    return Material(
      color: warna.permukaan,
      shape: RoundedRectangleBorder(
        side: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
        borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
      ),
      clipBehavior: Clip.antiAlias,
      child: Padding(padding: EdgeInsets.all(rapat ? 0 : TokenJarak.jarak16), child: anak),
    );
  }
}
