import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Sesi/StafLokal.dart';
import 'JamRuangKerja.dart';

/// Bilah atas ruang kerja (PRD §17.2.7): logo tanda PAYOU, outlet · perangkat, nama kasir (ketuk → ganti kasir tanpa
/// menutup shift), jam, dan tombol Kunci.
class BilahAtasRuangKerja extends ConsumerWidget {
  const BilahAtasRuangKerja({super.key, required this.kasir, required this.saatGantiKasir, required this.saatKunci});

  static const double tinggi = 56;

  final StafLokal kasir;
  final VoidCallback saatGantiKasir;
  final VoidCallback saatKunci;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final identitas = ref.watch(penyediaIdentitas).value;
    final sempit = MediaQuery.sizeOf(context).width < 600;
    final lokasi = identitas == null
        ? ''
        : [identitas.outlet, identitas.perangkat].where((b) => b.isNotEmpty).join(' · ');

    return Material(
      color: warna.permukaan,
      child: Container(
        height: tinggi,
        padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak8),
        decoration: BoxDecoration(
          border: Border(
            bottom: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
          ),
        ),
        child: Row(
          children: [
            const SizedBox(width: TokenJarak.jarak8),
            const LogoMerek.ikon(tinggi: 32),
            const SizedBox(width: TokenJarak.jarak12),
            Expanded(
              child: Text(
                lokasi,
                style: teks.labelLarge,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                softWrap: false,
              ),
            ),
            const SizedBox(width: TokenJarak.jarak8),
            ConstrainedBox(
              constraints: BoxConstraints(maxWidth: sempit ? 136 : 280),
              child: Tooltip(
                message: 'Ganti kasir',
                child: TextButton.icon(
                  onPressed: saatGantiKasir,
                  style: TextButton.styleFrom(foregroundColor: warna.teksUtama),
                  icon: const Icon(Icons.person_outline, size: TokenJarak.ikonSedang),
                  label: Text(kasir.nama, maxLines: 1, overflow: TextOverflow.ellipsis, softWrap: false),
                ),
              ),
            ),
            const SizedBox(width: TokenJarak.jarak8),
            JamRuangKerja(gaya: teks.labelLarge),
            const SizedBox(width: TokenJarak.jarak4),
            if (sempit)
              IconButton(
                tooltip: 'Kunci',
                onPressed: saatKunci,
                icon: Icon(Icons.lock_outline, color: warna.teksUtama),
              )
            else
              TextButton.icon(
                onPressed: saatKunci,
                style: TextButton.styleFrom(foregroundColor: warna.teksUtama),
                icon: const Icon(Icons.lock_outline, size: TokenJarak.ikonSedang),
                label: const Text('Kunci'),
              ),
          ],
        ),
      ),
    );
  }
}
