import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:test/test.dart';

KlienPos BuatKlien(Future<http.Response> Function(http.Request permintaan) penangan, {String? token = 'Tkn'}) =>
    KlienPos(
      alamatDasar: Uri.parse('https://kasir.contoh.id/'),
      versiAplikasi: '0.1.0',
      ambilToken: () => token,
      klien: MockClient(penangan),
    );

http.Response Json(Object isi, int status) =>
    http.Response(jsonEncode(isi), status, headers: {'content-type': 'application/json'});

void main() {
  test('aktivasi tanpa token; kode dirapikan; respons dipetakan termasuk kunci PIN offline', () async {
    late http.Request dikirim;
    final klien = BuatKlien((permintaan) async {
      dikirim = permintaan;
      return Json({
        'TokenPerangkat': '12|rahasia',
        'KunciPinOffline': 'a2V5',
        'Perangkat': {'Uuid': 'P1', 'Kode': 'POS-001', 'Nama': 'Kasir Depan'},
        'Outlet': {'Uuid': 'O1', 'Nama': 'Kopi Senja Solo Baru'},
        'Tenant': {'Uuid': 'T1', 'Nama': 'Kopi Senja'},
      }, 201);
    });

    final hasil = await klien.AktifkanPerangkat(kode: ' ab12cd34 ', platform: 'Android');

    expect(dikirim.url.toString(), 'https://kasir.contoh.id/api/pos/v1/perangkat/aktivasi');
    expect(dikirim.headers.containsKey('Authorization'), isFalse);
    expect(dikirim.headers['X-Versi-Aplikasi'], '0.1.0');
    expect(jsonDecode(dikirim.body), containsPair('Kode', 'AB12CD34'));
    expect(hasil.tokenPerangkat, '12|rahasia');
    expect(hasil.kunciPinOffline, 'a2V5');
    expect(hasil.kodePerangkat, 'POS-001');
    expect(hasil.namaUsaha, 'Kopi Senja');
  });

  test('sinkron memakai device token dan memetakan hasil per item', () async {
    late http.Request dikirim;
    final klien = BuatKlien((permintaan) async {
      dikirim = permintaan;
      return Json({
        'Hasil': [
          {'Uuid': 'A', 'Jenis': 'Shift.Buka', 'Status': 'Diterima', 'Galat': null},
          {
            'Uuid': 'B',
            'Jenis': 'MutasiKas.Catat',
            'Status': 'Ditolak',
            'Galat': {'Kode': 'PersetujuanDiperlukan', 'Pesan': 'Wajib disetujui'},
          },
        ],
      }, 200);
    });

    final hasil = await klien.KirimSinkron([
      const ItemOutbox(jenis: 'Shift.Buka', uuid: 'A', data: {'KasAwal': '500000.00'}),
      const ItemOutbox(jenis: 'MutasiKas.Catat', uuid: 'B', data: {}),
    ]);

    expect(dikirim.headers['Authorization'], 'Bearer Tkn');
    expect((jsonDecode(dikirim.body) as Map<String, Object?>)['Item'], hasLength(2));
    expect(hasil.map((h) => h.status), [StatusItemSinkron.Diterima, StatusItemSinkron.Ditolak]);
    expect(hasil[1].kodeGalat, 'PersetujuanDiperlukan');
  });

  test(
    'galat 4xx menjadi GalatApi (format Galat & errors Laravel); 5xx dan putus jaringan menjadi GalatJaringan',
    () async {
      final dicabut = BuatKlien(
        (_) async => Json({
          'Galat': {'Kode': 'PerangkatDicabut', 'Pesan': 'Dicabut'},
        }, 403),
      );
      await expectLater(
        dicabut.AmbilDataAwal(),
        throwsA(isA<GalatApi>().having((g) => g.CekPerangkatDitolak(), 'ditolak', isTrue)),
      );

      final validasi = BuatKlien(
        (_) async => Json({
          'message': 'x',
          'errors': {
            'Pin': ['PIN harus 6 angka.'],
          },
        }, 422),
      );
      await expectLater(
        validasi.MasukPin(uuidPengguna: 'U', pin: '1'),
        throwsA(
          isA<GalatApi>()
              .having((g) => g.pesan, 'pesan', 'PIN harus 6 angka.')
              .having((g) => g.bidang, 'bidang', 'Pin'),
        ),
      );

      final rusak = BuatKlien((_) async => http.Response('oops', 502));
      await expectLater(rusak.AmbilDataAwal(), throwsA(isA<GalatJaringan>()));

      final putus = BuatKlien((_) async => throw http.ClientException('putus'));
      await expectLater(putus.AmbilDataAwal(), throwsA(isA<GalatJaringan>()));
    },
  );

  test('data awal: staf, izin, PIN terbungkus, kategori, parameter Argon2id', () async {
    final klien = BuatKlien(
      (_) async => Json({
        'Pengaturan': {'BatasKasKeluar': '200000.00', 'ShiftBersama': false},
        'KategoriKas': [
          {'Uuid': 'K1', 'Nama': 'Beli es batu', 'Jenis': 'Keluar'},
        ],
        'Staf': [
          {
            'Uuid': 'S1',
            'Nama': 'Rina',
            'Pemilik': false,
            'Izin': ['penjualan.buat'],
            'PinDiatur': true,
            'Pin': {'Garam': 'g', 'Nonce': 'n', 'Sandi': 's'},
          },
          {'Uuid': 'S2', 'Nama': 'Budi', 'Pemilik': true, 'Izin': <String>[], 'PinDiatur': false, 'Pin': null},
        ],
        'PinOffline': {
          'Tersedia': true,
          'Parameter': {'Iterasi': 2, 'MemoriKiB': 19456, 'Paralelisme': 1, 'Panjang': 32},
          'BatasSalah': 5,
          'MenitKunci': 5,
        },
        'WaktuServer': '2026-09-24T01:00:00Z',
      }, 200),
    );

    final data = await klien.AmbilDataAwal();

    expect(data.batasKasKeluar, '200000.00');
    expect(data.staf.first.PunyaIzin('penjualan.buat'), isTrue);
    expect(data.staf.first.pin?.garam, 'g');
    expect(data.staf.last.pin, isNull);
    expect(data.staf.last.PunyaIzin('kas.keluar.setujui'), isTrue);
    expect(data.parameterPin.memoriKiB, 19456);
    expect(data.kategoriKas.single.jenis, 'Keluar');
  });

  test('mode meja: data meja, snapshot pesanan terbuka dengan ETag (304 = null), kunci bayar 409', () async {
    final dikirim = <http.Request>[];
    final klien = BuatKlien((permintaan) async {
      dikirim.add(permintaan);
      final jalur = permintaan.url.path;
      if (jalur.endsWith('/meja')) {
        return Json({
          'ModeMejaAktif': true,
          'Area': [
            {'Uuid': 'A1', 'Nama': 'Teras', 'Urutan': 1},
          ],
          'Meja': [
            {'Uuid': 'M7', 'Nama': '7', 'UuidArea': 'A1', 'Kapasitas': 4, 'Bentuk': 'Bundar', 'Urutan': 0},
          ],
          'StasiunDapur': [
            {'Uuid': 'S1', 'Nama': 'Bar'},
          ],
          'UuidStasiunBawaan': 'S1',
        }, 200);
      }
      if (jalur.endsWith('/pesanan-terbuka')) {
        if (permintaan.headers['If-None-Match'] == '"abc"') {
          return http.Response('', 304);
        }
        return http.Response(
          jsonEncode({
            'Pesanan': [
              {
                'Uuid': 'P1',
                'Nomor': 'OB/JKT1/260925/K01-0001',
                'UuidMeja': 'M7',
                'NamaMeja': '7',
                'JumlahTamu': 4,
                'DibukaPada': '2026-09-25T10:00:00Z',
                'DikunciBayar': false,
                'Baris': [
                  {
                    'Uuid': 'B1',
                    'UuidProduk': 'PR1',
                    'NamaProduk': 'Es Kopi Susu',
                    'Jumlah': '2.0000',
                    'HargaSatuan': '25000.00',
                    'HargaPilihan': '0.00',
                    'Pilihan': <Object>[],
                    'Ronde': 1,
                    'Dibatalkan': false,
                    'DikirimKeDapur': true,
                    'StatusDapur': 'Dimasak',
                  },
                ],
              },
            ],
            'Ditutup': [
              {'Uuid': 'P0', 'Status': 'Dibayar'},
            ],
          }),
          200,
          headers: {'content-type': 'application/json', 'etag': '"abc"'},
        );
      }
      return Json({
        'Galat': {'Kode': 'PesananSedangDibayar', 'Pesan': 'Sedang dibayar di perangkat lain.'},
      }, 409);
    });

    final meja = await klien.AmbilMeja();
    expect(meja.modeMejaAktif, isTrue);
    expect(meja.meja.single.uuidArea, 'A1');
    expect(meja.uuidStasiunBawaan, 'S1');

    final snapshot = await klien.AmbilPesananTerbuka();
    expect(snapshot!.etag, '"abc"');
    expect(snapshot.pesanan.single.baris.single.statusDapur, 'Dimasak');
    expect(snapshot.pesanan.single.baris.single.jumlah, '2.0000');
    expect(snapshot.pesanan.single.baris.single.uuidProduk, 'PR1');
    expect(snapshot.pesanan.single.baris.single.uuidProdukSatuan, isNull);
    expect(snapshot.ditutup.single.uuid, 'P0');
    expect(await klien.AmbilPesananTerbuka(etag: '"abc"'), isNull);

    await expectLater(
      klien.KunciBayar('P1'),
      throwsA(isA<GalatApi>().having((g) => g.kode, 'kode', 'PesananSedangDibayar')),
    );
    expect(dikirim.last.method, 'POST');
    expect(dikirim.last.url.path, '/api/pos/v1/pesanan-terbuka/P1/kunci-bayar');
  });

  test('KDS: tiket per stasiun lewat stasiun[] dan ubah status', () async {
    final dikirim = <http.Request>[];
    final klien = BuatKlien((permintaan) async {
      dikirim.add(permintaan);
      if (permintaan.method == 'GET') {
        return Json({
          'Tiket': [
            {
              'Uuid': 'T1',
              'UuidStasiun': 'S2',
              'NomorDokumen': 'OB/JKT1/260925/K01-0001',
              'NamaMeja': '7',
              'Ronde': 2,
              'Status': 'Antre',
              'DikirimPada': '2026-09-25T10:05:00Z',
              'Baris': [
                {
                  'UuidBaris': 'B1',
                  'NamaProduk': 'Nasi Goreng Kampung',
                  'Jumlah': '1.0000',
                  'Pilihan': ['Pedas'],
                  'Catatan': 'Tanpa kecap',
                  'Dibatalkan': false,
                },
              ],
            },
          ],
          'WaktuServer': '2026-09-25T10:15:00Z',
        }, 200);
      }
      return Json({'Uuid': 'T1', 'Status': 'Dimasak'}, 200);
    });

    final daftar = await klien.AmbilTiketDapur(stasiun: ['S2']);
    expect(dikirim.first.url.queryParametersAll['stasiun[]'], ['S2']);
    expect(daftar.tiket.single.ronde, 2);
    expect(daftar.tiket.single.baris.single.pilihan, ['Pedas']);
    expect(daftar.waktuServer, DateTime.utc(2026, 9, 25, 10, 15));
    expect(await klien.UbahStatusTiket('T1', 'Dimasak'), 'Dimasak');
    expect(jsonDecode(dikirim.last.body), {'Status': 'Dimasak'});
  });
}
