import 'package:flutter/material.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';
import 'BilahStatus.dart';

/// Lencana teks singkat berbingkai (misal tanda golongan obat "K", "OWA", "P" di ubin produk & baris keranjang,
/// Apotek §9.5). Teks selalu tampil; warna hanya penegas [nada] (§17.6.11: tetap terbaca bila warna dihapus).
/// [label] = arti lengkap untuk pembaca layar (misal "Obat keras"). Bukan tombol, jadi tanpa target sentuh.
class LencanaTeks extends StatelessWidget {
  const LencanaTeks({super.key, required this.teks, required this.label, this.nada = NadaStatus.Netral});

  final String teks;
  final String label;
  final NadaStatus nada;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final gaya = Theme.of(context).textTheme.labelSmall;
    final warnaNada = switch (nada) {
      NadaStatus.Netral => warna.teksSekunder,
      NadaStatus.Sukses => warna.sukses,
      NadaStatus.Peringatan => warna.peringatan,
      NadaStatus.Bahaya => warna.bahaya,
      NadaStatus.Info => warna.info,
    };
    return Semantics(
      label: label,
      excludeSemantics: true,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak4, vertical: 1),
        decoration: BoxDecoration(
          color: warna.permukaan,
          border: Border.all(color: warnaNada, width: TokenJarak.tebalGaris),
          borderRadius: BorderRadius.circular(TokenJarak.radiusKontrol),
        ),
        child: Text(
          teks,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: gaya?.copyWith(color: warnaNada, fontWeight: FontWeight.w700),
        ),
      ),
    );
  }
}
