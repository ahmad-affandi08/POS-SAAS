import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;

import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

void main() {
  testWidgets('aktivasi → daftar kasir dari data awal', (tester) async {
    final u = LingkunganUji.Buat();
    u.server.penangan = (p) async => p.url.path.endsWith('aktivasi')
        ? JsonUji({
            'TokenPerangkat': '12|rahasia',
            'KunciPinOffline': vektorPin['KunciPerangkat'],
            'Perangkat': {'Uuid': 'P1', 'Kode': 'POS-001', 'Nama': 'Kasir Depan'},
            'Outlet': {'Uuid': 'O1', 'Nama': 'Kopi Senja Solo Baru'},
            'Tenant': {'Nama': 'Kopi Senja'},
          }, 201)
        : JsonUji(DataAwalUji());
    await PasangAplikasi(tester, u);

    print('LANGKAH 1');
    await tester.enterText(find.byType(TextField), 'AB12CD34');
    print('LANGKAH 2');
    await tester.tap(find.text('Aktifkan perangkat'));
    print('LANGKAH 3');
    await Tunggu(tester, const Duration(seconds: 1));

    expect(find.text('Siapa yang bertugas?'), findsOneWidget);
    expect(find.text('Rina Wulandari'), findsOneWidget);
    expect(find.text('Kopi Senja Solo Baru · POS-001'), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('BR-06.3 offline: pilih kasir, PIN, buka shift dengan pecahan → layar shift & outbox tertunda', (
    tester,
  ) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(u.SiapkanAktif);
    u.server.penangan = (_) async => throw http.ClientException('offline');
    await PasangAplikasi(tester, u);

    print('LANGKAH 4');
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    print('LANGKAH 5');
    await KetikPin(tester, '111111');
    expect(find.textContaining('PIN salah. Sisa 4 percobaan'), findsOneWidget);

    print('LANGKAH 6');
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    expect(find.text('Buka shift · Rina Wulandari'), findsOneWidget);

    print('LANGKAH 7');
    await tester.tap(find.text('Hitung per pecahan'));
    await tester.pump();
    print('LANGKAH 8');
    await tester.tap(find.byTooltip('Tambah Rp 100.000'));
    print('LANGKAH 9');
    await tester.tap(find.byTooltip('Tambah Rp 100.000'));
    print('LANGKAH 10');
    await tester.tap(find.byTooltip('Tambah Rp 50.000'));
    await tester.pump();
    expect(find.widgetWithText(TextField, '250000'), findsOneWidget);

    print('LANGKAH 11');
    await tester.tap(find.text('Buka shift'));
    print('LANGKAH 12');
    await Tunggu(tester, const Duration(seconds: 1));

    expect(find.text('Shift Rina Wulandari'), findsOneWidget);
    expect(find.text('Rp 250.000'), findsWidgets);
    expect(find.text('1 belum terkirim'), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('BR-06.4 kas keluar di atas batas meminta PIN supervisor lalu tercatat', (tester) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(u.SiapkanAktif);
    u.server.penangan = (p) async {
      if (p.method == 'GET') {
        return JsonUji(DataAwalUji());
      }
      final item = (jsonDecode(p.body) as Map<String, Object?>)['Item']! as List<Object?>;
      return JsonUji({
        'Hasil': [
          for (final i in item.cast<Map<String, Object?>>())
            {'Uuid': i['Uuid'], 'Jenis': i['Jenis'], 'Status': 'Diterima', 'Galat': null},
        ],
      });
    };
    await PasangAplikasi(tester, u);
    print('LANGKAH 13');
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    print('LANGKAH 14');
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    print('LANGKAH 15');
    await tester.enterText(find.byType(TextField), '500000');
    print('LANGKAH 16');
    await tester.tap(find.text('Buka shift'));
    print('LANGKAH 17');
    await Tunggu(tester, const Duration(seconds: 1));

    print('LANGKAH 18');
    await tester.tap(find.text('Kas keluar'));
    print('LANGKAH 19');
    await Tunggu(tester);
    print('LANGKAH 20');
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    print('LANGKAH 21');
    await Tunggu(tester);
    print('LANGKAH 22');
    await tester.tap(find.text('Beli es batu & galon').last);
    print('LANGKAH 23');
    await Tunggu(tester);
    print('LANGKAH 24');
    await tester.enterText(find.widgetWithText(TextField, 'Jumlah'), '350000');
    print('LANGKAH 25');
    await tester.tap(find.text('Simpan kas keluar'));
    print('LANGKAH 26');
    await Tunggu(tester);

    expect(find.text('Persetujuan supervisor'), findsOneWidget);
    print('LANGKAH 27');
    await tester.tap(find.widgetWithText(OutlinedButton, 'Budi Santoso'));
    await tester.pump();
    print('LANGKAH 28');
    await KetikPin(tester, KasusPin(1)['Pin']! as String);
    print('LANGKAH 29');
    await Tunggu(tester, const Duration(seconds: 1));

    expect(find.text('Beli es batu & galon'), findsOneWidget);
    expect(find.textContaining('disetujui supervisor'), findsOneWidget);
    expect(find.text('Rp 150.000'), findsOneWidget);
    expect(find.text('Tersinkron'), findsOneWidget);
    await Lepas(tester, u);
  });
}
