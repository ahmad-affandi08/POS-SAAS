import 'package:flutter/material.dart';

import '../Token/TokenJarak.dart';

/// Papan angka besar untuk jumlah & uang (PRD §17.2.7): 1–9, 000, 0, hapus. Tombol mengisi lebar tersedia, tinggi
/// 56dp (≥ target sentuh 48dp). Nilai dikelola pemanggil lewat [Terapkan].
class PapanAngka extends StatelessWidget {
  const PapanAngka({super.key, required this.saatTekan, this.tinggiTombol = 56});

  static const String tombolHapus = 'Hapus';

  final ValueChanged<String> saatTekan;
  final double tinggiTombol;

  /// Terapkan [tombol] ke teks angka [nilai] (hanya digit, tanpa nol di depan, maks. [panjangMaks] digit).
  static String Terapkan(String nilai, String tombol, {int panjangMaks = 13}) {
    if (tombol == tombolHapus) {
      return nilai.isEmpty ? '' : nilai.substring(0, nilai.length - 1);
    }
    final baru = '$nilai$tombol'.replaceFirst(RegExp(r'^0+'), '');
    return baru.length > panjangMaks ? nilai : baru;
  }

  @override
  Widget build(BuildContext context) {
    Widget Tombol(String label, {String? semantik, IconData? ikon}) => Expanded(
      child: Padding(
        padding: const EdgeInsets.all(TokenJarak.jarak4),
        child: SizedBox(
          height: tinggiTombol,
          child: OutlinedButton(
            onPressed: () => saatTekan(label),
            child: ikon != null
                ? Icon(ikon, semanticLabel: semantik)
                : Text(label, style: Theme.of(context).textTheme.titleMedium),
          ),
        ),
      ),
    );

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (final baris in const [
          ['1', '2', '3'],
          ['4', '5', '6'],
          ['7', '8', '9'],
        ])
          Row(children: [for (final angka in baris) Tombol(angka)]),
        Row(
          children: [
            Tombol('000'),
            Tombol('0'),
            Tombol(tombolHapus, semantik: 'Hapus satu angka', ikon: Icons.backspace_outlined),
          ],
        ),
      ],
    );
  }
}
