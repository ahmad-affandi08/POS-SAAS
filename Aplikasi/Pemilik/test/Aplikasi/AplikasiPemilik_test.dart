import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sistem_desain/SistemDesain.dart';
import 'package:pemilik/Aplikasi/AplikasiPemilik.dart';
import 'package:pemilik/Aplikasi/Lingkungan.dart';

void main() {
  testWidgets('menampilkan penanda lingkungan selain produksi', (tester) async {
    await tester.pumpWidget(const AplikasiPemilik(lingkungan: Lingkungan.Staging));
    expect(find.text('Pemilik'), findsOneWidget);
    expect(find.byType(Banner), findsOneWidget);
  });

  testWidgets('tanpa penanda lingkungan di produksi', (tester) async {
    await tester.pumpWidget(const AplikasiPemilik(lingkungan: Lingkungan.Produksi));
    expect(find.byType(Banner), findsNothing);
  });

  testWidgets('hanya memakai tema terang walau sistem operasi dalam mode gelap (D-14)', (tester) async {
    tester.platformDispatcher.platformBrightnessTestValue = Brightness.dark;
    addTearDown(tester.platformDispatcher.clearPlatformBrightnessTestValue);
    await tester.pumpWidget(const AplikasiPemilik(lingkungan: Lingkungan.Produksi));

    final aplikasi = tester.widget<MaterialApp>(find.byType(MaterialApp));
    expect(aplikasi.darkTheme, isNull);
    expect(aplikasi.highContrastDarkTheme, isNull);
    expect(aplikasi.themeMode, ThemeMode.light);

    final tema = Theme.of(tester.element(find.text('Pemilik')));
    expect(tema.brightness, Brightness.light);
    expect(tema.extension<TokenWarna>(), TokenWarna.bawaan);
  });
}
