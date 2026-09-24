import 'package:inti/Inti.dart';
import 'package:rational/rational.dart';

/// Arah pembulatan tunai (BR-08.6, PRD Rincian F-07a). `Terdekat`: tepat di tengah dibulatkan ke atas.
enum ArahPembulatan { Bawah, Atas, Terdekat }

/// Dasar pengenaan pajak: hanya nilai barang (`Subtotal`) atau barang ditambah biaya layanan (`SubtotalPlusLayanan`).
enum DasarPengenaanPajak { Subtotal, SubtotalPlusLayanan }

/// Satu jenis pajak dokumen, misal PPN 12% DPP nilai lain 11/12 atau PB1 10% atas subtotal + biaya layanan.
///
/// [tarif] dalam persen (tidak pernah di-hard-code, diambil dari `TarifPajak` bertanggal berlaku, CLAUDE.md #12).
final class DataPajakKalkulasi {
  DataPajakKalkulasi({
    required this.kode,
    required this.tarif,
    Rational? pengaliDpp,
    this.dasarPengenaan = DasarPengenaanPajak.Subtotal,
  }) : pengaliDpp = pengaliDpp ?? Rational.one;

  final String kode;
  final Decimal tarif;

  /// Pengali DPP pecahan eksak (bawaan 1/1), misal 11/12 untuk PPN DPP nilai lain.
  final Rational pengaliDpp;
  final DasarPengenaanPajak dasarPengenaan;
}

/// Potongan (diskon manual atau promo yang sudah diterapkan): persen dari dasar, atau nominal tetap.
///
/// Tepat satu dari [persen] atau [jumlah] terisi. Persen dihitung dari `Bruto` baris (potongan baris) atau dari
/// `Subtotal` (potongan pesanan), lalu dibulatkan setengah menjauhi nol ke sen.
final class DataPotongan {
  const DataPotongan._({this.persen, this.jumlah});

  /// Potongan persen, `0 ≤ persen ≤ 100`.
  static DataPotongan DariPersen(Decimal persen) => DataPotongan._(persen: persen);

  /// Potongan nominal tetap, `jumlah ≥ 0`.
  static DataPotongan DariJumlah(Uang jumlah) => DataPotongan._(jumlah: jumlah);

  final Decimal? persen;
  final Uang? jumlah;
}

/// Satu baris keranjang yang dihitung.
final class DataBarisKalkulasi {
  DataBarisKalkulasi({
    required this.jumlah,
    required this.hargaSatuan,
    Uang? hargaPilihan,
    this.hargaTermasukPajak,
    this.kodePajak,
    this.potongan = const [],
  }) : hargaPilihan = hargaPilihan ?? Uang.Nol();

  /// Jumlah barang (> 0, boleh desimal untuk barang timbangan).
  final Kuantitas jumlah;
  final Uang hargaSatuan;

  /// Tambahan harga dari pilihan/modifier per satuan (bawaan 0).
  final Uang hargaPilihan;

  /// Null = ikut pengaturan dokumen.
  final bool? hargaTermasukPajak;

  /// Kode pajak yang berlaku untuk baris ini. Null = semua pajak dokumen; daftar kosong = tanpa pajak.
  final List<String>? kodePajak;

  /// Potongan baris: diskon manual dan promo item yang sudah diterapkan ke baris ini.
  final List<DataPotongan> potongan;
}

/// Pembayaran. Metode `Tunai` memicu pembulatan tunai; [jumlah] null pada tunai berarti uang pas.
final class DataPembayaranKalkulasi {
  const DataPembayaranKalkulasi({required this.metode, this.jumlah});

  static const String metodeTunai = 'Tunai';

  final String metode;
  final Uang? jumlah;

  bool CekTunai() => metode == metodeTunai;
}

/// Pengaturan pembulatan tunai outlet (BR-08.6), misal kelipatan Rp 100 ke bawah.
final class DataPembulatanTunai {
  const DataPembulatanTunai({required this.kelipatan, required this.arah});

  /// Kelipatan Rupiah bulat (> 0).
  final int kelipatan;
  final ArahPembulatan arah;
}

/// Masukan lengkap mesin kalkulasi penjualan F-07a.
final class DataKalkulasi {
  DataKalkulasi({
    required this.hargaTermasukPajak,
    required this.baris,
    Decimal? persenBiayaLayanan,
    this.pembulatanTunai,
    this.pajak = const [],
    this.potonganPesanan = const [],
    this.pembayaran = const [],
  }) : persenBiayaLayanan = persenBiayaLayanan ?? Decimal.zero;

  /// Pengaturan bawaan harga termasuk pajak (baris boleh menimpa).
  final bool hargaTermasukPajak;

  /// Persen biaya layanan dari subtotal setelah diskon pesanan (bawaan 0).
  final Decimal persenBiayaLayanan;

  /// Null = tanpa pembulatan tunai.
  final DataPembulatanTunai? pembulatanTunai;

  /// Daftar pajak dokumen (kode unik).
  final List<DataPajakKalkulasi> pajak;
  final List<DataBarisKalkulasi> baris;

  /// Potongan tingkat pesanan: promo pesanan dan diskon manual pesanan.
  final List<DataPotongan> potonganPesanan;
  final List<DataPembayaranKalkulasi> pembayaran;
}
