import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/GerbangKasir.dart';

import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// K-6 (§18.3 butir 6): pemicu sinkron di luar pewaktu 30 detik Ruang Kerja. Perangkat yang berdiri di layar pilih
/// kasir tetap menyetor outbox, koneksi yang pulih langsung mengirim outbox walau jadwal coba ulangnya belum tiba, dan
/// aplikasi yang kembali ke depan tidak menunggu jeda coba ulang.
void main() {
  late bool online;

  Future<LingkunganUji> Siapkan(WidgetTester tester) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      // Buka shift tercatat di outbox (`Shift.Buka`); kasir belum masuk sehingga aplikasi di layar pilih kasir.
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    online = false;
    u.server.penangan = (permintaan) async {
      if (!online) {
        throw http.ClientException('offline');
      }
      final jalur = permintaan.url.path;
      if (jalur.endsWith('/sinkron/kirim')) {
        final item = (jsonDecode(permintaan.body) as Map<String, Object?>)['Item']! as List<Object?>;
        return JsonUji({
          'Hasil': [
            for (final i in item.cast<Map<String, Object?>>())
              {'Uuid': i['Uuid'], 'Jenis': i['Jenis'], 'Status': 'Diterima', 'Galat': null},
          ],
        });
      }
      if (jalur.endsWith('/konfigurasi-aplikasi')) {
        return JsonUji({
          'Aplikasi': {'VersiSaatIni': '0.1.0', 'AdaPembaruan': false, 'WajibPembaruan': false},
          'FlagFitur': <String, Object?>{},
          'Pengumuman': const <Object?>[],
        });
      }
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u);
    expect(find.text('Siapa yang bertugas?'), findsOneWidget);
    return u;
  }

  Future<int> JumlahOutbox(WidgetTester tester, LingkunganUji u) async =>
      (await tester.runAsync(() => u.repositori.HitungJumlahTertunda()))!;

  int JumlahKirim(LingkunganUji u) => u.server.permintaan.where((p) => p.url.path.endsWith('/sinkron/kirim')).length;

  /// Satu putaran pemeriksaan berkala saat offline: koneksi tercatat Offline dan outbox dijadwalkan ulang ke masa
  /// depan (jam uji tidak bergerak, jadi tanpa pemicu K-6 item itu tidak akan pernah siap kirim lagi).
  Future<void> PutaranOffline(WidgetTester tester) async {
    await tester.pump(GerbangKasir.selangPeriksaPerangkat);
    await Tunggu(tester);
  }

  testWidgets('layar pilih kasir: pemeriksaan berkala ikut mengirim outbox', (tester) async {
    final u = await Siapkan(tester);
    online = true;
    expect(await JumlahOutbox(tester, u), 1);

    await tester.pump(GerbangKasir.selangPeriksaPerangkat);
    await Tunggu(tester);

    expect(await JumlahOutbox(tester, u), 0);
    expect(JumlahKirim(u), greaterThan(0));
    expect(find.text('Siapa yang bertugas?'), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('koneksi pulih: outbox yang menunggu jadwal coba ulang langsung dikirim', (tester) async {
    final u = await Siapkan(tester);
    await PutaranOffline(tester);
    expect(await JumlahOutbox(tester, u), 1);
    final kirimSaatOffline = JumlahKirim(u);

    online = true;
    await tester.pump(GerbangKasir.selangPeriksaPerangkat);
    await Tunggu(tester);

    expect(await JumlahOutbox(tester, u), 0, reason: 'Koneksi pulih menyegerakan item yang dijadwalkan ulang.');
    expect(JumlahKirim(u), greaterThan(kirimSaatOffline));
    await Lepas(tester, u);
  });

  testWidgets('aplikasi kembali ke depan: outbox dikirim tanpa menunggu pewaktu', (tester) async {
    final u = await Siapkan(tester);
    await PutaranOffline(tester);
    expect(await JumlahOutbox(tester, u), 1);

    online = true;
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await Tunggu(tester);

    expect(await JumlahOutbox(tester, u), 0);
    await Lepas(tester, u);
  });
}
