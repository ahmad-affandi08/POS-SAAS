import 'package:inti/Inti.dart';

import 'UraiJson.dart';

/// Modul Salesman (§9.7, SLS-11; `GET /api/pos/v1/salesman/*`): data cache offline HP salesman. Uang string desimal di
/// JSON diurai menjadi [Uang] (tidak pernah lewat pecahan biner); nilai rusak dibaca sebagai nol supaya satu baris aneh
/// tidak menggagalkan unduhan seluruh daftar pelanggan.
abstract final class UraiUangSalesman {
  static Uang Ambil(Object? nilai) {
    final teks = UraiJson.AmbilDesimal(nilai);
    return RegExp(r'^-?\d+(\.\d+)?$').hasMatch(teks) ? Uang.Dari(teks) : Uang.Nol();
  }

  static Uang? AmbilAtauNull(Object? nilai) => nilai == null ? null : Ambil(nilai);

  static Kuantitas AmbilJumlah(Object? nilai) {
    final teks = UraiJson.AmbilDesimal(nilai);
    return RegExp(r'^-?\d+(\.\d+)?$').hasMatch(teks) ? Kuantitas.Dari(teks) : Kuantitas.Nol();
  }

  static DateTime? AmbilWaktu(Object? nilai) => nilai is String ? DateTime.tryParse(nilai)?.toUtc() : null;
}

/// Satu pelanggan aktif untuk salesman beserta posisi kreditnya. [noHp] **penuh** (K30, v3.86): salesman perlu
/// menghubungi toko; disimpan di basis data lokal terenkripsi dan tidak pernah dicatat ke log.
class PelangganSalesmanPos {
  const PelangganSalesmanPos({
    required this.uuid,
    required this.nama,
    required this.noHp,
    required this.alamat,
    required this.kodeTier,
    required this.namaTier,
    required this.limitKredit,
    required this.terminHari,
    required this.sisaPiutang,
    required this.jumlahPiutangJatuhTempo,
    required this.hariLewatJatuhTempo,
    required this.terakhirDikunjungiPada,
  });

  final String uuid;
  final String nama;
  final String? noHp;
  final String? alamat;
  final String? kodeTier;
  final String? namaTier;

  /// Null = tanpa limit kredit.
  final Uang? limitKredit;
  final int terminHari;
  final Uang sisaPiutang;

  /// Σ sisa piutang yang jatuh temponya sudah lewat.
  final Uang jumlahPiutangJatuhTempo;
  final int hariLewatJatuhTempo;
  final DateTime? terakhirDikunjungiPada;

  static PelangganSalesmanPos DariJson(Map<String, Object?> json) => PelangganSalesmanPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nama: UraiJson.AmbilTeks(json['Nama']),
    noHp: UraiJson.AmbilTeksAtauNull(json['NoHp']),
    alamat: UraiJson.AmbilTeksAtauNull(json['Alamat']),
    kodeTier: UraiJson.AmbilTeksAtauNull(json['KodeTier']),
    namaTier: UraiJson.AmbilTeksAtauNull(json['NamaTier']),
    limitKredit: UraiUangSalesman.AmbilAtauNull(json['LimitKredit']),
    terminHari: UraiJson.AmbilBulat(json['TerminHari']),
    sisaPiutang: UraiUangSalesman.Ambil(json['SisaPiutang']),
    jumlahPiutangJatuhTempo: UraiUangSalesman.Ambil(json['JumlahPiutangJatuhTempo']),
    hariLewatJatuhTempo: UraiJson.AmbilBulat(json['HariLewatJatuhTempo']),
    terakhirDikunjungiPada: UraiUangSalesman.AmbilWaktu(json['TerakhirDikunjungiPada']),
  );
}

/// Satu halaman `GET salesman/pelanggan` (50 per halaman).
class HalamanPelangganSalesman {
  const HalamanPelangganSalesman({required this.pelanggan, required this.halaman, required this.adaBerikutnya});

  final List<PelangganSalesmanPos> pelanggan;
  final int halaman;
  final bool adaBerikutnya;

  static HalamanPelangganSalesman DariJson(Map<String, Object?> json) => HalamanPelangganSalesman(
    pelanggan: [for (final p in UraiJson.AmbilDaftarPeta(json['Pelanggan'])) PelangganSalesmanPos.DariJson(p)],
    halaman: UraiJson.AmbilBulat(json['Halaman'], 1),
    adaBerikutnya: UraiJson.AmbilBenar(json['AdaBerikutnya']),
  );
}

/// Satu piutang terbuka pelanggan (kasir tempo & faktur grosir), jatuh tempo terdekat dulu. [umurHari] = hari sejak
/// jatuh tempo (negatif = belum jatuh tempo). Tanggal `YYYY-MM-DD`.
class PiutangSalesmanPos {
  const PiutangSalesmanPos({
    required this.uuid,
    required this.nomor,
    required this.tanggal,
    required this.jatuhTempo,
    required this.jumlah,
    required this.sisa,
    required this.umurHari,
    required this.status,
  });

  final String uuid;
  final String nomor;
  final String tanggal;
  final String jatuhTempo;
  final Uang jumlah;
  final Uang sisa;
  final int umurHari;
  final String status;

  bool get lewatJatuhTempo => umurHari > 0;

  static PiutangSalesmanPos DariJson(Map<String, Object?> json) => PiutangSalesmanPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nomor: UraiJson.AmbilTeks(json['Nomor']),
    tanggal: UraiJson.AmbilTeks(json['Tanggal']),
    jatuhTempo: UraiJson.AmbilTeks(json['JatuhTempo']),
    jumlah: UraiUangSalesman.Ambil(json['Jumlah']),
    sisa: UraiUangSalesman.Ambil(json['Sisa']),
    umurHari: UraiJson.AmbilBulat(json['UmurHari']),
    status: UraiJson.AmbilTeks(json['Status']),
  );
}

/// Snapshot stok tersedia per produk di lokasi Toko outlet perangkat (`GET salesman/stok`), hanya petunjuk.
class StokSalesmanPos {
  const StokSalesmanPos({required this.stok, required this.diambilPada});

  /// Uuid produk → jumlah tersedia (satuan dasar).
  final Map<String, Kuantitas> stok;
  final DateTime? diambilPada;

  static StokSalesmanPos DariJson(Map<String, Object?> json) => StokSalesmanPos(
    stok: {
      for (final s in UraiJson.AmbilDaftarPeta(json['Stok']))
        if (UraiJson.AmbilTeks(s['UuidProduk']).isNotEmpty)
          UraiJson.AmbilTeks(s['UuidProduk']): UraiUangSalesman.AmbilJumlah(s['JumlahTersedia']),
    },
    diambilPada: UraiUangSalesman.AmbilWaktu(json['DiambilPada']),
  );
}

/// Kunjungan salesman yang sudah tercatat di server (`GET salesman/kunjungan`). Koordinat string desimal apa adanya.
class KunjunganSalesmanPos {
  const KunjunganSalesmanPos({
    required this.uuid,
    required this.uuidPelanggan,
    required this.namaPelanggan,
    required this.masukPada,
    required this.keluarPada,
    required this.latitude,
    required this.longitude,
    required this.akurasiMeter,
    required this.hasil,
    required this.labelHasil,
    required this.catatan,
    required this.uuidPesananGrosir,
    required this.nomorPesananGrosir,
  });

  final String uuid;
  final String? uuidPelanggan;
  final String namaPelanggan;
  final DateTime? masukPada;
  final DateTime? keluarPada;
  final String? latitude;
  final String? longitude;
  final int? akurasiMeter;

  /// `PesananDibuat` / `TidakPesan` / `TokoTutup` / `Lainnya`.
  final String hasil;
  final String labelHasil;
  final String? catatan;
  final String? uuidPesananGrosir;

  /// Nomor `PG/...` yang diberikan server (draf di perangkat tidak bernomor).
  final String? nomorPesananGrosir;

  static KunjunganSalesmanPos DariJson(Map<String, Object?> json) => KunjunganSalesmanPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    uuidPelanggan: UraiJson.AmbilTeksAtauNull(json['UuidPelanggan']),
    namaPelanggan: UraiJson.AmbilTeks(json['NamaPelanggan']),
    masukPada: UraiUangSalesman.AmbilWaktu(json['MasukPada']),
    keluarPada: UraiUangSalesman.AmbilWaktu(json['KeluarPada']),
    latitude: UraiJson.AmbilTeksAtauNull(json['Latitude']),
    longitude: UraiJson.AmbilTeksAtauNull(json['Longitude']),
    akurasiMeter: UraiJson.AmbilBulatAtauNull(json['AkurasiMeter']),
    hasil: UraiJson.AmbilTeks(json['Hasil']),
    labelHasil: UraiJson.AmbilTeks(json['LabelHasil'], UraiJson.AmbilTeks(json['Hasil'])),
    catatan: UraiJson.AmbilTeksAtauNull(json['Catatan']),
    uuidPesananGrosir: UraiJson.AmbilTeksAtauNull(json['UuidPesananGrosir']),
    nomorPesananGrosir: UraiJson.AmbilTeksAtauNull(json['NomorPesananGrosir']),
  );
}

/// Kunjungan salesman pada satu tanggal (zona waktu outlet).
class DaftarKunjunganSalesman {
  const DaftarKunjunganSalesman({required this.tanggal, required this.kunjungan});

  /// `YYYY-MM-DD`.
  final String tanggal;
  final List<KunjunganSalesmanPos> kunjungan;

  static DaftarKunjunganSalesman DariJson(Map<String, Object?> json) => DaftarKunjunganSalesman(
    tanggal: UraiJson.AmbilTeks(json['Tanggal']),
    kunjungan: [for (final k in UraiJson.AmbilDaftarPeta(json['Kunjungan'])) KunjunganSalesmanPos.DariJson(k)],
  );
}
