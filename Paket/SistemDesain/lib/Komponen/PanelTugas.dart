import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';

/// Cara panel tugas ditampilkan: panel samping (layar lebar) atau lembar dari bawah (layar sempit).
enum TataLetakPanel { Samping, Lembar }

/// Panel tugas rutin di atas area kerja (PRD §17.2.7): kas masuk/keluar, cari pelanggan, catatan item, diskon.
/// Dibuka di dalam ruang kerja, bukan halaman baru, sehingga layar di bawahnya (keranjang) tidak hilang.
///
/// Panel ini hanya menggambar dirinya; penempatan (kanan penuh tinggi atau bawah) diatur oleh bingkai pemakainya.
/// Tombol Esc dan tombol tutup memanggil [saatTutup].
class PanelTugas extends StatelessWidget {
  const PanelTugas({
    super.key,
    required this.judul,
    required this.anak,
    required this.saatTutup,
    this.tataLetak = TataLetakPanel.Samping,
  });

  /// Lebar panel samping.
  static const double lebarSamping = 400;

  final String judul;
  final Widget anak;
  final VoidCallback saatTutup;
  final TataLetakPanel tataLetak;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final samping = tataLetak == TataLetakPanel.Samping;
    final garis = BorderSide(color: warna.garis, width: TokenJarak.tebalGaris);

    final isi = Column(
      mainAxisSize: samping ? MainAxisSize.max : MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(TokenJarak.jarak24, TokenJarak.jarak8, TokenJarak.jarak8, 0),
          child: Row(
            children: [
              Expanded(
                child: Semantics(header: true, child: Text(judul, style: teks.titleMedium)),
              ),
              IconButton(tooltip: 'Tutup', onPressed: saatTutup, icon: const Icon(Icons.close)),
            ],
          ),
        ),
        Divider(height: TokenJarak.tebalGaris, thickness: TokenJarak.tebalGaris, color: warna.garis),
        Flexible(child: SingleChildScrollView(child: anak)),
      ],
    );

    return CallbackShortcuts(
      bindings: {const SingleActivator(LogicalKeyboardKey.escape): saatTutup},
      child: FocusScope(
        child: Semantics(
          container: true,
          explicitChildNodes: true,
          label: judul,
          child: Material(
            color: warna.permukaan,
            shape: samping
                ? Border(left: garis)
                : RoundedRectangleBorder(
                    side: garis,
                    borderRadius: const BorderRadius.vertical(top: Radius.circular(TokenJarak.radiusPanel)),
                  ),
            clipBehavior: Clip.antiAlias,
            child: SizedBox(width: samping ? lebarSamping : double.infinity, child: isi),
          ),
        ),
      ),
    );
  }
}
