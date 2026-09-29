import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pemilik/Aplikasi/Penyedia.dart';
import 'package:pemilik/Data/KlienPemilik.dart';
import 'package:pemilik/Tampilan/LayarNotifikasi.dart';
import 'package:sistem_desain/SistemDesain.dart';

void main() {
  testWidgets('menampilkan pusat notifikasi dan penanda belum dibaca', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          penyediaNotifikasi.overrideWith(
            (ref) async => DaftarNotifikasiPemilik(
              belumDibaca: 1,
              notifikasi: [
                NotifikasiPemilik(
                  uuid: '01KABCDEF0123456789ABCDEFG',
                  jenis: 'StokKritis',
                  judul: 'Stok menipis',
                  isi: '3 produk di bawah batas minimum.',
                  data: const {'Tautan': 'notifikasi'},
                  dibuatPada: DateTime(2026, 9, 29, 8),
                ),
              ],
            ),
          ),
        ],
        child: MaterialApp(theme: BuatTema(), home: const Scaffold(body: LayarNotifikasi())),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Stok menipis'), findsOneWidget);
    expect(find.textContaining('3 produk di bawah batas minimum.'), findsOneWidget);
    expect(find.text('Tandai semua dibaca'), findsOneWidget);
    expect(find.byIcon(Icons.notifications_active), findsOneWidget);
  });
}
