import 'UraiJson.dart';

/// Hasil cari pelanggan dari POS (F-16a, `GET /api/pos/v1/pelanggan?kata=`). Nomor HP tersamar (`0812****7890`).
/// F-16b: kode & nama tier (harga per tier) dan saldo poin; server lama tanpa kolom ini = tanpa tier & 0 poin.
/// F-12: posisi kredit untuk cek BR-12.1 saat offline ([limitKredit] null = tidak boleh tempo tanpa penyetuju).
class PelangganPos {
  const PelangganPos({
    required this.uuid,
    required this.nama,
    required this.noHpSamar,
    this.kodeTier,
    this.namaTier,
    this.saldoPoin = 0,
    this.limitKredit,
    this.sisaPiutang = '0',
    this.hariLewatJatuhTempo = 0,
  });

  final String uuid;
  final String nama;
  final String noHpSamar;
  final String? kodeTier;
  final String? namaTier;
  final int saldoPoin;
  final String? limitKredit;
  final String sisaPiutang;
  final int hariLewatJatuhTempo;

  static PelangganPos DariJson(Map<String, Object?> json) => PelangganPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nama: UraiJson.AmbilTeks(json['Nama']),
    noHpSamar: UraiJson.AmbilTeks(json['NoHp']),
    kodeTier: UraiJson.AmbilTeksAtauNull(json['KodeTier']),
    namaTier: UraiJson.AmbilTeksAtauNull(json['NamaTier']),
    saldoPoin: UraiJson.AmbilBulat(json['SaldoPoin']),
    limitKredit: UraiJson.AmbilDesimalAtauNull(json['LimitKredit']),
    sisaPiutang: UraiJson.AmbilDesimal(json['SisaPiutang']),
    hariLewatJatuhTempo: UraiJson.AmbilBulat(json['HariLewatJatuhTempo']),
  );
}

/// Saldo poin terkini & aturan tukar (F-16b, `GET /api/pos/v1/pelanggan/{uuid}/poin`); penukaran poin wajib online
/// (§18.4). [nilaiTukarPoin] string desimal Rupiah per poin.
class SaldoPoinPos {
  const SaldoPoinPos({
    required this.uuid,
    required this.saldoPoin,
    required this.berlaku,
    required this.nilaiTukarPoin,
    required this.minimalTukarPoin,
  });

  final String uuid;
  final int saldoPoin;

  /// Loyalti diaktifkan tenant dan termasuk paketnya.
  final bool berlaku;
  final String nilaiTukarPoin;
  final int minimalTukarPoin;

  static SaldoPoinPos DariJson(Map<String, Object?> json) {
    final pelanggan = UraiJson.AmbilPeta(json['Pelanggan']);
    final tukar = UraiJson.AmbilPeta(json['TukarPoin']);
    return SaldoPoinPos(
      uuid: UraiJson.AmbilTeks(pelanggan['Uuid']),
      saldoPoin: UraiJson.AmbilBulat(pelanggan['SaldoPoin']),
      berlaku: UraiJson.AmbilBenar(tukar['Berlaku']),
      nilaiTukarPoin: UraiJson.AmbilDesimal(tukar['NilaiTukarPoin']),
      minimalTukarPoin: UraiJson.AmbilBulat(tukar['MinimalTukarPoin'], 1),
    );
  }
}
