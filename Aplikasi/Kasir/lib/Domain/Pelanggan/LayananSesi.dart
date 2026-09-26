import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/RepositoriKasir.dart';
import '../GalatKasir.dart';
import '../Katalog/KatalogLokal.dart';
import '../Sesi/StafLokal.dart';

/// Layanan yang boleh ditukar dengan satu paket sesi pelanggan.
typedef LayananTukarSesi = ({String uuid, String nama});

/// Paket sesi pelanggan di aplikasi kasir (F-16d bagian 2, CRM-04):
/// - **Saldo** (online): paket aktif pelanggan, sisa sesi, dan layanan yang boleh ditukar. Tidak di-cache karena sesi
///   bisa dipakai di perangkat/outlet lain.
/// - **Pakai** (offline setelah saldo dibaca): outbox `Sesi.Pakai` `{UuidSaldoSesi, UuidProduk, Jumlah, UuidPengguna,
///   DibuatPada}`. Server memotong sisa sesi & mengakui pendapatan (J-16.3); sesi yang sudah habis dipakai perangkat lain
///   tetap diterima + ditinjau.
class LayananSesi {
  LayananSesi({required this.klien, required this.repositoriKasir, PembuatUlid? ulid, DateTime Function()? jam})
    : _ulid = ulid ?? PembuatUlid(),
      _jam = jam ?? DateTime.now;

  static const String jenisOutbox = 'Sesi.Pakai';
  static const int jumlahMaksimal = 100;

  final KlienPos klien;
  final RepositoriKasir repositoriKasir;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;

  /// Paket sesi aktif pelanggan (wajib online).
  Future<SaldoSesiPos> AmbilSaldo(String uuidPelanggan) async {
    try {
      return await klien.AmbilSaldoSesi(uuidPelanggan);
    } on GalatJaringan {
      throw const GalatKasir(
        'PerluOnline',
        'Paket sesi perlu dicek online. Coba lagi saat perangkat terhubung internet.',
      );
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }

  /// Layanan yang boleh ditukar dengan [paket]: daftar dari server, atau semua produk Jasa yang tampil di katalog.
  static List<LayananTukarSesi> AmbilLayanan(PaketSesiPelangganPos paket, KatalogLokal katalog) {
    if (!paket.semuaProdukJasa) {
      return [for (final p in paket.produkBerlaku) (uuid: p.uuid, nama: p.nama)];
    }
    return [
      for (final p in katalog.produk)
        if (p.tampil && p.jenis == JenisProdukKasir.jasa && !p.paketSesi) (uuid: p.uuid, nama: p.nama),
    ];
  }

  /// Catat pemakaian [jumlah] sesi [paket] untuk layanan [uuidProduk] oleh [kasir]. Hasil: Uuid dokumen pemakaian.
  Future<String> Pakai({
    required PaketSesiPelangganPos paket,
    required String uuidProduk,
    required int jumlah,
    required StafLokal kasir,
    required KatalogLokal katalog,
  }) async {
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin mencatat layanan pelanggan.');
    }
    if (jumlah < 1 || jumlah > jumlahMaksimal) {
      throw const GalatKasir('JumlahTidakValid', 'Jumlah sesi antara 1 sampai 100.');
    }
    if (jumlah > paket.sisaSesi) {
      throw GalatKasir('SesiKurang', 'Sisa paket ${paket.namaPaket} tinggal ${paket.sisaSesi} sesi.');
    }
    if (!AmbilLayanan(paket, katalog).any((l) => l.uuid == uuidProduk)) {
      throw GalatKasir('ProdukDiluarPaket', 'Layanan ini tidak termasuk paket ${paket.namaPaket}.');
    }
    final sekarang = _jam().toUtc();
    final uuid = _ulid.Buat();
    await repositoriKasir.TambahOutbox(
      ItemOutbox(
        jenis: jenisOutbox,
        uuid: uuid,
        data: {
          'UuidSaldoSesi': paket.uuid,
          'UuidProduk': uuidProduk,
          'Jumlah': jumlah,
          'UuidPengguna': kasir.uuid,
          'DibuatPada': sekarang.toIso8601String(),
        },
      ),
      sekarang,
    );
    return uuid;
  }
}
