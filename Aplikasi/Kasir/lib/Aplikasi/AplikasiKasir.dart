import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

import 'Lingkungan.dart';

/// Akar widget Aplikasi POS. Rute & fitur ditambahkan per flow (PRD §8, §17).
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
      home: const LayarAwal(),
    );
  }
}

/// Layar sementara sampai flow pertama aplikasi ini dibangun.
class LayarAwal extends StatelessWidget {
  const LayarAwal({super.key});

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    return Scaffold(
      body: Center(child: Text('Kasir', style: teks.headlineSmall)),
    );
  }
}
