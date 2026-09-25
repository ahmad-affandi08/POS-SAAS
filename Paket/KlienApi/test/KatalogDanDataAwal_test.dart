import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:test/test.dart';

KlienPos BuatKlien(Future<http.Response> Function(http.Request permintaan) penangan) => KlienPos(
  alamatDasar: Uri.parse('https://kasir.contoh.id/'),
  versiAplikasi: '0.1.0',
  ambilToken: () => 'Tkn',
  klien: MockClient(penangan),
);

http.Response Json(Object isi, [int status = 200]) =>
    http.Response(jsonEncode(isi), status, headers: {'content-type': 'application/json'});

Map<String, Object?> DataAwalF06() => {
  'Pengaturan': {'BatasKasKeluar': '200000.00', 'ShiftBersama': false},
  'KategoriKas': <Object?>[],
  'Staf': <Object?>[],
  'PinOffline': {'Tersedia': false, 'Parameter': <String, Object?>{}, 'BatasSalah': 5, 'MenitKunci': 5},
  'WaktuServer': '2026-09-24T01:00:00Z',
};

void main() {
  group('AmbilKatalog (F-03 D.3)', () {
    test('tanpa kursor meminta katalog lengkap; bagian & tipe dipetakan, uang tetap string', () async {
      late http.Request dikirim;
      final klien = BuatKlien((p) async {
        dikirim = p;
        return Json({
          'Skema': 1,
          'Lengkap': true,
          'Kursor': 'a3Vyc29y',
          'WaktuServer': '2026-09-24T01:00:00Z',
          'Kategori': [
            {'Uuid': 'K1', 'UuidInduk': null, 'Nama': 'Kopi', 'Urutan': 1},
          ],
          'Satuan': [
            {'Uuid': 'S1', 'Nama': 'Cangkir', 'Simbol': 'cup', 'BolehDesimal': false},
          ],
          'KelompokPajak': [
            {
              'Uuid': 'KP1',
              'Nama': 'Makanan & minuman',
              'Kategori': 'KenaPbjt',
              'Pajak': [
                {'KodeJenisPajak': 'PbjtMakananMinuman', 'DasarPengenaan': 'SubtotalPlusLayanan', 'Urutan': 1},
              ],
            },
          ],
          'Produk': [
            {
              'Uuid': 'P1',
              'Sku': 'KSA-001',
              'Nama': 'Es Kopi Susu Aren Gula Semut Ukuran Besar',
              'Jenis': 'Resep',
              'UuidKategori': 'K1',
              'UuidSatuanDasar': 'S1',
              'Pelacakan': 'Tidak',
              'UuidKelompokPajak': 'KP1',
              'HargaTermasukPajak': null,
              'TampilDiPos': true,
              'Aktif': true,
              'Dihapus': false,
            },
          ],
          'ProdukSatuan': [
            {'Uuid': 'PS1', 'UuidProduk': 'P1', 'UuidSatuan': 'S1', 'KonversiKeDasar': '1.0000', 'DefaultJual': true},
          ],
          'ProdukBarcode': [
            {'Uuid': 'B1', 'UuidProduk': 'P1', 'UuidProdukSatuan': 'PS1', 'Barcode': '8991234567890'},
          ],
          'DaftarHarga': [
            {
              'Uuid': 'DH1',
              'Nama': 'Ojol',
              'UuidOutlet': null,
              'Kanal': 'Online',
              'Prioritas': 10,
              'Aktif': true,
              'MulaiPada': null,
              'SelesaiPada': null,
            },
          ],
          'ProdukHarga': [
            {
              'Uuid': 'H1',
              'UuidProduk': 'P1',
              'UuidProdukSatuan': 'PS1',
              'UuidDaftarHarga': null,
              'JumlahMinimum': '1.0000',
              'Harga': '28000.00',
            },
          ],
          'KelompokPilihan': [
            {'Uuid': 'KL1', 'Nama': 'Level gula', 'MinimalPilih': 1, 'MaksimalPilih': 1, 'Urutan': 1},
          ],
          'Pilihan': [
            {
              'Uuid': 'PL1',
              'UuidKelompokPilihan': 'KL1',
              'Nama': 'Kurang manis',
              'Harga': '0.00',
              'Aktif': true,
              'Urutan': 1,
            },
          ],
          'ProdukKelompokPilihan': [
            {'Uuid': 'PK1', 'UuidProduk': 'P1', 'UuidKelompokPilihan': 'KL1', 'Urutan': 1},
          ],
          'Resep': <Object?>[],
          'Terhapus': <Object?>[],
        });
      });

      final katalog = await klien.AmbilKatalog();

      expect(dikirim.url.toString(), 'https://kasir.contoh.id/api/pos/v1/katalog');
      expect(dikirim.headers['Authorization'], 'Bearer Tkn');
      expect(katalog.lengkap, isTrue);
      expect(katalog.kursor, 'a3Vyc29y');
      expect(katalog.produk.single.hargaTermasukPajak, isNull);
      expect(katalog.produk.single.jenis, 'Resep');
      expect(katalog.produkHarga.single.harga, '28000.00');
      expect(katalog.kelompokPajak.single.pajak.single.dasarPengenaan, 'SubtotalPlusLayanan');
      expect(katalog.kelompokPajak.single.pajak.single.kategori, isNull, reason: 'Server lama tanpa Kategori.');
      expect(katalog.daftarHarga.single.uuidOutlet, isNull);
      expect(katalog.kelompokPilihan.single.maksimalPilih, 1);
      expect(katalog.produkBarcode.single.barcode, '8991234567890');
    });

    test('dengan kursor mengirim ?sejak= ter-encode; delta membawa Terhapus', () async {
      late http.Request dikirim;
      final klien = BuatKlien((p) async {
        dikirim = p;
        return Json({
          'Skema': 1,
          'Lengkap': false,
          'Kursor': 'baru',
          'WaktuServer': '2026-09-24T01:01:00Z',
          'Produk': [
            {'Uuid': 'P1', 'Nama': 'Teh', 'Dihapus': true},
          ],
          'Terhapus': [
            {'Entitas': 'ProdukBarcode', 'Uuid': 'B1'},
          ],
        });
      });

      final katalog = await klien.AmbilKatalog(kursor: 'a+b/c=');

      expect(dikirim.url.queryParameters['sejak'], 'a+b/c=');
      expect(katalog.lengkap, isFalse);
      expect(katalog.produk.single.dihapus, isTrue);
      expect(katalog.terhapus.single.entitas, 'ProdukBarcode');
    });

    test('kursor tidak valid → GalatApi KursorTidakValid', () async {
      final klien = BuatKlien(
        (_) async => Json({
          'Galat': {'Kode': 'KursorTidakValid', 'Pesan': 'Kursor katalog tidak valid.'},
        }, 422),
      );
      expect(
        () => klien.AmbilKatalog(kursor: 'rusak'),
        throwsA(isA<GalatApi>().having((g) => g.kode, 'kode', 'KursorTidakValid')),
      );
    });
  });

  group('DataAwal F-07b', () {
    test('server lama tanpa kunci F-07b → nilai bawaan (kompatibel mundur)', () {
      final data = DataAwal.DariJson(DataAwalF06());
      expect(data.batasDiskonManual, '10');
      expect(data.batasDiskonPenyetuju, '30');
      expect(data.pembulatanTunai, isNull);
      expect(data.outlet, isNull);
      expect(data.perangkat, isNull);
      expect(data.profilPajak.pkp, isFalse);
      expect(data.profilPajak.persenBiayaLayanan, '0');
      expect(data.tarifPajak, isEmpty);
      expect(data.metodePembayaran, isEmpty);
    });

    test('kunci F-07b dipetakan: pengaturan, outlet, perangkat, profil pajak, tarif, metode', () {
      final data = DataAwal.DariJson({
        ...DataAwalF06(),
        'Pengaturan': {
          'BatasKasKeluar': '200000.00',
          'ShiftBersama': false,
          'BatasDiskonManual': '15.00',
          'BatasDiskonPenyetuju': 40,
          'PembulatanTunai': {'Kelipatan': 100, 'Arah': 'Bawah'},
        },
        'Outlet': {'Uuid': 'O1', 'Kode': 'SLB', 'Nama': 'Solo Baru', 'Alamat': null, 'Telepon': '0271'},
        'Perangkat': {'Uuid': 'D1', 'Kode': 'K02'},
        'ProfilPajak': {
          'Pkp': true,
          'PungutPbjt': false,
          'HargaTermasukPajak': true,
          'BiayaLayanan': {'Aktif': true, 'Persen': '5.00'},
        },
        'TarifPajak': [
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
          {
            'Uuid': 'M1',
            'Jenis': 'QrisStatis',
            'Nama': 'QRIS BCA',
            'NomorRekening': null,
            'NamaPemilikRekening': null,
            'AdaGambarQris': true,
            'Urutan': 2,
          },
        ],
      });
      expect(data.batasDiskonManual, '15.00');
      expect(data.batasDiskonPenyetuju, '40');
      expect(data.pembulatanTunai!.kelipatan, 100);
      expect(data.pembulatanTunai!.arah, 'Bawah');
      expect(data.outlet!.kode, 'SLB');
      expect(data.perangkat!.kode, 'K02');
      expect(data.profilPajak.pkp, isTrue);
      expect(data.profilPajak.biayaLayananAktif, isTrue);
      expect(data.profilPajak.persenBiayaLayanan, '5.00');
      expect(data.tarifPajak.single.pengaliDppPembilang, 11);
      expect(data.tarifPajak.single.berlakuSampai, isNull);
      expect(data.metodePembayaran.single.adaGambarQris, isTrue);
      // Server lama: tanpa ZonaWaktu & NomorUrutPenjualan.
      expect(data.outlet!.zonaWaktu, isNull);
      expect(data.perangkat!.nomorUrutPenjualan, isEmpty);
    });

    test('PRD v1.46: Outlet.ZonaWaktu & Perangkat.NomorUrutPenjualan dipetakan; entri tidak valid diabaikan', () {
      final data = DataAwal.DariJson({
        ...DataAwalF06(),
        'Outlet': {'Uuid': 'O1', 'Kode': 'MKS', 'Nama': 'Makassar', 'ZonaWaktu': 'Asia/Makassar'},
        'Perangkat': {
          'Uuid': 'D1',
          'Kode': 'K02',
          'NomorUrutPenjualan': {'260924': 12, '260925': '3', '2609': 5, '260926': 'x', '260927': 0, '260928': null},
        },
      });
      expect(data.outlet!.zonaWaktu, 'Asia/Makassar');
      expect(data.perangkat!.nomorUrutPenjualan, {'260924': 12, '260925': 3});

      final bukanPeta = DataAwal.DariJson({
        ...DataAwalF06(),
        'Perangkat': {'Uuid': 'D1', 'Kode': 'K02', 'NomorUrutPenjualan': <Object?>[]},
      });
      expect(bukanPeta.perangkat!.nomorUrutPenjualan, isEmpty);
    });
  });

  group('DataAwal F-11', () {
    test('server lama tanpa kunci F-11 → tutup shift buta & toleransi Rp 10.000 (kompatibel mundur)', () {
      final data = DataAwal.DariJson(DataAwalF06());
      expect(data.tutupShiftButa, isTrue);
      expect(data.toleransiSelisihKas, '10000');
    });

    test('TutupShiftButa & ToleransiSelisihKas dipetakan dari Pengaturan', () {
      final data = DataAwal.DariJson({
        ...DataAwalF06(),
        'Pengaturan': {
          'BatasKasKeluar': '200000.00',
          'ShiftBersama': false,
          'TutupShiftButa': false,
          'ToleransiSelisihKas': '25000.00',
        },
      });
      expect(data.tutupShiftButa, isFalse);
      expect(data.toleransiSelisihKas, '25000.00');
    });
  });

  group('DataAwal F-09', () {
    test('server lama tanpa BatasHariRetur → 7 hari; nilai server dipetakan', () {
      expect(DataAwal.DariJson(DataAwalF06()).batasHariRetur, 7);
      final data = DataAwal.DariJson({
        ...DataAwalF06(),
        'Pengaturan': {'BatasKasKeluar': '200000.00', 'ShiftBersama': false, 'BatasHariRetur': 14},
      });
      expect(data.batasHariRetur, 14);
    });
  });

  group('penjualan/cari (F-09 retur)', () {
    Map<String, Object?> Struk() => {
      'Penjualan': {
        'Uuid': '01K5PNJ0000000000000000007',
        'Nomor': 'INV/SLB/260920/POS-002-0007',
        'Status': 'DireturSebagian',
        'LabelStatus': 'Diretur sebagian',
        'TanggalBisnis': '2026-09-20',
        'DibuatPada': '2026-09-20T05:12:00Z',
        'NamaKasir': 'Sari Lestari',
        'HargaTermasukPajak': true,
        'Subtotal': '287500.00',
        'TotalDiskon': '0.00',
        'BiayaLayanan': '0.00',
        'TotalPajak': '0.00',
        'Pembulatan': '0.00',
        'TotalAkhir': '287500.00',
        'TotalDibayar': '300000.00',
        'Kembalian': '12500.00',
        'BatasHariRetur': 7,
        'BatasReturSampai': '2026-09-27',
        'BisaDiretur': true,
        'AlasanTidakBisaDiretur': null,
      },
      'Baris': [
        {
          'Uuid': '01K5BRS0000000000000000001',
          'UuidProduk': '01K5PRD0000000000000000001',
          'NamaProduk': 'Kopi Susu Literan 1 L',
          'SimbolSatuan': 'btl',
          'Jumlah': '3.0000',
          'HargaSatuan': '33333.33',
          'HargaPilihan': '0.00',
          'Pilihan': [
            {'UuidPilihan': 'P1', 'Nama': 'Kurang manis', 'Harga': '0.00'},
          ],
          'Bruto': '100000.00',
          'JumlahDiskon': '0.00',
          'JumlahDiskonPesanan': '0.00',
          'BiayaLayanan': '0.00',
          'JumlahPajak': '9909.91',
          'TotalBaris': '100000.00',
          'SnapshotPajak': <Object?>[],
          'JumlahSudahDiretur': '1.0000',
          'JumlahBisaDiretur': '2.0000',
          'NilaiBisaDiretur': '66666.67',
        },
      ],
      'Pembayaran': [
        {
          'Uuid': 'B1',
          'UuidMetodePembayaran': 'M1',
          'JenisMetode': 'Tunai',
          'NamaMetode': 'Tunai',
          'Jumlah': '300000.00',
          'Referensi': null,
        },
      ],
      'Retur': [
        {
          'Uuid': 'R1',
          'Nomor': 'RJ/SLB/260921/POS-002-0001',
          'DibuatPada': '2026-09-21T02:00:00Z',
          'TotalRefund': '33333.33',
        },
      ],
    };

    test('nomor dikodekan di query; respons dipetakan (uang & jumlah tetap teks desimal)', () async {
      late http.Request dikirim;
      final klien = BuatKlien((p) async {
        dikirim = p;
        return Json(Struk(), 200);
      });

      final hasil = await klien.CariPenjualan(' INV/SLB/260920/POS-002-0007 ');

      expect(dikirim.method, 'GET');
      expect(dikirim.url.path, '/api/pos/v1/penjualan/cari');
      expect(dikirim.url.queryParameters, {'nomor': 'INV/SLB/260920/POS-002-0007'});
      expect(dikirim.headers['Authorization'], startsWith('Bearer '));
      expect(hasil.penjualan.labelStatus, 'Diretur sebagian');
      expect((hasil.penjualan.bisaDiretur, hasil.penjualan.batasReturSampai), (true, '2026-09-27'));
      final b = hasil.baris.single;
      expect(
        (b.jumlah, b.jumlahSudahDiretur, b.jumlahBisaDiretur, b.nilaiBisaDiretur, b.totalBaris),
        ('3.0000', '1.0000', '2.0000', '66666.67', '100000.00'),
      );
      expect(b.pilihan, ['Kurang manis']);
      expect(hasil.pembayaran.single.jenisMetode, 'Tunai');
      expect(hasil.retur.single.totalRefund, '33333.33');
    });

    test('404 PenjualanTidakDitemukan → GalatApi; server tak terjangkau → GalatJaringan', () async {
      final tidakAda = BuatKlien(
        (_) async => Json({
          'Galat': {'Kode': 'PenjualanTidakDitemukan', 'Pesan': 'Penjualan tidak ditemukan.'},
        }, 404),
      );
      await expectLater(
        tidakAda.CariPenjualan('INV/X'),
        throwsA(isA<GalatApi>().having((g) => g.kode, 'kode', 'PenjualanTidakDitemukan')),
      );
      final offline = BuatKlien((_) async => throw http.ClientException('offline'));
      await expectLater(offline.CariPenjualan('INV/X'), throwsA(isA<GalatJaringan>()));
    });
  });

  test('AmbilGambarQris mengembalikan bait gambar; 404 → GalatApi', () async {
    final klien = BuatKlien(
      (p) async => p.url.path.endsWith('/M1/gambar-qris')
          ? http.Response.bytes([137, 80, 78, 71], 200, headers: {'content-type': 'image/png'})
          : Json({
              'Galat': {'Kode': 'GambarQrisTidakAda', 'Pesan': 'Gambar QRIS belum diunggah.'},
            }, 404),
    );
    expect(await klien.AmbilGambarQris('M1'), [137, 80, 78, 71]);
    expect(() => klien.AmbilGambarQris('M2'), throwsA(isA<GalatApi>()));
  });

  test('PRD v1.46: Pajak[].Kategori (Ppn/Pbjt/Lainnya) dipetakan; nilai lain → null', () {
    PajakKelompokPos Urai(Object? kategori) => PajakKelompokPos.DariJson({
      'KodeJenisPajak': 'PbjtMakananMinuman',
      'DasarPengenaan': 'Subtotal',
      'Urutan': 1,
      'Kategori': kategori,
    });
    expect(Urai('Ppn').kategori, 'Ppn');
    expect(Urai('Pbjt').kategori, 'Pbjt');
    expect(Urai('Lainnya').kategori, 'Lainnya');
    expect(Urai('PPN').kategori, isNull);
    expect(Urai(3).kategori, isNull);
  });
}
