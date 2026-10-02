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

/// K-20: layanan yang bisa dipesan (produk Jasa ber-durasi).
class LayananReservasiPos {
  const LayananReservasiPos({required this.uuid, required this.nama, required this.durasiMenit, required this.harga});

  final String uuid;
  final String nama;
  final int durasiMenit;
  final String? harga;

  static LayananReservasiPos DariJson(Map<String, Object?> json) => LayananReservasiPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nama: UraiJson.AmbilTeks(json['Nama']),
    durasiMenit: UraiJson.AmbilBulat(json['DurasiMenit']),
    harga: UraiJson.AmbilDesimalAtauNull(json['Harga']),
  );
}

/// K-20: staf yang bekerja pada tanggal kalender beserta jam kerjanya (`HH:MM`, zona waktu outlet).
class StafReservasiPos {
  const StafReservasiPos({required this.uuid, required this.nama, required this.jamMulai, required this.jamSelesai});

  final String uuid;
  final String nama;
  final String jamMulai;
  final String jamSelesai;

  static StafReservasiPos DariJson(Map<String, Object?> json) => StafReservasiPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nama: UraiJson.AmbilTeks(json['Nama']),
    jamMulai: UraiJson.AmbilTeks(json['JamMulai']),
    jamSelesai: UraiJson.AmbilTeks(json['JamSelesai']),
  );
}

/// K-20 (`GET /api/pos/v1/reservasi/kalender?tanggal=`): layanan, staf berjadwal, dan reservasi satu tanggal.
class KalenderReservasiPos {
  const KalenderReservasiPos({
    required this.tanggal,
    required this.layanan,
    required this.staf,
    required this.reservasi,
  });

  final String tanggal;
  final List<LayananReservasiPos> layanan;
  final List<StafReservasiPos> staf;
  final List<ReservasiPos> reservasi;

  static KalenderReservasiPos DariJson(Map<String, Object?> json) => KalenderReservasiPos(
    tanggal: UraiJson.AmbilTeks(json['Tanggal']),
    layanan: [for (final l in UraiJson.AmbilDaftarPeta(json['Layanan'])) LayananReservasiPos.DariJson(l)],
    staf: [for (final s in UraiJson.AmbilDaftarPeta(json['Staf'])) StafReservasiPos.DariJson(s)],
    reservasi: [for (final r in UraiJson.AmbilDaftarPeta(json['Reservasi'])) ReservasiPos.DariJson(r)],
  );
}

/// K-20 (`GET /api/pos/v1/reservasi/slot`): jam mulai kosong (`HH:MM`) dan staf yang bisa melayaninya.
class SlotReservasiPos {
  const SlotReservasiPos({required this.jam, required this.staf});

  final String jam;
  final List<({String uuid, String nama})> staf;

  static SlotReservasiPos DariJson(Map<String, Object?> json) => SlotReservasiPos(
    jam: UraiJson.AmbilTeks(json['Jam']),
    staf: [
      for (final s in UraiJson.AmbilDaftarPeta(json['Staf']))
        (uuid: UraiJson.AmbilTeks(s['Uuid']), nama: UraiJson.AmbilTeks(s['Nama'])),
    ],
  );
}
