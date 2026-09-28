import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Kerangka isi area kerja: judul layar + isi yang bisa digulir, dengan jarak kisi 8dp. Layar fitur di dalam ruang
/// kerja memakai ini alih-alih `Scaffold`/`AppBar` sendiri (bingkai sudah menyediakan bilah atas & navigasi).
///
/// Isinya **ditengahkan**, bukan rata kiri. Sebelumnya `Alignment.topLeft` membuat seluruh layar non-Jual menumpuk
/// di kiri dengan ±350dp kosong menganga di kanan pada layar 1280dp — terbaca seperti halaman yang belum selesai.
///
/// [kolomGanda] untuk layar yang isinya banyak bagian berdiri sendiri (mis. Pengaturan): di layar lebar bagian-bagian
/// itu dibagi dua kolom supaya tidak jadi gulungan panjang di sebelah ruang kosong.
class IsiAreaKerja extends StatelessWidget {
  const IsiAreaKerja({
    super.key,
    required this.judul,
    required this.anak,
    this.lebarMaksimum = 720,
    this.kolomGanda = false,
  });

  /// Batas lebar isi saat [kolomGanda] aktif dan layarnya memang cukup lebar.
  static const double lebarKolomGanda = 1120;

  /// Lebar area kerja minimum untuk dua kolom: di bawah ini satu kolom tetap lebih terbaca.
  static const double lebarMinimumKolomGanda = 1024;

  final String judul;
  final List<Widget> anak;
  final double lebarMaksimum;
  final bool kolomGanda;

  @override
  Widget build(BuildContext context) {
    final lebarLayar = MediaQuery.sizeOf(context).width;
    final sempit = lebarLayar < 600;
    final tepi = sempit ? TokenJarak.jarak16 : TokenJarak.jarak24;

    return LayoutBuilder(
      builder: (context, batas) {
        final duaKolom = kolomGanda && batas.maxWidth >= lebarMinimumKolomGanda;
        final lebarIsi = duaKolom ? lebarKolomGanda : lebarMaksimum;

        return Align(
          alignment: Alignment.topCenter,
          child: ConstrainedBox(
            constraints: BoxConstraints(maxWidth: lebarIsi),
            child: ListView(
              padding: EdgeInsets.all(tepi),
              children: [
                Semantics(header: true, child: Text(judul, style: Theme.of(context).textTheme.headlineSmall)),
                const SizedBox(height: TokenJarak.jarak16),
                if (duaKolom) _DuaKolom(anak: anak) else ...anak,
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Bagi bagian-bagian isi menjadi dua kolom secara berselang-seling, lalu rata atas. Berselang-seling (bukan
/// separuh-separuh) supaya urutan bacanya kiri-kanan seperti membaca biasa, dan tinggi kedua kolom tidak jomplang
/// ketika satu bagian jauh lebih panjang.
class _DuaKolom extends StatelessWidget {
  const _DuaKolom({required this.anak});

  final List<Widget> anak;

  @override
  Widget build(BuildContext context) {
    final kiri = <Widget>[];
    final kanan = <Widget>[];
    for (final (indeks, bagian) in anak.indexed) {
      (indeks.isEven ? kiri : kanan).add(bagian);
    }

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: kiri),
        ),
        const SizedBox(width: TokenJarak.jarak24),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: kanan),
        ),
      ],
    );
  }
}
