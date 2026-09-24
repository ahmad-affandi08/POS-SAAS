import 'package:flutter/material.dart';
import 'package:inti/Inti.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenTipografi.dart';
import '../Token/TokenWarna.dart';
import 'TeksUang.dart';

/// Ubin produk di katalog layar Jual (PRD §17.2.7): ukuran seragam, inisial di atas latar netral bila tanpa foto,
/// nama maksimal dua baris, harga tabular. [keterangan] untuk penanda singkat (misal "Ada pilihan"); [nonaktif] =
/// produk belum bisa dijual (tetap bisa diketuk agar kasir melihat alasannya).
class UbinProduk extends StatelessWidget {
  const UbinProduk({
    super.key,
    required this.nama,
    required this.harga,
    required this.saatDiketuk,
    this.keterangan,
    this.nonaktif = false,
  });

  /// Tinggi ubin seragam (kisi 8dp).
  static const double tinggi = 136;

  /// Lebar maksimum ubin di grid.
  static const double lebarMaksimum = 176;

  final String nama;

  /// Null = harga belum diatur.
  final Uang? harga;
  final VoidCallback saatDiketuk;
  final String? keterangan;
  final bool nonaktif;

  /// Inisial dua huruf pertama kata (misal "Es Kopi Susu" → "EK").
  static String AmbilInisial(String nama) {
    final kata = nama.trim().split(RegExp(r'\s+')).where((k) => k.isNotEmpty).toList();
    if (kata.isEmpty) {
      return '?';
    }
    final huruf = kata.take(2).map((k) => k.characters.first.toUpperCase()).join();
    return huruf;
  }

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final warnaTeks = nonaktif ? warna.teksSekunder : warna.teksUtama;
    final hargaTeks = harga;
    return Semantics(
      button: true,
      label: [nama, hargaTeks == null ? 'harga belum diatur' : hargaTeks.FormatRupiah(), ?keterangan].join(', '),
      excludeSemantics: true,
      child: Material(
        color: warna.permukaan,
        shape: RoundedRectangleBorder(
          side: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
          borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: saatDiketuk,
          child: SizedBox(
            height: tinggi,
            child: Padding(
              padding: const EdgeInsets.all(TokenJarak.jarak12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: warna.latar,
                          border: Border.all(color: warna.garis, width: TokenJarak.tebalGaris),
                          borderRadius: BorderRadius.circular(TokenJarak.radiusKontrol),
                        ),
                        child: Text(
                          AmbilInisial(nama),
                          style: teks.labelLarge?.copyWith(color: warna.teksSekunder, fontFamily: fontMono),
                        ),
                      ),
                      if (keterangan != null) ...[
                        const SizedBox(width: TokenJarak.jarak8),
                        Expanded(
                          child: Text(keterangan!, maxLines: 2, overflow: TextOverflow.ellipsis, style: teks.bodySmall),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: TokenJarak.jarak8),
                  Expanded(
                    child: Text(
                      nama,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: teks.labelLarge?.copyWith(color: warnaTeks),
                    ),
                  ),
                  if (hargaTeks == null)
                    Text('Harga belum diatur', style: teks.bodySmall?.copyWith(color: warna.peringatan))
                  else
                    TeksUang(hargaTeks, rataKanan: false, gaya: teks.bodyMedium?.copyWith(color: warnaTeks)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
