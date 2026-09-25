import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Struk/LayananStruk.dart';

/// Mencetak satu dokumen kasir.
typedef CetakDokumenKasir = Future<void> Function(LayananStruk layanan, bool cetakUlang, bool otomatis);

/// Bagian cetak dokumen kasir selain struk penjualan (bukti void, nota retur, laporan shift; cetak struk bagian 3b).
/// [otomatis] = cetak sekali saat tampil bila printer diatur & cetak otomatis aktif (sekali per [kunci]); cetak manual
/// berikutnya bertanda "CETAK ULANG" untuk dokumen yang mendukungnya. Gagal cetak tidak membatalkan dokumen.
class BagianCetakDokumen extends ConsumerStatefulWidget {
  const BagianCetakDokumen({
    super.key,
    required this.kunci,
    required this.namaDokumen,
    required this.cetak,
    this.otomatis = true,
  });

  final String kunci;

  /// Huruf kecil, misal "nota retur".
  final String namaDokumen;
  final CetakDokumenKasir cetak;
  final bool otomatis;

  @override
  ConsumerState<BagianCetakDokumen> createState() => _BagianCetakDokumenState();
}

class _BagianCetakDokumenState extends ConsumerState<BagianCetakDokumen> {
  var _jumlahCetak = 0;
  var _mencetak = false;
  String? _galat;

  @override
  void initState() {
    super.initState();
    if (widget.otomatis) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _CetakOtomatis());
    }
  }

  Future<void> _CetakOtomatis() async {
    if (!mounted || !await ref.read(penyediaLayananStruk).CekCetakOtomatis() || !mounted) {
      return;
    }
    setState(() => _mencetak = true);
    final hasil = await ref
        .read(penyediaPrinter.notifier)
        .CetakDokumenOtomatis(widget.kunci, (l) => widget.cetak(l, false, true));
    if (!mounted) {
      return;
    }
    setState(() {
      _mencetak = false;
      _galat = hasil.galat;
      if (hasil.dicetak || hasil.galat == null) {
        _jumlahCetak = hasil.dicetak ? 1 : _jumlahCetak;
      }
    });
  }

  Future<void> _Cetak() async {
    setState(() {
      _mencetak = true;
      _galat = null;
    });
    final ulang = _jumlahCetak > 0;
    final galat = await ref.read(penyediaPrinter.notifier).CetakDokumen((l) => widget.cetak(l, ulang, false));
    if (!mounted) {
      return;
    }
    setState(() {
      _mencetak = false;
      _galat = galat;
      if (galat == null) {
        _jumlahCetak++;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final printer = ref.watch(penyediaPrinter);
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    if (printer.keadaan == KeadaanPrinter.BelumDiatur && printer.profil == null) {
      return Text(
        'Printer belum diatur. Atur di menu Pengaturan untuk mencetak ${widget.namaDokumen}.',
        style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
      );
    }
    final pesan = _mencetak
        ? 'Mencetak ${widget.namaDokumen}…'
        : _galat ?? (_jumlahCetak > 0 ? 'Sudah dicetak.' : null);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (pesan != null)
          Text(
            pesan,
            style: teks.bodySmall?.copyWith(
              color: _galat != null
                  ? warna.bahaya
                  : _jumlahCetak > 0 && !_mencetak
                  ? warna.sukses
                  : null,
            ),
          ),
        const SizedBox(height: TokenJarak.jarak8),
        SizedBox(
          height: TokenJarak.targetSentuh,
          child: OutlinedButton.icon(
            onPressed: _mencetak ? null : _Cetak,
            icon: const Icon(Icons.print_outlined),
            label: Text(
              _galat != null
                  ? 'Coba cetak lagi'
                  : _jumlahCetak > 0
                  ? 'Cetak ulang ${widget.namaDokumen}'
                  : 'Cetak ${widget.namaDokumen}',
            ),
          ),
        ),
      ],
    );
  }
}
