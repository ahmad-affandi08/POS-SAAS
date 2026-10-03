import 'package:flutter/material.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';
import 'BilahStatus.dart';

/// Kartu angka ringkas: label kecil di atas, nilai tebal di bawah, ikon opsional. Dipakai layar non-Jual (Kas, Shift,
/// Sinkron, Riwayat) supaya angka penting terbaca sekilas dalam satu deret, bukan baris "label .... nilai" yang
/// memanjang ke bawah dan meninggalkan ruang kosong di samping.
///
/// [nada] hanya menegaskan; teks nilai & label selalu tampil (§17.6.11).
class KartuAngka extends StatelessWidget {
  const KartuAngka({
    super.key,
    required this.label,
    required this.nilai,
    this.ikon,
    this.keterangan,
    this.nada = NadaStatus.Netral,
    this.tebal = false,
  });

  final String label;

  /// Isi nilai: biasanya `Text` atau `TeksUang`; gaya teksnya diwarisi dari kartu.
  final Widget nilai;
  final IconData? ikon;

  /// Satu baris kecil di bawah nilai (misal "3 transaksi"); null = tidak ada.
  final String? keterangan;
  final NadaStatus nada;

  /// Kartu utama (misal "Perkiraan kas di laci"): bergaris warna merek supaya menonjol di antara deretnya.
  final bool tebal;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final warnaNada = switch (nada) {
      NadaStatus.Netral => warna.teksUtama,
      NadaStatus.Sukses => warna.sukses,
      NadaStatus.Peringatan => warna.peringatan,
      NadaStatus.Bahaya => warna.bahaya,
      NadaStatus.Info => warna.info,
    };
    return DecoratedBox(
      decoration: BoxDecoration(
        color: warna.permukaan,
        border: Border.all(color: tebal ? warna.brand : warna.garis, width: TokenJarak.tebalGaris),
        borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak12, vertical: TokenJarak.jarak8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                if (ikon != null) ...[
                  Icon(ikon, size: TokenJarak.ikonKecil, color: warna.teksSekunder),
                  const SizedBox(width: TokenJarak.jarak4),
                ],
                Expanded(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 2),
            DefaultTextStyle.merge(
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: teks.titleMedium?.copyWith(color: warnaNada, fontWeight: FontWeight.w700),
              child: FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft, child: nilai),
            ),
            if (keterangan case final String isi)
              Text(
                isi,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
              ),
          ],
        ),
      ),
    );
  }
}

/// Deret [KartuAngka] yang membagi habis lebarnya: jumlah kolom dari lebar tersedia (kartu ≥ [lebarMinimum]),
/// tinggi kartu dalam satu baris disamakan. Di HP 360dp jadi 2 kolom, di tablet 4, di desktop 5–6.
class DeretKartuAngka extends StatelessWidget {
  const DeretKartuAngka({super.key, required this.kartu, this.lebarMinimum = 160});

  final List<Widget> kartu;
  final double lebarMinimum;

  static int HitungKolom(double lebar, double lebarMinimum, int jumlah) {
    final muat = ((lebar + TokenJarak.jarak8) / (lebarMinimum + TokenJarak.jarak8)).floor().clamp(1, 8);
    return jumlah == 0 ? 1 : muat.clamp(1, jumlah);
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, batas) {
        final kolom = HitungKolom(batas.maxWidth, lebarMinimum, kartu.length);
        final baris = <Widget>[];
        for (var i = 0; i < kartu.length; i += kolom) {
          final isi = kartu.sublist(i, (i + kolom).clamp(0, kartu.length));
          baris.add(
            IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (var j = 0; j < kolom; j++) ...[
                    if (j > 0) const SizedBox(width: TokenJarak.jarak8),
                    Expanded(child: j < isi.length ? isi[j] : const SizedBox.shrink()),
                  ],
                ],
              ),
            ),
          );
        }
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (final (i, b) in baris.indexed) ...[if (i > 0) const SizedBox(height: TokenJarak.jarak8), b],
          ],
        );
      },
    );
  }
}
