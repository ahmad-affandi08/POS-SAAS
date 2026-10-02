import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Domain/Dapur/PenyusunLayarAntrian.dart';

/// K-2 lanjutan (§9.2): tampilan layar panggil antrian di perangkat KDS (bisa dipasang di TV/tablet menghadap pelanggan).
/// Dua kolom bernomor besar: "Sedang disiapkan" dan "Silakan diambil"; nomor siap terbaru ditandai "Baru".
class PanelPanggilAntrian extends StatelessWidget {
  const PanelPanggilAntrian({super.key, required this.disiapkan, required this.siap, this.baru = const {}});

  final List<NomorPanggil> disiapkan;
  final List<NomorPanggil> siap;

  /// Nomor dokumen yang baru masuk kolom siap (sejak tarikan sebelumnya).
  final Set<String> baru;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, batas) {
        final sempit = batas.maxWidth < 600;
        final kolom = [
          _Kolom(judul: 'Sedang disiapkan', daftar: disiapkan, siap: false, baru: const {}),
          _Kolom(judul: 'Silakan diambil', daftar: siap, siap: true, baru: baru),
        ];
        return Padding(
          padding: const EdgeInsets.all(TokenJarak.jarak16),
          child: sempit
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(child: kolom[1]),
                    const SizedBox(height: TokenJarak.jarak16),
                    Expanded(child: kolom[0]),
                  ],
                )
              : Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(child: kolom[0]),
                    const SizedBox(width: TokenJarak.jarak16),
                    Expanded(child: kolom[1]),
                  ],
                ),
        );
      },
    );
  }
}

class _Kolom extends StatelessWidget {
  const _Kolom({required this.judul, required this.daftar, required this.siap, required this.baru});

  final String judul;
  final List<NomorPanggil> daftar;
  final bool siap;
  final Set<String> baru;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: warna.permukaan,
        borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
        border: Border.all(color: siap ? warna.sukses : warna.garis, width: siap ? 2 : TokenJarak.tebalGaris),
      ),
      child: Padding(
        padding: const EdgeInsets.all(TokenJarak.jarak16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Semantics(
              header: true,
              child: Row(
                children: [
                  Icon(
                    siap ? Icons.check_circle_outline : Icons.hourglass_top,
                    color: siap ? warna.sukses : warna.teksSekunder,
                  ),
                  const SizedBox(width: TokenJarak.jarak8),
                  Expanded(child: Text(judul, style: teks.headlineSmall)),
                ],
              ),
            ),
            const SizedBox(height: TokenJarak.jarak12),
            Expanded(
              child: daftar.isEmpty
                  ? Center(
                      child: Text(
                        siap ? 'Belum ada pesanan siap.' : 'Tidak ada pesanan yang sedang disiapkan.',
                        textAlign: TextAlign.center,
                        style: teks.bodyLarge?.copyWith(color: warna.teksSekunder),
                      ),
                    )
                  : SingleChildScrollView(
                      child: Wrap(
                        spacing: TokenJarak.jarak12,
                        runSpacing: TokenJarak.jarak12,
                        children: [
                          for (final n in daftar)
                            Semantics(
                              label:
                                  '${siap ? 'Siap diambil' : 'Disiapkan'}: ${n.nomor}${n.nama == null ? '' : ', ${n.nama}'}',
                              excludeSemantics: true,
                              child: Container(
                                key: ValueKey('antrian-${n.nomorDokumen}'),
                                constraints: const BoxConstraints(minWidth: 140),
                                padding: const EdgeInsets.symmetric(
                                  horizontal: TokenJarak.jarak16,
                                  vertical: TokenJarak.jarak12,
                                ),
                                decoration: BoxDecoration(
                                  color: siap ? warna.sukses.withValues(alpha: 0.12) : warna.latar,
                                  borderRadius: BorderRadius.circular(TokenJarak.jarak8),
                                ),
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Text(
                                      n.nomor,
                                      style: teks.displaySmall?.copyWith(
                                        fontWeight: FontWeight.bold,
                                        fontFeatures: const [FontFeature.tabularFigures()],
                                      ),
                                    ),
                                    if (n.nama case final nama?)
                                      Text(nama, style: teks.titleMedium, maxLines: 1, overflow: TextOverflow.ellipsis),
                                    if (siap && baru.contains(n.nomorDokumen))
                                      Text('Baru', style: teks.labelLarge?.copyWith(color: warna.sukses)),
                                  ],
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
