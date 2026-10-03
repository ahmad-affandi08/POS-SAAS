import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Kerangka isi area kerja layar non-Jual: kepala (judul + tombol aksi dalam **satu baris**) lalu isi yang bisa
/// digulir, dengan jarak kisi 8dp. Layar fitur di dalam ruang kerja memakai ini alih-alih `Scaffold`/`AppBar` sendiri
/// (bingkai sudah menyediakan bilah atas & navigasi).
///
/// Padat (v4.11, permintaan pemilik produk): isi memakai lebar area kerja sampai [lebarMaksimum] (bukan kolom sempit
/// ±720dp di tengah yang meninggalkan ruang kosong di kiri-kanan), tepi 16dp (12dp di HP), dan tombol aksi layar
/// berada di kanan judul alih-alih bertumpuk di bawahnya. Angka ringkas memakai `DeretKartuAngka`.
///
/// [kolomGanda] untuk layar yang isinya banyak bagian berdiri sendiri (mis. Pengaturan): di layar lebar bagian-bagian
/// itu dibagi dua kolom supaya tidak jadi gulungan panjang di sebelah ruang kosong.
class IsiAreaKerja extends StatelessWidget {
  const IsiAreaKerja({
    super.key,
    required this.judul,
    required this.anak,
    this.aksi = const [],
    this.lebarMaksimum = 1200,
    this.kolomGanda = false,
  });

  /// Batas lebar isi saat [kolomGanda] aktif dan layarnya memang cukup lebar.
  static const double lebarKolomGanda = 1200;

  /// Lebar area kerja minimum untuk dua kolom: di bawah ini satu kolom tetap lebih terbaca.
  static const double lebarMinimumKolomGanda = 840;

  final String judul;
  final List<Widget> anak;

  /// Tombol aksi layar (misal "Kas masuk", "Tutup shift"), tampil sebaris di kanan judul.
  final List<Widget> aksi;
  final double lebarMaksimum;
  final bool kolomGanda;

  @override
  Widget build(BuildContext context) {
    final lebarLayar = MediaQuery.sizeOf(context).width;
    final sempit = lebarLayar < 600;
    final tepi = sempit ? TokenJarak.jarak12 : TokenJarak.jarak16;

    return LayoutBuilder(
      builder: (context, batas) {
        final duaKolom = kolomGanda && batas.maxWidth >= lebarMinimumKolomGanda;
        final lebarIsi = duaKolom ? lebarKolomGanda : lebarMaksimum;
        final judulTeks = Semantics(header: true, child: Text(judul, style: Theme.of(context).textTheme.titleLarge));
        final tombol = Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak8,
          alignment: WrapAlignment.end,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: aksi,
        );

        return Align(
          alignment: Alignment.topLeft,
          child: ConstrainedBox(
            constraints: BoxConstraints(maxWidth: lebarIsi),
            child: ListView(
              padding: EdgeInsets.all(tepi),
              children: [
                if (aksi.isEmpty)
                  judulTeks
                else
                  // Judul kiri, tombol kanan selama muat sebaris; bila tidak muat (HP, judul panjang, banyak tombol)
                  // tombol turun ke baris berikutnya alih-alih meluap.
                  Wrap(
                    alignment: WrapAlignment.spaceBetween,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    spacing: TokenJarak.jarak16,
                    runSpacing: TokenJarak.jarak8,
                    children: [judulTeks, tombol],
                  ),
                const SizedBox(height: TokenJarak.jarak12),
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
        const SizedBox(width: TokenJarak.jarak16),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: kanan),
        ),
      ],
    );
  }
}
