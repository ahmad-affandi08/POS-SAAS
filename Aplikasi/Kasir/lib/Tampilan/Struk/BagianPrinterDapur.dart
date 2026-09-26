import 'dart:async';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Dapur/LayananTiketDapur.dart';
import '../../Domain/Struk/ProfilPrinter.dart';

enum _ModePrinterDapur {
  Tidak('Tidak dicetak'),
  Struk('Printer struk'),
  Jaringan('Printer LAN/Wi-Fi');

  const _ModePrinterDapur(this.label);

  final String label;
}

/// Printer tiket dapur per stasiun di perangkat ini (cetak struk bagian 4c). Stasiun berasal dari data meja outlet;
/// tiap stasiun bisa tidak dicetak (memakai layar dapur), memakai printer struk, atau printer LAN/Wi-Fi sendiri.
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
  late _ModePrinterDapur _mode;
  final _alamat = TextEditingController();
  final _port = TextEditingController(text: '${TransportJaringan.portBawaan}');
  var _lebar = LebarKertas.Mm80;
  String? _galat;
  String? _pesan;
  var _sibuk = false;

  @override
  void initState() {
    super.initState();
    final printer = widget.printer;
    final profil = printer?.profil;
    _mode = printer == null
        ? _ModePrinterDapur.Tidak
        : (printer.samaDenganStruk ? _ModePrinterDapur.Struk : _ModePrinterDapur.Jaringan);
    if (profil != null) {
      _alamat.text = profil.alamat;
      _port.text = '${profil.port}';
      _lebar = profil.lebar;
    }
  }

  @override
  void dispose() {
    _alamat.dispose();
    _port.dispose();
    super.dispose();
  }

  /// Null bila isian printer jaringan tidak sah (pesan galat diisi).
  PrinterDapur? _SusunPrinter() {
    switch (_mode) {
      case _ModePrinterDapur.Tidak:
        return null;
      case _ModePrinterDapur.Struk:
        return const PrinterDapur.Struk();
      case _ModePrinterDapur.Jaringan:
        final galat = ProfilPrinter.ValidasiAlamat(_alamat.text) ?? ProfilPrinter.ValidasiPort(_port.text);
        setState(() => _galat = galat);
        return galat == null
            ? PrinterDapur.Sendiri(
                ProfilPrinter(alamat: _alamat.text.trim(), port: int.parse(_port.text.trim()), lebar: _lebar),
              )
            : null;
    }
  }

  Future<void> _Simpan() async {
    final printer = _SusunPrinter();
    if (_mode == _ModePrinterDapur.Jaringan && printer == null) {
      return;
    }
    await widget.saatSimpan(printer);
    if (mounted) {
      setState(() => _pesan = _mode == _ModePrinterDapur.Tidak ? 'Tiket stasiun ini tidak dicetak.' : 'Tersimpan.');
    }
  }

  Future<void> _CetakUji() async {
    final printer = _SusunPrinter();
    if (printer == null) {
      return;
    }
    setState(() {
      _sibuk = true;
      _pesan = null;
    });
    try {
      await ref
          .read(penyediaLayananStruk)
          .CetakDokumenKe(
            printer.profil,
            DokumenStruk([
              BarisTeks('TIKET ${widget.stasiun.nama.toUpperCase()}', rata: RataStruk.Tengah, tebal: true),
              const BarisTeks('Cetak uji printer dapur', rata: RataStruk.Tengah),
              const BarisGaris(),
            ]),
          );
      if (mounted) {
        setState(() => _pesan = 'Cetak uji terkirim. Periksa kertas di printer.');
      }
    } on GalatPrinter catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _sibuk = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: TokenJarak.jarak16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.stasiun.nama, style: teks.titleSmall),
          const SizedBox(height: TokenJarak.jarak8),
          Wrap(
            spacing: TokenJarak.jarak8,
            runSpacing: TokenJarak.jarak8,
            children: [
              for (final m in _ModePrinterDapur.values)
                ChoiceChip(
                  label: Text(m.label),
                  selected: _mode == m,
                  onSelected: (_) => setState(() {
                    _mode = m;
                    _galat = null;
                    _pesan = null;
                  }),
                ),
            ],
          ),
          if (_mode == _ModePrinterDapur.Jaringan) ...[
            const SizedBox(height: TokenJarak.jarak12),
            Wrap(
              spacing: TokenJarak.jarak12,
              runSpacing: TokenJarak.jarak12,
              children: [
                SizedBox(
                  width: 220,
                  child: TextField(
                    controller: _alamat,
                    keyboardType: TextInputType.url,
                    decoration: const InputDecoration(labelText: 'Alamat IP', hintText: '192.168.1.60'),
                  ),
                ),
                SizedBox(
                  width: 120,
                  child: TextField(
                    controller: _port,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Port'),
                  ),
                ),
                SegmentedButton<LebarKertas>(
                  showSelectedIcon: false,
                  segments: [for (final l in LebarKertas.values) ButtonSegment(value: l, label: Text(l.label))],
                  selected: {_lebar},
                  onSelectionChanged: (pilihan) => setState(() => _lebar = pilihan.single),
                ),
              ],
            ),
          ],
          if (_galat != null) ...[
            const SizedBox(height: TokenJarak.jarak8),
            Text(_galat!, style: teks.bodySmall?.copyWith(color: warna.bahaya)),
          ],
          if (_pesan != null) ...[const SizedBox(height: TokenJarak.jarak8), Text(_pesan!, style: teks.bodySmall)],
          const SizedBox(height: TokenJarak.jarak8),
          Wrap(
            spacing: TokenJarak.jarak8,
            runSpacing: TokenJarak.jarak8,
            children: [
              SizedBox(
                height: TokenJarak.targetSentuh,
                child: FilledButton(onPressed: _sibuk ? null : _Simpan, child: const Text('Simpan printer dapur')),
              ),
              if (_mode != _ModePrinterDapur.Tidak)
                SizedBox(
                  height: TokenJarak.targetSentuh,
                  child: OutlinedButton(
                    onPressed: _sibuk ? null : _CetakUji,
                    child: Text(_sibuk ? 'Mencetak…' : 'Cetak uji'),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
