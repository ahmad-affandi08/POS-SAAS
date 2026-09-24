import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Domain/GalatKasir.dart';
import '../Domain/Sesi/StafLokal.dart';
import 'Komponen/PapanPin.dart';

/// F-06 langkah 1: pilih nama kasir lalu masukkan PIN (bisa tanpa internet).
class LayarPilihKasir extends ConsumerStatefulWidget {
  const LayarPilihKasir({super.key});

  @override
  ConsumerState<LayarPilihKasir> createState() => _LayarPilihKasirState();
}

class _LayarPilihKasirState extends ConsumerState<LayarPilihKasir> {
  StafLokal? _dipilih;
  bool _sibuk = false;
  String? _galat;

  Future<void> _Masuk(String pin) async {
    final staf = _dipilih;
    if (staf == null) {
      return;
    }
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      await ref.read(penyediaSesi.notifier).Masuk(staf, pin);
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

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final identitas = ref.watch(penyediaIdentitas).value;
    final staf = ref.watch(penyediaStaf);

    return Scaffold(
      appBar: AppBar(
        title: Text(identitas == null ? 'Kasir' : '${identitas.outlet} · ${identitas.perangkat}'),
        actions: [
          TextButton(
            onPressed: () => ref.read(penyediaSesi.notifier).SegarkanData(),
            child: const Text('Perbarui data kasir'),
          ),
        ],
      ),
      body: Center(
        child: _dipilih == null
            ? staf.when(
                loading: () => const CircularProgressIndicator(),
                error: (galat, _) => Text('Data kasir tidak bisa dimuat: $galat'),
                data: (daftar) => daftar.isEmpty
                    ? Padding(
                        padding: const EdgeInsets.all(24),
                        child: Text(
                          'Belum ada kasir untuk outlet ini. Tambahkan pengguna di back-office, lalu ketuk "Perbarui data kasir".',
                          textAlign: TextAlign.center,
                          style: teks.bodyLarge,
                        ),
                      )
                    : SingleChildScrollView(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          children: [
                            Text('Siapa yang bertugas?', style: teks.headlineSmall),
                            const SizedBox(height: 16),
                            Wrap(
                              spacing: 12,
                              runSpacing: 12,
                              alignment: WrapAlignment.center,
                              children: [
                                for (final s in daftar)
                                  SizedBox(
                                    width: 200,
                                    height: 64,
                                    child: OutlinedButton(
                                      onPressed: () => setState(() {
                                        _dipilih = s;
                                        _galat = null;
                                      }),
                                      child: Text(s.nama, textAlign: TextAlign.center),
                                    ),
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
              )
            : SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text('PIN ${_dipilih!.nama}', style: teks.headlineSmall),
                    const SizedBox(height: 16),
                    PapanPin(saatSelesai: _Masuk, sibuk: _sibuk, pesanGalat: _galat),
                    const SizedBox(height: 16),
                    TextButton(
                      onPressed: _sibuk ? null : () => setState(() => _dipilih = null),
                      child: Text('Ganti kasir', style: TextStyle(color: warna.brand)),
                    ),
                  ],
                ),
              ),
      ),
    );
  }
}
