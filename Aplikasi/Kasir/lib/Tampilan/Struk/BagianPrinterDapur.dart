import 'dart:async';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Dapur/LayananTiketDapur.dart';
import '../../Domain/Struk/ProfilPrinter.dart';
import 'EditorProfilPrinter.dart';

/// Printer tiket dapur per stasiun di perangkat ini (cetak struk bagian 4c, v1.89 mendukung semua sambungan printer
/// struk). Stasiun berasal dari data meja outlet; tiap stasiun bisa tidak dicetak (memakai layar dapur), memakai printer
/// struk perangkat ini, atau printer sendiri: LAN/Wi-Fi, Bluetooth, Bluetooth LE, atau COM ([EditorProfilPrinter]).
class BagianPrinterDapur extends ConsumerStatefulWidget {
  const BagianPrinterDapur({super.key});

  @override
  ConsumerState<BagianPrinterDapur> createState() => _BagianPrinterDapurState();
}

class _BagianPrinterDapurState extends ConsumerState<BagianPrinterDapur> {
  RuteDapur? _rute;
  Map<String, PrinterDapur> _printer = const {};

  @override
  void initState() {
    super.initState();
    unawaited(_Muat());
  }

  Future<void> _Muat() async {
    final repositori = ref.read(penyediaRepositori);
    final rute = await RuteDapur.Muat(repositori);
    final printer = await PrinterDapur.MuatSemua(repositori);
    if (mounted) {
      setState(() {
        _rute = rute;
        _printer = printer;
      });
    }
  }

  Future<void> _Simpan(String uuidStasiun, PrinterDapur? printer) async {
    final baru = {..._printer}..remove(uuidStasiun);
    if (printer != null) {
      baru[uuidStasiun] = printer;
    }
    await PrinterDapur.SimpanSemua(ref.read(penyediaRepositori), baru);
    if (mounted) {
      setState(() => _printer = baru);
    }
  }

  @override
  Widget build(BuildContext context) {
    final rute = _rute;
    if (rute == null) {
      return const LinearProgressIndicator();
    }
    if (rute.stasiun.isEmpty) {
      return Text(
        'Belum ada stasiun dapur. Tambahkan stasiun di back-office menu Meja & dapur, lalu perbarui data kasir.',
        style: Theme.of(context).textTheme.bodyMedium,
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final s in rute.stasiun)
          _BarisPrinterDapur(
            key: ValueKey(s.uuid),
            stasiun: s,
            printer: _printer[s.uuid],
            saatSimpan: (p) => _Simpan(s.uuid, p),
          ),
      ],
    );
  }
}

class _BarisPrinterDapur extends ConsumerStatefulWidget {
  const _BarisPrinterDapur({super.key, required this.stasiun, required this.printer, required this.saatSimpan});

  final StasiunDapurLokal stasiun;
  final PrinterDapur? printer;
  final Future<void> Function(PrinterDapur? printer) saatSimpan;

  @override
  ConsumerState<_BarisPrinterDapur> createState() => _BarisPrinterDapurState();
}

class _BarisPrinterDapurState extends ConsumerState<_BarisPrinterDapur> {
  var _mengubah = false;
  var _sibuk = false;
  String? _pesan;
  var _pesanGalat = false;

  DokumenStruk _DokumenUji() => DokumenStruk([
    BarisTeks('TIKET ${widget.stasiun.nama.toUpperCase()}', rata: RataStruk.Tengah, tebal: true),
    const BarisTeks('Cetak uji printer dapur', rata: RataStruk.Tengah),
    const BarisGaris(),
  ]);

  /// Null = terkirim; selain itu pesan galat.
  Future<String?> _KirimUji(ProfilPrinter? profil) async {
    try {
      await ref.read(penyediaLayananStruk).CetakDokumenKe(profil, _DokumenUji());
      return null;
    } on GalatPrinter catch (galat) {
      return galat.pesan;
    }
  }

  Future<void> _Terapkan(PrinterDapur? printer, String pesan) async {
    await widget.saatSimpan(printer);
    if (mounted) {
      setState(() {
        _mengubah = false;
        _pesan = pesan;
        _pesanGalat = false;
      });
    }
  }

  Future<void> _CetakUji() async {
    setState(() {
      _sibuk = true;
      _pesan = null;
    });
    final galat = await _KirimUji(widget.printer?.profil);
    if (mounted) {
      setState(() {
        _sibuk = false;
        _pesan = galat ?? 'Cetak uji terkirim. Periksa kertas di printer.';
        _pesanGalat = galat != null;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final printer = widget.printer;
    final ringkasan = switch (printer) {
      null => 'Tidak dicetak (memakai layar dapur atau perangkat lain)',
      PrinterDapur(samaDenganStruk: true) => 'Printer struk perangkat ini',
      PrinterDapur(:final profil?) => 'Printer ${profil.label}',
      _ => '',
    };

    Widget Tombol(String label, IconData ikon, VoidCallback? aksi) => SizedBox(
      height: TokenJarak.targetSentuh,
      child: OutlinedButton.icon(onPressed: _sibuk ? null : aksi, icon: Icon(ikon), label: Text(label)),
    );

    return Padding(
      padding: const EdgeInsets.only(bottom: TokenJarak.jarak16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.stasiun.nama, style: teks.titleSmall),
          const SizedBox(height: TokenJarak.jarak4),
          if (_mengubah)
            EditorProfilPrinter(
              profilAwal: printer?.profil,
              opsiStruk: false,
              labelSimpan: 'Simpan printer dapur',
              saatSimpan: (p) => _Terapkan(PrinterDapur.Sendiri(p), 'Printer dapur disimpan.'),
              saatBatal: () => setState(() => _mengubah = false),
              saatCetakUji: _KirimUji,
            )
          else ...[
            Text(ringkasan, style: teks.bodyMedium),
            const SizedBox(height: TokenJarak.jarak8),
            Wrap(
              spacing: TokenJarak.jarak8,
              runSpacing: TokenJarak.jarak8,
              children: [
                Tombol('Atur printer sendiri', Icons.print_outlined, () => setState(() => _mengubah = true)),
                if (printer == null || !printer.samaDenganStruk)
                  Tombol(
                    'Pakai printer struk',
                    Icons.receipt_long_outlined,
                    () => _Terapkan(const PrinterDapur.Struk(), 'Tiket stasiun ini dicetak di printer struk.'),
                  ),
                if (printer != null) ...[
                  Tombol('Cetak uji', Icons.print, _CetakUji),
                  Tombol(
                    'Jangan cetak',
                    Icons.print_disabled_outlined,
                    () => _Terapkan(null, 'Tiket stasiun ini tidak dicetak di perangkat ini.'),
                  ),
                ],
              ],
            ),
          ],
          if (_pesan != null && !_mengubah)
            Padding(
              padding: const EdgeInsets.only(top: TokenJarak.jarak8),
              child: Text(
                _pesan!,
                style: teks.bodySmall?.copyWith(color: _pesanGalat ? warna.bahaya : warna.teksSekunder),
              ),
            ),
        ],
      ),
    );
  }
}
