import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Domain/GalatKasir.dart';
import 'Komponen/BingkaiMasuk.dart';

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
    return BingkaiMasuk(
      judul: 'Aktifkan perangkat kasir',
      keterangan: adaPemindai
          ? 'Buka back-office, menu Perangkat, lalu buat kode aktivasi untuk perangkat ini. Pindai QR-nya atau ketik kodenya.'
          : 'Buka back-office, menu Perangkat, lalu buat kode aktivasi untuk perangkat ini.',
      catatan: 'Aktivasi butuh internet satu kali. Setelah aktif, kasir bisa berjualan tanpa internet.',
      isi: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (widget.pesan case final String pesan) ...[
            _Peringatan(pesan: pesan),
            const SizedBox(height: TokenJarak.jarak16),
          ],
          if (adaPemindai) ...[
            SizedBox(
              height: TokenJarak.targetSentuh,
              child: OutlinedButton.icon(
                onPressed: _sibuk ? null : _Pindai,
                icon: const Icon(Icons.qr_code_scanner_outlined),
                label: const Text('Pindai kode QR'),
              ),
            ),
            const SizedBox(height: TokenJarak.jarak16),
            Row(
              children: [
                Expanded(child: Divider(color: warna.garis)),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak12),
                  child: Text('atau ketik kodenya', style: teks.bodySmall?.copyWith(color: warna.teksSekunder)),
                ),
                Expanded(child: Divider(color: warna.garis)),
              ],
            ),
            const SizedBox(height: TokenJarak.jarak16),
          ],
          TextField(
            controller: _kode,
            textCapitalization: TextCapitalization.characters,
            autofocus: !adaPemindai,
            decoration: InputDecoration(labelText: 'Kode aktivasi', errorText: _galat),
            onSubmitted: (_) => _Aktifkan(),
          ),
          const SizedBox(height: TokenJarak.jarak16),
          SizedBox(
            height: TokenJarak.targetSentuh,
            child: FilledButton(
              onPressed: _sibuk ? null : _Aktifkan,
              child: Text(_sibuk ? 'Mengaktifkan…' : 'Aktifkan perangkat'),
            ),
          ),
        ],
      ),
    );
  }
}

/// Pesan mengapa perangkat kembali ke layar aktivasi (mis. token dicabut): ditandai warna **dan** ikon, supaya
/// tetap terbaca tanpa warna (PRD §17.6.11).
class _Peringatan extends StatelessWidget {
  const _Peringatan({required this.pesan});

  final String pesan;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    return Container(
      padding: const EdgeInsets.all(TokenJarak.jarak12),
      decoration: BoxDecoration(
        color: warna.bahaya.withValues(alpha: 0.08),
        border: Border.all(color: warna.bahaya),
        borderRadius: BorderRadius.circular(TokenJarak.radiusKontrol),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.error_outline, size: TokenJarak.ikonSedang, color: warna.bahaya),
          const SizedBox(width: TokenJarak.jarak8),
          Expanded(
            child: Text(pesan, style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
          ),
        ],
      ),
    );
  }
}
