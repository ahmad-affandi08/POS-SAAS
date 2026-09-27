import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../GalatKasir.dart';
import '../Sesi/StafLokal.dart';
import 'Keranjang.dart';

/// Laundry di aplikasi kasir (§9.9, SLS-09):
/// - **Tiket** (offline): isian tiket di keranjang divalidasi lalu dikirim sebagai blok `Laundry` di `Penjualan.Buat`;
///   perkiraan selesai = sekarang + durasi reguler/express dari data awal.
/// - **Cari & ubah status** (online): cucian aktif outlet (siap diambil bila tanpa kata), maju ke tahap berikutnya atau
///   tandai diambil; offline → `PerluOnline`.
class LayananLaundry {
  LayananLaundry({required this.klien, DateTime Function()? jam}) : _jam = jam ?? DateTime.now;

  static const int panjangNamaItemMaksimal = 60;
  static final Decimal _beratMaksimal = Decimal.parse('9999.99');

  final KlienPos klien;
  final DateTime Function() _jam;

  /// Perkiraan selesai (UTC) untuk [jenisLayanan] menurut pengaturan.
  DateTime HitungEstimasi(String jenisLayanan, LaundryPos pengaturan) => _jam().toUtc().add(
    Duration(hours: jenisLayanan == LaundryKeranjang.express ? pengaturan.jamExpress : pengaturan.jamReguler),
  );

  /// Berat masukan kasir ("3,5" / "3.50") → Decimal 2 desimal; kosong = null. Tidak valid → GalatKasir.
  static Decimal? UraiBerat(String masukan) {
    final rapi = masukan.trim().replaceAll(',', '.');
    if (rapi.isEmpty) {
      return null;
    }
    final nilai = RegExp(r'^\d{1,4}(\.\d{1,2})?$').hasMatch(rapi) ? Decimal.tryParse(rapi) : null;
    if (nilai == null || nilai <= Decimal.zero || nilai > _beratMaksimal) {
      throw const GalatKasir(
        'BeratTidakValid',
        'Isi berat dalam kg, misalnya 3,5 (maksimal 2 angka di belakang koma).',
      );
    }
    return nilai;
  }

  /// Tiket siap disimpan ke keranjang: wajib berat atau item, dan nama penerima bila tanpa pelanggan tertaut.
  LaundryKeranjang BuatTiket({
    required String jenisLayanan,
    required LaundryPos pengaturan,
    required PelangganTerpilih? pelanggan,
    Decimal? berat,
    List<({String nama, int jumlah})> item = const [],
    String? parfum,
    String? catatan,
    String? namaPelanggan,
    String? noHp,
  }) {
    final itemRapi = [
      for (final i in item)
        if (i.nama.trim().isNotEmpty && i.jumlah > 0)
          (
            nama: i.nama.trim().length > panjangNamaItemMaksimal
                ? i.nama.trim().substring(0, panjangNamaItemMaksimal)
                : i.nama.trim(),
            jumlah: i.jumlah,
          ),
    ];
    if (berat == null && itemRapi.isEmpty) {
      throw const GalatKasir('IsiLaundryKosong', 'Isi berat cucian atau tambahkan item satuan (jas, bed cover, ...).');
    }
    final nama = namaPelanggan?.trim() ?? '';
    if (pelanggan == null && nama.isEmpty) {
      throw const GalatKasir('NamaWajib', 'Tulis nama pemilik cucian, atau pilih pelanggan.');
    }
    String? Rapikan(String? teks) => teks == null || teks.trim().isEmpty ? null : teks.trim();
    return LaundryKeranjang(
      jenisLayanan: jenisLayanan == LaundryKeranjang.express ? LaundryKeranjang.express : LaundryKeranjang.reguler,
      estimasiSelesaiPada: HitungEstimasi(jenisLayanan, pengaturan),
      berat: berat,
      item: itemRapi,
      parfum: Rapikan(parfum),
      catatan: Rapikan(catatan),
      namaPelanggan: pelanggan == null ? nama : null,
      noHp: pelanggan == null ? Rapikan(noHp) : null,
    );
  }

  Future<List<TiketLaundryPos>> Cari({String kata = ''}) => _Online(() => klien.CariLaundry(kata: kata));

  Future<TiketLaundryPos> UbahStatus(TiketLaundryPos tiket, String status, {required StafLokal kasir}) {
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat) && !kasir.PunyaIzin(IzinKasir.laundryKelola)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak boleh mengubah status cucian.');
    }
    return _Online(() => klien.UbahStatusLaundry(tiket.uuid, status: status, uuidPengguna: kasir.uuid));
  }

  Future<T> _Online<T>(Future<T> Function() kerja) async {
    try {
      return await kerja();
    } on GalatJaringan {
      throw const GalatKasir('PerluOnline', 'Daftar cucian perlu koneksi internet. Coba lagi saat perangkat online.');
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }
}
