import 'package:flutter/widgets.dart';

import '../Token/TokenTipografi.dart';

/// Menampilkan kode yang harus dibaca persis (SKU, nomor dokumen, voucher, kode aktivasi) dengan font Mono (PRD §17.5).
class TeksKode extends StatelessWidget {
  const TeksKode(this.kode, {super.key, this.gaya});

  final String kode;
  final TextStyle? gaya;

  @override
  Widget build(BuildContext context) {
    final gayaDasar = gaya ?? DefaultTextStyle.of(context).style;
    return Text(
      kode,
      style: gayaDasar.copyWith(
        fontFamily: fontMono,
        package: paketFont,
        fontFeatures: const [FontFeature.tabularFigures()],
      ),
    );
  }
}
