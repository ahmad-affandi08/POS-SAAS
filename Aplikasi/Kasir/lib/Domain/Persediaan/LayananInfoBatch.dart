import 'package:klien_api/KlienApi.dart';

/// K-19 (F-05g): info batch & kedaluwarsa produk ber-batch di kasir. Server tetap memilih batch saat penjualan
/// disinkronkan (FEFO); info ini hanya untuk kasir sebelum menjual. Online saja; jawaban disimpan sebentar
/// ([lamaSimpan]) supaya memindai produk yang sama berulang tidak memanggil server tiap kali.
class LayananInfoBatch {
  LayananInfoBatch({required this.klien, DateTime Function()? jam}) : _jam = jam ?? DateTime.now;

  final KlienPos klien;
  final DateTime Function() _jam;
  final Map<String, ({DateTime waktu, BatchProdukPos info})> _simpanan = {};

  static const Duration lamaSimpan = Duration(minutes: 5);

  /// Batch terdepan yang kedaluwarsa dalam sekian hari (atau sudah lewat) memicu peringatan saat ditambah ke keranjang.
  static const int hariPeringatanJual = 7;

  /// Info batch terbaru dari server; [paksa] melewati simpanan. Galat jaringan/API dilempar ke pemanggil.
  Future<BatchProdukPos> Ambil(String uuidProduk, {bool paksa = false}) async {
    final simpan = _simpanan[uuidProduk];
    if (!paksa && simpan != null && _jam().difference(simpan.waktu) < lamaSimpan) {
      return simpan.info;
    }
    final info = await klien.AmbilBatchProduk(uuidProduk);
    _simpanan[uuidProduk] = (waktu: _jam(), info: info);
    return info;
  }

  /// Peringatan saat produk ber-batch ditambah ke keranjang; null bila aman, offline, atau server gagal.
  Future<({String teks, bool lewat})?> PeriksaSaatJual(String uuidProduk, String namaProduk) async {
    try {
      return SusunPeringatan(namaProduk, await Ambil(uuidProduk));
    } on GalatJaringan {
      return null;
    } on GalatApi {
      return null;
    }
  }

  /// Teks peringatan untuk batch terdepan (yang akan terjual lebih dulu). `lewat` = sudah lewat kedaluwarsa.
  static ({String teks, bool lewat})? SusunPeringatan(String namaProduk, BatchProdukPos info) {
    if (info.pelacakan != 'Batch') {
      return null;
    }
    if (info.batch.isEmpty) {
      return (
        teks: '$namaProduk: stok ber-batch di toko kosong. Penjualan tetap tercatat dan diperiksa back-office.',
        lewat: false,
      );
    }
    final depan = info.batch.first;
    final sisa = depan.sisaHari;
    if (sisa == null || sisa > hariPeringatanJual) {
      return null;
    }
    if (sisa < 0) {
      return (
        teks:
            '$namaProduk: batch ${depan.nomorBatch} sudah lewat kedaluwarsa ${-sisa} hari. Tarik dari rak sebelum dijual.',
        lewat: true,
      );
    }
    return (
      teks: sisa == 0
          ? '$namaProduk: batch ${depan.nomorBatch} kedaluwarsa hari ini.'
          : '$namaProduk: batch ${depan.nomorBatch} kedaluwarsa $sisa hari lagi.',
      lewat: false,
    );
  }

  /// Label status satu batch untuk daftar: "lewat 2 hari", "kedaluwarsa hari ini", "12 hari lagi", "tanpa kedaluwarsa".
  static String LabelSisaHari(int? sisaHari) => switch (sisaHari) {
    null => 'tanpa kedaluwarsa',
    < 0 => 'lewat ${-sisaHari} hari',
    0 => 'kedaluwarsa hari ini',
    _ => '$sisaHari hari lagi',
  };
}
