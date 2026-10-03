import 'package:flutter/material.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Data/BasisData/BasisDataKasir.dart';
import '../Domain/Sesi/StafLokal.dart';
import 'Komponen/FormatWaktu.dart';
import 'RuangKerja/IsiAreaKerja.dart';

/// Area kerja "Shift" (F-06, F-11): ringkasan shift yang sedang berjalan, laporan X, dan tutup shift. Kasir yang
/// bertugas bisa berganti (ketuk nama kasir di bilah atas) tanpa menutup shift. Laporan X & formulir tutup shift
/// dibuka sebagai panel oleh bingkai ruang kerja.
class LayarShift extends StatelessWidget {
  const LayarShift({
    super.key,
    required this.shift,
    required this.kasir,
    required this.saatTutupShift,
    required this.saatLaporanX,
  });

  final BarisShift shift;
  final StafLokal kasir;
  final VoidCallback saatTutupShift;
  final VoidCallback saatLaporanX;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return IsiAreaKerja(
      judul: 'Shift',
      aksi: [
        OutlinedButton.icon(
          onPressed: saatLaporanX,
          icon: const Icon(Icons.summarize_outlined),
          label: const Text('Laporan X'),
        ),
        FilledButton.icon(
          onPressed: saatTutupShift,
          icon: const Icon(Icons.lock_clock_outlined),
          label: const Text('Tutup shift'),
        ),
      ],
      anak: [
        DeretKartuAngka(
          kartu: [
            KartuAngka(label: 'Dibuka oleh', ikon: Icons.person_outline, nilai: Text(shift.NamaKasir)),
            KartuAngka(
              label: 'Dibuka',
              ikon: Icons.schedule,
              nilai: Text(FormatWaktu.FormatTanggalJam(shift.DibukaPada)),
            ),
            KartuAngka(
              label: 'Kas awal',
              ikon: Icons.account_balance_wallet_outlined,
              nilai: TeksUang(Uang.Dari(shift.KasAwal)),
            ),
            KartuAngka(
              label: 'Jenis shift',
              ikon: Icons.groups_outlined,
              nilai: Text(shift.Bersama ? 'Bersama' : 'Per kasir'),
            ),
            KartuAngka(label: 'Kasir bertugas', ikon: Icons.badge_outlined, nilai: Text(kasir.nama)),
          ],
        ),
        const SizedBox(height: TokenJarak.jarak8),
        Text(
          'Shift tetap terbuka saat kasir berganti atau layar dikunci. Tutup shift setelah menghitung uang di laci.',
          style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
        ),
      ],
    );
  }
}
