import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../Aplikasi/Penyedia.dart';
import 'Dapur/LayarKds.dart';
import 'LayarAktivasi.dart';
import 'LayarBukaShift.dart';
import 'LayarPilihKasir.dart';
import 'RuangKerja/RuangKerja.dart';
import 'Shift/LayarLaporanZ.dart';

/// Menentukan layar menurut sesi: aktivasi → pilih kasir & PIN → buka shift → Ruang Kerja Kasir (§17.2.7) selama
/// shift terbuka, termasuk layar kunci & ganti kasir; setelah tutup shift → Laporan Z → buka shift (F-11). Perangkat
/// berjenis `Kds` langsung membuka layar dapur setelah aktif (F-10b; tanpa kasir & shift). Perangkat berjenis `Pelayan`
/// (v2.00) masuk dengan PIN lalu langsung ke Ruang Kerja mode Pelayan tanpa shift.
class GerbangKasir extends ConsumerWidget {
  const GerbangKasir({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sesi = ref.watch(penyediaSesi);
    final jenis = ref.watch(penyediaJenisPerangkat).value;
    final kds = jenis == 'Kds';
    if (kds && (sesi.tahap == TahapSesi.PilihKasir || sesi.tahap == TahapSesi.Masuk)) {
      return const LayarKds();
    }
    return switch (sesi.tahap) {
      TahapSesi.Memuat => const Scaffold(body: Center(child: CircularProgressIndicator())),
      TahapSesi.BelumAktif => LayarAktivasi(pesan: sesi.pesan),
      TahapSesi.PilihKasir => const LayarPilihKasir(),
      TahapSesi.Masuk when jenis == 'Pelayan' => RuangKerja(shift: null, kasir: sesi.kasir!, kunci: sesi.kunci),
      TahapSesi.Masuk =>
        ref
            .watch(penyediaShiftAktif)
            .when(
              loading: () => const Scaffold(body: Center(child: CircularProgressIndicator())),
              error: (galat, _) => Scaffold(body: Center(child: Text('Data shift tidak bisa dibaca: $galat'))),
              data: (shift) {
                if (shift != null) {
                  return RuangKerja(shift: shift, kasir: sesi.kasir!, kunci: sesi.kunci);
                }
                // F-11: laporan Z shift yang baru ditutup tampil dulu sebelum buka shift berikutnya.
                final laporanZ = ref.watch(penyediaLaporanZTertunda).value;
                return laporanZ == null ? LayarBukaShift(kasir: sesi.kasir!) : LayarLaporanZ(uuidShift: laporanZ);
              },
            ),
    };
  }
}
