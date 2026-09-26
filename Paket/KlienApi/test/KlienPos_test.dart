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

  test('F-16a cari pelanggan: kata < 3 tanpa permintaan; hasil tersamar', () async {
    final dikirim = <http.Request>[];
    final klien = BuatKlien((permintaan) async {
      dikirim.add(permintaan);
      return Json({
        'Pelanggan': [
          {
            'Uuid': 'P1',
            'Nama': 'Ani Rahmawati',
            'NoHp': '0812****7890',
            'KodeTier': 'GOLD',
            'NamaTier': 'Gold',
            'SaldoPoin': 125,
          },
          {'Uuid': 'P2', 'Nama': 'Anita (server lama)', 'NoHp': '0813****2222'},
        ],
      }, 200);
    });

    expect(await klien.CariPelanggan(' an '), isEmpty);
    expect(dikirim, isEmpty);
    final hasil = await klien.CariPelanggan('ani r');
    expect(dikirim.single.url.queryParameters['kata'], 'ani r');
    expect(hasil.first.nama, 'Ani Rahmawati');
    expect(hasil.first.noHpSamar, '0812****7890');
    expect(hasil.first.kodeTier, 'GOLD');
    expect(hasil.first.saldoPoin, 125);
    expect(hasil.last.kodeTier, isNull);
    expect(hasil.last.saldoPoin, 0);
  });

  test(
    'F-16c bagian 3: data promo pelanggan (hari lahir, jumlah transaksi, pemakaian) + tanggal bisnis acuannya',
    () async {
      final klien = BuatKlien(
        (permintaan) async => Json({
          'Pelanggan': [
            {
              'Uuid': 'P1',
              'Nama': 'Ani Rahmawati',
              'NoHp': '0812****7890',
              'HariLahir': '09-26',
              'JumlahTransaksi': 3,
              'PemakaianPromo': {
                'PR-HARIAN': {'Hari': 1, 'Promo': 4},
              },
            },
            {'Uuid': 'P2', 'Nama': 'Anita (server lama)', 'NoHp': '0813****2222', 'PemakaianPromo': <Object?>[]},
          ],
          'TanggalBisnis': '2026-09-26',
        }, 200),
      );

      final hasil = await klien.CariPelanggan('ani');
      expect(hasil.first.hariLahir, '09-26');
      expect(hasil.first.jumlahTransaksi, 3);
      expect(hasil.first.pemakaianPromo['PR-HARIAN'], (hari: 1, promo: 4));
      expect(hasil.first.pemakaianPada, '2026-09-26');
      expect(hasil.last.hariLahir, isNull);
      expect(hasil.last.jumlahTransaksi, isNull);
      expect(hasil.last.pemakaianPromo, isEmpty);
    },
  );

  test('F-16c bagian 3: promo diminta dengan voucher=1&lanjutan=1', () async {
    final dikirim = <http.Request>[];
    final klien = BuatKlien((permintaan) async {
      dikirim.add(permintaan);
      return Json({'ModeResolusi': 'Terbaik', 'Promo': <Object?>[], 'WaktuServer': '2026-09-26T03:00:00Z'}, 200);
    });

    await klien.AmbilPromo();
    expect(dikirim.single.url.queryParameters, {'voucher': '1', 'lanjutan': '1'});
  });

  test('F-16b saldo poin: jalur per pelanggan, aturan tukar', () async {
    final dikirim = <http.Request>[];
    final klien = BuatKlien((permintaan) async {
      dikirim.add(permintaan);
      return Json({
        'Pelanggan': {'Uuid': 'P1', 'SaldoPoin': 120},
        'TukarPoin': {'Berlaku': true, 'NilaiTukarPoin': '100.00', 'MinimalTukarPoin': 10},
      }, 200);
    });

    final saldo = await klien.AmbilSaldoPoin('P1');
    expect(dikirim.single.url.path, endsWith('/api/pos/v1/pelanggan/P1/poin'));
    expect(saldo.saldoPoin, 120);
    expect(saldo.berlaku, isTrue);
    expect(saldo.nilaiTukarPoin, '100.00');
    expect(saldo.minimalTukarPoin, 10);
  });

  test('F-16c promo: daftar promo aktif + mode resolusi, definisi dibawa apa adanya', () async {
    final klien = BuatKlien((permintaan) async {
      expect(permintaan.url.path, endsWith('/api/pos/v1/promo'));
      expect(permintaan.url.queryParameters['voucher'], '1');
      return Json({
        'ModeResolusi': 'PrioritasKetat',
        'Promo': [
          {
            'Uuid': 'PR1',
            'Kode': 'KOPI10',
            'Nama': 'Diskon 10% kopi',
            'Prioritas': 5,
            'Eksklusif': false,
            'MulaiPada': '2026-10-01T00:00:00Z',
            'SelesaiPada': null,
            'KuotaTersisa': 12,
            'Definisi': {
              'Aksi': {'Jenis': 'DiskonPersenItem', 'Persen': '10'},
            },
          },
        ],
      }, 200);
    });

    final data = await klien.AmbilPromo();
    expect(data.modeResolusi, 'PrioritasKetat');
    expect(data.promo.single.kode, 'KOPI10');
    expect(data.promo.single.kuotaTersisa, 12);
    expect(data.promo.single.mulaiPada, DateTime.utc(2026, 10));
    expect(data.promo.single.selesaiPada, isNull);
    expect(DataPromoPos.DariJson(data.KeJson()).promo.single.definisi['Aksi'], {
      'Jenis': 'DiskonPersenItem',
      'Persen': '10',
    });
  });

  test('F-16c bagian 2 voucher: pesan mengirim kode & penjualan, promo ikut; habis → GalatApi VoucherHabis', () async {
    final dikirim = <http.Request>[];
    var habis = false;
    final klien = BuatKlien((permintaan) async {
      dikirim.add(permintaan);
      if (habis) {
        return Json({
          'Galat': {'Kode': 'VoucherHabis', 'Pesan': 'Voucher HEMAT10K sudah habis dipakai.'},
        }, 409);
      }
      if (permintaan.url.path.endsWith('/lepas')) {
        return http.Response('', 204);
      }
      return Json({
        'Voucher': {'Kode': 'HEMAT10K', 'UuidPromo': 'PR2', 'DipesanSampai': '2026-10-05T06:00:00Z', 'SisaPakai': 0},
        'Promo': {
          'Uuid': 'PR2',
          'Kode': 'VCR-HEMAT',
          'Nama': 'Voucher hemat',
          'Prioritas': 0,
          'Eksklusif': false,
          'MulaiPada': null,
          'SelesaiPada': null,
          'KuotaTersisa': null,
          'Definisi': {
            'WajibVoucher': true,
            'Aksi': {'Jenis': 'DiskonTetapPesanan', 'Jumlah': '10000'},
          },
        },
      }, 200);
    });

    final voucher = await klien.PesanVoucher('hemat10k', 'J1');
    expect(dikirim.single.url.path, endsWith('/api/pos/v1/voucher/pesan'));
    expect(jsonDecode(dikirim.single.body), {'Kode': 'hemat10k', 'UuidPenjualan': 'J1'});
    expect(voucher.kode, 'HEMAT10K');
    expect(voucher.uuidPromo, 'PR2');
    expect(voucher.sisaPakai, 0);
    expect(voucher.dipesanSampai, DateTime.utc(2026, 10, 5, 6));
    expect(voucher.promo.definisi['WajibVoucher'], isTrue);

    await klien.LepasVoucher('HEMAT10K', 'J1');
    expect(dikirim.last.url.path, endsWith('/api/pos/v1/voucher/lepas'));

    habis = true;
    await expectLater(
      klien.PesanVoucher('HEMAT10K', 'J2'),
      throwsA(isA<GalatApi>().having((g) => g.kode, 'kode', 'VoucherHabis')),
    );
  });

  test('F-12 bagian 2: cari pre-order membawa baris, sisa DP, pelanggan, dan metode Uang Muka', () async {
    final klien = BuatKlien((permintaan) async {
      expect(permintaan.url.path, endsWith('/api/pos/v1/pesanan-penjualan'));
      expect(permintaan.url.queryParameters['kata'], 'ratna');
      return Json({
        'Pesanan': [
          {
            'Uuid': 'PO1',
            'Nomor': 'SO/SLO/260925/POS-001-0001',
            'Status': 'Siap',
            'TanggalAmbil': '2026-09-28',
            'Catatan': null,
            'TotalPesanan': '77000.00',
            'UangMuka': '50000.00',
            'SisaUangMuka': '50000.00',
            'Pelanggan': {
              'Uuid': 'PL1',
              'Nama': 'Ibu Ratna',
              'NoHp': '0813****0077',
              'KodeTier': null,
              'NamaTier': null,
            },
            'Baris': [
              {
                'Uuid': 'B1',
                'UuidProduk': 'P1',
                'UuidProdukSatuan': null,
                'NamaProduk': 'Kue Cokelat',
                'Jumlah': '2.0000',
                'HargaSatuan': '38500.00',
                'HargaPilihan': '0.00',
                'Pilihan': <Object?>[],
                'Catatan': 'Krim vanila',
              },
            ],
          },
        ],
        'MetodeUangMuka': {'Uuid': 'MUM', 'Nama': 'Uang muka (DP)'},
      }, 200);
    });

    final hasil = await klien.CariPesananPenjualan(' ratna ');
    final p = hasil.pesanan.single;
    expect(p.sisaUangMuka, '50000.00');
    expect(p.pelanggan?['Nama'], 'Ibu Ratna');
    expect(p.baris.single.catatan, 'Krim vanila');
    expect(hasil.uuidMetodeUangMuka, 'MUM');
  });

  test(
    'P-10 konfigurasi-aplikasi: header X-Outbox-Tertunda, versi, catatan rilis, dan flag fitur (kunci bertitik)',
    () async {
      late http.Request dikirim;
      final klien = KlienPos(
        alamatDasar: Uri.parse('https://kasir.contoh.id/'),
        versiAplikasi: '1.4.0',
        ambilToken: () => 'Tkn',
        ambilJumlahOutbox: () => 3,
        klien: MockClient((permintaan) async {
          dikirim = permintaan;
          return Json({
            'Aplikasi': {
              'VersiSaatIni': '1.4.0',
              'VersiTerbaru': '1.5.0',
              'VersiMinimal': '1.5.0',
              'TautanUnduh': 'https://unduh.payou.id/kasir.apk',
              'CatatanRilis': 'Cetak struk Bluetooth.',
              'AdaPembaruan': true,
              'WajibPembaruan': true,
            },
            'FlagFitur': {'pos.mode-meja': false, 'kasir.struk-digital': true, 'rusak': 'ya'},
          }, 200);
        }),
      );

      final konfigurasi = await klien.AmbilKonfigurasiAplikasi();

      expect(dikirim.url.path, '/api/pos/v1/konfigurasi-aplikasi');
      expect(dikirim.headers['X-Outbox-Tertunda'], '3');
      expect(konfigurasi.wajibPembaruan, isTrue);
      expect(konfigurasi.versiTerbaru, '1.5.0');
      expect(konfigurasi.catatanRilis, 'Cetak struk Bluetooth.');
      expect(konfigurasi.flagFitur, {'pos.mode-meja': false, 'kasir.struk-digital': true});
      expect(konfigurasi.CekFlag('pos.mode-meja'), isFalse);
      expect(konfigurasi.CekFlag('tidak.ada'), isTrue);
    },
  );

  test(
    'P-10 server lama tanpa FlagFitur & versi: nilai bawaan aman; tanpa ambilJumlahOutbox header tidak dikirim',
    () async {
      late http.Request dikirim;
      final klien = BuatKlien((permintaan) async {
        dikirim = permintaan;
        return Json({'Aplikasi': <String, Object?>{}}, 200);
      });

      final konfigurasi = await klien.AmbilKonfigurasiAplikasi();

      expect(dikirim.headers.containsKey('X-Outbox-Tertunda'), isFalse);
      expect(konfigurasi.wajibPembaruan, isFalse);
      expect(konfigurasi.adaPembaruan, isFalse);
      expect(konfigurasi.flagFitur, isEmpty);
    },
  );
}
