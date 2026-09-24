import 'package:flutter/material.dart';
import 'package:inti/Inti.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';
import 'TeksUang.dart';

/// Satu baris keranjang (PRD §17.2.7): nama & total di baris atas, rincian (satuan, pilihan, catatan, diskon) dan
/// tombol kurang/tambah di baris bawah. Target sentuh 48dp. Ketuk area nama untuk mengubah item.
class BarisKeranjang extends StatelessWidget {
  const BarisKeranjang({
    super.key,
    required this.nama,
    required this.jumlah,
    required this.total,
    required this.saatDiketuk,
    required this.saatTambah,
    required this.saatKurang,
    this.rincian = const [],
  });

  final String nama;

  /// Jumlah terformat (misal "2" atau "1,5").
  final String jumlah;
  final Uang total;
  final List<String> rincian;
  final VoidCallback saatDiketuk;
  final VoidCallback saatTambah;
  final VoidCallback saatKurang;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    return Material(
      color: warna.permukaan,
      child: InkWell(
        onTap: saatDiketuk,
        child: Container(
          padding: const EdgeInsets.fromLTRB(TokenJarak.jarak16, TokenJarak.jarak8, TokenJarak.jarak8, 0),
          decoration: BoxDecoration(
            border: Border(
              bottom: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(nama, maxLines: 2, overflow: TextOverflow.ellipsis, style: teks.labelLarge),
                  ),
                  const SizedBox(width: TokenJarak.jarak8),
                  Padding(
                    padding: const EdgeInsets.only(right: TokenJarak.jarak8),
                    child: TeksUang(total, gaya: teks.labelLarge),
                  ),
                ],
              ),
              Row(
                children: [
                  Expanded(
                    child: rincian.isEmpty
                        ? const SizedBox.shrink()
                        : Text(
                            rincian.join(' · '),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: teks.bodySmall,
                          ),
                  ),
                  IconButton(
                    tooltip: 'Kurangi $nama',
                    onPressed: saatKurang,
                    icon: const Icon(Icons.remove),
                    constraints: const BoxConstraints.tightFor(
                      width: TokenJarak.targetSentuh,
                      height: TokenJarak.targetSentuh,
                    ),
                  ),
                  ConstrainedBox(
                    constraints: const BoxConstraints(minWidth: 32),
                    child: Text(
                      jumlah,
                      textAlign: TextAlign.center,
                      style: teks.labelLarge?.copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Tambah $nama',
                    onPressed: saatTambah,
                    icon: const Icon(Icons.add),
                    constraints: const BoxConstraints.tightFor(
                      width: TokenJarak.targetSentuh,
                      height: TokenJarak.targetSentuh,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
