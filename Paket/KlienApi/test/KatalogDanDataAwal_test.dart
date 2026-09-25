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
}
