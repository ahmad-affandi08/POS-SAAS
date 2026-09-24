import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';

/// Status sinkron (PRD §18): jumlah data belum terkirim dan daftar "Perlu Tindakan" (ditolak server) beserta
/// alasannya. Item bisa dikirim ulang setelah penyebabnya diperbaiki di back-office.
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

    return Scaffold(
      appBar: AppBar(title: const Text('Status sinkron')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text(
            tertunda == 0 ? 'Semua data sudah terkirim.' : '$tertunda data belum terkirim.',
            style: teks.titleMedium,
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerLeft,
            child: SizedBox(
              height: 48,
              child: FilledButton(
                onPressed: _sibuk ? null : _Kirim,
                child: Text(_sibuk ? 'Mengirim…' : 'Kirim sekarang'),
              ),
            ),
          ),
          if (_pesan != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_pesan!)),
          const SizedBox(height: 24),
          Text('Perlu tindakan', style: teks.titleMedium),
          const SizedBox(height: 8),
          if (perlu.isEmpty)
            Text('Tidak ada data yang ditolak server.', style: teks.bodyMedium?.copyWith(color: warna.teksSekunder))
          else
            for (final b in perlu)
              Card(
                child: ListTile(
                  title: Text(b.Jenis == 'Shift.Buka' ? 'Buka shift' : 'Kas masuk/keluar'),
                  subtitle: Text(b.PesanGalat ?? 'Ditolak server.', style: TextStyle(color: warna.bahaya)),
                  trailing: TextButton(
                    onPressed: () async {
                      await ref.read(penyediaRepositori).CobaLagi(b.Uuid, ref.read(penyediaJam)());
                      await _Kirim();
                    },
                    child: const Text('Kirim ulang'),
                  ),
                ),
              ),
        ],
      ),
    );
  }
}
