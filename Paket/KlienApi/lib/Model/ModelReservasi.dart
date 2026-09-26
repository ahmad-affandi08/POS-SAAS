import 'UraiJson.dart';

/// Reservasi layanan untuk antrian kasir (F-07 mode service bagian 2, `GET /api/pos/v1/reservasi?tanggal=`).
class ReservasiPos {
  const ReservasiPos({
    required this.uuid,
    required this.nomor,
    required this.mulaiPada,
    required this.selesaiPada,
    required this.namaPelanggan,
    required this.noHp,
    required this.pelanggan,
    required this.uuidProduk,
    required this.namaLayanan,
    required this.uuidStaf,
    required this.namaStaf,
    required this.status,
    required this.labelStatus,
    required this.catatan,
  });

  final String uuid;
  final String nomor;

  /// UTC.
  final DateTime mulaiPada;
  final DateTime selesaiPada;
  final String namaPelanggan;
  final String noHp;

  /// `{Uuid, Nama, NoHp (tersamar), KodeTier, NamaTier}` bila reservasi tertaut ke data pelanggan.
  final Map<String, Object?>? pelanggan;

  /// Null = layanan sudah dihapus dari katalog.
  final String? uuidProduk;
  final String namaLayanan;
  final String? uuidStaf;
  final String? namaStaf;

  /// `Menunggu` / `Dikonfirmasi` / `Hadir` / `Selesai` / `Batal` / `TidakDatang`.
  final String status;
  final String labelStatus;
  final String? catatan;

  bool get BisaDilayani => status == 'Menunggu' || status == 'Dikonfirmasi' || status == 'Hadir';

  static DateTime _AmbilWaktu(Object? nilai) =>
      DateTime.tryParse(UraiJson.AmbilTeks(nilai))?.toUtc() ?? DateTime.utc(1970);

  static ReservasiPos DariJson(Map<String, Object?> json) => ReservasiPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nomor: UraiJson.AmbilTeks(json['Nomor']),
    mulaiPada: _AmbilWaktu(json['MulaiPada']),
    selesaiPada: _AmbilWaktu(json['SelesaiPada']),
    namaPelanggan: UraiJson.AmbilTeks(json['NamaPelanggan']),
    noHp: UraiJson.AmbilTeks(json['NoHp']),
    pelanggan: UraiJson.AmbilPetaAtauNull(json['Pelanggan']),
    uuidProduk: UraiJson.AmbilTeksAtauNull(json['UuidProduk']),
    namaLayanan: UraiJson.AmbilTeks(json['NamaLayanan']),
    uuidStaf: UraiJson.AmbilTeksAtauNull(json['UuidStaf']),
    namaStaf: UraiJson.AmbilTeksAtauNull(json['NamaStaf']),
    status: UraiJson.AmbilTeks(json['Status']),
    labelStatus: UraiJson.AmbilTeks(json['LabelStatus']),
    catatan: UraiJson.AmbilTeksAtauNull(json['Catatan']),
  );
}
