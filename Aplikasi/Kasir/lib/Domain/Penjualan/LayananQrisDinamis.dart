import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../GalatKasir.dart';
import 'KonteksPenjualan.dart';

/// QRIS dinamis lewat gerbang pembayaran yang diatur di konsol Platform Pengelola (F-08, v2.05). Wajib online:
/// perangkat meminta tagihan ke server (server yang memanggil gerbang; kredensial tidak pernah ada di perangkat),
/// menampilkan QR, lalu memantau status sampai `Lunas`. Pembayaran di penjualan membawa Uuid tagihan sebagai
/// `Referensi` sehingga server bisa mencocokkannya (tagihan tidak cocok → penjualan ditandai perlu ditinjau).
///
/// Uuid tagihan dibuat di perangkat (ULID) agar permintaan ulang saat jaringan putus tidak membuat tagihan ganda.
class LayananQrisDinamis {
  LayananQrisDinamis({required this.klien, PembuatUlid? ulid}) : _ulid = ulid ?? PembuatUlid();

  final KlienPos klien;
  final PembuatUlid _ulid;

  /// Buat tagihan untuk [jumlah] (Rupiah bulat). Offline/gerbang gagal → [GalatKasir] dengan saran metode lain.
  Future<TagihanQrisPos> Buat({required BarisMetodePembayaran metode, required Uang jumlah, String? keterangan}) async {
    if (metode.Jenis != JenisMetodeBayar.qrisDinamis) {
      throw GalatKasir('MetodeBukanQrisDinamis', 'Metode ${metode.Nama} bukan QRIS dinamis.');
    }
    final desimal = jumlah.KeDesimal();
    if (desimal <= Decimal.zero || desimal != desimal.floor()) {
      throw const GalatKasir('JumlahTidakBulat', 'Jumlah QRIS harus Rupiah bulat lebih dari Rp 0.');
    }
    return _Jalankan(
      () => klien.BuatQris(
        uuid: _ulid.Buat(),
        uuidMetode: metode.Uuid,
        jumlah: desimal.toBigInt().toString(),
        keterangan: keterangan,
      ),
    );
  }

  Future<StatusQrisPos> AmbilStatus(String uuid) => _Jalankan(() => klien.AmbilStatusQris(uuid));

  /// Batalkan tagihan yang belum dibayar. Sudah lunas → [GalatKasir] `SudahLunas` (pemanggil memakai tagihan itu).
  Future<void> Batalkan(String uuid) => _Jalankan(() => klien.BatalkanQris(uuid));

  static Future<T> _Jalankan<T>(Future<T> Function() aksi) async {
    try {
      return await aksi();
    } on GalatJaringan {
      throw const GalatKasir(
        'QrisDinamisOffline',
        'QRIS dinamis butuh internet dan gerbang pembayaran yang aktif. Coba lagi, atau pakai QRIS statis/tunai.',
      );
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }
}
