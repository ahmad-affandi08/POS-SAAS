import 'package:flutter/material.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Data/BasisData/BasisDataKasir.dart';
import '../Domain/Sesi/StafLokal.dart';
import 'Komponen/FormatWaktu.dart';
import 'RuangKerja/IsiAreaKerja.dart';

/// Area kerja "Shift" (F-06): ringkasan shift yang sedang berjalan. Kasir yang bertugas bisa berganti (ketuk nama
/// kasir di bilah atas) tanpa menutup shift. Tutup shift & laporan shift menyusul bersama flow tutup shift.
class LayarShift extends StatelessWidget {
  const LayarShift({super.key, required this.shift, required this.kasir});

  final BarisShift shift;
  final StafLokal kasir;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return IsiAreaKerja(
      judul: 'Shift',
      anak: [
        KotakPanel(
          anak: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _BarisInfo(label: 'Dibuka oleh', isi: Text(shift.NamaKasir)),
              _BarisInfo(label: 'Dibuka', isi: Text(FormatWaktu.FormatTanggalJam(shift.DibukaPada))),
              _BarisInfo(label: 'Kas awal', isi: TeksUang(Uang.Dari(shift.KasAwal))),
              _BarisInfo(label: 'Jenis shift', isi: Text(shift.Bersama ? 'Bersama' : 'Per kasir')),
              _BarisInfo(label: 'Kasir bertugas', isi: Text(kasir.nama)),
            ],
          ),
        ),
        const SizedBox(height: TokenJarak.jarak16),
        Text(
          'Tutup shift dan laporan shift belum tersedia di versi aplikasi ini. Shift tetap terbuka saat kasir '
          'berganti atau layar dikunci.',
          style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
        ),
      ],
    );
  }
}

class _BarisInfo extends StatelessWidget {
  const _BarisInfo({required this.label, required this.isi});

  final String label;
  final Widget isi;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(label, style: teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
          ),
          const SizedBox(width: TokenJarak.jarak16),
          Flexible(
            child: DefaultTextStyle.merge(textAlign: TextAlign.right, child: isi),
          ),
        ],
      ),
    );
  }
}
