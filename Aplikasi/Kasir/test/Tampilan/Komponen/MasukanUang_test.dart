import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/Komponen/MasukanUang.dart';

void main() {
  TextEditingValue Ketik(String lama, String baru, {int? kursor}) => MasukanUang.SusunNilaiBerformat(
    TextEditingValue(text: lama),
    TextEditingValue(
      text: baru,
      selection: TextSelection.collapsed(offset: kursor ?? baru.length),
    ),
  );

  test('pemformat Rupiah: ribuan bertitik, hanya angka, nol di depan dibuang, maks. 13 digit', () {
    expect(Ketik('', '1250000').text, '1.250.000');
    expect(Ketik('', 'Rp 50.000abc').text, '50.000');
    expect(Ketik('', '000150').text, '150');
    expect(Ketik('', '0').text, '0');
    expect(Ketik('1.234.567.890.123', '1.234.567.890.1234').text, '1.234.567.890.123');
    expect(Ketik('', '').text, '');
  });

  test('pemformat Rupiah: kursor tetap di digit yang sama saat menyisip & menghapus di tengah', () {
    // `1.000` → sisipkan 5 setelah angka 1 → `15000` diketik `15.000`, kursor setelah angka 5.
    final sisip = Ketik('1.000', '15.000', kursor: 2);
    expect(sisip.text, '15.000');
    expect(sisip.selection.baseOffset, 2);
    // Hapus angka 2 dari `1.250.000` (kursor setelah 2) → `150.000`, kursor setelah angka 1.
    final hapus = Ketik('1.250.000', '1.50.000', kursor: 2);
    expect(hapus.text, '150.000');
    expect(hapus.selection.baseOffset, 1);
    // Backspace di titik ribuan `1.250.000` (kursor setelah titik pertama) menghapus angka 1 → `250.000`.
    final titik = Ketik('1.250.000', '1250.000', kursor: 1);
    expect(titik.text, '250.000');
    expect(titik.selection.baseOffset, 0);
  });

  test('UraiTeks, FormatTeks, dan Isi: teks berformat ↔ Uang bulat tanpa float', () {
    expect(MasukanUang.UraiTeks('1.250.000'), Uang.DariBulat(1250000));
    expect(MasukanUang.UraiTeks('Rp 75.500'), Uang.DariBulat(75500));
    expect(MasukanUang.UraiTeks(''), isNull);
    expect(MasukanUang.FormatTeks(Uang.Dari('1250000.75')), '1.250.000');
    final pengendali = TextEditingController();
    MasukanUang.Isi(pengendali, Uang.DariBulat(98000));
    expect(pengendali.text, '98.000');
    expect(MasukanUang.AmbilNilai(pengendali), Uang.DariBulat(98000));
    MasukanUang.Isi(pengendali, null);
    expect(pengendali.text, '');
  });

  testWidgets('isian uang menampilkan ribuan saat diketik dan membaca nilai Rupiah utuh', (tester) async {
    final pengendali = TextEditingController();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MasukanUang(pengendali: pengendali, label: 'Modal awal (kas awal)'),
        ),
      ),
    );
    await tester.enterText(find.byType(TextField), '2500000');
    await tester.pump();
    expect(find.text('2.500.000'), findsOneWidget);
    expect(MasukanUang.AmbilNilai(pengendali), Uang.DariBulat(2500000));
  });
}
