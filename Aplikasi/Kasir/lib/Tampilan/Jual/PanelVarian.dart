import 'package:flutter/material.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Domain/Katalog/KatalogLokal.dart';

/// Panel pemilih varian (K-9, §9.4): produk induk varian (ukuran × warna) dipilih per atribut lewat chip; begitu
/// kombinasinya lengkap, varian yang cocok tampil dengan harga dan tombol tambah. Nilai yang tidak punya varian aktif
/// untuk pilihan atribut lain dinonaktifkan, jadi kasir tidak bisa memilih kombinasi yang tidak dijual. Varian tanpa
/// atribut (data lama) tampil sebagai daftar biasa.
class PanelVarian extends StatefulWidget {
  const PanelVarian({
    super.key,
    required this.induk,
    required this.varian,
    required this.hargaDari,
    required this.saatPilih,
  });

  final ProdukJual induk;
  final List<ProdukJual> varian;
  final Uang? Function(ProdukJual varian) hargaDari;
  final void Function(ProdukJual varian) saatPilih;

  @override
  State<PanelVarian> createState() => _PanelVarianState();
}

class _PanelVarianState extends State<PanelVarian> {
  final Map<String, String> _terpilih = {};

  /// Urutan atribut: definisi induk, lalu atribut anak yang tidak tercantum di definisi (data lama).
  late final List<({String nama, List<String> nilai})> _atribut = () {
    final hasil = <({String nama, List<String> nilai})>[];
    final ada = <String>{};
    for (final a in widget.induk.definisiVarian) {
      final nilai = [
        for (final n in a.nilai)
          if (widget.varian.any((v) => v.atributVarian[a.nama] == n)) n,
      ];
      if (nilai.isNotEmpty) {
        hasil.add((nama: a.nama, nilai: nilai));
        ada.add(a.nama);
      }
    }
    for (final v in widget.varian) {
      for (final e in v.atributVarian.entries) {
        if (ada.contains(e.key)) {
          continue;
        }
        final i = hasil.indexWhere((a) => a.nama == e.key);
        if (i < 0) {
          hasil.add((nama: e.key, nilai: [e.value]));
        } else if (!hasil[i].nilai.contains(e.value)) {
          hasil[i].nilai.add(e.value);
        }
      }
    }
    return hasil;
  }();

  bool _CekCocok(ProdukJual v, Map<String, String> pilihan) =>
      pilihan.entries.every((e) => v.atributVarian[e.key] == e.value);

  /// Nilai [nilai] atribut [nama] masih punya varian bila digabung pilihan atribut lain.
  bool _CekTersedia(String nama, String nilai) {
    final uji = {..._terpilih, nama: nilai};
    return widget.varian.any((v) => v.AmbilAlasanTidakBisaDijual() == null && _CekCocok(v, uji));
  }

  ProdukJual? get _hasil {
    if (_atribut.isEmpty || _terpilih.length < _atribut.length) {
      return null;
    }
    return widget.varian.where((v) => _CekCocok(v, _terpilih)).firstOrNull;
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    if (_atribut.isEmpty) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final v in widget.varian)
            BarisProduk(
              key: ValueKey('varian-${v.uuid}'),
              nama: v.nama,
              sku: v.sku,
              harga: widget.hargaDari(v),
              saatDiketuk: () => widget.saatPilih(v),
            ),
        ],
      );
    }
    final hasil = _hasil;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final a in _atribut) ...[
          Text(a.nama, style: teks.titleSmall),
          const SizedBox(height: TokenJarak.jarak8),
          Wrap(
            spacing: TokenJarak.jarak8,
            runSpacing: TokenJarak.jarak8,
            children: [
              for (final n in a.nilai)
                ChoiceChip(
                  key: ValueKey('varian-${a.nama}-$n'),
                  label: Text(n),
                  selected: _terpilih[a.nama] == n,
                  onSelected: _CekTersedia(a.nama, n) || _terpilih[a.nama] == n
                      ? (pilih) => setState(() {
                          if (pilih) {
                            _terpilih[a.nama] = n;
                            // Pilihan atribut lain yang jadi tidak cocok dilepas, bukan dibiarkan buntu.
                            _terpilih.removeWhere(
                              (k, nilai) =>
                                  k != a.nama && !widget.varian.any((v) => _CekCocok(v, {a.nama: n, k: nilai})),
                            );
                          } else {
                            _terpilih.remove(a.nama);
                          }
                        })
                      : null,
                ),
            ],
          ),
          const SizedBox(height: TokenJarak.jarak16),
        ],
        if (hasil == null)
          Text(
            'Pilih ${_atribut.where((a) => !_terpilih.containsKey(a.nama)).map((a) => a.nama.toLowerCase()).join(' dan ')}.',
            style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
          )
        else ...[
          Row(
            children: [
              Expanded(child: Text(hasil.nama, style: teks.titleSmall)),
              if (widget.hargaDari(hasil) case final harga?) TeksUang(harga, gaya: teks.titleSmall),
            ],
          ),
          if (hasil.AmbilAlasanTidakBisaDijual() case final alasan?)
            Padding(
              padding: const EdgeInsets.only(top: TokenJarak.jarak8),
              child: Text(alasan.pesan, style: teks.bodySmall?.copyWith(color: warna.bahaya)),
            ),
          const SizedBox(height: TokenJarak.jarak16),
          FilledButton(
            onPressed: hasil.AmbilAlasanTidakBisaDijual() == null ? () => widget.saatPilih(hasil) : null,
            child: const Text('Tambah ke keranjang'),
          ),
        ],
      ],
    );
  }
}
