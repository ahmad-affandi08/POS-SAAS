import 'package:flutter/material.dart';
import 'package:inti/Inti.dart';

import 'TeksUang.dart';

/// Hitung uang laci per pecahan (buka & tutup shift, PRD F-06/F-11): satu baris per nominal dengan tombol kurang/tambah
/// (target sentuh ≥ 48dp) dan jumlah lembar/keping. [jumlah] = lembar per nominal; nominal yang tidak ada = 0.
/// Pemakai menyimpan keadaan dan menerima perubahan lewat [saatBerubah] (nominal, jumlah baru ≥ 0).
class HitungPecahan extends StatelessWidget {
  const HitungPecahan({super.key, required this.nominal, required this.jumlah, required this.saatBerubah});

  /// Batas lembar per nominal (sama dengan validasi server).
  static const int jumlahMaksimum = 100000;

  final List<int> nominal;
  final Map<int, int> jumlah;
  final void Function(int nominal, int jumlahBaru) saatBerubah;

  /// Total uang dari hitungan per pecahan.
  static Uang HitungTotal(Map<int, int> jumlah) =>
      jumlah.entries.fold(Uang.Nol(), (t, e) => t.Tambah(Uang.DariBulat(e.key).Kali(Decimal.fromInt(e.value))));

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final n in nominal)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 2),
            child: Row(
              children: [
                Expanded(child: TeksUang(Uang.DariBulat(n), rataKanan: false)),
                IconButton(
                  tooltip: 'Kurangi ${Uang.DariBulat(n).FormatRupiah()}',
                  onPressed: (jumlah[n] ?? 0) > 0 ? () => saatBerubah(n, (jumlah[n] ?? 0) - 1) : null,
                  icon: const Icon(Icons.remove),
                ),
                SizedBox(
                  width: 48,
                  child: Text(
                    '${jumlah[n] ?? 0}',
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontFeatures: [FontFeature.tabularFigures()]),
                  ),
                ),
                IconButton(
                  tooltip: 'Tambah ${Uang.DariBulat(n).FormatRupiah()}',
                  onPressed: (jumlah[n] ?? 0) < jumlahMaksimum ? () => saatBerubah(n, (jumlah[n] ?? 0) + 1) : null,
                  icon: const Icon(Icons.add),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
