import 'package:klien_api/KlienApi.dart';

import '../GalatKasir.dart';
import '../Sesi/StafLokal.dart';

/// F-17 BR-17.2 ("86") di aplikasi kasir: produk yang habis hari ini ditandai per outlet supaya hilang dari menu
/// self-order & toko online, dan di layar Jual tampil "Habis" (tidak bisa ditambahkan sampai ditandai tersedia lagi).
/// Keadaannya milik server (dipakai bersama semua perangkat & kanal), jadi:
/// - **Muat**: unduh daftar saat online; offline/galat → `null` dan keadaan terakhir di perangkat dipakai.
/// - **Ubah**: tandai habis / tersedia lagi, wajib online, idempoten, dan hanya untuk staf yang boleh berjualan,
///   mencatat pesanan meja, atau mengelola produk.
class LayananKetersediaan {
  LayananKetersediaan({required this.klien});

  final KlienPos klien;

  Future<Set<String>?> Muat() async {
    try {
      return await klien.AmbilProdukHabis();
    } on GalatApi {
      return null;
    } on GalatJaringan {
      return null;
    }
  }

  /// Mengembalikan keadaan terbaru menurut server: true = habis.
  Future<bool> Ubah(String uuidProduk, {required bool habis, required StafLokal kasir}) async {
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat) &&
        !kasir.PunyaIzin(IzinKasir.pesananMejaCatat) &&
        !kasir.PunyaIzin(IzinKasir.produkKelola)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak boleh mengubah ketersediaan produk.');
    }
    try {
      return await klien.UbahKetersediaanProduk(uuidProduk, habis: habis, uuidPengguna: kasir.uuid);
    } on GalatJaringan {
      throw const GalatKasir(
        'PerluOnline',
        'Mengubah ketersediaan produk perlu koneksi internet. Coba lagi saat perangkat online.',
      );
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }
}
