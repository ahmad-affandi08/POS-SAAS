import 'dart:convert';

import 'package:drift/drift.dart' show OrderingTerm;
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:kasir/Domain/GalatKasir.dart';
import 'package:kasir/Domain/Penjualan/Keranjang.dart';
import 'package:kasir/Domain/Penjualan/LayananPenjualan.dart';
import 'package:kasir/Domain/Penjualan/LayananPesananOnline.dart';
import 'package:kasir/Domain/Penjualan/LayananPreOrder.dart';
import 'package:kasir/Domain/Sesi/StafLokal.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Pendukung/KatalogUji.dart';
import '../../Pendukung/LingkunganUji.dart';

/// F-17 di perangkat: pesanan toko online dimuat ke keranjang kanal `Online` dengan harga saat dipesan; pesanan yang
/// sudah dibayar di muka membawa uang mukanya sehingga `Penjualan.Buat` memakai metode Uang Muka dan merujuk
/// `UuidPesananOnline` (bukan `UuidPesananPenjualan`). Pesanan berongkir ditolak karena `Penjualan` belum punya
/// baris biaya kirim.
void main() {
  late LingkunganUji u;
  late StafLokal rina;

  /// @param ongkir dan @param sisaUangMuka string desimal seperti kiriman server.
  Map<String, Object?> Pesanan({
    String status = 'Siap',
    String jenisPemenuhan = 'AmbilSendiri',
    String ongkir = '0.00',
    String sisaUangMuka = '28000.00',
    bool sudahDibayar = true,
  }) => {
    'Uuid': '01K5PESANANONLINE000000001',
    'Nomor': 'ON/SLB/260930-0001',
    'NamaPelanggan': 'Bu Ratna',
    'JenisPemenuhan': jenisPemenuhan,
    'MetodePembayaran': sudahDibayar ? 'QrisOnline' : 'BayarSaatAmbil',
    'Status': status,
    'Subtotal': '28000.00',
    'Ongkir': ongkir,
    'Total': '28000.00',
    'SudahDibayar': sudahDibayar,
    'SisaUangMuka': sisaUangMuka,
    'Catatan': 'Tolong tanpa gula',
    'DibuatPada': '2026-09-30T02:00:00Z',
    'Baris': [
      {
        'UuidProduk': UuidUji.americano,
        'UuidProdukSatuan': null,
        'NamaProduk': 'Americano Panas',
        'Jumlah': '2.0000',
        'HargaSatuan': '14000.00',
        'HargaPilihan': '0.00',
        'Pilihan': <Object?>[],
        'Catatan': null,
      },
    ],
  };

  Map<String, Object?> Balasan(Map<String, Object?> pesanan, {bool adaMetode = true}) => {
    'Pesanan': [pesanan],
    'MetodeUangMuka': adaMetode ? {'Uuid': '01K5METODEUANGMUKA00000001', 'Nama': 'Uang muka (DP)'} : null,
  };

  Future<Map<String, Object?>> BacaOutboxTerakhir() async {
    final outbox = await (u.db.select(u.db.outbox)..orderBy([(o) => OrderingTerm.asc(o.Id)])).get();
    return jsonDecode(outbox.last.Data) as Map<String, Object?>;
  }

  setUp(() async {
    u = LingkunganUji.Buat();
    await u.SiapkanAktif();
    rina = await u.Staf('Rina Wulandari');
    await u.SiapkanKatalog();
    await u.shift.BukaShift(kasir: rina, kasAwal: Uang.DariBulat(500000));
  });
  tearDown(() => u.Tutup());

  test('offline ditolak jelas, lalu daftar pesanan aktif dibaca dari server', () async {
    u.server.penangan = (_) async => throw http.ClientException('offline');
    await expectLater(
      u.pesananOnline.AmbilAktif(),
      throwsA(isA<GalatKasir>().having((g) => g.kode, 'kode', 'PerluOnline')),
    );

    u.server.penangan = (_) async => JsonUji(Balasan(Pesanan()));
    final hasil = await u.pesananOnline.AmbilAktif();
    expect(hasil.pesanan.single.nomor, 'ON/SLB/260930-0001');
    expect(hasil.pesanan.single.sudahDibayar, isTrue);
    expect(hasil.uuidMetodeUangMuka, '01K5METODEUANGMUKA00000001');
  });

  test('pesanan berbayar dimuat ke keranjang Online dan ditagih lewat metode Uang muka', () async {
    final katalog = await u.MuatKatalog();
    final k = await u.MuatKonteks();
    u.server.penangan = (_) async => JsonUji(Balasan(Pesanan()));
    final hasil = await u.pesananOnline.AmbilAktif();

    final keranjang = u.pesananOnline.MuatKeKeranjang(hasil.pesanan.single, hasil, katalog, k);
    expect(keranjang.kanal, KanalPenjualan.Online);
    expect(keranjang.baris.single.hargaSatuan, Uang.DariBulat(14000), reason: 'Harga saat dipesan.');
    expect(keranjang.baris.single.jumlah, Kuantitas.DariBulat(2));
    expect(keranjang.praPesan?.sumber, SumberUangMuka.pesananOnline);
    expect(keranjang.praPesan?.sisaUangMuka, Uang.DariBulat(28000));
    // Draf keranjang yang disimpan & dibaca ulang tidak boleh berubah sumber uang mukanya.
    expect(Keranjang.DariJson(keranjang.KeJson()).praPesan?.sumber, SumberUangMuka.pesananOnline);

    final uangMuka = LayananPreOrder.MetodeUangMuka(keranjang.praPesan!);
    final total = u.penjualan.Hitung(keranjang, k).hasil.totalAkhir;
    // Uang muka yang dipakai tidak boleh melebihi sisa yang dicatat server, meski masih di bawah total belanja.
    await expectLater(
      u.penjualan.Bayar(
        keranjang: keranjang.Salin(
          praPesan: () => PraPesananKeranjang(
            uuid: keranjang.praPesan!.uuid,
            nomor: keranjang.praPesan!.nomor,
            sisaUangMuka: Uang.DariBulat(10000),
            uuidMetode: keranjang.praPesan!.uuidMetode,
            namaMetode: keranjang.praPesan!.namaMetode,
            sumber: SumberUangMuka.pesananOnline,
          ),
        ),
        pembayaran: [PembayaranMasukan(metode: uangMuka, jumlah: total)],
        kasir: rina,
        k: k,
      ),
      throwsA(isA<GalatKasir>().having((g) => g.kode, 'kode', 'UangMukaMelebihiSisa')),
    );

    // Uang muka menutup sebagian; sisanya ditagih tunai seperti pengambilan pre-order.
    final sisa = keranjang.praPesan!.sisaUangMuka;
    final tunai = k.metodePembayaran.firstWhere((m) => m.Jenis == 'Tunai');
    await u.penjualan.Bayar(
      keranjang: keranjang,
      pembayaran: [
        PembayaranMasukan(metode: uangMuka, jumlah: sisa),
        PembayaranMasukan(metode: tunai, jumlah: total.Kurangi(sisa)),
      ],
      kasir: rina,
      k: k,
    );
    final data = await BacaOutboxTerakhir();
    expect(data['UuidPesananOnline'], '01K5PESANANONLINE000000001');
    expect(data.containsKey('UuidPesananPenjualan'), isFalse, reason: 'Satu sumber uang muka saja.');
    expect(data['Kanal'], 'Online');
  });

  test('pesanan COD tanpa uang muka tetap bisa ditagih penuh di kasir', () async {
    final katalog = await u.MuatKatalog();
    final k = await u.MuatKonteks();
    u.server.penangan = (_) async =>
        JsonUji(Balasan(Pesanan(sudahDibayar: false, sisaUangMuka: '0.00'), adaMetode: false));
    final hasil = await u.pesananOnline.AmbilAktif();

    final keranjang = u.pesananOnline.MuatKeKeranjang(hasil.pesanan.single, hasil, katalog, k);
    expect(keranjang.praPesan?.sisaUangMuka, Uang.Nol());
    final tunai = k.metodePembayaran.firstWhere((m) => m.Jenis == 'Tunai');
    final total = u.penjualan.Hitung(keranjang, k).hasil.totalAkhir;
    await u.penjualan.Bayar(
      keranjang: keranjang,
      pembayaran: [PembayaranMasukan(metode: tunai, jumlah: total)],
      kasir: rina,
      k: k,
    );
    final data = await BacaOutboxTerakhir();
    expect(data['UuidPesananOnline'], '01K5PESANANONLINE000000001');
  });

  test('pesanan berongkir dan yang belum Siap ditolak dengan alasan yang bisa dibaca kasir', () async {
    final katalog = await u.MuatKatalog();
    final k = await u.MuatKonteks();

    u.server.penangan = (_) async => JsonUji(Balasan(Pesanan(jenisPemenuhan: 'Kirim', ongkir: '12000.00')));
    final berongkir = await u.pesananOnline.AmbilAktif();
    expect(LayananPesananOnline.AlasanBelumBisaDitagih(berongkir.pesanan.single), contains('Ongkir'));
    expect(
      () => u.pesananOnline.MuatKeKeranjang(berongkir.pesanan.single, berongkir, katalog, k),
      throwsA(isA<GalatKasir>().having((g) => g.kode, 'kode', 'PesananBelumBisaDitagih')),
    );

    u.server.penangan = (_) async => JsonUji(Balasan(Pesanan(status: 'Dikonfirmasi')));
    final belumSiap = await u.pesananOnline.AmbilAktif();
    expect(LayananPesananOnline.AlasanBelumBisaDitagih(belumSiap.pesanan.single), contains('Siap'));
  });

  test('uang muka tanpa metode dari server ditolak, bukan dijadikan metode kosong', () async {
    final katalog = await u.MuatKatalog();
    final k = await u.MuatKonteks();
    u.server.penangan = (_) async => JsonUji(Balasan(Pesanan(), adaMetode: false));
    final hasil = await u.pesananOnline.AmbilAktif();

    expect(
      () => u.pesananOnline.MuatKeKeranjang(hasil.pesanan.single, hasil, katalog, k),
      throwsA(isA<GalatKasir>().having((g) => g.kode, 'kode', 'DataAwalBelumLengkap')),
    );
  });
}
