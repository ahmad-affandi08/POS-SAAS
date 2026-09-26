import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:kasir/Tampilan/Dapur/LayarKds.dart';
import 'package:kasir/Tampilan/Meja/LayarMeja.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// F-07 mode meja & F-10b fase 1 di aplikasi (PRD §17.2.7): menu Meja muncul bila mode meja outlet aktif, denah meja
/// per area, buka pesanan → layar Jual mode pesanan → kirim ke dapur → batal item (BR-07.5, PIN penyetuju) → bayar
/// menutup pesanan; perangkat `Kds` membuka layar dapur. Diuji di 360/800/1280dp.
void main() {
  const ukuranHp = Size(360, 740);
  const ukuranTablet = Size(800, 1280);
  const ukuranDesktop = Size(1280, 900);

  Future<http.Response> Function(http.Request) PenanganServer({
    List<Map<String, Object?>> tiket = const [],
    List<Map<String, Object?>>? pesanSendiri,
  }) => (p) async {
    final jalur = p.url.path;
    if (pesanSendiri != null && jalur.endsWith('/pesan-sendiri')) {
      return JsonUji({'Pesanan': pesanSendiri});
    }
    if (pesanSendiri != null && jalur.contains('/pesan-sendiri/')) {
      return JsonUji({'Status': jalur.endsWith('/terima') ? 'Diterima' : 'Ditolak'});
    }
    if (jalur.endsWith('/data-awal')) {
      return JsonUji(DataAwalUji());
    }
    if (jalur.endsWith('/katalog')) {
      return JsonUji(KatalogUji());
    }
    if (jalur.endsWith('/meja')) {
      return JsonUji(DataMejaUji());
    }
    if (jalur.endsWith('/dapur/tiket')) {
      return JsonUji({'Tiket': tiket, 'WaktuServer': '2026-09-24T01:30:00Z'});
    }
    if (jalur.endsWith('/status')) {
      return JsonUji({'Status': (jsonDecode(p.body) as Map<String, Object?>)['Status']});
    }
    // Sinkron, pesanan terbuka, dan kunci bayar: offline (mode meja tetap bisa dipakai).
    throw http.ClientException('offline');
  };

  Future<LingkunganUji> MasukKasir(WidgetTester tester, Size ukuran) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = PenanganServer();
    await PasangAplikasi(tester, u, ukuran: ukuran);
    await Tunggu(tester, const Duration(milliseconds: 600));
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);
    expect(find.byType(RuangKerja), findsOneWidget);
    return u;
  }

  Finder Ubin(String nama) => find.byWidgetPredicate((w) => w is UbinProduk && w.nama == nama);

  Future<void> Ketuk(WidgetTester tester, Finder finder) async {
    await tester.ensureVisible(finder);
    await tester.pump();
    await tester.tap(finder);
    await Tunggu(tester);
  }

  Future<List<({String jenis, String uuid, Map<String, Object?> data})>> AmbilOutbox(
    WidgetTester tester,
    LingkunganUji u,
  ) async {
    final baris = await tester.runAsync(() => u.db.select(u.db.outbox).get());
    return [for (final b in baris!) (jenis: b.Jenis, uuid: b.Uuid, data: jsonDecode(b.Data) as Map<String, Object?>)];
  }

  for (final (nama, ukuran) in [('360', ukuranHp), ('800', ukuranTablet), ('1280', ukuranDesktop)]) {
    testWidgets('denah meja per area di $nama dp; buka meja → layar Jual mode pesanan', (tester) async {
      final u = await MasukKasir(tester, ukuran);
      expect(find.text('Meja'), findsOneWidget, reason: 'Menu Meja tampil karena mode meja outlet aktif.');

      await Ketuk(tester, find.text('Meja'));
      expect(find.byType(LayarMeja), findsOneWidget);
      for (final teks in ['Dalam', 'Teras', 'D-01', 'D-02', 'T-01', '0 pesanan terbuka · 3 meja kosong']) {
        expect(find.text(teks), findsOneWidget, reason: teks);
      }
      expect(tester.takeException(), isNull);

      await Ketuk(tester, find.text('D-01'));
      expect(find.text('Buka D-01'), findsOneWidget);
      await Ketuk(tester, find.byTooltip('Tambah tamu'));
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Buka pesanan'));

      expect(find.byType(LayarMeja).hitTestable(), findsNothing, reason: 'Pindah ke layar Jual.');
      expect(find.text(ukuran.width < 600 ? 'D-01 kosong' : 'D-01'), findsOneWidget);
      final buka = (await AmbilOutbox(tester, u)).firstWhere((o) => o.jenis == 'PesananTerbuka.Buka');
      expect(buka.data['Nomor'], 'OB/SLB/260924/POS-001-0001');
      expect(buka.data['JumlahTamu'], 5);
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }

  testWidgets('pesanan meja: kirim ke dapur → batal item (PIN supervisor) → bayar menutup pesanan (1280dp)', (
    tester,
  ) async {
    final u = await MasukKasir(tester, ukuranDesktop);
    await Ketuk(tester, find.text('Meja'));
    await Ketuk(tester, find.text('T-01'));
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Buka pesanan'));

    await Ketuk(tester, Ubin('Americano Panas'));
    await Ketuk(tester, Ubin('Americano Panas'));
    await Ketuk(tester, Ubin('Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo'));
    expect(find.textContaining('Item baru'), findsNWidgets(2));
    await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Kirim ke dapur'));
    expect(find.text('Pesanan T-01 dikirim ke dapur.'), findsOneWidget);
    expect(find.textContaining('Status: Terkirim'), findsNWidgets(2));

    var outbox = await AmbilOutbox(tester, u);
    final tambah = outbox.firstWhere((o) => o.jenis == 'PesananTerbuka.Tambah');
    expect(tambah.data['KirimDapur'], true);
    expect(tambah.data['Ronde'], 1);
    expect((tambah.data['Baris']! as List<Object?>), hasLength(2));

    // Item yang sudah dipesan tidak bisa diubah jumlahnya.
    await Ketuk(tester, find.descendant(of: find.byType(BarisKeranjang).first, matching: find.byIcon(Icons.add)));
    expect(find.textContaining('tidak bisa diubah jumlahnya'), findsOneWidget);

    // BR-07.5: batal croissant yang sudah di dapur → alasan + PIN supervisor.
    await Ketuk(tester, find.text('Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo').last);
    expect(find.text('Batalkan Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo?'), findsOneWidget);
    await tester.enterText(find.widgetWithText(TextField, 'Alasan'), 'Tamu ganti menu');
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Batalkan item'));
    expect(find.text('Persetujuan supervisor'), findsOneWidget);
    await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Budi Santoso'));
    await KetikPin(tester, KasusPin(1)['Pin']! as String);
    await Tunggu(tester);
    expect(find.text('Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo dibatalkan.'), findsOneWidget);
    outbox = await AmbilOutbox(tester, u);
    final batal = outbox.firstWhere((o) => o.jenis == 'PesananTerbuka.BatalkanBaris');
    expect(batal.data['Alasan'], 'Tamu ganti menu');
    expect(batal.data['UuidPenyetuju'], '01K5STAF000000000000000002');

    // 2 × 15.000 = 30.000 + PBJT 10% = 33.000.
    expect(find.text('Rp 33.000'), findsOneWidget);
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Bayar'));
    expect(u.server.permintaan.any((p) => p.url.path.endsWith('/kunci-bayar')), isTrue);
    await Ketuk(tester, find.widgetWithText(ChoiceChip, 'Tunai'));
    await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Uang pas'));
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Selesaikan pembayaran'));
    await Tunggu(tester);
    expect(find.text('Pembayaran berhasil'), findsOneWidget);

    outbox = await AmbilOutbox(tester, u);
    final jual = outbox.firstWhere((o) => o.jenis == 'Penjualan.Buat');
    expect(jual.data['UuidPesananTerbuka'], outbox.firstWhere((o) => o.jenis == 'PesananTerbuka.Buka').uuid);
    expect(jual.data['Kanal'], 'MakanDiTempat');
    expect((jual.data['Ringkasan']! as Map<String, Object?>)['TotalAkhir'], '33000.00');
    expect((jual.data['Baris']! as List<Object?>), hasLength(1));

    // Selesai → kembali ke Meja; T-01 kosong lagi.
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Transaksi baru'));
    expect(find.byType(LayarMeja).hitTestable(), findsOneWidget);
    expect(find.text('0 pesanan terbuka · 3 meja kosong'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await Lepas(tester, u);
  });

  Map<String, Object?> TiketUji(
    String uuid,
    String meja,
    String status,
    String dikirim,
    List<Map<String, Object?>> baris,
  ) => {
    'Uuid': uuid,
    'UuidStasiun': '01K5STAS1VN000000000DAPUR1',
    'NomorDokumen': 'OB/SLB/260924/POS-001-0001',
    'NamaMeja': meja,
    'Label': null,
    'Ronde': 1,
    'Status': status,
    'DikirimPada': dikirim,
    'Baris': baris,
  };

  testWidgets('F-17 pesanan QR menunggu konfirmasi → terima & kirim ke dapur (800 dp)', (tester) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.SiapkanKatalog();
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = PenanganServer(
      pesanSendiri: [
        {
          'Uuid': '01K5QR00000000000000000001',
          'Nomor': 'QR/SLB/260924-0001',
          'UuidMeja': '01K5MEJA0000000000000D0101',
          'NamaMeja': 'D-01',
          'NamaPemesan': 'Bu Ani',
          'Catatan': null,
          'DibuatPada': '2026-09-24T01:00:00Z',
          'Subtotal': '30000.00',
          'Baris': [
            {
              'Uuid': '01K5QRBAR1S000000000000001',
              'UuidProduk': UuidUji.americano,
              'UuidProdukSatuan': UuidUji.psAmericano,
              'NamaProduk': 'Americano Panas',
              'Jumlah': '2',
              'HargaSatuan': '15000.00',
              'HargaPilihan': '0.00',
              'Pilihan': <Object?>[],
              'Catatan': 'Tanpa gula',
            },
          ],
        },
      ],
    );
    await PasangAplikasi(tester, u, ukuran: ukuranTablet);
    await Tunggu(tester, const Duration(milliseconds: 600));
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);
    await Ketuk(tester, find.text('Meja'));
    await Tunggu(tester, const Duration(seconds: 8));

    expect(find.text('Pesanan QR menunggu konfirmasi (1)'), findsOneWidget);
    expect(find.text('Meja D-01 · Bu Ani'), findsOneWidget);
    expect(find.text('2 × Americano Panas · Tanpa gula'), findsOneWidget);

    await Ketuk(tester, find.widgetWithText(FilledButton, 'Terima & kirim ke dapur'));
    expect(find.textContaining('Pesanan QR/SLB/260924-0001 diterima'), findsOneWidget);
    expect(find.text('Pesanan QR menunggu konfirmasi (1)'), findsNothing);
    final outbox = (await AmbilOutbox(tester, u)).where((o) => o.jenis.startsWith('PesananTerbuka.')).toList();
    expect(outbox.map((o) => o.jenis), ['PesananTerbuka.Buka', 'PesananTerbuka.Tambah']);
    final terima = u.server.permintaan.lastWhere((p) => p.url.path.endsWith('/terima'));
    expect((jsonDecode(terima.body) as Map<String, Object?>)['UuidPesananTerbuka'], outbox.first.uuid);
    expect(tester.takeException(), isNull);
    await Lepas(tester, u);
  });

  for (final (nama, ukuran) in [('360', ukuranHp), ('1280', ukuranDesktop)]) {
    testWidgets('perangkat Kds membuka layar dapur di $nama dp: umur tiket berwarna & berlabel, ketuk maju status', (
      tester,
    ) async {
      final u = LingkunganUji.Buat();
      await tester.runAsync(() async {
        await u.SiapkanAktif();
        await u.repositori.SimpanPengaturan(KunciPengaturan.jenisPerangkat, 'Kds');
      });
      u.server.penangan = PenanganServer(
        tiket: [
          TiketUji('01K5T1KET00000000000000001', 'T-01', 'Antre', '2026-09-24T01:05:00Z', [
            {
              'UuidBaris': '01K5BARIS00000000000000001',
              'NamaProduk': 'Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo',
              'Jumlah': '2.0000',
              'Pilihan': <Object?>[],
              'Catatan': 'Hangatkan',
              'Dibatalkan': false,
            },
          ]),
          TiketUji('01K5T1KET00000000000000002', 'D-02', 'Dimasak', '2026-09-24T01:25:00Z', [
            {
              'UuidBaris': '01K5BARIS00000000000000002',
              'NamaProduk': 'Es Kopi Susu Aren',
              'Jumlah': '1.0000',
              'Pilihan': ['Kurang manis'],
              'Catatan': null,
              'Dibatalkan': true,
            },
          ]),
        ],
      );
      await PasangAplikasi(tester, u, ukuran: ukuran);
      await Tunggu(tester, const Duration(milliseconds: 600));

      expect(find.byType(LayarKds), findsOneWidget);
      expect(find.byType(RuangKerja), findsNothing, reason: 'KDS tanpa pilih kasir & shift.');
      expect(find.text('2 × Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo'), findsOneWidget);
      expect(find.text('Catatan: Hangatkan'), findsOneWidget);
      expect(find.text('1 × Es Kopi Susu Aren (dibatalkan)'), findsOneWidget);
      expect(find.text('Terlambat'), findsOneWidget, reason: 'Tiket 25 menit menurut jam server.');
      expect(find.text('25:00'), findsOneWidget);
      expect(find.text('5:00'), findsOneWidget);
      expect(tester.takeException(), isNull);

      await Ketuk(tester, find.widgetWithText(FilledButton, 'Mulai masak'));
      final ubah = u.server.permintaan.lastWhere((p) => p.url.path.endsWith('/status'));
      expect(ubah.url.path, '/api/pos/v1/dapur/tiket/01K5T1KET00000000000000001/status');
      expect(jsonDecode(ubah.body), {'Status': 'Dimasak'});
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }

  for (final (nama, ukuran) in [('360', ukuranHp), ('800', ukuranTablet), ('1280', ukuranDesktop)]) {
    testWidgets('v2.00 perangkat Pelayan di $nama dp: tanpa shift, beranda Meja, kirim ke dapur tanpa Bayar', (
      tester,
    ) async {
      final u = LingkunganUji.Buat();
      await tester.runAsync(() async {
        await u.SiapkanAktif();
        await u.repositori.SimpanPengaturan(KunciPengaturan.jenisPerangkat, 'Pelayan');
      });
      u.server.penangan = PenanganServer();
      await PasangAplikasi(tester, u, ukuran: ukuran);
      await Tunggu(tester, const Duration(milliseconds: 600));
      await tester.tap(find.text('Rina Wulandari'));
      await tester.pump();
      await KetikPin(tester, KasusPin(0)['Pin']! as String);
      await Tunggu(tester);

      expect(find.byType(RuangKerja), findsOneWidget, reason: 'Pelayan langsung ke ruang kerja tanpa buka shift.');
      expect(find.byType(LayarMeja).hitTestable(), findsOneWidget, reason: 'Beranda pelayan = Meja.');
      expect(find.text('Mode pelayan'), findsOneWidget);
      for (final tanpa in ['Kas', 'Shift', 'Riwayat']) {
        expect(find.text(tanpa), findsNothing, reason: tanpa);
      }

      await Ketuk(tester, find.text('D-02'));
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Buka pesanan'));
      await Ketuk(tester, Ubin('Americano Panas'));
      expect(find.widgetWithText(FilledButton, 'Bayar'), findsNothing);
      if (ukuran.width < 600) {
        expect(find.widgetWithText(FilledButton, 'Kirim ke dapur'), findsOneWidget);
      }
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Kirim ke dapur'));
      expect(find.text('Pesanan D-02 dikirim ke dapur.'), findsOneWidget);
      expect(find.byType(LayarMeja).hitTestable(), findsOneWidget, reason: 'Kembali ke denah untuk tamu berikutnya.');

      final outbox = await AmbilOutbox(tester, u);
      expect(outbox.map((o) => o.jenis), ['PesananTerbuka.Buka', 'PesananTerbuka.Tambah']);
      expect(outbox.last.data['KirimDapur'], true);
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }
}
