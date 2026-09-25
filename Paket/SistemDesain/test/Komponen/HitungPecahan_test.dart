import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Hitung pecahan kas (buka & tutup shift, F-06/F-11).
void main() {
  testWidgets('HitungPecahan: tambah/kurang per nominal, kurang nonaktif di 0, total tanpa pecahan biner', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(360, 640);
    addTearDown(tester.view.reset);
    final jumlah = <int, int>{100000: 2, 50000: 0, 500: 3};

    await tester.pumpWidget(
      MaterialApp(
        theme: BuatTema(),
        home: Scaffold(
          body: StatefulBuilder(
            builder: (context, atur) => HitungPecahan(
              nominal: const [100000, 50000, 500],
              jumlah: jumlah,
              saatBerubah: (n, baru) => atur(() => jumlah[n] = baru),
            ),
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Rp 100.000'), findsOneWidget);
    expect(tester.widget<IconButton>(find.widgetWithIcon(IconButton, Icons.remove).at(1)).onPressed, isNull);

    await tester.tap(find.byTooltip('Tambah Rp 50.000'));
    await tester.tap(find.byTooltip('Kurangi Rp 100.000'));
    await tester.pump();

    expect(jumlah, {100000: 1, 50000: 1, 500: 3});
    expect(HitungPecahan.HitungTotal(jumlah), Uang.DariBulat(151500));
  });
}
