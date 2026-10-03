import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:inti/Inti.dart';

/// Isian nominal Rupiah bulat (tanpa desimal, tanpa float) berformat ribuan Indonesia saat diketik: `1250000` tampil
/// `1.250.000` dengan awalan `Rp`. Maks. 13 digit. Teks pengendali berisi angka berformat; baca lewat [AmbilNilai] /
/// [UraiTeks] dan isi lewat [Isi] / [FormatTeks], jangan `Uang.Dari(pengendali.text)` langsung.
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

  static const int digitMaksimal = 13;

  /// Pemformat ribuan Rupiah untuk isian uang lain yang tidak memakai widget ini (misal isian bersaklar persen/nominal).
  static const TextInputFormatter pemformat = TextInputFormatter.withFunction(SusunNilaiBerformat);

  static Uang? AmbilNilai(TextEditingController pengendali) => UraiTeks(pengendali.text);

  /// `1.250.000` / `1250000` / `Rp 1.250.000` → Uang; null bila kosong.
  static Uang? UraiTeks(String teks) {
    final digit = teks.replaceAll(RegExp(r'[^0-9]'), '');
    return digit.isEmpty ? null : Uang.Dari(digit);
  }

  /// Uang → teks isian berformat (`1.250.000`); bagian sen dibuang karena isian kasir selalu Rupiah bulat.
  static String FormatTeks(Uang nilai) => FormatDigit(nilai.KeDesimal().truncate().toBigInt().abs().toString());

  static void Isi(TextEditingController pengendali, Uang? nilai) {
    final teks = nilai == null ? '' : FormatTeks(nilai);
    pengendali.value = TextEditingValue(
      text: teks,
      selection: TextSelection.collapsed(offset: teks.length),
    );
  }

  /// `1250000` → `1.250.000` (titik ribuan Indonesia).
  static String FormatDigit(String digit) {
    final bersih = digit.replaceFirst(RegExp(r'^0+(?=\d)'), '');
    final hasil = StringBuffer();
    for (var i = 0; i < bersih.length; i++) {
      if (i > 0 && (bersih.length - i) % 3 == 0) {
        hasil.write('.');
      }
      hasil.write(bersih[i]);
    }
    return hasil.toString();
  }

  /// Fungsi pemformat: hanya angka, maks. [digitMaksimal] digit, dikelompokkan per ribuan; kursor tetap di posisi digit
  /// yang sama dihitung dari kanan, sehingga menyisipkan atau menghapus di tengah tidak melompat ke akhir.
  static TextEditingValue SusunNilaiBerformat(TextEditingValue lama, TextEditingValue baru) {
    final bukanDigit = RegExp(r'[^0-9]');
    var digit = baru.text.replaceAll(bukanDigit, '');
    if (digit.length > digitMaksimal) {
      return lama;
    }
    final ujung = baru.selection.end < 0 ? baru.text.length : baru.selection.end.clamp(0, baru.text.length);
    final digitKananKursor = baru.text.substring(ujung).replaceAll(bukanDigit, '').length;
    // Backspace tepat di titik ribuan hanya menghapus titiknya: hapus digit di kiri kursor agar tidak tersangkut.
    if (baru.text.length < lama.text.length && digit == lama.text.replaceAll(bukanDigit, '')) {
      final indeks = digit.length - digitKananKursor - 1;
      if (indeks >= 0) {
        digit = digit.substring(0, indeks) + digit.substring(indeks + 1);
      }
    }
    digit = digit.replaceFirst(RegExp(r'^0+(?=\d)'), '');
    final teks = FormatDigit(digit);
    var posisi = teks.length;
    var terlewati = 0;
    while (posisi > 0 && terlewati < digitKananKursor) {
      posisi--;
      if (teks[posisi] != '.') {
        terlewati++;
      }
    }
    // Kursor tepat setelah titik ribuan digeser ke sebelum titik (setelah digit yang baru diketik).
    if (posisi > 0 && teks[posisi - 1] == '.') {
      posisi--;
    }
    return TextEditingValue(
      text: teks,
      selection: TextSelection.collapsed(offset: posisi),
    );
  }

  @override
  Widget build(BuildContext context) => TextField(
    controller: pengendali,
    autofocus: autofocus,
    keyboardType: TextInputType.number,
    inputFormatters: [pemformat],
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
