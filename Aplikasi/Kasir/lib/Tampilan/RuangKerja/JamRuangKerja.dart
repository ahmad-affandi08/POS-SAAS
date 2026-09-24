import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../Aplikasi/Penyedia.dart';
import '../Komponen/FormatWaktu.dart';

/// Jam (tingkat menit) untuk bilah atas & layar kunci. Diperbarui tepat saat menit berganti, memakai `penyediaJam`
/// sehingga test bisa mengendalikan waktunya.
class JamRuangKerja extends ConsumerStatefulWidget {
  const JamRuangKerja({super.key, this.gaya});

  final TextStyle? gaya;

  @override
  ConsumerState<JamRuangKerja> createState() => _JamRuangKerjaState();
}

class _JamRuangKerjaState extends ConsumerState<JamRuangKerja> {
  Timer? _pewaktu;

  @override
  void initState() {
    super.initState();
    _JadwalkanMenitBerikutnya();
  }

  void _JadwalkanMenitBerikutnya() {
    final sekarang = ref.read(penyediaJam)();
    final sisa = Duration(seconds: 60 - sekarang.second);
    _pewaktu = Timer(sisa, () {
      if (mounted) {
        setState(() {});
        _JadwalkanMenitBerikutnya();
      }
    });
  }

  @override
  void dispose() {
    _pewaktu?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final sekarang = ref.watch(penyediaJam)();
    return Text(
      FormatWaktu.FormatJam(sekarang),
      style: (widget.gaya ?? DefaultTextStyle.of(context).style).copyWith(
        fontFeatures: const [FontFeature.tabularFigures()],
      ),
      semanticsLabel: 'Pukul ${FormatWaktu.FormatJam(sekarang)}',
    );
  }
}
