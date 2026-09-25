import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/Jual/PanelPelanggan.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// F-16a di layar Jual (PRD §17.2.3 F2, §17.2.7): panel pelanggan dari baris pelanggan di keranjang atau F2, pelanggan
/// baru saat offline, dan penjualan membawa `UuidPelanggan` setelah `Pelanggan.Buat` di outbox.
void main() {
  Future<LingkunganUji> MasukJual(WidgetTester tester, Size ukuran) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (p) async {
      if (p.url.path.endsWith('/data-awal')) {
        return JsonUji(DataAwalUji());
      }
      if (p.url.path.endsWith('/katalog')) {
        return JsonUji(KatalogUji());
      }
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u, ukuran: ukuran);
    await Tunggu(tester, const Duration(milliseconds: 600));
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);
    expect(find.byType(RuangKerja), findsOneWidget);
    return u;
  }

  Future<void> Ketuk(WidgetTester tester, Finder finder) async {
    await tester.ensureVisible(finder);
    await tester.pump();
    await tester.tap(finder);
    await Tunggu(tester);
  }

  for (final (nama, ukuran, pakaiF2) in [
    ('1280', const Size(1280, 900), false),
    ('800', const Size(800, 1280), true),
  ]) {
    testWidgets('pelanggan baru offline di $nama dp → dipakai di transaksi → UuidPelanggan di outbox', (tester) async {
      final u = await MasukJual(tester, ukuran);
      await Ketuk(tester, find.byWidgetPredicate((w) => w is UbinProduk && w.nama == 'Americano Panas'));

      if (pakaiF2) {
        await tester.sendKeyEvent(LogicalKeyboardKey.f2);
        await Tunggu(tester);
      } else {
        await Ketuk(tester, find.textContaining('Pelanggan umum'));
      }
      expect(find.byType(PanelPelanggan), findsOneWidget);

      await tester.enterText(find.widgetWithText(TextField, 'Cari nama atau nomor HP (min. 3 huruf)'), 'budi');
      await Tunggu(tester, const Duration(milliseconds: 600));
      expect(find.textContaining('Offline: hanya pelanggan'), findsOneWidget);
      expect(find.text('Pelanggan tidak ditemukan. Tambahkan sebagai pelanggan baru.'), findsOneWidget);

      await Ketuk(tester, find.widgetWithText(FilledButton, 'Pelanggan baru'));
      expect(find.widgetWithText(TextField, 'Nama pelanggan'), findsOneWidget);
      await tester.enterText(find.widgetWithText(TextField, 'Nama pelanggan'), 'Budi Santoso');
      await tester.enterText(find.widgetWithText(TextField, 'No. HP/WA'), '0813');
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan & pakai'));
      expect(find.text('Nomor HP tidak valid. Contoh: 0812-3456-7890.'), findsOneWidget);
      await tester.enterText(find.widgetWithText(TextField, 'No. HP/WA'), '0813-1111-2222');
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan & pakai'));

      expect(find.byType(PanelPelanggan), findsNothing);
      expect(find.text('Budi Santoso · 0813****2222'), findsOneWidget);
      expect(tester.takeException(), isNull);

      await Ketuk(tester, find.widgetWithText(FilledButton, 'Bayar'));
      await Ketuk(tester, find.widgetWithText(ChoiceChip, 'Tunai'));
      await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Uang pas'));
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Selesaikan pembayaran'));
      await Tunggu(tester);
      expect(find.text('Pembayaran berhasil'), findsOneWidget);

      final outbox = await tester.runAsync(() => u.db.select(u.db.outbox).get());
      final pelanggan = outbox!.firstWhere((o) => o.Jenis == 'Pelanggan.Buat');
      final jual = outbox.firstWhere((o) => o.Jenis == 'Penjualan.Buat');
      expect(pelanggan.Id, lessThan(jual.Id));
      expect((jsonDecode(jual.Data) as Map<String, Object?>)['UuidPelanggan'], pelanggan.Uuid);

      // Transaksi baru kembali ke pelanggan umum.
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Transaksi baru'));
      expect(find.textContaining('Pelanggan umum'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }
}
