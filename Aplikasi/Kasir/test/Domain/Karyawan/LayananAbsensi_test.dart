import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Domain/GalatKasir.dart';
import 'package:kasir/Domain/Karyawan/LayananAbsensi.dart';

import '../../Pendukung/LingkunganUji.dart';
import '../../Pendukung/PasangAplikasi.dart';

Matcher GalatDengan(String kode) => throwsA(isA<GalatKasir>().having((g) => g.kode, 'kode', kode));

/// F-18 EMP-03: absen masuk lalu keluar tercatat lokal + outbox persis kontrak `Absensi.Masuk`/`Absensi.Keluar`,
/// swafoto wajib bila kamera ada (batal = tidak tercatat), tanpa kamera cukup PIN.
void main() {
  late LingkunganUji u;

  setUp(() => u = LingkunganUji.Buat());
  tearDown(() => u.Tutup());

  LayananAbsensi Layanan(KameraSwafotoTiruan kamera) =>
      LayananAbsensi(repositori: u.repositoriAbsensi, kamera: kamera, jam: () => u.jam);

  test('masuk lalu keluar: outbox persis kontrak, swafoto base64, baris lokal tertutup', () async {
    await u.SiapkanAktif();
    final rina = await u.Staf('Rina Wulandari');
    final kamera = KameraSwafotoTiruan(foto: Uint8List.fromList([0xFF, 0xD8, 0xFF, 0xE0, 1, 2, 3]));
    final layanan = Layanan(kamera);

    final masuk = await layanan.Catat(rina, await layanan.AmbilSwafoto());
    expect(masuk.jenis, JenisAbsensi.Masuk);
    u.jam = u.jam.add(const Duration(hours: 8, minutes: 5));
    final keluar = await layanan.Catat(rina, await layanan.AmbilSwafoto());
    expect(keluar.jenis, JenisAbsensi.Keluar);
    expect(keluar.waktu.difference(keluar.masukPada!), const Duration(hours: 8, minutes: 5));

    final outbox = await u.db.select(u.db.outbox).get();
    final lokal = (await u.db.select(u.db.absensiLokal).get()).single;
    expect(outbox.map((o) => o.Jenis), ['Absensi.Masuk', 'Absensi.Keluar']);
    expect(outbox.first.Uuid, lokal.Uuid);
    expect(jsonDecode(outbox.first.Data), {
      'UuidPengguna': rina.uuid,
      'MasukPada': '2026-09-24T01:00:00.000Z',
      'Swafoto': base64Encode([0xFF, 0xD8, 0xFF, 0xE0, 1, 2, 3]),
    });
    final dataKeluar = jsonDecode(outbox.last.Data) as Map<String, Object?>;
    expect(dataKeluar['UuidAbsensi'], lokal.Uuid);
    expect(dataKeluar['KeluarPada'], '2026-09-24T09:05:00.000Z');
    expect(lokal.KeluarPada, isNotNull);
    expect(await layanan.AmbilTerbuka(rina), isNull);
  });

  test(
    'kamera ada tetapi dibatalkan → SwafotoDibatalkan; tanpa kamera → Swafoto null; foto terlalu besar ditolak',
    () async {
      await u.SiapkanAktif();
      final budi = await u.Staf('Budi Santoso');
      await expectLater(Layanan(KameraSwafotoTiruan()).AmbilSwafoto(), GalatDengan('SwafotoDibatalkan'));
      await expectLater(
        Layanan(KameraSwafotoTiruan()).Catat(budi, Uint8List(LayananAbsensi.ukuranMaksimalSwafoto + 1)),
        GalatDengan('SwafotoTerlaluBesar'),
      );
      expect(await u.db.select(u.db.outbox).get(), isEmpty);

      final tanpa = Layanan(KameraSwafotoTiruan(tersedia: false));
      expect(await tanpa.AmbilSwafoto(), isNull);
      await tanpa.Catat(budi, null);
      expect(
        (jsonDecode((await u.db.select(u.db.outbox).get()).single.Data) as Map<String, Object?>)['Swafoto'],
        isNull,
      );
    },
  );
}
