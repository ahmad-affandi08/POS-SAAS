import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Domain/GalatKasir.dart';
import 'package:kasir/Domain/Shift/LayananShift.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Pendukung/LingkunganUji.dart';

Matcher GalatDengan(String kode) => throwsA(isA<GalatKasir>().having((g) => g.kode, 'kode', kode));

/// K-18: foto bukti kas ditolak di perangkat bila pada setoran atau melebihi batas server (400 KB).
void main() {
  test('setoran berfoto & foto > 400 KB ditolak tanpa entri outbox', () async {
    final u = LingkunganUji.Buat();
    addTearDown(u.Tutup);
    await u.SiapkanAktif();
    final rina = await u.Staf('Rina Wulandari');
    final shift = await u.shift.BukaShift(kasir: rina, kasAwal: Uang.DariBulat(500000));
    final kecil = Uint8List.fromList([0xFF, 0xD8, 0xFF, 0xE0, 1]);

    await expectLater(
      u.shift.CatatMutasi(
        shift: shift,
        jenis: JenisMutasi.setoran,
        jumlah: Uang.DariBulat(100000),
        pencatat: rina,
        bukti: kecil,
      ),
      GalatDengan('BuktiTidakValid'),
    );
    await expectLater(
      u.shift.CatatMutasi(
        shift: shift,
        jenis: JenisMutasi.keluar,
        jumlah: Uang.DariBulat(45000),
        pencatat: rina,
        uuidKategori: '01K5KATEGORI00000000000001',
        bukti: Uint8List(LayananShift.ukuranMaksimalBuktiKb * 1024 + 1),
      ),
      GalatDengan('BuktiTerlaluBesar'),
    );
    final outbox = await u.db.select(u.db.outbox).get();
    expect(outbox.where((o) => o.Jenis == 'MutasiKas.Catat'), isEmpty);
  });
}
