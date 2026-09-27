import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Domain/GalatKasir.dart';

/// F-02 langkah 5: tukar kode aktivasi dari back-office (menu Perangkat) menjadi token perangkat. Butuh internet.
class LayarAktivasi extends ConsumerStatefulWidget {
  const LayarAktivasi({super.key, this.pesan});

  final String? pesan;

  @override
  ConsumerState<LayarAktivasi> createState() => _LayarAktivasiState();
}

class _LayarAktivasiState extends ConsumerState<LayarAktivasi> {
  final _kode = TextEditingController();
  bool _sibuk = false;
  String? _galat;

  @override
  void dispose() {
    _kode.dispose();
    super.dispose();
  }

  Future<void> _Pindai() async {
    final pemindai = ref.read(penyediaPemindaiQr);
    final hasil = await pemindai.Pindai(context);
    if (hasil == null || !mounted) {
      return;
    }
    // Isi QR = kode aktivasi apa adanya (PembuatQrKodeAktivasi), jadi langsung dipakai.
    _kode.text = hasil;
    await _Aktifkan();
  }

  Future<void> _Aktifkan() async {
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      await ref.read(penyediaSesi.notifier).Aktifkan(_kode.text);
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
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    // Windows & perangkat tanpa kamera: isian manual saja (mobile_scanner tidak mendukung Windows).
    final adaPemindai = ref.watch(penyediaPemindaiQr).CekTersedia();
    return Scaffold(
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Align(alignment: Alignment.centerLeft, child: LogoMerek.lengkap()),
                const SizedBox(height: 32),
                Text('Aktifkan perangkat kasir', style: teks.headlineSmall),
                const SizedBox(height: 8),
                Text(
                  adaPemindai
                      ? 'Buka back-office, menu Perangkat, lalu buat kode aktivasi untuk perangkat ini. Pindai QR-nya atau ketik kodenya.'
                      : 'Buka back-office, menu Perangkat, lalu buat kode aktivasi untuk perangkat ini.',
                  style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
                ),
                if (widget.pesan != null) ...[
                  const SizedBox(height: 16),
                  Text(widget.pesan!, style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
                ],
                const SizedBox(height: 24),
                if (adaPemindai) ...[
                  SizedBox(
                    height: 48,
                    child: OutlinedButton.icon(
                      onPressed: _sibuk ? null : _Pindai,
                      icon: const Icon(Icons.qr_code_scanner_outlined),
                      label: const Text('Pindai kode QR'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'atau ketik kodenya',
                    textAlign: TextAlign.center,
                    style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                  ),
                  const SizedBox(height: 16),
                ],
                TextField(
                  controller: _kode,
                  textCapitalization: TextCapitalization.characters,
                  decoration: InputDecoration(
                    labelText: 'Kode aktivasi',
                    errorText: _galat,
                    border: const OutlineInputBorder(),
                  ),
                  onSubmitted: (_) => _Aktifkan(),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  height: 48,
                  child: FilledButton(
                    onPressed: _sibuk ? null : _Aktifkan,
                    child: Text(_sibuk ? 'Mengaktifkan…' : 'Aktifkan perangkat'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
