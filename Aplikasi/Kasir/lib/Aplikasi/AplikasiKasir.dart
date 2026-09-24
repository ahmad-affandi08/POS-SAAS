import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Tampilan/GerbangKasir.dart';
import 'Lingkungan.dart';

/// Akar widget Aplikasi POS (PRD §17.2). Harus berada di dalam `ProviderScope` (lihat `Persiapan.dart`).
class AplikasiKasir extends StatelessWidget {
  const AplikasiKasir({super.key, required this.lingkungan});

  final Lingkungan lingkungan;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Kasir',
      debugShowCheckedModeBanner: false,
      // Hanya tema terang, tanpa darkTheme (D-14).
      theme: BuatTema(),
      themeMode: ThemeMode.light,
      builder: (context, anak) => lingkungan.tampilkanPenanda
          ? Banner(
              message: lingkungan.label,
              location: BannerLocation.topEnd,
              color: TokenWarna.AmbilDari(context).peringatan,
              child: anak,
            )
          : anak!,
      home: const GerbangKasir(),
    );
  }
}
