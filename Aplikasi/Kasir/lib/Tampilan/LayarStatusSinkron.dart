import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import 'RuangKerja/IsiAreaKerja.dart';

/// Status sinkron (PRD §18): jumlah data belum terkirim dan daftar "Perlu Tindakan" (ditolak server) beserta
/// alasannya. Item bisa dikirim ulang setelah penyebabnya diperbaiki di back-office. Tampil di area ruang kerja
/// (dibuka dari rel navigasi atau dengan mengetuk bilah status).
class LayarStatusSinkron extends ConsumerStatefulWidget {
  const LayarStatusSinkron({super.key});

  @override
  ConsumerState<LayarStatusSinkron> createState() => _LayarStatusSinkronState();
}

class _LayarStatusSinkronState extends ConsumerState<LayarStatusSinkron> {
  bool _sibuk = false;
  String? _pesan;

  Future<void> _Kirim() async {
    setState(() {
      _sibuk = true;
      _pesan = null;
    });
    final hasil = await ref.read(penyediaSesi.notifier).Sinkronkan();
    if (mounted) {
      setState(() {
        _sibuk = false;
        _pesan = hasil.offline
            ? 'Belum tersambung ke server. Data aman di perangkat dan akan dikirim otomatis.'
            : '${hasil.terkirim} data terkirim${hasil.ditolak > 0 ? ', ${hasil.ditolak} perlu tindakan' : ''}.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final tertunda = ref.watch(penyediaJumlahTertunda).value ?? 0;
    final perlu = ref.watch(penyediaPerluTindakan).value ?? const [];

    final koneksi = ref.watch(penyediaKoneksi);

    return IsiAreaKerja(
      judul: 'Status sinkron',
      anak: [
        Text(tertunda == 0 ? 'Semua data sudah terkirim.' : '$tertunda data belum terkirim.', style: teks.titleMedium),
        const SizedBox(height: TokenJarak.jarak4),
        Text(switch (koneksi) {
          StatusKoneksi.Online => 'Perangkat tersambung ke server.',
          StatusKoneksi.Offline => 'Perangkat sedang offline. Data tetap tersimpan dan dikirim otomatis saat online.',
          StatusKoneksi.BelumDiketahui => 'Koneksi ke server belum diperiksa.',
        }, style: teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
        const SizedBox(height: TokenJarak.jarak12),
        Align(
          alignment: Alignment.centerLeft,
          child: SizedBox(
            height: TokenJarak.targetSentuh,
            child: FilledButton(
              onPressed: _sibuk ? null : _Kirim,
              child: Text(_sibuk ? 'Mengirim…' : 'Kirim sekarang'),
            ),
          ),
        ),
        if (_pesan != null)
          Padding(
            padding: const EdgeInsets.only(top: TokenJarak.jarak8),
            child: Text(_pesan!),
          ),
        const SizedBox(height: TokenJarak.jarak24),
        Text('Perlu tindakan', style: teks.titleMedium),
        const SizedBox(height: TokenJarak.jarak8),
        if (perlu.isEmpty)
          Text('Tidak ada data yang ditolak server.', style: teks.bodyMedium?.copyWith(color: warna.teksSekunder))
        else
          for (final b in perlu)
            Padding(
              padding: const EdgeInsets.only(bottom: TokenJarak.jarak8),
              child: KotakPanel(
                anak: Row(
                  children: [
                    Icon(Icons.error_outline, size: TokenJarak.ikonSedang, color: warna.bahaya),
                    const SizedBox(width: TokenJarak.jarak12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(b.Jenis == 'Shift.Buka' ? 'Buka shift' : 'Kas masuk/keluar'),
                          Text(b.PesanGalat ?? 'Ditolak server.', style: TextStyle(color: warna.bahaya)),
                        ],
                      ),
                    ),
                    TextButton(
                      onPressed: () async {
                        await ref.read(penyediaRepositori).CobaLagi(b.Uuid, ref.read(penyediaJam)());
                        await _Kirim();
                      },
                      child: const Text('Kirim ulang'),
                    ),
                  ],
                ),
              ),
            ),
      ],
    );
  }
}
