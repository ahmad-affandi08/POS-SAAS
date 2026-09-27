import 'UraiJson.dart';

/// Penyetuju persetujuan jarak jauh yang sudah menyetujui (X4): cukup untuk dicatat sebagai `UuidPenyetuju` dokumen.
class PenyetujuJarakJauh {
  const PenyetujuJarakJauh({required this.uuid, required this.nama, required this.pemilik, required this.izin});

  final String uuid;
  final String nama;
  final bool pemilik;
  final List<String> izin;

  static PenyetujuJarakJauh DariJson(Map<String, Object?> json) => PenyetujuJarakJauh(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nama: UraiJson.AmbilTeks(json['Nama']),
    pemilik: UraiJson.AmbilBenar(json['Pemilik']),
    izin: UraiJson.AmbilDaftarTeks(json['Izin']),
  );
}

/// Permintaan persetujuan jarak jauh (X4, `/api/pos/v1/persetujuan/jarak-jauh` & `/api/pemilik/v1/persetujuan`).
/// [status]: `Menunggu`, `Disetujui`, `Ditolak`, `Dibatalkan`, `Kedaluwarsa`. Nilai uang string desimal atau null.
class PermintaanPersetujuanPos {
  const PermintaanPersetujuanPos({
    required this.uuid,
    required this.status,
    required this.labelStatus,
    required this.judul,
    required this.rincian,
    required this.nilai,
    required this.namaOutlet,
    required this.namaPerangkat,
    required this.namaPemohon,
    required this.dibuatPada,
    required this.kedaluwarsaPada,
    required this.alasanTolak,
    required this.penyetuju,
    required this.namaPemutus,
  });

  static const String menunggu = 'Menunggu';
  static const String disetujui = 'Disetujui';
  static const String ditolak = 'Ditolak';

  final String uuid;
  final String status;
  final String labelStatus;
  final String judul;
  final List<({String label, String nilai})> rincian;
  final String? nilai;
  final String namaOutlet;
  final String namaPerangkat;
  final String namaPemohon;
  final DateTime? dibuatPada;
  final DateTime? kedaluwarsaPada;
  final String? alasanTolak;

  /// Terisi hanya untuk perangkat pemohon setelah disetujui.
  final PenyetujuJarakJauh? penyetuju;
  final String? namaPemutus;

  bool get selesai => status != menunggu;

  static DateTime? _Waktu(Object? nilai) => DateTime.tryParse(UraiJson.AmbilTeks(nilai))?.toUtc();

  static PermintaanPersetujuanPos DariJson(Map<String, Object?> json) {
    final penyetuju = UraiJson.AmbilPetaAtauNull(json['Penyetuju']);
    return PermintaanPersetujuanPos(
      uuid: UraiJson.AmbilTeks(json['Uuid']),
      status: UraiJson.AmbilTeks(json['Status'], menunggu),
      labelStatus: UraiJson.AmbilTeks(json['LabelStatus']),
      judul: UraiJson.AmbilTeks(json['Judul']),
      rincian: [
        for (final r in UraiJson.AmbilDaftarPeta(json['Rincian']))
          (label: UraiJson.AmbilTeks(r['Label']), nilai: UraiJson.AmbilTeks(r['Nilai'])),
      ],
      nilai: UraiJson.AmbilDesimalAtauNull(json['Nilai']),
      namaOutlet: UraiJson.AmbilTeks(json['NamaOutlet']),
      namaPerangkat: UraiJson.AmbilTeks(json['NamaPerangkat']),
      namaPemohon: UraiJson.AmbilTeks(json['NamaPemohon']),
      dibuatPada: _Waktu(json['DibuatPada']),
      kedaluwarsaPada: _Waktu(json['KedaluwarsaPada']),
      alasanTolak: UraiJson.AmbilTeksAtauNull(json['AlasanTolak']),
      penyetuju: penyetuju == null ? null : PenyetujuJarakJauh.DariJson(penyetuju),
      namaPemutus: UraiJson.AmbilTeksAtauNull(json['NamaPemutus']),
    );
  }
}
