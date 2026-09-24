import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Aplikasi/AplikasiKasir.dart';
import 'package:kasir/Aplikasi/Lingkungan.dart';
import 'package:kasir/Aplikasi/Penyedia.dart';
import 'package:kasir/Domain/Pin/PemverifikasiPinOffline.dart';
import 'package:klien_api/KlienApi.dart';

import 'LingkunganUji.dart';

/// Pasang aplikasi utuh dengan basis data memori, secure storage memori, dan server tiruan.
Future<void> PasangAplikasi(WidgetTester tester, LingkunganUji u, {Lingkungan lingkungan = Lingkungan.Produksi}) async {
  await tester.binding.setSurfaceSize(const Size(1280, 900));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        penyediaBasisData.overrideWithValue(u.db),
        penyediaRahasia.overrideWithValue(u.rahasia),
        penyediaKlienHttp.overrideWithValue(u.server.BuatKlien()),
        penyediaJam.overrideWithValue(() => u.jam),
        penyediaLingkungan.overrideWithValue(lingkungan),
        penyediaPemverifikasiPin.overrideWithValue(const PemverifikasiPinTiruan()),
      ],
      child: AplikasiKasir(lingkungan: lingkungan),
    ),
  );
  await Tunggu(tester);
}

/// Beri waktu untuk kerja async (SQLite, Argon2id): bergantian menunggu waktu nyata dan memajukan waktu palsu test,
/// sehingga future yang dimulai dari ketukan (zona waktu palsu) maupun dari luar sama-sama selesai.
Future<void> Tunggu(WidgetTester tester, [Duration lama = const Duration(milliseconds: 300)]) async {
  final putaran = (lama.inMilliseconds / 20).ceil().clamp(1, 1000);
  for (var i = 0; i < putaran; i++) {
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 5)));
    await tester.pump(const Duration(milliseconds: 20));
  }
}

/// Lepas pohon widget lalu tutup basis data. Stream Drift hidup di zona waktu palsu test, jadi penutupan dijalankan di
/// zona yang sama sambil memompa frame (menutup di `runAsync` akan menunggu selamanya).
Future<void> Lepas(WidgetTester tester, LingkunganUji u) async {
  await tester.pumpWidget(const SizedBox.shrink());
  var selesai = false;
  unawaited(u.Tutup().then((_) => selesai = true));
  for (var i = 0; i < 200 && !selesai; i++) {
    await tester.pump(const Duration(milliseconds: 20));
  }
  expect(selesai, isTrue, reason: 'Basis data uji harus tertutup bersih.');
}

/// Ketuk PIN 6 digit di `PapanPin`.
Future<void> KetikPin(WidgetTester tester, String pin) async {
  for (final angka in pin.split('')) {
    await tester.tap(find.widgetWithText(OutlinedButton, angka).last);
    await tester.pump();
  }
  await Tunggu(tester);
}

/// Verifier PIN tiruan untuk test widget: PIN benar bila sama dengan PIN kasus vektor yang garamnya cocok. Menghindari
/// Argon2id di zona waktu palsu test widget; kriptografi asli diuji `PemverifikasiPinOffline_test.dart`.
class PemverifikasiPinTiruan extends PemverifikasiPinOffline {
  const PemverifikasiPinTiruan();

  @override
  Future<bool> Verifikasi({
    required String pin,
    required PinTerbungkus terbungkus,
    required String kunciPerangkatBase64,
    required ParameterPin parameter,
  }) async {
    for (final kasus in (vektorPin['Kasus']! as List<Object?>).cast<Map<String, Object?>>()) {
      if (kasus['Garam'] == terbungkus.garam) {
        return kasus['Pin'] == pin;
      }
    }
    return false;
  }
}
