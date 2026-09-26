import 'package:klien_api/KlienApi.dart';

import '../GalatKasir.dart';
import '../Katalog/KatalogLokal.dart';
import '../Sesi/StafLokal.dart';
import 'Keranjang.dart';
import 'KonteksPenjualan.dart';
import 'LayananPenjualan.dart';

/// Reservasi layanan di aplikasi kasir (F-07 mode service bagian 2, POS-04/SLS-07):
/// - **Ambil** (online): antrian reservasi outlet ini pada satu tanggal; offline → `PerluOnline`.
/// - **Hadir** (online): pelanggan datang (check-in), idempoten.
/// - **Muat ke keranjang**: layanan reservasi dengan staf yang dipilih (komisi F-18) dan pelanggannya; `Penjualan.Buat`
///   membawa `UuidReservasi` sehingga server menyelesaikan & menautkan reservasi di transaksi yang sama. Pembayaran
///   sendiri tetap offline-first seperti penjualan biasa.
class LayananReservasi {
  LayananReservasi({required this.klien, required this.penjualan});

  final KlienPos klien;
  final LayananPenjualan penjualan;

  Future<List<ReservasiPos>> Ambil({String? tanggal}) =>
      _Online(() => klien.AmbilReservasi(tanggal: tanggal), 'Melihat reservasi');

  Future<ReservasiPos> Hadir(ReservasiPos reservasi, {required StafLokal kasir}) {
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat) && !kasir.PunyaIzin(IzinKasir.reservasiKelola)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak boleh menerima pelanggan reservasi.');
    }
    return _Online(() => klien.HadirReservasi(reservasi.uuid, uuidPengguna: kasir.uuid), 'Mencatat kedatangan');
  }

  /// Keranjang berisi layanan reservasi (harga katalog saat ini) dengan staf pelaksana & pelanggannya.
  Keranjang MuatKeKeranjang(ReservasiPos reservasi, KatalogLokal katalog, KonteksPenjualan k) {
    if (!reservasi.BisaDilayani) {
      throw GalatKasir(
        'ReservasiSelesai',
        'Reservasi ${reservasi.nomor} sudah ${reservasi.labelStatus.toLowerCase()}.',
      );
    }
    final uuidProduk = reservasi.uuidProduk;
    final produk = uuidProduk == null ? null : katalog.CariProduk(uuidProduk);
    if (produk == null) {
      throw GalatKasir(
        'ProdukTidakDikenal',
        '"${reservasi.namaLayanan}" belum ada di katalog perangkat ini. Perbarui katalog, lalu coba lagi.',
      );
    }
    final data = reservasi.pelanggan;
    final pelanggan = data == null
        ? null
        : PelangganTerpilih(
            uuid: '${data['Uuid']}',
            nama: '${data['Nama']}',
            noHpSamar: '${data['NoHp'] ?? ''}',
            kodeTier: data['KodeTier'] as String?,
            namaTier: data['NamaTier'] as String?,
          );
    final item = penjualan.BuatBaris(katalog, k, produk, tierPelanggan: pelanggan?.kodeTier);
    final uuidStaf = reservasi.uuidStaf;
    return Keranjang(
      baris: [
        if (uuidStaf == null) item else item.Salin(staf: [uuidStaf]),
      ],
      pelanggan: pelanggan,
      reservasi: ReservasiKeranjang(uuid: reservasi.uuid, nomor: reservasi.nomor),
    );
  }

  Future<T> _Online<T>(Future<T> Function() kerja, String tindakan) async {
    try {
      return await kerja();
    } on GalatJaringan {
      throw GalatKasir('PerluOnline', '$tindakan perlu koneksi internet. Coba lagi saat perangkat online.');
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }
}
