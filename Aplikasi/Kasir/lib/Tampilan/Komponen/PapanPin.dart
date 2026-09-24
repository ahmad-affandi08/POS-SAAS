import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Papan angka PIN 6 digit (target sentuh ≥ 48dp, PRD §17.2). PIN hanya ada di memori widget sampai dikirim ke
/// `saatSelesai`, tidak pernah disimpan atau dicatat.
class PapanPin extends StatefulWidget {
  const PapanPin({super.key, required this.saatSelesai, this.sibuk = false, this.pesanGalat});

  final Future<void> Function(String pin) saatSelesai;
  final bool sibuk;
  final String? pesanGalat;

  @override
  State<PapanPin> createState() => _PapanPinState();
}

class _PapanPinState extends State<PapanPin> {
  String _pin = '';

  Future<void> _Tekan(String angka) async {
    if (widget.sibuk || _pin.length >= 6) {
      return;
    }
    setState(() => _pin += angka);
    if (_pin.length == 6) {
      final pin = _pin;
      setState(() => _pin = '');
      await widget.saatSelesai(pin);
    }
  }

  void _Hapus() => setState(() => _pin = _pin.isEmpty ? '' : _pin.substring(0, _pin.length - 1));

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    Widget Tombol(String label, VoidCallback? aksi, {String? semantik}) => SizedBox(
      width: 88,
      height: 64,
      child: Semantics(
        label: semantik,
        button: true,
        excludeSemantics: semantik != null,
        child: OutlinedButton(
          onPressed: widget.sibuk ? null : aksi,
          child: Text(label, style: const TextStyle(fontSize: 24)),
        ),
      ),
    );

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Semantics(
          label: 'PIN terisi ${_pin.length} dari 6 angka',
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (var i = 0; i < 6; i++)
                Container(
                  margin: const EdgeInsets.all(6),
                  width: 16,
                  height: 16,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: i < _pin.length ? warna.brand : null,
                    border: Border.all(color: warna.garisInput, width: 2),
                  ),
                ),
            ],
          ),
        ),
        SizedBox(
          height: 48,
          child: Center(
            child: widget.sibuk
                ? const Text('Memeriksa PIN…')
                : widget.pesanGalat == null
                ? null
                : Text(
                    widget.pesanGalat!,
                    textAlign: TextAlign.center,
                    style: TextStyle(color: warna.bahaya),
                  ),
          ),
        ),
        for (final baris in const [
          ['1', '2', '3'],
          ['4', '5', '6'],
          ['7', '8', '9'],
        ])
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (final angka in baris)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Tombol(angka, () => _Tekan(angka)),
                  ),
              ],
            ),
          ),
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(width: 96),
            Padding(padding: const EdgeInsets.symmetric(horizontal: 4), child: Tombol('0', () => _Tekan('0'))),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: Tombol('⌫', _Hapus, semantik: 'Hapus satu angka'),
            ),
          ],
        ),
      ],
    );
  }
}
