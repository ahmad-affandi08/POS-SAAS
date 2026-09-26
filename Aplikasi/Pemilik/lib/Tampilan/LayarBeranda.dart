import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/KlienPemilik.dart';
import 'BilahSaringan.dart';
import 'FormatTampilan.dart';
import 'KeadaanData.dart';

/// Beranda OWN-02 (§17.6 "Hari ini untung berapa, ada masalah apa?"): satu angka besar omzet + perbandingan kemarin &
/// minggu lalu, transaksi, rata-rata, laba kotor; lalu hal yang butuh tindakan, omzet per outlet, per jam, dan produk
/// terlaris.
class LayarBeranda extends ConsumerWidget {
  const LayarBeranda({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dasbor = ref.watch(penyediaDasbor);
    return RefreshIndicator(
      onRefresh: () => ref.refresh(penyediaDasbor.future),
      child: KeadaanData(
        nilai: dasbor,
        saatCobaLagi: () => ref.invalidate(penyediaDasbor),
        isi: (d) => _IsiBeranda(dasbor: d),
      ),
    );
  }
}

class _IsiBeranda extends StatelessWidget {
  const _IsiBeranda({required this.dasbor});

  final DasborPemilik dasbor;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final d = dasbor;

    Widget Perbandingan(String label, String pembanding) {
      final ubah = FormatTampilan.Perubahan(d.omzet, pembanding);
      final naik = ubah != null && !ubah.startsWith('-');
      return Row(
        children: [
          if (ubah != null)
            Icon(naik ? Icons.trending_up : Icons.trending_down, size: 18, color: naik ? warna.sukses : warna.bahaya),
          const SizedBox(width: TokenJarak.jarak4),
          Expanded(
            child: Text(
              ubah == null
                  ? '$label: ${FormatTampilan.Rupiah(pembanding)}'
                  : '$ubah dari $label (${FormatTampilan.Rupiah(pembanding)})',
              style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
            ),
          ),
        ],
      );
    }

    Widget Angka(String label, String nilai) => Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: teks.bodySmall),
          Text(nilai, style: teks.titleMedium),
        ],
      ),
    );

    Widget Judul(String t) => Padding(
      padding: const EdgeInsets.only(top: TokenJarak.jarak24, bottom: TokenJarak.jarak8),
      child: Text(t, style: teks.titleMedium),
    );

    final maksJam = d.perJam.fold<double>(0, (m, j) {
      final v = double.tryParse(j.omzet) ?? 0;
      return v > m ? v : m;
    });

    return ListView(
      padding: const EdgeInsets.all(TokenJarak.jarak16),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        BilahSaringan(outlet: d.outlet),
        const SizedBox(height: TokenJarak.jarak16),
        Text('Omzet', style: teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
        Text(FormatTampilan.Rupiah(d.omzet), style: teks.displaySmall),
        const SizedBox(height: TokenJarak.jarak4),
        Perbandingan('kemarin', d.omzetKemarin),
        Perbandingan('minggu lalu', d.omzetMingguLalu),
        const SizedBox(height: TokenJarak.jarak16),
        Row(
          children: [
            Angka('Transaksi', '${d.transaksi}'),
            Angka('Rata-rata', FormatTampilan.Rupiah(d.rataRata)),
            if (d.labaKotor != null) Angka('Laba kotor', FormatTampilan.Rupiah(d.labaKotor!)),
          ],
        ),
        Judul('Perlu tindakan'),
        if (d.perluTindakan.isEmpty)
          Row(
            children: [
              Icon(Icons.check_circle_outline, color: warna.sukses),
              const SizedBox(width: TokenJarak.jarak8),
              Expanded(child: Text('Tidak ada yang perlu ditindaklanjuti.', style: teks.bodyMedium)),
            ],
          )
        else
          for (final h in d.perluTindakan)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.warning_amber_rounded, color: warna.peringatan),
              title: Text(h.judul),
              subtitle: Text(h.keterangan),
            ),
        if (d.perOutlet.length > 1) ...[
          Judul('Per outlet'),
          for (final o in d.perOutlet)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(o.nama),
              subtitle: Text('${o.transaksi ?? 0} transaksi'),
              trailing: Text(FormatTampilan.Rupiah(o.omzet), style: teks.titleSmall),
            ),
        ],
        if (d.perJam.isNotEmpty && maksJam > 0) ...[
          Judul('Per jam'),
          SizedBox(
            height: 120,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                for (final j in d.perJam)
                  Expanded(
                    child: Tooltip(
                      message: 'Jam ${j.nama}: ${FormatTampilan.Rupiah(j.omzet)}',
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 1),
                        child: FractionallySizedBox(
                          heightFactor: ((double.tryParse(j.omzet) ?? 0) / maksJam).clamp(0.02, 1),
                          alignment: Alignment.bottomCenter,
                          child: ColoredBox(color: warna.brand),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Jam ${d.perJam.first.nama}', style: teks.bodySmall),
              Text('Jam ${d.perJam.last.nama}', style: teks.bodySmall),
            ],
          ),
        ],
        if (d.produkTeratas.isNotEmpty) ...[
          Judul('Produk terlaris'),
          for (final p in d.produkTeratas)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(p.nama),
              subtitle: p.jumlah == null ? null : Text('${_Jumlah(p.jumlah!)} terjual'),
              trailing: Text(FormatTampilan.Rupiah(p.omzet), style: teks.titleSmall),
            ),
        ],
      ],
    );
  }

  static String _Jumlah(String nilai) {
    var t = nilai;
    if (t.contains('.')) {
      t = t.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
    }
    return t.replaceAll('.', ',');
  }
}
