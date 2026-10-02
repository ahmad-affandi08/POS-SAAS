import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:kasir/Domain/GalatKasir.dart';
import 'package:kasir/Domain/Sesi/StafLokal.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Pendukung/LingkunganUji.dart';

Matcher GalatDengan(String kode) => throwsA(isA<GalatKasir>().having((g) => g.kode, 'kode', kode));

/// K-18 di perangkat: shift terakhir yang ditutup hari ini bisa dibuka ulang dengan alasan + supervisor; data tutup
/// dikosongkan, outbox `Shift.BukaUlang`, laporan Z tertunda dibersihkan, lalu bisa ditutup ulang.
void main() {
  late LingkunganUji u;
  late StafLokal rina;
  late StafLokal budi;

  setUp(() async {
    u = LingkunganUji.Buat();
    await u.SiapkanAktif();
    rina = await u.Staf('Rina Wulandari');
    budi = await u.Staf('Budi Santoso');
  });

  tearDown(() => u.Tutup());

  Future<List<Map<String, Object?>>> Outbox(String jenis) async => [
    for (final o in await u.db.select(u.db.outbox).get())
      if (o.Jenis == jenis) jsonDecode(o.Data) as Map<String, Object?>,
  ];

  test('buka ulang: alasan & supervisor wajib, kolom tutup dikosongkan, outbox terkirim, tutup ulang', () async {
    final shift = await u.shift.BukaShift(kasir: rina, kasAwal: Uang.DariBulat(500000));
    expect(await u.shift.AmbilShiftBisaDibukaUlang(), isNull, reason: 'Masih ada shift terbuka.');
    await u.tutupShift.TutupShift(shift: shift, penutup: rina, kasAktual: Uang.DariBulat(500000));
    expect(await u.repositori.AmbilPengaturan(KunciPengaturan.laporanZTertunda), shift.Uuid);

    final kandidat = (await u.shift.AmbilShiftBisaDibukaUlang())!;
    expect(kandidat.Uuid, shift.Uuid);
    await expectLater(
      u.shift.BukaUlangShift(shift: kandidat, peminta: rina, penyetuju: rina, alasan: 'Salah tekan tutup'),
      GalatDengan('PenyetujuTidakBerwenang'),
    );
    await expectLater(
      u.shift.BukaUlangShift(shift: kandidat, peminta: rina, penyetuju: budi, alasan: ' ok '),
      GalatDengan('AlasanDiperlukan'),
    );

    final aktif = await u.shift.BukaUlangShift(
      shift: kandidat,
      peminta: rina,
      penyetuju: budi,
      alasan: '  Salah tekan tutup  ',
    );
    expect(aktif.Uuid, shift.Uuid);
    expect(aktif.Status, StatusShiftLokal.terbuka);
    expect(aktif.DitutupPada, isNull);
    expect(aktif.KasAktual, isNull);
    expect(aktif.Selisih, isNull);
    expect(await u.repositori.AmbilPengaturan(KunciPengaturan.laporanZTertunda), '');
    final data = (await Outbox('Shift.BukaUlang')).single;
    expect(data['UuidShift'], shift.Uuid);
    expect(data['UuidPengguna'], rina.uuid);
    expect(data['UuidPenyetuju'], budi.uuid);
    expect(data['Alasan'], 'Salah tekan tutup');
    expect(data['DibukaUlangPada'], isA<String>());

    await expectLater(
      u.shift.BukaUlangShift(shift: aktif, peminta: rina, penyetuju: budi, alasan: 'Salah tekan tutup'),
      GalatDengan('TidakBisaDibukaUlang'),
    );
    await u.tutupShift.TutupShift(shift: aktif, penutup: rina, kasAktual: Uang.DariBulat(500000));
    expect(await Outbox('Shift.Tutup'), hasLength(2), reason: 'Tutup ulang = item outbox baru.');
  });

  test('shift yang ditutup kemarin tidak bisa dibuka ulang', () async {
    final shift = await u.shift.BukaShift(kasir: rina, kasAwal: Uang.DariBulat(500000));
    await u.tutupShift.TutupShift(shift: shift, penutup: rina, kasAktual: Uang.DariBulat(500000));
    u.jam = u.jam.add(const Duration(days: 1));
    expect(await u.shift.AmbilShiftBisaDibukaUlang(), isNull);
    await expectLater(
      u.shift.BukaUlangShift(shift: shift, peminta: rina, penyetuju: budi, alasan: 'Salah tekan tutup'),
      GalatDengan('TidakBisaDibukaUlang'),
    );
  });
}
