import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Domain/Katalog/BarcodeTimbangan.dart';
import 'package:klien_api/KlienApi.dart';

/// v3.55 (§9.3): barcode timbangan EAN-13 `AA PPPPP NNNNN C`.
void main() {
  const berat = BarcodeTimbanganPos(aktif: true, awalan: ['27', '28']);
  const harga = BarcodeTimbanganPos(aktif: true, awalan: ['27'], nilaiHarga: true);

  test('berat gram → kg 3 desimal, kode produk 7 digit pertama', () {
    final hasil = PenguraiBarcodeTimbangan.Urai('2712345012508', berat)!;
    expect(hasil.kodeProduk, '2712345');
    expect(hasil.nilai, '1.250');
    expect(hasil.harga, isFalse);
    expect(PenguraiBarcodeTimbangan.Urai(' 2712345999991 ', berat)!.nilai, '99.999');
  });

  test('harga Rupiah apa adanya', () {
    final hasil = PenguraiBarcodeTimbangan.Urai('2712345400008', harga)!;
    expect((hasil.kodeProduk, hasil.nilai, hasil.harga), ('2712345', '40000', true));
  });

  test('bukan barcode timbangan: tidak aktif, awalan lain, panjang/angka salah, digit cek salah, nilai nol', () {
    expect(PenguraiBarcodeTimbangan.Urai('2712345012508', const BarcodeTimbanganPos(awalan: ['27'])), isNull);
    expect(PenguraiBarcodeTimbangan.Urai('2912345012502', berat), isNull, reason: 'Awalan 29 tidak diatur.');
    expect(PenguraiBarcodeTimbangan.Urai('2712345012509', berat), isNull, reason: 'Digit cek salah.');
    expect(PenguraiBarcodeTimbangan.Urai('271234501250', berat), isNull);
    expect(PenguraiBarcodeTimbangan.Urai('27123450125O8', berat), isNull);
    expect(PenguraiBarcodeTimbangan.Urai('8991234567893', berat), isNull);
    expect(PenguraiBarcodeTimbangan.CekDigitEan13('2712345000000'), isTrue);
    expect(PenguraiBarcodeTimbangan.Urai('2712345000000', berat), isNull, reason: 'Nilai nol bukan penjualan.');
  });
}
