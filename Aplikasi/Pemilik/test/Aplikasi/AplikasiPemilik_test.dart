import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pemilik/Aplikasi/AplikasiPemilik.dart';
import 'package:pemilik/Aplikasi/Lingkungan.dart';

void main() {
  testWidgets('menampilkan penanda lingkungan selain produksi', (tester) async {
    await tester.pumpWidget(const AplikasiPemilik(lingkungan: Lingkungan.staging));
    expect(find.text('Pemilik'), findsOneWidget);
    expect(find.byType(Banner), findsOneWidget);
  });

  testWidgets('tanpa penanda lingkungan di produksi', (tester) async {
    await tester.pumpWidget(const AplikasiPemilik(lingkungan: Lingkungan.produksi));
    expect(find.byType(Banner), findsNothing);
  });
}
