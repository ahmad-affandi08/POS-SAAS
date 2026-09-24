import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Data/BasisData/BasisDataKasir.dart';
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:kasir/Domain/GalatKasir.dart';
import 'package:kasir/Domain/Katalog/KatalogLokal.dart';
import 'package:kasir/Domain/Penjualan/Keranjang.dart';
import 'package:kasir/Domain/Penjualan/KonteksPenjualan.dart';
import 'package:kasir/Domain/Penjualan/LayananPenjualan.dart';
import 'package:kasir/Domain/Sesi/StafLokal.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Pendukung/KatalogUji.dart';
import '../../Pendukung/LingkunganUji.dart';

Matcher GalatDengan(String kode) => throwsA(isA<GalatKasir>().having((g) => g.kode, 'kode', kode));

void main() {
  late LingkunganUji u;
  late KatalogLokal katalog;
  late KonteksPenjualan k;
  late StafLokal rina;
  late StafLokal budi;

  Future<void> Siapkan({Map<String, Object?>? dataAwal, bool bukaShift = true}) async {
    await u.SiapkanAktif(dataAwal: dataAwal);
    await u.SiapkanKatalog();
    katalog = await u.MuatKatalog();
    k = await u.MuatKonteks();
    rina = await u.Staf('Rina Wulandari');
    budi = await u.Staf('Budi Santoso');
    if (bukaShift) {
      await u.shift.BukaShift(kasir: rina, kasAwal: Uang.DariBulat(500000));
    }
  }

  ProdukJual Produk(String uuid) => katalog.CariProduk(uuid)!;

  BarisMetodePembayaran Metode(String jenis) => k.metodePembayaran.firstWhere((m) => m.Jenis == jenis);

  /// 2× Es Kopi Susu Aren (kurang manis) + 1× Croissant = Rp 61.000 + PBJT 10% = Rp 67.100.
  Keranjang KeranjangContoh() {
    var keranjang = Keranjang.kosong;
    final gula = [PilihanTerpilih(uuid: UuidUji.gulaKurang, nama: 'Kurang manis', harga: Uang.Nol())];
    for (var i = 0; i < 2; i++) {
      keranjang = u.penjualan.TambahBaris(
        keranjang,
        u.penjualan.BuatBaris(katalog, k, Produk(UuidUji.kopiSusu), pilihan: gula),
        katalog,
        k,
      );
    }
    return u.penjualan.TambahBaris(keranjang, u.penjualan.BuatBaris(katalog, k, Produk(UuidUji.croissant)), katalog, k);
  }

  Future<Map<String, Object?>> AmbilDataOutbox(String uuid) async {
    final baris = (await u.db.select(u.db.outbox).get()).firstWhere((o) => o.Uuid == uuid);
    expect(baris.Jenis, 'Penjualan.Buat');
    return jsonDecode(baris.Data) as Map<String, Object?>;
  }

  setUp(() => u = LingkunganUji.Buat());
  tearDown(() => u.Tutup());

  group('katalog & keranjang', () {
    test('konteks dari data awal F-07b: outlet, perangkat, batas diskon, metode fase 1 saja', () async {
      await Siapkan(bukaShift: false);
      expect(k.kodeOutlet, 'SLB');
      expect(k.kodePerangkat, 'POS-001');
      expect(k.uuidOutlet, '01K50VT1ET0000000000000001');
      expect(k.batasDiskonManual, Decimal.parse('10'));
      expect(k.batasDiskonPenyetuju, Decimal.parse('30'));
      expect(k.metodePembayaran.map((m) => m.Jenis), ['Tunai', 'QrisStatis', 'Edc', 'Transfer', 'Ewallet']);
      expect(katalog.AmbilTampil().map((p) => p.nama), isNot(contains('Gula Aren Cair')));
    });

    test('produk sama (satuan & pilihan sama) → jumlah +1; pilihan berbeda → baris baru', () async {
      await Siapkan(bukaShift: false);
      final keranjang = KeranjangContoh();
      expect(keranjang.baris, hasLength(2));
      expect(keranjang.baris.first.jumlah, Kuantitas.DariBulat(2));

      final lain = u.penjualan.TambahBaris(
        keranjang,
        u.penjualan.BuatBaris(
          katalog,
          k,
          Produk(UuidUji.kopiSusu),
          pilihan: [PilihanTerpilih(uuid: UuidUji.gulaNormal, nama: 'Normal', harga: Uang.Nol())],
        ),
        katalog,
        k,
      );
      expect(lain.baris, hasLength(3));
    });

    test('pilihan wajib (min 1) & maksimal dicek; harga pilihan ditambahkan ke harga satuan', () async {
      await Siapkan(bukaShift: false);
      expect(() => u.penjualan.BuatBaris(katalog, k, Produk(UuidUji.kopiSusu)), GalatDengan('PilihanBelumLengkap'));
      expect(
        () => u.penjualan.BuatBaris(
          katalog,
          k,
          Produk(UuidUji.kopiSusu),
          pilihan: [
            PilihanTerpilih(uuid: UuidUji.gulaNormal, nama: 'Normal', harga: Uang.Nol()),
            PilihanTerpilih(uuid: UuidUji.gulaKurang, nama: 'Kurang manis', harga: Uang.Nol()),
          ],
        ),
        GalatDengan('PilihanTerlaluBanyak'),
      );

      final baris = u.penjualan.BuatBaris(
        katalog,
        k,
        Produk(UuidUji.kopiSusu),
        pilihan: [
          PilihanTerpilih(uuid: UuidUji.gulaNormal, nama: 'Normal', harga: Uang.Nol()),
          PilihanTerpilih(uuid: UuidUji.extraShot, nama: 'Extra shot', harga: Uang.DariBulat(5000)),
        ],
      );
      final hasil = u.penjualan.Hitung(Keranjang(baris: [baris]), k).hasil;
      expect(hasil.subtotal, Uang.DariBulat(23000));
    });

    test('produk induk varian, bahan baku, dan berpelacakan batch ditolak dengan pesan jelas', () async {
      await Siapkan(bukaShift: false);
      expect(() => u.penjualan.BuatBaris(katalog, k, Produk(UuidUji.kaos)), GalatDengan('ProdukTidakBisaDijual'));
      expect(() => u.penjualan.BuatBaris(katalog, k, Produk(UuidUji.gulaAren)), GalatDengan('ProdukTidakBisaDijual'));
      expect(() => u.penjualan.BuatBaris(katalog, k, Produk(UuidUji.susuUht)), GalatDengan('PelacakanBelumDidukung'));
      try {
        u.penjualan.BuatBaris(katalog, k, Produk(UuidUji.kaos));
      } on GalatKasir catch (galat) {
        expect(galat.pesan, contains('Pilih salah satu variannya'));
      }
    });

    test('harga lewat PenentuHarga: bertingkat saat jumlah naik, ganti satuan ke lusin, cari barcode & SKU', () async {
      await Siapkan(bukaShift: false);
      var keranjang = Keranjang(baris: [u.penjualan.BuatBaris(katalog, k, Produk(UuidUji.roti))]);
      expect(keranjang.baris.single.hargaSatuan, Uang.DariBulat(12000));

      keranjang = u.penjualan.UbahJumlah(keranjang, keranjang.baris.single.uuid, Kuantitas.DariBulat(10), katalog, k);
      expect(keranjang.baris.single.hargaSatuan, Uang.DariBulat(11000), reason: 'Harga bertingkat mulai 10 pcs.');

      final lusin = Produk(UuidUji.roti).satuan.firstWhere((s) => s.nama == 'Lusin');
      keranjang = u.penjualan.GantiSatuan(keranjang, keranjang.baris.single.uuid, lusin, katalog, k);
      expect(keranjang.baris.single.hargaSatuan, Uang.DariBulat(130000));
      expect(keranjang.baris.single.namaSatuan, 'Lusin');

      expect(
        () => u.penjualan.UbahJumlah(keranjang, keranjang.baris.single.uuid, Kuantitas.Dari('1.5'), katalog, k),
        GalatDengan('JumlahTidakValid'),
      );
      keranjang = u.penjualan.UbahJumlah(keranjang, keranjang.baris.single.uuid, Kuantitas.Nol(), katalog, k);
      expect(keranjang.CekKosong, isTrue);

      final barcode = katalog.CariKode(UuidUji.barcodeRotiLusin)!;
      expect(barcode.produk.uuid, UuidUji.roti);
      expect(barcode.satuan!.nama, 'Lusin');
      expect(katalog.CariKode('amr-01')!.produk.nama, 'Americano Panas');
      expect(katalog.CariKode('0000'), isNull);
    });
  });

  group('penentuan pajak (CLAUDE.md #12)', () {
    test('PBJT dipungut bila outlet memungut PBJT; PPN tidak dihitung bila bukan PKP', () async {
      await Siapkan(bukaShift: false);
      final roti = u.penjualan.BuatBaris(katalog, k, Produk(UuidUji.roti));
      final hitungan = u.penjualan.Hitung(Keranjang(baris: [...KeranjangContoh().baris, roti]), k);
      expect(hitungan.kodePajakBaris, [
        ['PbjtMakananMinuman'],
        ['PbjtMakananMinuman'],
        <String>[],
      ]);
      expect(hitungan.hasil.subtotal, Uang.DariBulat(73000));
      expect(hitungan.hasil.totalPajak, Uang.DariBulat(6100));
      expect(hitungan.hasil.totalAkhir, Uang.DariBulat(79100));
      expect(hitungan.peringatan, isEmpty);
    });

    test('PKP: PPN 12% DPP 11/12 dari tarif bertanggal; tidak memungut PBJT → PBJT tidak dihitung', () async {
      await Siapkan(
        bukaShift: false,
        dataAwal: DataAwalUji(
          profilPajak: {
            'Pkp': true,
            'PungutPbjt': false,
            'HargaTermasukPajak': false,
            'BiayaLayanan': {'Aktif': false, 'Persen': '0'},
          },
        ),
      );
      final roti = u.penjualan.BuatBaris(katalog, k, Produk(UuidUji.roti));
      final croissant = u.penjualan.BuatBaris(katalog, k, Produk(UuidUji.croissant));
      final hitungan = u.penjualan.Hitung(Keranjang(baris: [roti, croissant]), k);
      expect(hitungan.kodePajakBaris, [
        ['Ppn'],
        <String>[],
      ]);
      // DPP 12.000 × 11/12 = 11.000; PPN 12% = 1.320.
      expect(hitungan.hasil.totalPajak, Uang.DariBulat(1320));
      expect(hitungan.tarifDipakai['Ppn']!.tarif, '12.00');
    });

    test('tanpa tarif berlaku pada tanggal bisnis → pajak tidak dihitung + peringatan', () async {
      await Siapkan(
        bukaShift: false,
        dataAwal: DataAwalUji(
          tarifPajak: [
            {
              'KodeJenisPajak': 'PbjtMakananMinuman',
              'Tarif': '10.00',
              'PengaliDppPembilang': 1,
              'PengaliDppPenyebut': 1,
              'BerlakuMulai': '2027-01-01',
              'BerlakuSampai': null,
            },
            {
              'KodeJenisPajak': 'PbjtMakananMinuman',
              'Tarif': '11.00',
              'PengaliDppPembilang': 1,
              'PengaliDppPenyebut': 1,
              'BerlakuMulai': '2024-01-01',
              'BerlakuSampai': '2026-09-23',
            },
          ],
        ),
      );
      final hitungan = u.penjualan.Hitung(KeranjangContoh(), k);
      expect(hitungan.hasil.totalPajak, Uang.Nol());
      expect(hitungan.pajakDokumen, isEmpty);
      expect(hitungan.peringatan.single, contains('Tarif PBJT belum tersedia'));

      // Sehari sebelumnya tarif 11% masih berlaku (BerlakuSampai inklusif).
      u.jam = DateTime.utc(2026, 9, 23, 1);
      expect(u.penjualan.Hitung(KeranjangContoh(), k).hasil.totalPajak, Uang.Dari('6710.00'));
    });
  });

  group('BR-07.3 diskon manual', () {
    test('≤ batas manual boleh; di atas batas butuh penyetuju; di atas batas penyetuju hanya Pemilik', () async {
      await Siapkan(bukaShift: false);
      final dasar = Uang.DariBulat(100000);
      StatusDiskon Periksa(String persen, StafLokal kasir, {PenyetujuDiskon? penyetuju}) =>
          LayananPenjualan.PeriksaDiskon(
            dasar: dasar,
            diskon: DiskonManual.DariPersen(Decimal.parse(persen)),
            kasir: kasir,
            k: k,
            penyetuju: penyetuju,
          );

      expect(Periksa('10', rina), StatusDiskon.Boleh);
      expect(Periksa('15', rina), StatusDiskon.ButuhPenyetuju);
      expect(Periksa('35', rina), StatusDiskon.MelebihiBatas);
      final penyetujuBudi = PenyetujuDiskon(uuid: budi.uuid, nama: budi.nama, pemilik: false);
      expect(Periksa('15', rina, penyetuju: penyetujuBudi), StatusDiskon.Boleh);
      expect(Periksa('35', rina, penyetuju: penyetujuBudi), StatusDiskon.MelebihiBatas);
      expect(
        Periksa(
          '35',
          rina,
          penyetuju: const PenyetujuDiskon(uuid: 'P', nama: 'Pemilik', pemilik: true),
        ),
        StatusDiskon.Boleh,
      );
      expect(Periksa('20', budi), StatusDiskon.Boleh, reason: 'Budi berizin menyetujui diskon sendiri sampai 30%.');

      // Nominal: Rp 15.000 dari Rp 100.000 = 15%.
      expect(
        LayananPenjualan.PeriksaDiskon(
          dasar: dasar,
          diskon: DiskonManual.DariJumlah(Uang.DariBulat(15000)),
          kasir: rina,
          k: k,
        ),
        StatusDiskon.ButuhPenyetuju,
      );

      final sari = await u.Staf('Sari Lestari');
      expect(() => Periksa('5', sari), GalatDengan('TanpaIzin'));
    });

    test('bayar dengan diskon di atas batas tanpa penyetuju ditolak; dengan penyetuju tercatat di outbox', () async {
      await Siapkan();
      var keranjang = KeranjangContoh();
      keranjang = keranjang.Salin(diskonPesanan: () => DiskonManual.DariPersen(Decimal.parse('20')));
      final tunai = PembayaranMasukan(metode: Metode('Tunai'), jumlah: Uang.DariBulat(100000));

      await expectLater(
        u.penjualan.Bayar(keranjang: keranjang, pembayaran: [tunai], kasir: rina, k: k),
        GalatDengan('PersetujuanDiperlukan'),
      );

      keranjang = keranjang.Salin(
        penyetuju: () => PenyetujuDiskon(uuid: budi.uuid, nama: budi.nama, pemilik: false),
      );
      final hasil = await u.penjualan.Bayar(keranjang: keranjang, pembayaran: [tunai], kasir: rina, k: k);
      final data = await AmbilDataOutbox(hasil.uuid);
      expect(data['DiskonManualPesanan'], {'Persen': '20'});
      expect(data['UuidPenyetujuDiskon'], budi.uuid);
      // 61.000 − 12.200 = 48.800 + PBJT 4.880 = 53.680.
      expect((data['Ringkasan']! as Map<String, Object?>)['TotalAkhir'], '53680.00');
    });
  });

  group('bayar & simpan (BR-07.1, BR-07.4, BR-08.1, BR-08.6)', () {
    test('BR-07.4: tanpa shift terbuka tidak bisa menjual', () async {
      await Siapkan(bukaShift: false);
      await expectLater(
        u.penjualan.Bayar(
          keranjang: KeranjangContoh(),
          pembayaran: [PembayaranMasukan(metode: Metode('Tunai'), jumlah: Uang.DariBulat(100000))],
          kasir: rina,
          k: k,
        ),
        GalatDengan('ShiftTidakDitemukan'),
      );
    });

    test(
      'tunai: payload outbox Penjualan.Buat persis kontrak F-07b; penjualan, detail, pembayaran tersimpan',
      () async {
        await Siapkan();
        final shift = (await u.repositori.AmbilShiftAktif())!;
        final keranjang = KeranjangContoh();
        final hasil = await u.penjualan.Bayar(
          keranjang: keranjang,
          pembayaran: [PembayaranMasukan(metode: Metode('Tunai'), jumlah: Uang.DariBulat(100000))],
          kasir: rina,
          k: k,
        );

        expect(hasil.nomor, 'INV/SLB/260924/POS-001-0001');
        expect(hasil.totalAkhir, Uang.DariBulat(67100));
        expect(hasil.kembalian, Uang.DariBulat(32900));

        final data = await AmbilDataOutbox(hasil.uuid);
        expect(data.keys.toList(), [
          'UuidShift',
          'UuidPengguna',
          'Nomor',
          'Kanal',
          'DibuatPada',
          'HargaTermasukPajak',
          'PersenBiayaLayanan',
          'PembulatanTunai',
          'Pajak',
          'Baris',
          'DiskonManualPesanan',
          'UuidPenyetujuDiskon',
          'Pembayaran',
          'Ringkasan',
          'Catatan',
        ]);
        expect(data['UuidShift'], shift.Uuid);
        expect(data['UuidPengguna'], rina.uuid);
        expect(data['Nomor'], 'INV/SLB/260924/POS-001-0001');
        expect(data['Kanal'], 'BawaPulang');
        expect(data['DibuatPada'], '2026-09-24T01:00:00.000Z');
        expect(data['HargaTermasukPajak'], isFalse);
        expect(data['PersenBiayaLayanan'], '0');
        expect(data['PembulatanTunai'], isNull);
        expect(data['Pajak'], [
          {
            'Kode': 'PbjtMakananMinuman',
            'Tarif': '10.00',
            'PengaliDppPembilang': 1,
            'PengaliDppPenyebut': 1,
            'DasarPengenaan': 'SubtotalPlusLayanan',
          },
        ]);
        final baris = (data['Baris']! as List<Object?>).cast<Map<String, Object?>>();
        expect(baris.first, {
          'Uuid': keranjang.baris.first.uuid,
          'UuidProduk': UuidUji.kopiSusu,
          'UuidProdukSatuan': UuidUji.psKopiSusu,
          'Jumlah': '2.0000',
          'HargaSatuan': '18000.00',
          'HargaPilihan': '0.00',
          'Pilihan': [
            {'UuidPilihan': UuidUji.gulaKurang, 'Nama': 'Kurang manis', 'Harga': '0.00'},
          ],
          'HargaTermasukPajak': null,
          'KodePajak': ['PbjtMakananMinuman'],
          'DiskonManual': null,
          'Catatan': null,
        });
        final bayar = (data['Pembayaran']! as List<Object?>).single! as Map<String, Object?>;
        expect(bayar['UuidMetodePembayaran'], '01K5MTD0000000000000000001');
        expect(bayar['Jumlah'], '100000.00', reason: 'Tunai: Jumlah = uang diterima.');
        expect(bayar['Referensi'], isNull);
        expect(PembuatUlid.CekValid(bayar['Uuid']! as String), isTrue);
        expect(data['Ringkasan'], {
          'Subtotal': '61000.00',
          'TotalPajak': '6100.00',
          'Pembulatan': '0.00',
          'TotalAkhir': '67100.00',
          'Kembalian': '32900.00',
        });
        expect(PembuatUlid.CekValid(hasil.uuid), isTrue, reason: 'BR-07.6: UuidKlien ULID dari perangkat.');
        // Server memvalidasi semua Uuid sebagai ULID (Rincian F-07b).
        final semuaUuid = [
          data['UuidShift'],
          data['UuidPengguna'],
          for (final b in baris) ...[b['Uuid'], b['UuidProduk'], b['UuidProdukSatuan']],
          for (final b in baris)
            for (final p in (b['Pilihan']! as List<Object?>).cast<Map<String, Object?>>()) p['UuidPilihan'],
          bayar['Uuid'],
          bayar['UuidMetodePembayaran'],
        ];
        for (final nilai in semuaUuid) {
          expect(PembuatUlid.CekValid(nilai! as String), isTrue, reason: '$nilai harus ULID');
        }

        final penjualan = (await u.repositoriPenjualan.CariPenjualan(hasil.uuid))!;
        expect(penjualan.Status, 'Lunas');
        expect(penjualan.TanggalBisnis, '2026-09-24');
        expect(penjualan.TotalDibayar, '100000.00');
        final detail = await u.repositoriPenjualan.AmbilDetail(hasil.uuid);
        expect(detail.map((d) => d.TotalBaris), ['39600.00', '27500.00']);
        expect(detail.map((d) => d.JumlahPajak), ['3600.00', '2500.00']);
        expect((await u.repositoriPenjualan.AmbilPembayaran(hasil.uuid)).single.NamaMetode, 'Tunai');
      },
    );

    test('BR-07.1: nomor urut per perangkat per hari; hari berikutnya mulai dari 0001', () async {
      await Siapkan();
      Future<String> Jual() async => (await u.penjualan.Bayar(
        keranjang: KeranjangContoh(),
        pembayaran: [PembayaranMasukan(metode: Metode('Tunai'), jumlah: Uang.DariBulat(70000))],
        kasir: rina,
        k: k,
      )).nomor;

      expect(await Jual(), 'INV/SLB/260924/POS-001-0001');
      expect(await Jual(), 'INV/SLB/260924/POS-001-0002');
      u.jam = DateTime.utc(2026, 9, 25, 1);
      expect(await Jual(), 'INV/SLB/260925/POS-001-0001');
    });

    test('BR-08.1 split & BR-08.6 pembulatan hanya bagian tunai; referensi EDC tersimpan', () async {
      await Siapkan(dataAwal: DataAwalUji(pembulatanTunai: {'Kelipatan': 1000, 'Arah': 'Bawah'}));
      final keranjang = KeranjangContoh();

      // Semua tunai: 67.100 → 67.000.
      expect(u.penjualan.HitungTagihanTunai(keranjang, k, const []), Uang.DariBulat(67000));

      // QRIS 20.000 + sisa tunai: bagian tunai 47.100 → 47.000.
      final qris = PembayaranMasukan(metode: Metode('QrisStatis'), jumlah: Uang.DariBulat(20000));
      expect(u.penjualan.HitungTagihanTunai(keranjang, k, [qris]), Uang.DariBulat(47000));

      final edc = PembayaranMasukan(metode: Metode('Edc'), jumlah: Uang.DariBulat(20000), referensi: 'BCA · 123456');
      final hasil = await u.penjualan.Bayar(
        keranjang: keranjang,
        pembayaran: [
          edc,
          PembayaranMasukan(metode: Metode('Tunai'), jumlah: Uang.DariBulat(50000)),
        ],
        kasir: rina,
        k: k,
      );
      expect(hasil.totalAkhir, Uang.DariBulat(67000));
      expect(hasil.kembalian, Uang.DariBulat(3000));
      final data = await AmbilDataOutbox(hasil.uuid);
      expect(data['PembulatanTunai'], {'Kelipatan': 1000, 'Arah': 'Bawah'});
      expect((data['Ringkasan']! as Map<String, Object?>)['Pembulatan'], '-100.00');
      expect(((data['Pembayaran']! as List<Object?>).first! as Map<String, Object?>)['Referensi'], 'BCA · 123456');
    });

    test('pembayaran kurang, non-tunai melebihi total, dan tunai ganda ditolak', () async {
      await Siapkan();
      final keranjang = KeranjangContoh();
      await expectLater(
        u.penjualan.Bayar(
          keranjang: keranjang,
          pembayaran: [PembayaranMasukan(metode: Metode('Tunai'), jumlah: Uang.DariBulat(50000))],
          kasir: rina,
          k: k,
        ),
        GalatDengan('PembayaranKurang'),
      );
      await expectLater(
        u.penjualan.Bayar(
          keranjang: keranjang,
          pembayaran: [PembayaranMasukan(metode: Metode('Transfer'), jumlah: Uang.DariBulat(70000))],
          kasir: rina,
          k: k,
        ),
        GalatDengan('PembayaranMelebihiTotal'),
      );
      await expectLater(
        u.penjualan.Bayar(
          keranjang: keranjang,
          pembayaran: [
            PembayaranMasukan(metode: Metode('Tunai'), jumlah: Uang.DariBulat(50000)),
            PembayaranMasukan(metode: Metode('Tunai'), jumlah: Uang.DariBulat(50000)),
          ],
          kasir: rina,
          k: k,
        ),
        GalatDengan('TunaiGanda'),
      );
      expect(await u.db.select(u.db.penjualan).get(), isEmpty);
    });

    test('§18.3 no. 3: satu transaksi SQLite — outbox gagal → penjualan & nomor urut tidak tersimpan', () async {
      await Siapkan();
      final uuidTetap = u.penjualan.BuatUuid();
      final penjualan = LayananPenjualanUuidTetap(u, uuidTetap);
      // Entri outbox ber-Uuid sama sudah ada → insert outbox melanggar UNIQUE di akhir transaksi.
      await u.db
          .into(u.db.outbox)
          .insert(
            OutboxCompanion.insert(
              Uuid: uuidTetap,
              Jenis: 'Uji',
              Data: '{}',
              Status: StatusOutbox.tertunda,
              DibuatPada: u.jam,
              BerikutnyaPada: u.jam,
            ),
          );

      await expectLater(
        penjualan.Bayar(
          keranjang: KeranjangContoh(),
          pembayaran: [PembayaranMasukan(metode: Metode('Tunai'), jumlah: Uang.DariBulat(70000))],
          kasir: rina,
          k: k,
        ),
        throwsA(anything),
      );
      expect(await u.db.select(u.db.penjualan).get(), isEmpty);
      expect(await u.db.select(u.db.penjualanDetail).get(), isEmpty);
      expect(await u.db.select(u.db.penjualanPembayaran).get(), isEmpty);
      expect(await u.db.select(u.db.nomorUrutPenjualan).get(), isEmpty, reason: 'Nomor urut tidak terpakai.');
    });

    test('data awal tanpa kode outlet (server lama) → penjualan ditolak dengan pesan jelas', () async {
      final lama = DataAwalUji()..remove('Outlet');
      await Siapkan(dataAwal: lama);
      await u.repositori.SimpanPengaturan(KunciPengaturan.kodeOutlet, '');
      k = await u.MuatKonteks();
      await expectLater(
        u.penjualan.Bayar(
          keranjang: KeranjangContoh(),
          pembayaran: [PembayaranMasukan(metode: Metode('Tunai'), jumlah: Uang.DariBulat(70000))],
          kasir: rina,
          k: k,
        ),
        GalatDengan('DataAwalBelumLengkap'),
      );
    });
  });

  group('pesanan tertahan & riwayat', () {
    test('tahan → daftar lokal (tidak ke outbox) → buka mengembalikan keranjang utuh', () async {
      await Siapkan();
      final keranjang = KeranjangContoh().Salin(catatan: () => 'Meja teras');
      await u.penjualan.TahanPesanan(keranjang, rina, Uang.DariBulat(67100));

      final daftar = await u.db.select(u.db.pesananTertahan).get();
      expect(daftar.single.Label, startsWith('Es Kopi Susu Aren +1 · '));
      expect(await u.db.select(u.db.outbox).get(), hasLength(1), reason: 'Hanya Shift.Buka; pesanan tertahan lokal.');

      final dibuka = await u.penjualan.BukaPesanan(daftar.single.Uuid);
      expect(dibuka.baris.map((b) => b.nama), keranjang.baris.map((b) => b.nama));
      expect(dibuka.baris.first.pilihan.single.nama, 'Kurang manis');
      expect(dibuka.catatan, 'Meja teras');
      expect(await u.db.select(u.db.pesananTertahan).get(), isEmpty);
    });

    test('riwayat hari ini dengan status sinkron dari outbox', () async {
      await Siapkan();
      final hasil = await u.penjualan.Bayar(
        keranjang: KeranjangContoh(),
        pembayaran: [PembayaranMasukan(metode: Metode('QrisStatis'), jumlah: Uang.DariBulat(67100))],
        kasir: rina,
        k: k,
      );
      var riwayat = await u.repositoriPenjualan.PantauRiwayat('2026-09-24').first;
      expect(riwayat.single.status.name, 'BelumTerkirim');
      expect(riwayat.single.metode, ['QRIS']);

      await u.repositori.TandaiPerluTindakan(hasil.uuid, 'HitunganTidakCocok', 'Total berbeda.');
      riwayat = await u.repositoriPenjualan.PantauRiwayat('2026-09-24').first;
      expect(riwayat.single.status.name, 'PerluTindakan');

      await u.repositori.HapusOutbox([hasil.uuid]);
      riwayat = await u.repositoriPenjualan.PantauRiwayat('2026-09-24').first;
      expect(riwayat.single.status.name, 'Terkirim');
    });
  });
}

/// Layanan penjualan dengan UuidKlien tetap (untuk memaksa bentrok outbox).
LayananPenjualan LayananPenjualanUuidTetap(LingkunganUji u, String uuid) => LayananPenjualan(
  repositori: u.repositori,
  repositoriPenjualan: u.repositoriPenjualan,
  ulid: _UlidTetap(uuid),
  jam: () => u.jam,
);

class _UlidTetap extends PembuatUlid {
  _UlidTetap(this.nilai);

  final String nilai;
  var _pertama = true;

  @override
  String Buat() {
    if (_pertama) {
      _pertama = false;
      return nilai;
    }
    return super.Buat();
  }
}
