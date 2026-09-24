import 'package:flutter_test/flutter_test.dart';
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/Jual/PanelBayar.dart';
import 'package:kasir/Tampilan/Jual/PengenalPemindai.dart';
import 'package:kasir/Tampilan/Komponen/FormatAngka.dart';

/// Pemindai tanpa fokus (PRD §17.2.7) & pecahan cepat tunai (Rincian F-07c).
void main() {
  Duration Ms(int n) => Duration(milliseconds: n);

  test('rangkaian karakter cepat diakhiri Enter = hasil pindai', () {
    final pemindai = PengenalPemindai();
    var t = 1000;
    for (final c in '8991000000028'.split('')) {
      pemindai.Terima(c, Ms(t += 8));
    }
    expect(pemindai.Selesai(Ms(t + 10)), '8991000000028');
    expect(pemindai.Selesai(Ms(t + 20)), isNull, reason: 'Penyangga dikosongkan setelah Enter.');
  });

  test('ketikan manusia (jeda > 100 ms) dan kode terlalu pendek diabaikan', () {
    final pemindai = PengenalPemindai();
    var t = 1000;
    for (final c in '12345'.split('')) {
      pemindai.Terima(c, Ms(t += 250));
    }
    expect(pemindai.Selesai(Ms(t + 30)), isNull);

    for (final c in 'AB1'.split('')) {
      pemindai.Terima(c, Ms(t += 5));
    }
    expect(pemindai.Selesai(Ms(t + 5)), isNull);

    // Jeda lama sebelum Enter juga bukan pindaian.
    for (final c in '99887766'.split('')) {
      pemindai.Terima(c, Ms(t += 5));
    }
    expect(pemindai.Selesai(Ms(t + 500)), isNull);
  });

  test('karakter lambat di awal lalu rangkaian cepat: hanya rangkaian cepat yang dipakai', () {
    final pemindai = PengenalPemindai();
    pemindai.Terima('x', Ms(0));
    var t = 2000;
    for (final c in 'KSA-01'.split('')) {
      pemindai.Terima(c, Ms(t += 10));
    }
    expect(pemindai.Selesai(Ms(t + 10)), 'KSA-01');
  });

  test('pecahan cepat tunai di atas tagihan, unik, maksimal 4', () {
    expect(HitungPecahanCepat(Uang.DariBulat(60500)).map((u) => u.FormatRupiah()), [
      'Rp 65.000',
      'Rp 70.000',
      'Rp 80.000',
      'Rp 100.000',
    ]);
    expect(HitungPecahanCepat(Uang.DariBulat(100000)), isEmpty, reason: 'Tagihan bulat: cukup uang pas.');
    expect(HitungPecahanCepat(Uang.DariBulat(1255500)).map((u) => u.FormatRupiah()), [
      'Rp 1.260.000',
      'Rp 1.300.000',
    ], reason: 'Kelipatan yang menghasilkan nominal sama tidak diulang.');
  });

  test('format angka Indonesia untuk jumlah & persen', () {
    expect(FormatAngka.FormatJumlah(Kuantitas.Dari('2.0000')), '2');
    expect(FormatAngka.FormatJumlah(Kuantitas.Dari('1.5000')), '1,5');
    expect(FormatAngka.FormatPersen('12.00'), '12%');
    expect(FormatAngka.UraiDesimal('12,5'), Decimal.parse('12.5'));
    expect(FormatAngka.UraiDesimal(''), isNull);
  });
}
