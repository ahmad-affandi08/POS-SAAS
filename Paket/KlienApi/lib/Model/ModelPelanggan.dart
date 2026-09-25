import 'UraiJson.dart';

/// Hasil cari pelanggan dari POS (F-16a, `GET /api/pos/v1/pelanggan?kata=`). Nomor HP tersamar (`0812****7890`).
class PelangganPos {
  const PelangganPos({required this.uuid, required this.nama, required this.noHpSamar});

  final String uuid;
  final String nama;
  final String noHpSamar;

  static PelangganPos DariJson(Map<String, Object?> json) => PelangganPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nama: UraiJson.AmbilTeks(json['Nama']),
    noHpSamar: UraiJson.AmbilTeks(json['NoHp']),
  );
}
