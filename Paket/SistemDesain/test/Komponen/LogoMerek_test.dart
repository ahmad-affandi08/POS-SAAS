import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sistem_desain/SistemDesain.dart';

void main() {
  testWidgets(
    'LogoMerek memuat aset paket dan diberi label nama merek untuk pembaca layar',
    (tester) async {
      final semantik = tester.ensureSemantics();
      await tester.pumpWidget(
        MaterialApp(
          theme: BuatTema(),
          home: const Column(
            children: [
              LogoMerek.lengkap(),
              LogoMerek.ikon(),
              LogoMerek.lengkapPutih(),
              LogoMerek.ikonPutih(),
            ],
          ),
        ),
      );

      final gambar = tester
          .widgetList<Image>(find.byType(Image))
          .map((i) => i.image)
          .cast<AssetImage>()
          .toList();
      expect(gambar.map((a) => a.keyName), [
        'packages/sistem_desain/assets/merek/LogoHorizontal.png',
        'packages/sistem_desain/assets/merek/IkonMerek.png',
        'packages/sistem_desain/assets/merek/LogoHorizontalPutih.png',
        'packages/sistem_desain/assets/merek/IkonMerekPutih.png',
      ]);
      for (final aset in gambar) {
        final data = await tester.runAsync(() => rootBundle.load(aset.keyName));
        expect(data!.lengthInBytes, greaterThan(0));
      }
      expect(find.bySemanticsLabel(namaMerek), findsNWidgets(4));
      semantik.dispose();
    },
  );
}
