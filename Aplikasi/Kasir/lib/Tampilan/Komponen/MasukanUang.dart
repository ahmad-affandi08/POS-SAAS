import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:inti/Inti.dart';

/// Isian nominal Rupiah bulat (tanpa desimal, tanpa float): hanya angka, maks. 13 digit. `AmbilNilai` → [Uang].
class MasukanUang extends StatelessWidget {
  const MasukanUang({
    super.key,
    required this.pengendali,
    required this.label,
    this.galat,
    this.saatBerubah,
    this.autofocus = false,
  });

  final TextEditingController pengendali;
  final String label;
  final String? galat;
  final ValueChanged<String>? saatBerubah;
  final bool autofocus;

  static Uang? AmbilNilai(TextEditingController pengendali) {
    final teks = pengendali.text.trim();
    return teks.isEmpty ? null : Uang.Dari(teks);
  }

  @override
  Widget build(BuildContext context) => TextField(
    controller: pengendali,
    autofocus: autofocus,
    keyboardType: TextInputType.number,
    inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(13)],
    onChanged: saatBerubah,
    textAlign: TextAlign.right,
    style: const TextStyle(fontFeatures: [FontFeature.tabularFigures()]),
    decoration: InputDecoration(
      labelText: label,
      prefixText: 'Rp ',
      errorText: galat,
      border: const OutlineInputBorder(),
    ),
  );
}
