import 'package:flutter/widgets.dart';
import 'package:inti/Inti.dart';

/// Menampilkan [Uang] dalam format Rupiah dengan angka tabular (PRD §17.5).
///
/// Semua tampilan nominal wajib lewat widget ini agar format & perataan angka seragam.
class TeksUang extends StatelessWidget {
  const TeksUang(this.nilai, {super.key, this.gaya, this.rataKanan = true});

  final Uang nilai;
  final TextStyle? gaya;
  final bool rataKanan;

  @override
  Widget build(BuildContext context) {
    final gayaDasar = gaya ?? DefaultTextStyle.of(context).style;
    return Text(
      nilai.FormatRupiah(),
      textAlign: rataKanan ? TextAlign.right : TextAlign.left,
      style: gayaDasar.copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
    );
  }
}
