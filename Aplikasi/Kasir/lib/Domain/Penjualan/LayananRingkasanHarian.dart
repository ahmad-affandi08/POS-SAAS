import 'package:klien_api/KlienApi.dart';

import '../GalatKasir.dart';

/// K-24: ringkasan akhir hari outlet dari server (semua perangkat di outlet; perlu online). Riwayat transaksi per
/// tanggal milik perangkat ini sendiri dibaca dari basis data lokal dan tetap tersedia offline.
class LayananRingkasanHarian {
  LayananRingkasanHarian({required this.klien});

  final KlienPos klien;

  Future<RingkasanHarianPos> Ambil({String? tanggal}) async {
    try {
      return await klien.AmbilRingkasanHarian(tanggal: tanggal);
    } on GalatJaringan {
      throw const GalatKasir(
        'PerluOnline',
        'Ringkasan outlet perlu koneksi internet. Coba lagi saat perangkat online.',
      );
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }
}
