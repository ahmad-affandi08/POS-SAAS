import 'package:klien_api/KlienApi.dart';

/// Hasil urai barcode timbangan: [kodeProduk] = 7 digit pertama (awalan + kode produk) yang dicocokkan dengan barcode
/// produk di katalog, dan [nilai] = string desimal berat dalam kg ([harga] false) atau harga Rupiah ([harga] true).
typedef HasilBarcodeTimbangan = ({String kodeProduk, String nilai, bool harga});

/// Pengurai barcode timbangan EAN-13 `AA PPPPP NNNNN C` (§9.3, v3.55). Murni: tanpa katalog & tanpa I/O.
///
/// Hanya kode 13 digit angka dengan awalan yang diatur tenant dan digit cek EAN-13 yang benar yang diurai; selain itu
/// null (barcode biasa tetap dicari apa adanya). Berat ditulis dalam gram (`01250` = 1,250 kg), harga dalam Rupiah.
abstract final class PenguraiBarcodeTimbangan {
  static HasilBarcodeTimbangan? Urai(String kode, BarcodeTimbanganPos pengaturan) {
    final k = kode.trim();
    if (!pengaturan.aktif || k.length != 13 || !RegExp(r'^\d{13}$').hasMatch(k)) {
      return null;
    }
    if (!pengaturan.awalan.contains(k.substring(0, 2)) || !CekDigitEan13(k)) {
      return null;
    }
    final angka = int.parse(k.substring(7, 12));
    if (angka == 0) {
      return null;
    }
    // Berat: gram → kg tiga desimal tanpa pembagian pecahan (uang & jumlah tidak pernah double).
    final nilai = pengaturan.nilaiHarga ? '$angka' : '${angka ~/ 1000}.${(angka % 1000).toString().padLeft(3, '0')}';
    return (kodeProduk: k.substring(0, 7), nilai: nilai, harga: pengaturan.nilaiHarga);
  }

  /// Digit ke-13 EAN-13 = (10 − (Σ digit ganjil + 3 × Σ digit genap) mod 10) mod 10.
  static bool CekDigitEan13(String kode) {
    var jumlah = 0;
    for (var i = 0; i < 12; i++) {
      final d = kode.codeUnitAt(i) - 48;
      jumlah += i.isEven ? d : d * 3;
    }
    return (10 - jumlah % 10) % 10 == kode.codeUnitAt(12) - 48;
  }
}
