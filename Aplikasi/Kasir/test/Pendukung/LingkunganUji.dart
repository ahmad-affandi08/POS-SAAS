import 'dart:convert';
import 'dart:io';

import 'package:drift/drift.dart' show driftRuntimeOptions;
import 'package:drift/native.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:kasir/Data/BasisData/BasisDataKasir.dart';
import 'package:kasir/Data/PenyimpanRahasia.dart';
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:kasir/Data/RepositoriKatalog.dart';
import 'package:kasir/Data/RepositoriPenjualan.dart';
import 'package:kasir/Data/RepositoriPesananMeja.dart';
import 'package:kasir/Domain/Katalog/KatalogLokal.dart';
import 'package:kasir/Domain/Katalog/LayananKatalog.dart';
import 'package:kasir/Domain/Meja/LayananPesananMeja.dart';
import 'package:kasir/Domain/Penjualan/KonteksPenjualan.dart';
import 'package:kasir/Domain/Penjualan/LayananPenjualan.dart';
import 'package:kasir/Domain/Sesi/LayananMasuk.dart';
import 'package:kasir/Domain/Sesi/LayananPerangkat.dart';
import 'package:kasir/Domain/Sesi/StafLokal.dart';
import 'package:kasir/Domain/Shift/LayananShift.dart';
import 'package:kasir/Domain/Shift/LayananTutupShift.dart';
import 'package:kasir/Domain/Sinkron/LayananSinkron.dart';
import 'package:klien_api/KlienApi.dart';

import 'KatalogUji.dart';

/// Vektor PIN bersama PHP & Dart: staf uji memakai PIN, garam, dan verifier terbungkus dari sini sehingga verifikasi
/// offline berjalan tanpa server.
final Map<String, Object?> vektorPin =
    jsonDecode(File('../../Spesifikasi/VektorUjiPin/VerifierPin.json').readAsStringSync()) as Map<String, Object?>;

Map<String, Object?> KasusPin(int indeks) => (vektorPin['Kasus']! as List<Object?>)[indeks]! as Map<String, Object?>;

Map<String, Object?> StafJson(String uuid, String nama, List<String> izin, int? indeksPin, {bool pemilik = false}) {
  final kasus = indeksPin == null ? null : KasusPin(indeksPin);
  return {
    'Uuid': uuid,
    'Nama': nama,
    'Pemilik': pemilik,
    'Izin': izin,
    'PinDiatur': true,
    'Pin': kasus == null ? null : {'Garam': kasus['Garam'], 'Nonce': kasus['Nonce'], 'Sandi': kasus['Sandi']},
  };
}

/// Data awal uji: Rina (kasir, boleh diskon manual, PIN kasus 0 "246810"), Budi (supervisor, penyetuju kas keluar &
/// diskon & selisih kas tutup shift & void/retur, PIN kasus 1 "135790"), Sari (kasir tanpa verifier offline), kategori keluar & masuk, batas kas keluar
/// Rp 200.000. F-07b: outlet SLB, perangkat POS-001, memungut PBJT 10% (bukan PKP), batas diskon 10%/30%, lima
/// metode pembayaran fase 1.
Map<String, Object?> DataAwalUji({
  bool shiftBersama = false,
  Map<String, Object?>? pembulatanTunai,
  Map<String, Object?>? profilPajak,
  List<Map<String, Object?>>? tarifPajak,
  bool tutupShiftButa = true,
  String toleransiSelisihKas = '10000.00',
  String? zonaWaktu,
  String? jamTutupBuku,
  Map<String, Object?>? nomorUrutPenjualan,
  Map<String, Object?>? nomorUrutRetur,
}) => {
  'Pengaturan': {
    'BatasKasKeluar': '200000.00',
    'ShiftBersama': shiftBersama,
    'BatasDiskonManual': '10.00',
    'BatasDiskonPenyetuju': '30.00',
    'PembulatanTunai': pembulatanTunai,
    'TutupShiftButa': tutupShiftButa,
    'ToleransiSelisihKas': toleransiSelisihKas,
  },
  'Outlet': {
    'Uuid': '01K50VT1ET0000000000000001',
    'Kode': 'SLB',
    'Nama': 'Kopi Senja Solo Baru',
    'Alamat': 'Jl. Ir. Soekarno No. 12, Solo Baru, Sukoharjo',
    'Telepon': '0271-555123',
    'JamTutupBuku': ?jamTutupBuku,
    'ZonaWaktu': ?zonaWaktu,
  },
  'Perangkat': {
    'Uuid': '01K5PERANGKAT0000000000001',
    'Kode': 'POS-001',
    'NomorUrutPenjualan': ?nomorUrutPenjualan,
    'NomorUrutRetur': ?nomorUrutRetur,
  },
  'ProfilPajak':
      profilPajak ??
      {
        'Pkp': false,
        'PungutPbjt': true,
        'HargaTermasukPajak': false,
        'BiayaLayanan': {'Aktif': false, 'Persen': '0.00'},
      },
  'TarifPajak':
      tarifPajak ??
      [
        {
          'KodeJenisPajak': 'PbjtMakananMinuman',
          'Tarif': '10.00',
          'PengaliDppPembilang': 1,
          'PengaliDppPenyebut': 1,
          'BerlakuMulai': '2024-01-01',
          'BerlakuSampai': null,
        },
        {
          'KodeJenisPajak': 'Ppn',
          'Tarif': '12.00',
          'PengaliDppPembilang': 11,
          'PengaliDppPenyebut': 12,
          'BerlakuMulai': '2025-01-01',
          'BerlakuSampai': null,
        },
      ],
  'MetodePembayaran': [
    {'Uuid': '01K5MTD0000000000000000001', 'Jenis': 'Tunai', 'Nama': 'Tunai', 'AdaGambarQris': false, 'Urutan': 1},
    {'Uuid': '01K5MTD0000000000000000002', 'Jenis': 'QrisStatis', 'Nama': 'QRIS', 'AdaGambarQris': true, 'Urutan': 2},
    {'Uuid': '01K5MTD0000000000000000003', 'Jenis': 'Edc', 'Nama': 'EDC BCA', 'AdaGambarQris': false, 'Urutan': 3},
    {
      'Uuid': '01K5MTD0000000000000000004',
      'Jenis': 'Transfer',
      'Nama': 'Transfer BCA',
      'NomorRekening': '0151234567',
      'NamaPemilikRekening': 'CV Kopi Senja',
      'AdaGambarQris': false,
      'Urutan': 4,
    },
    {'Uuid': '01K5MTD0000000000000000005', 'Jenis': 'Ewallet', 'Nama': 'GoPay', 'AdaGambarQris': false, 'Urutan': 5},
    {'Uuid': '01K5MTD0000000000000000006', 'Jenis': 'Piutang', 'Nama': 'Kasbon', 'AdaGambarQris': false, 'Urutan': 6},
  ],
  'KategoriKas': [
    {'Uuid': '01K5KATEGORI00000000000001', 'Nama': 'Beli es batu & galon', 'Jenis': 'Keluar'},
    {'Uuid': '01K5KATEGORI00000000000002', 'Nama': 'Tambahan uang receh', 'Jenis': 'Masuk'},
  ],
  'Staf': [
    StafJson('01K5STAF000000000000000001', 'Rina Wulandari', ['penjualan.buat', 'penjualan.diskon.manual'], 0),
    StafJson('01K5STAF000000000000000002', 'Budi Santoso', [
      'penjualan.buat',
      'kas.keluar.setujui',
      'penjualan.diskon.manual',
      'penjualan.diskon.setujui',
      'shift.selisih.setujui',
      'penjualan.void',
    ], 1),
    StafJson('01K5STAF000000000000000003', 'Sari Lestari', ['penjualan.buat'], null),
  ],
  'PinOffline': {'Tersedia': true, 'Parameter': vektorPin['Parameter'], 'BatasSalah': 5, 'MenitKunci': 5},
  'WaktuServer': '2026-09-24T01:00:00Z',
};

/// Respons `GET /api/pos/v1/meja` uji.
Map<String, Object?> DataMejaUji() => {
  'ModeMejaAktif': true,
  'Area': [
    {'Uuid': '01K5AREA000000000000DALAM1', 'Nama': 'Dalam', 'Urutan': 1},
    {'Uuid': '01K5AREA000000000000TERAS1', 'Nama': 'Teras', 'Urutan': 2},
  ],
  'Meja': [
    {'Uuid': '01K5MEJA0000000000000D0101', 'Nama': 'D-01', 'UuidArea': '01K5AREA000000000000DALAM1', 'Kapasitas': 4},
    {'Uuid': '01K5MEJA0000000000000D0201', 'Nama': 'D-02', 'UuidArea': '01K5AREA000000000000DALAM1', 'Kapasitas': 2},
    {'Uuid': '01K5MEJA0000000000000T0101', 'Nama': 'T-01', 'UuidArea': '01K5AREA000000000000TERAS1', 'Kapasitas': 6},
  ],
  'StasiunDapur': [
    {'Uuid': '01K5STAS1VN000000000BAR001', 'Nama': 'Bar'},
    {'Uuid': '01K5STAS1VN000000000DAPUR1', 'Nama': 'Dapur'},
  ],
  'UuidStasiunBawaan': '01K5STAS1VN000000000DAPUR1',
};

/// Server tiruan: penangan bisa diganti per test; semua permintaan dicatat.
class ServerTiruan {
  final List<http.Request> permintaan = [];
  Future<http.Response> Function(http.Request permintaan) penangan = (_) async => http.Response('{}', 200);

  http.Client BuatKlien() => MockClient((p) async {
    permintaan.add(p);
    return penangan(p);
  });
}

http.Response JsonUji(Object isi, [int status = 200]) =>
    http.Response(jsonEncode(isi), status, headers: {'content-type': 'application/json'});

class LingkunganUji {
  LingkunganUji._(this.db, this.server, this.rahasia, this.jam);

  final BasisDataKasir db;
  final ServerTiruan server;
  final PenyimpanRahasiaMemori rahasia;
  DateTime jam;

  late final RepositoriKasir repositori = RepositoriKasir(db);
  late final RepositoriKatalog repositoriKatalog = RepositoriKatalog(db);
  late final RepositoriPenjualan repositoriPenjualan = RepositoriPenjualan(db, repositori);
  late final KlienPos klien = KlienPos(
    alamatDasar: Uri.parse('https://kasir.contoh.id/'),
    versiAplikasi: '0.1.0',
    ambilToken: () => rahasia.isi[PenyimpanRahasia.kunciToken],
    klien: server.BuatKlien(),
  );
  late final LayananPerangkat perangkat = LayananPerangkat(
    klien: klien,
    repositori: repositori,
    rahasia: rahasia,
    platform: 'Android',
    jam: () => jam,
  );
  late final LayananMasuk masuk = LayananMasuk(repositori: repositori, rahasia: rahasia, klien: klien, jam: () => jam);
  late final LayananShift shift = LayananShift(repositori: repositori, jam: () => jam);
  late final LayananTutupShift tutupShift = LayananTutupShift(
    repositori: repositori,
    repositoriPenjualan: repositoriPenjualan,
    jam: () => jam,
  );
  late final LayananSinkron sinkron = LayananSinkron(
    klien: klien,
    repositori: repositori,
    perangkat: perangkat,
    jam: () => jam,
  );

  late final LayananKatalog katalog = LayananKatalog(
    klien: klien,
    repositori: repositori,
    repositoriKatalog: repositoriKatalog,
    jam: () => jam,
  );
  late final LayananPenjualan penjualan = LayananPenjualan(
    repositori: repositori,
    repositoriPenjualan: repositoriPenjualan,
    jam: () => jam,
  );

  late final RepositoriPesananMeja repositoriMeja = RepositoriPesananMeja(db, repositori);
  late final LayananPesananMeja pesananMeja = LayananPesananMeja(
    klien: klien,
    repositori: repositori,
    repositoriMeja: repositoriMeja,
    jam: () => jam,
  );

  /// Area "Dalam" & "Teras", meja D-01 (4 kursi), D-02, T-01; mode meja aktif (bentuk `GET /api/pos/v1/meja`).
  Future<void> SiapkanMeja() => repositoriMeja.SimpanDataMeja(DataMejaPos.DariJson(DataMejaUji()));

  static LingkunganUji Buat() {
    driftRuntimeOptions.dontWarnAboutMultipleDatabases = true;
    return LingkunganUji._(
      BasisDataKasir(NativeDatabase.memory()),
      ServerTiruan(),
      PenyimpanRahasiaMemori(),
      DateTime.utc(2026, 9, 24, 1),
    );
  }

  /// Perangkat sudah aktif + data awal uji tersimpan (tanpa lewat server).
  Future<void> SiapkanAktif({bool shiftBersama = false, Map<String, Object?>? dataAwal}) async {
    rahasia.isi[PenyimpanRahasia.kunciToken] = '12|rahasia';
    rahasia.isi[PenyimpanRahasia.kunciPin] = vektorPin['KunciPerangkat']! as String;
    await repositori.SimpanDataAwal(DataAwal.DariJson(dataAwal ?? DataAwalUji(shiftBersama: shiftBersama)), jam);
  }

  /// Katalog uji "Kopi Senja" tersimpan lokal (tanpa lewat server).
  Future<void> SiapkanKatalog([Map<String, Object?>? katalog]) =>
      repositoriKatalog.GantiKatalog(KatalogPos.DariJson(katalog ?? KatalogUji()));

  Future<KatalogLokal> MuatKatalog() async => KatalogLokal.Bangun(await repositoriKatalog.Muat());

  Future<KonteksPenjualan> MuatKonteks() => KonteksPenjualan.Muat(repositori, repositoriKatalog);

  Future<StafLokal> Staf(String nama) async =>
      StafLokal.DariBaris((await repositori.AmbilStaf()).firstWhere((s) => s.Nama == nama));

  Future<void> Tutup() => db.close();
}
