import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import '../GalatKasir.dart';
import 'Keranjang.dart';

/// Voucher di keranjang (F-16c bagian 2, CRM-06). Voucher **wajib online** (§18.4): kode diperiksa & dipesan server
/// untuk Uuid penjualan yang akan dibuat, sehingga voucher sekali pakai tidak terpakai dua kali di perangkat lain.
/// Offline → `GalatKasir` `PerluOnline`. Melepas voucher (hapus/batal transaksi) dicoba sekali; bila gagal, pesanan di
/// server kedaluwarsa sendiri.
class LayananVoucher {
  LayananVoucher({required this.klien, PembuatUlid? ulid}) : _ulid = ulid ?? PembuatUlid();

  static const int panjangKodeMaksimal = 30;

  final KlienPos klien;
  final PembuatUlid _ulid;

  /// Pesan [kode] untuk [keranjang]; Uuid penjualan dari voucher sebelumnya dipakai ulang agar tetap satu transaksi.
  Future<VoucherKeranjang> Pesan(String kode, Keranjang keranjang) async {
    final rapi = kode.trim().toUpperCase();
    if (rapi.isEmpty) {
      throw const GalatKasir('KodeVoucherWajib', 'Isi kode voucher.');
    }
    if (rapi.length > panjangKodeMaksimal) {
      throw const GalatKasir('VoucherTidakDitemukan', 'Kode voucher tidak ditemukan.');
    }
    final lama = keranjang.voucher;
    final uuidPenjualan = lama?.uuidPenjualan ?? _ulid.Buat();
    try {
      final hasil = await klien.PesanVoucher(rapi, uuidPenjualan);
      if (lama != null && lama.kode != hasil.kode) {
        await Lepas(lama);
      }
      return VoucherKeranjang(
        kode: hasil.kode,
        uuidPenjualan: uuidPenjualan,
        uuidPromo: hasil.uuidPromo,
        namaPromo: hasil.promo.nama,
        promo: hasil.promo.KeJson(),
      );
    } on GalatJaringan {
      throw const GalatKasir('PerluOnline', 'Voucher perlu koneksi internet. Coba lagi saat perangkat online.');
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }

  Future<void> Lepas(VoucherKeranjang voucher) async {
    try {
      await klien.LepasVoucher(voucher.kode, voucher.uuidPenjualan);
    } on GalatJaringan {
      // Pesanan voucher di server kedaluwarsa sendiri.
    } on GalatApi {
      // Idem.
    }
  }
}
