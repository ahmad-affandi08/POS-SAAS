import 'package:inti/Inti.dart';

/// K-8 (§5.1 mode Grosir "SKU × jumlah", juga dipakai mode lain): kasir mengetik `12*` lalu memindai barcode, atau
/// mengetik `12*8991234567890`/`12x kopi susu` di kolom cari, dan produk masuk keranjang sebanyak 12. Pemisah `*`, `x`,
/// atau `X`; jumlah boleh desimal (koma atau titik, maks. 3 angka di belakang) untuk satuan yang boleh desimal.
class PengaliJumlah {
  const PengaliJumlah._(this.jumlah, this.sisa);

  static final RegExp _pola = RegExp(r'^\s*(\d{1,6}(?:[.,]\d{1,3})?)\s*[*xX]\s*(.*)$');

  /// Jumlah yang diminta, atau null bila masukan tidak berawalan pengali.
  final Kuantitas? jumlah;

  /// Kata/kode setelah pengali (seluruh masukan bila tanpa pengali).
  final String sisa;

  /// Pengali tanpa kode (`12*`): menunggu pindaian berikutnya.
  bool get CekMenunggu => jumlah != null && sisa.isEmpty;

  static PengaliJumlah Urai(String masukan) {
    final cocok = _pola.firstMatch(masukan);
    if (cocok == null) {
      return PengaliJumlah._(null, masukan.trim());
    }
    final jumlah = Kuantitas.Dari(cocok.group(1)!.replaceAll(',', '.'));
    if (jumlah.BernilaiNol()) {
      return PengaliJumlah._(null, masukan.trim());
    }
    return PengaliJumlah._(jumlah, cocok.group(2)!.trim());
  }
}
