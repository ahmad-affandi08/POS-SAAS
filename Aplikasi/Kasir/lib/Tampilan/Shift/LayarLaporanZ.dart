import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/GalatKasir.dart';
import '../Komponen/FormatWaktu.dart';
import '../Struk/BagianCetakDokumen.dart';
import 'KartuLaporanShift.dart';

/// Laporan Z (Rincian F-11): tampil setelah shift ditutup, sebelum layar buka shift berikutnya. Bertahan bila aplikasi
/// dimulai ulang sampai kasir menekan "Selesai". Laporan dicetak otomatis sekali bila cetak otomatis aktif (v1.84).
class LayarLaporanZ extends ConsumerWidget {
  const LayarLaporanZ({super.key, required this.uuidShift});

  final String uuidShift;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final laporan = ref.watch(penyediaLaporanShift(uuidShift));
    final warna = TokenWarna.AmbilDari(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Laporan tutup shift (Z)'), automaticallyImplyLeading: false),
      body: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 640),
          child: ListView(
            padding: const EdgeInsets.all(TokenJarak.jarak16),
            children: [
              laporan.when(
                loading: () => const LinearProgressIndicator(),
                error: (galat, _) => Text('Laporan tidak bisa dibaca: $galat', style: TextStyle(color: warna.bahaya)),
                data: (l) => Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    KartuLaporanShift(laporan: l),
                    const SizedBox(height: TokenJarak.jarak12),
                    // Cetak struk bagian 3b: laporan Z dicetak otomatis sekali bila cetak otomatis aktif.
                    BagianCetakDokumen(
                      kunci: 'LaporanZ:$uuidShift',
                      namaDokumen: 'laporan Z',
                      cetak: (layanan, _, _) => layanan.CetakLaporanShift(l),
                    ),
                  ],
                ),
              ),
              const _TombolAbsenPulang(),
              const SizedBox(height: TokenJarak.jarak8),
              Text(
                'Data tutup shift terkirim otomatis ke back-office saat online.',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(color: warna.teksSekunder),
              ),
            ],
          ),
        ),
      ),
      // Tombol utama menempel di bawah agar selalu terjangkau di layar sempit.
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(TokenJarak.jarak16),
          child: SizedBox(
            height: 56,
            child: FilledButton(
              onPressed: () => ref.read(penyediaLayananTutupShift).SelesaikanLaporanZ(),
              child: const Text('Selesai'),
            ),
          ),
        ),
      ),
    );
  }
}

/// Audit kemudahan pakai #31: kasir yang masih tercatat masuk bisa langsung absen pulang dari laporan tutup shift
/// (tanpa keluar ke layar Absen dan memasukkan PIN lagi; PIN sudah dipakai untuk masuk & tutup shift).
class _TombolAbsenPulang extends ConsumerStatefulWidget {
  const _TombolAbsenPulang();

  @override
  ConsumerState<_TombolAbsenPulang> createState() => _StatusTombolAbsenPulang();
}

class _StatusTombolAbsenPulang extends ConsumerState<_TombolAbsenPulang> {
  bool _masihMasuk = false;
  bool _sibuk = false;
  String? _pesan;

  @override
  void initState() {
    super.initState();
    final kasir = ref.read(penyediaSesi).kasir;
    if (kasir != null) {
      unawaited(
        ref.read(penyediaLayananAbsensi).AmbilTerbuka(kasir).then((terbuka) {
          if (mounted) {
            setState(() => _masihMasuk = terbuka != null);
          }
        }),
      );
    }
  }

  Future<void> _Absen() async {
    final kasir = ref.read(penyediaSesi).kasir;
    if (kasir == null) {
      return;
    }
    setState(() => _sibuk = true);
    try {
      final layanan = ref.read(penyediaLayananAbsensi);
      final hasil = await layanan.Catat(kasir, await layanan.AmbilSwafoto());
      if (mounted) {
        setState(() {
          _masihMasuk = false;
          _pesan = '${hasil.nama} absen pulang pukul ${FormatWaktu.FormatJam(hasil.waktu)}.';
        });
      }
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _pesan = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _sibuk = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final pesan = _pesan;
    if (!_masihMasuk && pesan == null) {
      return const SizedBox.shrink();
    }
    return Padding(
      padding: const EdgeInsets.only(top: TokenJarak.jarak8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_masihMasuk)
            OutlinedButton.icon(
              onPressed: _sibuk ? null : _Absen,
              icon: const Icon(Icons.badge_outlined),
              label: Text(_sibuk ? 'Menyimpan…' : 'Absen pulang sekarang'),
            ),
          if (pesan != null) Text(pesan),
        ],
      ),
    );
  }
}
