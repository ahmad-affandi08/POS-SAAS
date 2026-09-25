import 'UraiJson.dart';

/// Hasil cari pelanggan dari POS (F-16a, `GET /api/pos/v1/pelanggan?kata=`). Nomor HP tersamar (`0812****7890`).
/// F-16b: kode & nama tier (harga per tier) dan saldo poin; server lama tanpa kolom ini = tanpa tier & 0 poin.
class PelangganPos {
  const PelangganPos({
    required this.uuid,
    required this.nama,
    required this.noHpSamar,
    this.kodeTier,
    this.namaTier,
    this.saldoPoin = 0,
  });

  final String uuid;
  final String nama;
  final String noHpSamar;
  final String? kodeTier;
  final String? namaTier;
  final int saldoPoin;

  static PelangganPos DariJson(Map<String, Object?> json) => PelangganPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nama: UraiJson.AmbilTeks(json['Nama']),
    noHpSamar: UraiJson.AmbilTeks(json['NoHp']),
    kodeTier: UraiJson.AmbilTeksAtauNull(json['KodeTier']),
    namaTier: UraiJson.AmbilTeksAtauNull(json['NamaTier']),
    saldoPoin: UraiJson.AmbilBulat(json['SaldoPoin']),
  );
}
