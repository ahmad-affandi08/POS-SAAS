import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../Aplikasi/Penyedia.dart';
import 'LayarAktivasi.dart';
import 'LayarBukaShift.dart';
import 'LayarPilihKasir.dart';
import 'RuangKerja/RuangKerja.dart';

/// Menentukan layar menurut sesi: aktivasi → pilih kasir & PIN → buka shift → Ruang Kerja Kasir (§17.2.7) selama
/// shift terbuka, termasuk layar kunci & ganti kasir.
class GerbangKasir extends ConsumerWidget {
  const GerbangKasir({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sesi = ref.watch(penyediaSesi);
    return switch (sesi.tahap) {
      TahapSesi.Memuat => const Scaffold(body: Center(child: CircularProgressIndicator())),
      TahapSesi.BelumAktif => LayarAktivasi(pesan: sesi.pesan),
      TahapSesi.PilihKasir => const LayarPilihKasir(),
      TahapSesi.Masuk =>
        ref
            .watch(penyediaShiftAktif)
            .when(
              loading: () => const Scaffold(body: Center(child: CircularProgressIndicator())),
              error: (galat, _) => Scaffold(body: Center(child: Text('Data shift tidak bisa dibaca: $galat'))),
              data: (shift) => shift == null
                  ? LayarBukaShift(kasir: sesi.kasir!)
                  : RuangKerja(shift: shift, kasir: sesi.kasir!, kunci: sesi.kunci),
            ),
    };
  }
}
