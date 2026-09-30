import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Penjualan/LayananPesananOnline.dart';

/// F-17: pesanan toko online outlet ini yang belum ditagihkan (perlu online), lalu muat ke keranjang kanal `Online`.
/// Pesanan yang sudah dibayar di muka membawa uang mukanya, sehingga panel Bayar otomatis mengisi baris Uang muka dan
/// kasir hanya menagih sisanya (biasanya nol).
class LembarPesananOnline extends ConsumerStatefulWidget {
  const LembarPesananOnline({super.key, required this.saatDimuat});

  static const String judul = 'Pesanan toko online';

  /// Dipanggil setelah keranjang terisi (pindah ke layar Jual).
  final VoidCallback saatDimuat;

  @override
  ConsumerState<LembarPesananOnline> createState() => _LembarPesananOnlineState();
}

class _LembarPesananOnlineState extends ConsumerState<LembarPesananOnline> {
  HasilPesananOnline? _hasil;
  String? _galat;
  bool _sibuk = false;

  @override
  void initState() {
    super.initState();
    unawaited(_Muat());
  }

  Future<void> _Muat() async {
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      final hasil = await ref.read(penyediaLayananPesananOnline).AmbilAktif();
      if (mounted) {
        setState(() => _hasil = hasil);
      }
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _sibuk = false);
      }
    }
  }

  Future<void> _Tagih(PesananOnlinePos pesanan) async {
    final hasil = _hasil;
    if (hasil == null) {
      return;
    }
    if (!ref.read(penyediaKeranjang).CekKosong) {
      setState(() => _galat = 'Keranjang masih berisi. Selesaikan, tahan, atau batalkan transaksi itu dulu.');
      return;
    }
    try {
      final katalog = await ref.read(penyediaKatalog.future);
      final k = await ref.read(penyediaKonteksPenjualan.future);
      final keranjang = ref.read(penyediaLayananPesananOnline).MuatKeKeranjang(pesanan, hasil, katalog, k);
      ref.read(penyediaKeranjang.notifier).Ganti(keranjang);
      widget.saatDimuat();
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final pesanan = _hasil?.pesanan ?? const <PesananOnlinePos>[];
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Pesanan dari toko online yang belum ditagihkan di outlet ini. Perlu koneksi internet.',
                style: teks.bodySmall,
              ),
            ),
            SizedBox(
              height: TokenJarak.targetSentuh,
              child: OutlinedButton.icon(
                onPressed: _sibuk ? null : () => unawaited(_Muat()),
                icon: const Icon(Icons.refresh),
                label: Text(_sibuk ? 'Memuat…' : 'Muat ulang'),
              ),
            ),
          ],
        ),
        if (_galat != null)
          Padding(
            padding: const EdgeInsets.only(top: TokenJarak.jarak8),
            child: Text(_galat!, style: TextStyle(color: warna.bahaya)),
          ),
        if (!_sibuk && _galat == null && pesanan.isEmpty)
          Padding(
            padding: const EdgeInsets.only(top: TokenJarak.jarak12),
            child: Text('Belum ada pesanan toko online yang menunggu ditagihkan.', style: teks.bodyMedium),
          ),
        for (final p in pesanan) _KartuPesanan(pesanan: p, saatTagih: () => unawaited(_Tagih(p))),
      ],
    );
  }
}

class _KartuPesanan extends StatelessWidget {
  const _KartuPesanan({required this.pesanan, required this.saatTagih});

  final PesananOnlinePos pesanan;
  final VoidCallback saatTagih;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final alasan = LayananPesananOnline.AlasanBelumBisaDitagih(pesanan);
    final sisa = Uang.Dari(pesanan.sisaUangMuka);
    return Padding(
      padding: const EdgeInsets.only(top: TokenJarak.jarak12),
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: Border.all(color: warna.garis, width: TokenJarak.tebalGaris),
          borderRadius: BorderRadius.circular(TokenJarak.jarak8),
        ),
        child: Padding(
          padding: const EdgeInsets.all(TokenJarak.jarak12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TeksKode(pesanan.nomor, gaya: teks.titleSmall),
              Text(
                [
                  pesanan.namaPelanggan,
                  pesanan.CekKirim ? 'dikirim' : 'ambil sendiri',
                  pesanan.status.toLowerCase(),
                ].join(' · '),
                style: teks.bodySmall,
              ),
              Text(
                '${pesanan.baris.length} barang · ${Uang.Dari(pesanan.total).FormatRupiah()}',
                style: teks.bodyMedium,
              ),
              if (pesanan.sudahDibayar)
                Text(
                  sisa.Bandingkan(Uang.Nol()) > 0
                      ? 'Sudah dibayar online · uang muka ${sisa.FormatRupiah()}'
                      : 'Sudah dibayar online · uang mukanya sudah terpakai',
                  style: teks.bodySmall,
                )
              else
                Text('Belum dibayar · tagih penuh di kasir', style: teks.bodySmall),
              if (alasan != null)
                Padding(
                  padding: const EdgeInsets.only(top: TokenJarak.jarak8),
                  child: Text(alasan, style: TextStyle(color: warna.teksSekunder)),
                ),
              const SizedBox(height: TokenJarak.jarak8),
              Align(
                alignment: Alignment.centerRight,
                child: SizedBox(
                  height: TokenJarak.targetSentuh,
                  child: OutlinedButton(
                    onPressed: alasan == null ? saatTagih : null,
                    child: Text('Tagih ${pesanan.nomor.split('-').last}'),
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
