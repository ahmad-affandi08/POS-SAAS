import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriKasir.dart';
import '../GalatKasir.dart';
import '../Sesi/StafLokal.dart';
import '../Struk/LayananStruk.dart';

/// Buka laci kas manual tanpa transaksi (cetak struk bagian 4, POS-17, §19.2 "selalu dicatat, opsional PIN").
///
/// Laci dibuka lewat printer lebih dulu; bila printer gagal, tidak ada yang dicatat karena laci tidak terbuka. Setelah
/// laci terbuka, log dicatat sebagai entri outbox `Laci.Buka` (berlaku offline). PIN supervisor ber-izin
/// `kas.keluar.setujui` wajib bila pengaturan tenant `BukaLaciPerluPin` aktif; PIN diperiksa `LayananMasuk`.
class LayananBukaLaci {
  LayananBukaLaci({required this.repositori, required this.struk, PembuatUlid? ulid, DateTime Function()? jam})
    : _ulid = ulid ?? PembuatUlid(),
      _jam = jam ?? DateTime.now;

  /// Panjang minimal alasan (sama dengan server).
  static const int panjangAlasanMinimal = 3;

  final RepositoriKasir repositori;
  final LayananStruk struk;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;

  Future<bool> CekPerluPin() async => await repositori.AmbilPengaturan(KunciPengaturan.bukaLaciPerluPin) == '1';

  /// Buka laci lalu catat log-nya. Kembalikan Uuid log.
  Future<String> BukaLaci({
    required BarisShift shift,
    required StafLokal pembuka,
    required String alasan,
    StafLokal? penyetuju,
  }) async {
    final alasanRapi = alasan.trim();
    if (alasanRapi.length < panjangAlasanMinimal) {
      throw const GalatKasir('AlasanWajib', 'Isi alasan membuka laci minimal 3 karakter.');
    }

    if (!pembuka.PunyaIzin(IzinKasir.penjualanBuat)) {
      throw GalatKasir('TanpaIzin', '${pembuka.nama} tidak punya izin memakai laci kas.');
    }

    if (penyetuju == null && await CekPerluPin()) {
      throw const GalatKasir(
        'PersetujuanDiperlukan',
        'Buka laci tanpa transaksi wajib disetujui supervisor dengan PIN.',
      );
    }

    if (penyetuju != null && !penyetuju.PunyaIzin(IzinKasir.kasKeluarSetujui)) {
      throw GalatKasir('PenyetujuTidakBerwenang', '${penyetuju.nama} tidak punya izin menyetujui buka laci.');
    }

    await struk.BukaLaci();

    final sekarang = _jam().toUtc();
    final uuid = _ulid.Buat();
    await repositori.SimpanBukaLaci(
      ItemOutbox(
        jenis: 'Laci.Buka',
        uuid: uuid,
        data: {
          'UuidShift': shift.Uuid,
          'Alasan': alasanRapi.length > 255 ? alasanRapi.substring(0, 255) : alasanRapi,
          'UuidPembuka': pembuka.uuid,
          'DibukaPada': sekarang.toIso8601String(),
          'UuidPenyetuju': penyetuju?.uuid,
        },
      ),
      sekarang,
    );
    return uuid;
  }
}
