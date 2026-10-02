import 'package:flutter/material.dart';
import 'package:inti/Inti.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';
import 'TeksKode.dart';
import 'TeksUang.dart';

/// Baris produk katalog layar Jual dalam tampilan **daftar** (K-8, §5.1): mode kasir Retail & Grosir bekerja dengan
/// ribuan SKU dan pemindai, jadi nama, SKU, dan harga yang rapat lebih berguna daripada ubin bergambar. Perilakunya
/// sama dengan [UbinProduk]: ketuk menambah, tahan untuk aksi tambahan, [keterangan] tampil sebagai teks berikon
/// (bukan warna saja, §17.6.11), [nonaktif] tetap bisa diketuk agar kasir melihat alasannya.
class BarisProduk extends StatelessWidget {
  const BarisProduk({
    super.key,
    required this.nama,
    required this.harga,
    required this.saatDiketuk,
    this.sku,
    this.keterangan,
    this.nonaktif = false,
    this.saatDitahan,
  });

  final String nama;

  /// Null = harga belum diatur.
  final Uang? harga;
  final VoidCallback saatDiketuk;
  final String? sku;
  final String? keterangan;
  final bool nonaktif;
  final VoidCallback? saatDitahan;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final warnaTeks = nonaktif ? warna.teksSekunder : warna.teksUtama;
    final hargaTeks = harga;
    final kecil = teks.bodySmall?.copyWith(color: warna.teksSekunder);
    return Semantics(
      button: true,
      label: [nama, ?sku, hargaTeks == null ? 'harga belum diatur' : hargaTeks.FormatRupiah(), ?keterangan].join(', '),
      excludeSemantics: true,
      onLongPress: saatDitahan,
      child: InkWell(
        onTap: saatDiketuk,
        onLongPress: saatDitahan,
        child: Container(
          constraints: const BoxConstraints(minHeight: TokenJarak.targetSentuh + TokenJarak.jarak8),
          padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak12, vertical: TokenJarak.jarak8),
          decoration: BoxDecoration(
            border: Border(
              bottom: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
            ),
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      nama,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: teks.bodyMedium?.copyWith(color: warnaTeks),
                    ),
                    if (sku case final String kode) TeksKode(kode, gaya: kecil),
                    if (keterangan case final String tanda)
                      Text.rich(
                        TextSpan(
                          children: [
                            WidgetSpan(
                              alignment: PlaceholderAlignment.middle,
                              child: Padding(
                                padding: const EdgeInsets.only(right: 2),
                                child: Icon(
                                  nonaktif ? Icons.block : Icons.tune,
                                  size: TokenJarak.ikonKecil,
                                  color: nonaktif ? warna.bahaya : warna.teksSekunder,
                                ),
                              ),
                            ),
                            TextSpan(text: tanda),
                          ],
                        ),
                        style: kecil,
                      ),
                  ],
                ),
              ),
              const SizedBox(width: TokenJarak.jarak12),
              // Nominal besar di layar sempit mengecil, tidak meluap.
              Flexible(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerRight,
                  child: hargaTeks == null
                      ? Text('Harga belum diatur', style: teks.bodySmall?.copyWith(color: warna.peringatan))
                      : TeksUang(
                          hargaTeks,
                          gaya: teks.labelLarge?.copyWith(color: warnaTeks, fontWeight: FontWeight.w700),
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
