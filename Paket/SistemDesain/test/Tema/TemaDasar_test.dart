import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sistem_desain/SistemDesain.dart';

void main() {
  group('BuatTema (PRD §17.5, §17.6)', () {
    test('memakai font Atkinson Hyperlegible Next yang di-bundle', () {
      final tema = BuatTema(kecerahan: Brightness.light);
      expect(tema.textTheme.bodyMedium!.fontFamily, 'packages/sistem_desain/AtkinsonHyperlegibleNext');
    });

    test('memasang token warna sesuai kecerahan', () {
      expect(BuatTema(kecerahan: Brightness.light).extension<TokenWarna>(), TokenWarna.terang);
      expect(BuatTema(kecerahan: Brightness.dark).extension<TokenWarna>(), TokenWarna.gelap);
    });

    test('skala tipografi mengikuti mode kepadatan', () {
      final nyaman = BuatTema(kecerahan: Brightness.light).textTheme;
      final ringkas = BuatTema(kecerahan: Brightness.light, kepadatan: KepadatanTipografi.ringkas).textTheme;
      expect(nyaman.displayMedium!.fontSize, 36);
      expect(nyaman.bodyMedium!.fontSize, 16);
      expect(ringkas.displayMedium!.fontSize, 30);
      expect(ringkas.bodyMedium!.fontSize, 14);
      expect(nyaman.headlineSmall!.fontWeight, FontWeight.w700);
      expect(nyaman.headlineSmall!.letterSpacing, 0);
    });
  });
}
