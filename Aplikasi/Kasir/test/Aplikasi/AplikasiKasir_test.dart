import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Aplikasi/AplikasiKasir.dart';
import 'package:kasir/Aplikasi/Lingkungan.dart';

void main() {
  testWidgets('menampilkan penanda lingkungan selain produksi', (tester) async {
    await tester.pumpWidget(const AplikasiKasir(lingkungan: Lingkungan.Staging));
    expect(find.text('Kasir'), findsOneWidget);
    expect(find.byType(Banner), findsOneWidget);
  });

  testWidgets('tanpa penanda lingkungan di produksi', (tester) async {
    await tester.pumpWidget(const AplikasiKasir(lingkungan: Lingkungan.Produksi));
    expect(find.byType(Banner), findsNothing);
  });
}
