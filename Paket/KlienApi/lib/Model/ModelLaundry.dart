import 'UraiJson.dart';

/// Tiket laundry untuk aplikasi kasir (§9.9, `GET /api/pos/v1/laundry?kata=`); `uuid` = Uuid penjualan. Berat string
/// desimal (kg), waktu UTC.
class TiketLaundryPos {
  const TiketLaundryPos({
    required this.uuid,
    required this.nomor,
    required this.dibuatPada,
    required this.estimasiSelesaiPada,
    required this.siapPada,
    required this.namaPelanggan,
    required this.noHp,
    required this.jenisLayanan,
    required this.berat,
    required this.item,
    required this.parfum,
    required this.catatan,
    required this.status,
    required this.labelStatus,
    required this.lewatEstimasi,
    required this.notifikasiTerkirim,
    required this.statusBerikutnya,
  });

  final String uuid;
  final String nomor;
  final DateTime? dibuatPada;
  final DateTime estimasiSelesaiPada;
  final DateTime? siapPada;
  final String namaPelanggan;

  /// Terformat (`0812-3456-7890`) atau null.
  final String? noHp;

  /// `Reguler` / `Express`.
  final String jenisLayanan;
  final String? berat;

  /// `{Nama, Jumlah}`.
  final List<({String nama, int jumlah})> item;
  final String? parfum;
  final String? catatan;

  /// `Diterima` / `Dicuci` / `Dikeringkan` / `Disetrika` / `Siap` / `Diambil` / `Dibatalkan`.
  final String status;
  final String labelStatus;
  final bool lewatEstimasi;
  final bool notifikasiTerkirim;
  final List<String> statusBerikutnya;

  static DateTime? _Waktu(Object? nilai) => DateTime.tryParse(UraiJson.AmbilTeks(nilai))?.toUtc();

  static TiketLaundryPos DariJson(Map<String, Object?> json) => TiketLaundryPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nomor: UraiJson.AmbilTeks(json['Nomor']),
    dibuatPada: _Waktu(json['DibuatPada']),
    estimasiSelesaiPada: _Waktu(json['EstimasiSelesaiPada']) ?? DateTime.utc(1970),
    siapPada: _Waktu(json['SiapPada']),
    namaPelanggan: UraiJson.AmbilTeks(json['NamaPelanggan']),
    noHp: UraiJson.AmbilTeksAtauNull(json['NoHp']),
    jenisLayanan: UraiJson.AmbilTeks(json['JenisLayanan'], 'Reguler'),
    berat: UraiJson.AmbilDesimalAtauNull(json['Berat']),
    item: [
      for (final i in UraiJson.AmbilDaftarPeta(json['Item']))
        (nama: UraiJson.AmbilTeks(i['Nama']), jumlah: UraiJson.AmbilBulat(i['Jumlah'], 1)),
    ],
    parfum: UraiJson.AmbilTeksAtauNull(json['Parfum']),
    catatan: UraiJson.AmbilTeksAtauNull(json['Catatan']),
    status: UraiJson.AmbilTeks(json['Status']),
    labelStatus: UraiJson.AmbilTeks(json['LabelStatus']),
    lewatEstimasi: UraiJson.AmbilBenar(json['LewatEstimasi']),
    notifikasiTerkirim: UraiJson.AmbilBenar(json['NotifikasiTerkirim']),
    statusBerikutnya: UraiJson.AmbilDaftarTeks(json['StatusBerikutnya']),
  );
}
