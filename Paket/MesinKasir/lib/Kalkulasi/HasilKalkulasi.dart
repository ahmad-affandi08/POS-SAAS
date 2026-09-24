import 'package:inti/Inti.dart';

/// Rincian satu jenis pajak dokumen: DPP (dibulatkan dari nilai eksak) dan jumlah pajak (eksklusif + inklusif).
final class HasilPajakKalkulasi {
  const HasilPajakKalkulasi({required this.dpp, required this.jumlah});

  final Uang dpp;
  final Uang jumlah;
}

/// Hasil per baris, disnapshot ke `PenjualanDetail` (BR-07.2).
final class HasilBarisKalkulasi {
  const HasilBarisKalkulasi({
    required this.bruto,
    required this.diskon,
    required this.diskonPesanan,
    required this.biayaLayanan,
    required this.pajak,
    required this.pajakEksklusif,
    required this.totalBaris,
  });

  /// (HargaSatuan + HargaPilihan) × Jumlah, dibulatkan ke sen.
  final Uang bruto;

  /// Diskon baris (manual + promo item), dibatasi `bruto`.
  final Uang diskon;

  /// Bagian diskon pesanan yang dialokasikan ke baris ini.
  final Uang diskonPesanan;

  /// Bagian biaya layanan yang dialokasikan ke baris ini.
  final Uang biayaLayanan;

  /// Total pajak baris (inklusif + eksklusif).
  final Uang pajak;

  /// Bagian pajak yang ditambahkan di atas harga.
  final Uang pajakEksklusif;

  /// Bruto − diskon − diskon pesanan + biaya layanan + pajak eksklusif (tanpa pembulatan tunai).
  final Uang totalBaris;
}

/// Keluaran mesin kalkulasi penjualan F-07a. Σ `totalBaris` = `totalAkhir` − `pembulatan`.
final class HasilKalkulasi {
  const HasilKalkulasi({
    required this.subtotal,
    required this.diskonBaris,
    required this.diskonPesanan,
    required this.totalDiskon,
    required this.biayaLayanan,
    required this.totalPajak,
    required this.totalPajakEksklusif,
    required this.pembulatan,
    required this.totalAkhir,
    required this.kembalian,
    required this.pajak,
    required this.baris,
  });

  /// Σ netto baris (bruto − diskon baris).
  final Uang subtotal;
  final Uang diskonBaris;
  final Uang diskonPesanan;
  final Uang totalDiskon;
  final Uang biayaLayanan;
  final Uang totalPajak;
  final Uang totalPajakEksklusif;

  /// Selisih pembulatan tunai (negatif = dibulatkan ke bawah).
  final Uang pembulatan;
  final Uang totalAkhir;

  /// Null bila tidak ada pembayaran tunai; 0 bila tunai uang pas (tanpa jumlah).
  final Uang? kembalian;

  /// Rincian per kode pajak, urut sesuai daftar pajak dokumen.
  final Map<String, HasilPajakKalkulasi> pajak;
  final List<HasilBarisKalkulasi> baris;
}
