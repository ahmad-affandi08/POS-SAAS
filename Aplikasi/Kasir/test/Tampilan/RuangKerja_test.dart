import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:kasir/Domain/Perangkat/PengaturanPerangkat.dart';
import 'package:kasir/Tampilan/RuangKerja/LayarKunci.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// Ruang Kerja Kasir (PRD §17.2.7, D-16): bingkai di 360/800/1280dp, panel tugas, kunci cepat & otomatis, ganti kasir
/// tanpa menutup shift, dan pengaturan perangkat.
void main() {
  const ukuranHp = Size(360, 740);
  const ukuranTablet = Size(800, 1280);
  const ukuranDesktop = Size(1280, 900);

  /// Perangkat aktif, shift Rina sudah terbuka (Rp 500.000), server offline; masuk sebagai Rina.
  Future<LingkunganUji> MasukRuangKerja(
    WidgetTester tester, {
    Size ukuran = ukuranDesktop,
    PenjagaLayarTiruan? penjagaLayar,
    Map<String, String> pengaturan = const {},
  }) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.repositori.SimpanPengaturan(KunciPengaturan.namaOutlet, 'Kopi Senja Solo Baru');
      await u.repositori.SimpanPengaturan(KunciPengaturan.kodePerangkat, 'POS-001');
      for (final e in pengaturan.entries) {
        await u.repositori.SimpanPengaturan(e.key, e.value);
      }
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (_) async => throw http.ClientException('offline');
    await PasangAplikasi(tester, u, ukuran: ukuran, penjagaLayar: penjagaLayar);
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    expect(find.byType(RuangKerja), findsOneWidget);
    return u;
  }

  Future<String?> BacaPengaturan(WidgetTester tester, LingkunganUji u, String kunci) =>
      tester.runAsync<String?>(() => u.repositori.AmbilPengaturan(kunci));

  for (final (nama, ukuran) in [('360', ukuranHp), ('800', ukuranTablet), ('1280', ukuranDesktop)]) {
    testWidgets('bingkai di lebar $nama dp: navigasi, beranda Jual, bilah status, panel kas, layar menyala', (
      tester,
    ) async {
      final penjagaLayar = PenjagaLayarTiruan();
      final u = await MasukRuangKerja(tester, ukuran: ukuran, penjagaLayar: penjagaLayar);

      // Bilah atas: outlet · perangkat, kasir, tombol kunci.
      expect(find.text('Kopi Senja Solo Baru · POS-001'), findsOneWidget);
      expect(find.text('Rina Wulandari'), findsOneWidget);
      expect(find.byTooltip('Ganti kasir'), findsOneWidget);
      expect(ukuran.width < 600 ? find.byTooltip('Kunci') : find.text('Kunci'), findsOneWidget);

      // Rel kiri di ≥ 600dp, bilah navigasi bawah di < 600dp. Beranda = Jual.
      expect(find.byType(NavigationRail), ukuran.width >= 600 ? findsOneWidget : findsNothing);
      expect(find.byType(NavigationBar), ukuran.width >= 600 ? findsNothing : findsOneWidget);
      expect(find.text('Layar jual belum tersedia'), findsOneWidget);
      for (final label in ['Jual', 'Kas', 'Shift', 'Sinkron', 'Pengaturan']) {
        expect(find.text(label), findsOneWidget, reason: 'Item navigasi $label');
      }

      // Bilah status selalu terlihat: koneksi, tertunda, printer, jam buka shift.
      expect(find.text('Offline'), findsOneWidget);
      expect(find.text('1 belum terkirim'), findsOneWidget);
      expect(find.text('Printer belum diatur'), findsOneWidget);
      expect(find.textContaining(RegExp(r'^Shift \d{2}\.\d{2}$')), findsOneWidget);

      // Layar tetap menyala selama shift terbuka.
      expect(penjagaLayar.menyala, isTrue);

      // Kas → Kas masuk: panel samping di ≥ 1024dp, lembar bawah di bawahnya; area kerja tetap di belakangnya.
      await tester.tap(find.text('Kas'));
      await Tunggu(tester);
      await tester.tap(find.widgetWithText(OutlinedButton, 'Kas masuk'));
      await Tunggu(tester);
      final panel = tester.widget<PanelTugas>(find.byType(PanelTugas));
      expect(panel.judul, 'Kas masuk');
      expect(panel.tataLetak, ukuran.width >= 1024 ? TataLetakPanel.Samping : TataLetakPanel.Lembar);
      expect(find.widgetWithText(TextField, 'Jumlah'), findsOneWidget);
      expect(find.text('Kas awal'), findsOneWidget, reason: 'Layar Kas tetap ada di bawah panel.');
      await tester.tap(find.byTooltip('Tutup'));
      await Tunggu(tester);
      expect(find.byType(PanelTugas), findsNothing);

      // Ketuk bilah status → Status sinkron.
      await tester.tap(find.text('Printer belum diatur'));
      await Tunggu(tester);
      expect(find.text('Status sinkron'), findsOneWidget);
      expect(find.text('1 data belum terkirim.'), findsOneWidget);

      await Lepas(tester, u);
      expect(penjagaLayar.menyala, isFalse, reason: 'Penjaga layar dilepas saat ruang kerja ditutup.');
    });
  }

  testWidgets('rel navigasi bisa diciutkan menjadi ikon saja', (tester) async {
    final u = await MasukRuangKerja(tester);
    var rel = tester.widget<NavigationRail>(find.byType(NavigationRail));
    expect(rel.extended, isTrue);

    await tester.tap(find.byTooltip('Ciutkan menu'));
    await Tunggu(tester);
    rel = tester.widget<NavigationRail>(find.byType(NavigationRail));
    expect(rel.extended, isFalse);
    expect(rel.labelType, NavigationRailLabelType.none);

    await tester.tap(find.byTooltip('Lebarkan menu'));
    await Tunggu(tester);
    expect(tester.widget<NavigationRail>(find.byType(NavigationRail)).extended, isTrue);
    await Lepas(tester, u);
  });

  testWidgets('kunci otomatis setelah diam 5 menit (bawaan); sentuhan mengulang hitungan; buka dengan PIN sama', (
    tester,
  ) async {
    final u = await MasukRuangKerja(tester);

    await tester.pump(const Duration(minutes: 4));
    await Tunggu(tester);
    expect(find.byType(LayarKunci), findsNothing);

    // Sentuhan = aktivitas: hitungan diam mulai dari nol.
    await tester.tap(find.text('Layar jual belum tersedia'));
    await tester.pump(const Duration(minutes: 4));
    await Tunggu(tester);
    expect(find.byType(LayarKunci), findsNothing);

    await tester.pump(const Duration(minutes: 1, seconds: 1));
    await Tunggu(tester);
    expect(find.byType(LayarKunci), findsOneWidget);
    expect(find.text('Terkunci · Rina Wulandari'), findsOneWidget);
    expect(find.text('Kopi Senja Solo Baru'), findsOneWidget);
    expect(find.text('Layar jual belum tersedia'), findsNothing, reason: 'Area kerja tersembunyi saat terkunci.');

    await KetikPin(tester, '111111');
    expect(find.textContaining('PIN salah'), findsOneWidget);
    expect(find.byType(LayarKunci), findsOneWidget);

    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    expect(find.byType(LayarKunci), findsNothing);
    expect(find.text('Layar jual belum tersedia'), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('kunci otomatis memakai waktu dari pengaturan perangkat', (tester) async {
    final u = await MasukRuangKerja(tester, pengaturan: {KunciPengaturan.menitKunciOtomatis: '1'});
    await tester.pump(const Duration(seconds: 50));
    await Tunggu(tester);
    expect(find.byType(LayarKunci), findsNothing);
    await tester.pump(const Duration(seconds: 15));
    await Tunggu(tester);
    expect(find.byType(LayarKunci), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('kunci cepat menjaga panel & isi area kerja tetap utuh setelah dibuka', (tester) async {
    final u = await MasukRuangKerja(tester);
    await tester.tap(find.text('Kas'));
    await Tunggu(tester);
    await tester.tap(find.widgetWithText(OutlinedButton, 'Kas masuk'));
    await Tunggu(tester);
    await tester.enterText(find.widgetWithText(TextField, 'Jumlah'), '75000');

    await tester.tap(find.text('Kunci'));
    await Tunggu(tester);
    expect(find.byType(LayarKunci), findsOneWidget);
    expect(find.byType(PanelTugas), findsNothing);

    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    expect(find.byType(PanelTugas), findsOneWidget);
    expect(find.widgetWithText(TextField, '75000'), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('ganti kasir dari layar kunci tanpa menutup shift', (tester) async {
    final u = await MasukRuangKerja(tester);
    final shiftAwal = (await tester.runAsync(u.repositori.AmbilShiftAktif))!;

    await tester.tap(find.text('Kunci'));
    await Tunggu(tester);
    await tester.tap(find.text('Ganti kasir'));
    await Tunggu(tester);
    expect(find.text('Siapa yang bertugas?'), findsOneWidget);
    await tester.tap(find.widgetWithText(OutlinedButton, 'Budi Santoso'));
    await tester.pump();
    expect(find.text('PIN Budi Santoso'), findsOneWidget);
    await KetikPin(tester, KasusPin(1)['Pin']! as String);

    expect(find.byType(LayarKunci), findsNothing);
    expect(find.text('Budi Santoso'), findsOneWidget, reason: 'Kasir aktif di bilah atas berganti.');
    expect(find.text('Rina Wulandari'), findsNothing);

    final shift = await tester.runAsync(u.repositori.AmbilShiftAktif);
    expect(shift, isNotNull, reason: 'Shift tetap terbuka.');
    expect(shift!.Uuid, shiftAwal.Uuid);
    expect(shift.DibukaOleh, shiftAwal.DibukaOleh);
    await Lepas(tester, u);
  });

  testWidgets('ketuk nama kasir → ganti kasir; bisa dibatalkan tanpa PIN', (tester) async {
    final u = await MasukRuangKerja(tester, ukuran: ukuranHp);
    await tester.tap(find.byTooltip('Ganti kasir'));
    await Tunggu(tester);
    expect(find.byType(LayarKunci), findsOneWidget);
    expect(find.text('Siapa yang bertugas?'), findsOneWidget);

    await tester.tap(find.text('Batal'));
    await Tunggu(tester);
    expect(find.byType(LayarKunci), findsNothing);
    expect(find.text('Rina Wulandari'), findsOneWidget);

    await tester.tap(find.byTooltip('Ganti kasir'));
    await Tunggu(tester);
    await tester.tap(find.widgetWithText(OutlinedButton, 'Budi Santoso'));
    await tester.pump();
    await KetikPin(tester, KasusPin(1)['Pin']! as String);
    expect(find.text('Budi Santoso'), findsOneWidget);
    expect(await tester.runAsync(u.repositori.AmbilShiftAktif), isNotNull);
    await Lepas(tester, u);
  });

  testWidgets('pengaturan perangkat tersimpan lokal dan ukuran Besar memperbesar teks 1,15×', (tester) async {
    final u = await MasukRuangKerja(tester, ukuran: ukuranTablet);
    await tester.tap(find.text('Pengaturan'));
    await Tunggu(tester);
    expect(find.text('Perbarui data kasir'), findsOneWidget);

    double Skala() => MediaQuery.textScalerOf(tester.element(find.text('Ukuran tampilan'))).scale(100) / 100;
    expect(Skala(), closeTo(1, 0.001));

    await tester.tap(find.text('Besar'));
    await Tunggu(tester);
    expect(Skala(), closeTo(1.15, 0.001));
    expect(await BacaPengaturan(tester, u, KunciPengaturan.ukuranTampilan), 'Besar');

    await tester.tap(find.text('Kiri'));
    await Tunggu(tester);
    expect(await BacaPengaturan(tester, u, KunciPengaturan.posisiKeranjang), 'Kiri');

    await tester.tap(find.text('5 menit'));
    await Tunggu(tester);
    await tester.tap(find.text('10 menit').last);
    await Tunggu(tester);
    expect(await BacaPengaturan(tester, u, KunciPengaturan.menitKunciOtomatis), '10');

    final dimuat = await tester.runAsync(() => PengaturanPerangkat.Muat(u.repositori));
    expect(dimuat!.ukuran, UkuranTampilan.Besar);
    expect(dimuat.posisiKeranjang, PosisiKeranjang.Kiri);
    expect(dimuat.menitKunciOtomatis, 10);
    await Lepas(tester, u);
  });

  testWidgets('tombol kembali menutup panel lalu kembali ke beranda Jual, tidak keluar dari ruang kerja', (
    tester,
  ) async {
    final u = await MasukRuangKerja(tester);
    await tester.tap(find.text('Kas'));
    await Tunggu(tester);
    await tester.tap(find.widgetWithText(OutlinedButton, 'Setoran'));
    await Tunggu(tester);
    expect(find.byType(PanelTugas), findsOneWidget);

    await tester.binding.handlePopRoute();
    await Tunggu(tester);
    expect(find.byType(PanelTugas), findsNothing);
    expect(find.text('Kas awal'), findsOneWidget);

    await tester.binding.handlePopRoute();
    await Tunggu(tester);
    expect(find.text('Layar jual belum tersedia'), findsOneWidget);
    expect(find.byType(RuangKerja), findsOneWidget);
    await Lepas(tester, u);
  });

  test('pengaturan perangkat: nilai bawaan dan nilai tidak dikenal kembali ke bawaan', () async {
    final u = LingkunganUji.Buat();
    final bawaan = await PengaturanPerangkat.Muat(u.repositori);
    expect(bawaan.ukuran, UkuranTampilan.Normal);
    expect(bawaan.posisiKeranjang, PosisiKeranjang.Kanan);
    expect(bawaan.menitKunciOtomatis, 5);
    expect(UkuranTampilan.Besar.skalaTeks, 1.15);

    await u.repositori.SimpanPengaturan(KunciPengaturan.ukuranTampilan, 'Raksasa');
    await u.repositori.SimpanPengaturan(KunciPengaturan.menitKunciOtomatis, '0');
    final rusak = await PengaturanPerangkat.Muat(u.repositori);
    expect(rusak.ukuran, UkuranTampilan.Normal);
    expect(rusak.menitKunciOtomatis, 5);
    await u.Tutup();
  });
}
