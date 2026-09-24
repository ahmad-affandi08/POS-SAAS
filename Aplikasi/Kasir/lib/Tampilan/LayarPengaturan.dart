import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Domain/Perangkat/PengaturanPerangkat.dart';
import 'RuangKerja/IsiAreaKerja.dart';

/// Pengaturan perangkat kasir (PRD §17.2.7): ukuran tampilan, posisi keranjang, kunci otomatis, dan perbarui data
/// kasir dari back-office. Tersimpan lokal di perangkat ini dan langsung berlaku.
class LayarPengaturan extends ConsumerStatefulWidget {
  const LayarPengaturan({super.key});

  @override
  ConsumerState<LayarPengaturan> createState() => _LayarPengaturanState();
}

class _LayarPengaturanState extends ConsumerState<LayarPengaturan> {
  bool _memperbarui = false;
  String? _pesanData;

  Future<void> _Simpan(PengaturanPerangkat baru) => ref.read(penyediaPengaturanPerangkat.notifier).Simpan(baru);

  Future<void> _PerbaruiData() async {
    setState(() {
      _memperbarui = true;
      _pesanData = null;
    });
    await ref.read(penyediaSesi.notifier).SegarkanData();
    if (!mounted) {
      return;
    }
    final koneksi = ref.read(penyediaKoneksi);
    setState(() {
      _memperbarui = false;
      _pesanData = koneksi == StatusKoneksi.Offline
          ? 'Belum tersambung ke server. Data kasir terakhir tetap dipakai.'
          : 'Data kasir sudah diperbarui.';
    });
  }

  @override
  Widget build(BuildContext context) {
    final pengaturan = ref.watch(penyediaPengaturanPerangkat);
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final keterangan = teks.bodySmall;

    Widget Bagian(String judul, String penjelasan, Widget kontrol) => Padding(
      padding: const EdgeInsets.only(bottom: TokenJarak.jarak24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(judul, style: teks.titleMedium),
          const SizedBox(height: TokenJarak.jarak4),
          Text(penjelasan, style: keterangan),
          const SizedBox(height: TokenJarak.jarak12),
          kontrol,
        ],
      ),
    );

    return IsiAreaKerja(
      judul: 'Pengaturan',
      anak: [
        Bagian(
          'Ukuran tampilan',
          'Besar memperbesar semua teks 15% agar mudah dibaca dari jarak jauh.',
          SegmentedButton<UkuranTampilan>(
            showSelectedIcon: false,
            segments: [for (final u in UkuranTampilan.values) ButtonSegment(value: u, label: Text(u.label))],
            selected: {pengaturan.ukuran},
            onSelectionChanged: (pilihan) => _Simpan(pengaturan.copyWith(ukuran: pilihan.single)),
          ),
        ),
        Bagian(
          'Posisi keranjang',
          'Sisi layar untuk keranjang di layar jual, misalnya kiri untuk kasir kidal.',
          SegmentedButton<PosisiKeranjang>(
            showSelectedIcon: false,
            segments: [for (final p in PosisiKeranjang.values) ButtonSegment(value: p, label: Text(p.label))],
            selected: {pengaturan.posisiKeranjang},
            onSelectionChanged: (pilihan) => _Simpan(pengaturan.copyWith(posisiKeranjang: pilihan.single)),
          ),
        ),
        Bagian(
          'Kunci otomatis',
          'Layar terkunci bila perangkat tidak disentuh selama waktu ini. Shift tetap terbuka.',
          SizedBox(
            width: 240,
            child: DropdownButtonFormField<int>(
              key: ValueKey(pengaturan.menitKunciOtomatis),
              initialValue: pengaturan.menitKunciOtomatis,
              decoration: const InputDecoration(labelText: 'Kunci setelah diam'),
              items: [
                for (final menit in {...PengaturanPerangkat.pilihanMenitKunci, pengaturan.menitKunciOtomatis})
                  DropdownMenuItem(value: menit, child: Text('$menit menit')),
              ],
              onChanged: (menit) => menit == null ? null : _Simpan(pengaturan.copyWith(menitKunciOtomatis: menit)),
            ),
          ),
        ),
        Bagian(
          'Data kasir',
          'Ambil daftar kasir, PIN offline, dan kategori kas terbaru dari back-office.',
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                height: TokenJarak.targetSentuh,
                child: OutlinedButton(
                  onPressed: _memperbarui ? null : _PerbaruiData,
                  child: Text(_memperbarui ? 'Memperbarui…' : 'Perbarui data kasir'),
                ),
              ),
              if (_pesanData != null)
                Padding(
                  padding: const EdgeInsets.only(top: TokenJarak.jarak8),
                  child: Text(_pesanData!, style: teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
                ),
            ],
          ),
        ),
      ],
    );
  }
}
