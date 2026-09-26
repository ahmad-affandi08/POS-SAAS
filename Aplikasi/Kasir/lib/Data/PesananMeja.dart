import 'dart:convert';

import 'package:klien_api/KlienApi.dart';

import 'BasisData/BasisDataKasir.dart';

/// Status pesanan terbuka (sama dengan server).
abstract final class StatusPesananMeja {
  static const String terbuka = 'Terbuka';
  static const String dibayar = 'Dibayar';
  static const String dibatalkan = 'Dibatalkan';

  /// v1.99: semua item dipindah ke pesanan lain (gabung meja/tagihan).
  static const String digabung = 'Digabung';
}

/// Satu baris pesanan terbuka di perangkat (kolom JSON `PesananTerbuka.Baris`). Append-only: baris yang sudah tersimpan
/// tidak diubah jumlahnya; pembatalan = void item (BR-07.5).
class BarisPesananMeja {
  const BarisPesananMeja({
    required this.uuid,
    required this.uuidProduk,
    required this.uuidProdukSatuan,
    required this.namaProduk,
    required this.jumlah,
    required this.hargaSatuan,
    required this.hargaPilihan,
    this.pilihan = const [],
    this.catatan,
    this.ronde = 1,
    this.dibatalkan = false,
    this.dikirimKeDapur = false,
    this.statusDapur,
  });

  final String uuid;

  /// Null hanya untuk baris dari server lama (tanpa `UuidProduk`); baris seperti itu tidak bisa dibayar di perangkat.
  final String? uuidProduk;
  final String? uuidProdukSatuan;
  final String namaProduk;

  /// String desimal (kontrak API).
  final String jumlah;
  final String hargaSatuan;
  final String hargaPilihan;

  /// `[{UuidPilihan, Nama, Harga}]`.
  final List<Map<String, Object?>> pilihan;
  final String? catatan;
  final int ronde;
  final bool dibatalkan;
  final bool dikirimKeDapur;

  /// Antre/Dimasak/Siap/Disajikan dari tiket dapur; null = belum ada tiket.
  final String? statusDapur;

  BarisPesananMeja Salin({bool? dibatalkan, bool? dikirimKeDapur, int? ronde}) => BarisPesananMeja(
    uuid: uuid,
    uuidProduk: uuidProduk,
    uuidProdukSatuan: uuidProdukSatuan,
    namaProduk: namaProduk,
    jumlah: jumlah,
    hargaSatuan: hargaSatuan,
    hargaPilihan: hargaPilihan,
    pilihan: pilihan,
    catatan: catatan,
    ronde: ronde ?? this.ronde,
    dibatalkan: dibatalkan ?? this.dibatalkan,
    dikirimKeDapur: dikirimKeDapur ?? this.dikirimKeDapur,
    statusDapur: statusDapur,
  );

  Map<String, Object?> KeJson() => {
    'Uuid': uuid,
    'UuidProduk': uuidProduk,
    'UuidProdukSatuan': uuidProdukSatuan,
    'NamaProduk': namaProduk,
    'Jumlah': jumlah,
    'HargaSatuan': hargaSatuan,
    'HargaPilihan': hargaPilihan,
    'Pilihan': pilihan,
    'Catatan': catatan,
    'Ronde': ronde,
    'Dibatalkan': dibatalkan,
    'DikirimKeDapur': dikirimKeDapur,
    'StatusDapur': statusDapur,
  };

  /// Bentuk JSON lokal sama dengan snapshot server, jadi keduanya dibaca dengan pengurai yang sama.
  static BarisPesananMeja DariJson(Map<String, Object?> json) => DariServer(BarisPesananTerbukaPos.DariJson(json));

  static BarisPesananMeja DariServer(BarisPesananTerbukaPos b) => BarisPesananMeja(
    uuid: b.uuid,
    uuidProduk: b.uuidProduk,
    uuidProdukSatuan: b.uuidProdukSatuan,
    namaProduk: b.namaProduk,
    jumlah: b.jumlah,
    hargaSatuan: b.hargaSatuan,
    hargaPilihan: b.hargaPilihan,
    pilihan: b.pilihan,
    catatan: b.catatan,
    ronde: b.ronde,
    dibatalkan: b.dibatalkan,
    dikirimKeDapur: b.dikirimKeDapur,
    statusDapur: b.statusDapur,
  );

  /// Label status untuk kasir: status tiket dapur, "Belum dikirim", atau "Dibatalkan".
  String AmbilLabelStatus() {
    if (dibatalkan) {
      return 'Dibatalkan';
    }
    if (!dikirimKeDapur) {
      return 'Belum dikirim';
    }
    return switch (statusDapur) {
      'Dimasak' => 'Dimasak',
      'Siap' => 'Siap',
      'Disajikan' => 'Disajikan',
      'Antre' => 'Di dapur',
      _ => 'Terkirim',
    };
  }
}

/// Pesanan terbuka (open bill) yang dilihat perangkat ini.
class PesananMeja {
  const PesananMeja({
    required this.uuid,
    required this.nomor,
    required this.uuidMeja,
    required this.namaMeja,
    required this.label,
    required this.jumlahTamu,
    required this.dibukaOleh,
    required this.dibukaPada,
    required this.status,
    required this.dikunciBayar,
    required this.baris,
  });

  final String uuid;
  final String nomor;
  final String? uuidMeja;
  final String? namaMeja;
  final String? label;
  final int jumlahTamu;
  final String? dibukaOleh;
  final DateTime dibukaPada;
  final String status;
  final bool dikunciBayar;
  final List<BarisPesananMeja> baris;

  List<BarisPesananMeja> AmbilBarisAktif() => baris.where((b) => !b.dibatalkan).toList();

  /// Ronde berikutnya untuk tambahan pesanan (1, 2, 3, …; maks. 99 sesuai server).
  int AmbilRondeBerikutnya() {
    final terakhir = baris.fold(0, (maks, b) => b.ronde > maks ? b.ronde : maks);
    return terakhir >= 99 ? 99 : terakhir + 1;
  }

  /// Nama tampil: meja, label, atau nomor pesanan.
  String AmbilJudul() => namaMeja ?? label ?? nomor;

  static PesananMeja DariBaris(BarisPesananTerbuka b) {
    final isi = jsonDecode(b.Baris);
    return PesananMeja(
      uuid: b.Uuid,
      nomor: b.Nomor,
      uuidMeja: b.UuidMeja,
      namaMeja: b.NamaMeja,
      label: b.Label,
      jumlahTamu: b.JumlahTamu,
      dibukaOleh: b.DibukaOleh,
      dibukaPada: b.DibukaPada,
      status: b.Status,
      dikunciBayar: b.DikunciBayar,
      baris: [
        if (isi is List<Object?>)
          for (final json in isi.whereType<Map<String, Object?>>()) BarisPesananMeja.DariJson(json),
      ],
    );
  }
}
