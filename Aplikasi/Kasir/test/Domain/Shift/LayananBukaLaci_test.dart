import 'dart:convert';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Data/BasisData/BasisDataKasir.dart';
import 'package:kasir/Domain/GalatKasir.dart';
import 'package:kasir/Domain/Shift/LayananBukaLaci.dart';
import 'package:kasir/Domain/Sesi/StafLokal.dart';
import 'package:kasir/Domain/Struk/ProfilPrinter.dart';
import 'package:inti/Inti.dart';

import '../../Pendukung/LingkunganUji.dart';

/// Cetak struk bagian 4 (POS-17, §19.2): buka laci tanpa transaksi selalu dicatat (outbox `Laci.Buka`), PIN
/// supervisor opsional lewat pengaturan tenant, dan tidak dicatat bila laci gagal dibuka.
void main() {
  late LingkunganUji u;
  late LayananBukaLaci layanan;
  late StafLokal rina;
  late StafLokal budi;
  late BarisShift shift;

  setUp(() => u = LingkunganUji.Buat());
  tearDown(() => u.Tutup());

  Future<void> Siapkan({bool? perluPin}) async {
    await u.SiapkanAktif(dataAwal: DataAwalUji(bukaLaciPerluPin: perluPin));
    rina = await u.Staf('Rina Wulandari');
    budi = await u.Staf('Budi Santoso');
    shift = await u.shift.BukaShift(kasir: rina, kasAwal: Uang.DariBulat(500000));
    layanan = LayananBukaLaci(repositori: u.repositori, struk: u.struk, jam: () => u.jam);
    await const ProfilPrinter(alamat: '192.168.1.50').Simpan(u.repositori);
  }

  Future<List<BarisOutbox>> OutboxLaci() async =>
      (await u.db.select(u.db.outbox).get()).where((o) => o.Jenis == 'Laci.Buka').toList();

  test('laci dibuka lewat printer (ESC p) lalu dicatat ke outbox Laci.Buka dengan alasan & pembuka', () async {
    await Siapkan();
    expect(await layanan.CekPerluPin(), isFalse);

    final uuid = await layanan.BukaLaci(shift: shift, pembuka: rina, alasan: '  Tukar uang kecil  ');

    expect(u.printer.CekBukaLaci(), isTrue);
    final outbox = await OutboxLaci();
    expect(outbox, hasLength(1));
    expect(outbox.single.Uuid, uuid);
    final data = jsonDecode(outbox.single.Data) as Map<String, Object?>;
    expect(data['UuidShift'], shift.Uuid);
    expect(data['Alasan'], 'Tukar uang kecil');
    expect(data['UuidPembuka'], rina.uuid);
    expect(data['UuidPenyetuju'], isNull);
  });

  test('alasan kurang dari 3 karakter ditolak tanpa membuka laci', () async {
    await Siapkan();
    await expectLater(
      layanan.BukaLaci(shift: shift, pembuka: rina, alasan: ' a '),
      throwsA(isA<GalatKasir>().having((g) => g.kode, 'kode', 'AlasanWajib')),
    );
    expect(u.printer.kiriman, isEmpty);
    expect(await OutboxLaci(), isEmpty);
  });

  test(
    'PIN wajib (pengaturan BukaLaciPerluPin): tanpa penyetuju ditolak, kasir tanpa izin ditolak, supervisor lolos',
    () async {
      await Siapkan(perluPin: true);
      expect(await layanan.CekPerluPin(), isTrue);

      await expectLater(
        layanan.BukaLaci(shift: shift, pembuka: rina, alasan: 'Tukar uang kecil'),
        throwsA(isA<GalatKasir>().having((g) => g.kode, 'kode', 'PersetujuanDiperlukan')),
      );
      await expectLater(
        layanan.BukaLaci(shift: shift, pembuka: rina, alasan: 'Tukar uang kecil', penyetuju: rina),
        throwsA(isA<GalatKasir>().having((g) => g.kode, 'kode', 'PenyetujuTidakBerwenang')),
      );
      expect(u.printer.kiriman, isEmpty);

      await layanan.BukaLaci(shift: shift, pembuka: rina, alasan: 'Tukar uang kecil', penyetuju: budi);
      final data = jsonDecode((await OutboxLaci()).single.Data) as Map<String, Object?>;
      expect(data['UuidPenyetuju'], budi.uuid);
    },
  );

  test('printer gagal atau belum diatur: laci tidak terbuka dan tidak dicatat', () async {
    await Siapkan();
    u.printer.galat = 'Printer di 192.168.1.50:9100 tidak tersambung.';
    await expectLater(
      layanan.BukaLaci(shift: shift, pembuka: rina, alasan: 'Tukar uang kecil'),
      throwsA(isA<GalatPrinter>()),
    );
    u.printer.galat = null;
    await ProfilPrinter.Hapus(u.repositori);
    await expectLater(
      layanan.BukaLaci(shift: shift, pembuka: rina, alasan: 'Tukar uang kecil'),
      throwsA(isA<GalatPrinter>().having((g) => g.pesan, 'pesan', contains('Printer belum diatur'))),
    );
    expect(await OutboxLaci(), isEmpty);
  });
}
