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
}
