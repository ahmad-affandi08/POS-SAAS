import 'package:flutter/material.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';

/// Kotak panel netral bergaris tipis (radius 8), pengganti kartu berbayangan.
class KotakPanel extends StatelessWidget {
  const KotakPanel({super.key, required this.anak});

  final Widget anak;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: warna.permukaan,
        border: Border.all(color: warna.garis, width: TokenJarak.tebalGaris),
        borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
      ),
      child: Padding(padding: const EdgeInsets.all(TokenJarak.jarak16), child: anak),
    );
  }
}
