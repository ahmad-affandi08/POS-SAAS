import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Kerangka isi area kerja: judul layar + isi yang bisa digulir, dengan jarak kisi 8dp. Layar fitur di dalam ruang
/// kerja memakai ini alih-alih `Scaffold`/`AppBar` sendiri (bingkai sudah menyediakan bilah atas & navigasi).
class IsiAreaKerja extends StatelessWidget {
  const IsiAreaKerja({super.key, required this.judul, required this.anak, this.lebarMaksimum = 720});

  final String judul;
  final List<Widget> anak;
  final double lebarMaksimum;

  @override
  Widget build(BuildContext context) {
    final sempit = MediaQuery.sizeOf(context).width < 600;
    final tepi = sempit ? TokenJarak.jarak16 : TokenJarak.jarak24;
    return Align(
      alignment: Alignment.topLeft,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: lebarMaksimum),
        child: ListView(
          padding: EdgeInsets.all(tepi),
          children: [
            Semantics(header: true, child: Text(judul, style: Theme.of(context).textTheme.headlineSmall)),
            const SizedBox(height: TokenJarak.jarak16),
            ...anak,
          ],
        ),
      ),
    );
  }
}
