import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/BasisData/BasisDataKasir.dart';
import '../Domain/GalatKasir.dart';
import '../Domain/Sesi/StafLokal.dart';
import '../Domain/Shift/LayananBukaLaci.dart';
import 'LembarMutasiKas.dart';

/// Cetak struk bagian 4 (POS-17, §19.2): buka laci kas tanpa transaksi. Alasan wajib; PIN supervisor diminta bila
/// pengaturan tenant mewajibkannya. Laci dibuka lewat printer lalu dicatat (outbox `Laci.Buka`) dan tampil di detail
/// shift back-office. Ditampilkan bingkai ruang kerja di dalam `PanelTugas`.
class LembarBukaLaci extends ConsumerStatefulWidget {
  const LembarBukaLaci({super.key, required this.shift, required this.pembuka, required this.saatSelesai});

  static const String judul = 'Buka laci';

  /// Kunci panel kas di bingkai ruang kerja (berbeda dari jenis mutasi kas).
  static const String kunciPanel = 'BukaLaci';

  final BarisShift shift;
  final StafLokal pembuka;
  final VoidCallback saatSelesai;

  @override
  ConsumerState<LembarBukaLaci> createState() => _LembarBukaLaciState();
}

class _LembarBukaLaciState extends ConsumerState<LembarBukaLaci> {
  final _alasan = TextEditingController();
  bool _sibuk = false;
  String? _galat;

  @override
  void dispose() {
    _alasan.dispose();
    super.dispose();
  }

  Future<void> _Buka() async {
    final layanan = ref.read(penyediaLayananBukaLaci);
    if (_alasan.text.trim().length < LayananBukaLaci.panjangAlasanMinimal) {
      setState(() => _galat = 'Isi alasan membuka laci minimal 3 karakter.');
      return;
    }

    StafLokal? penyetuju;
    if (await layanan.CekPerluPin()) {
      if (!mounted) {
        return;
      }
      penyetuju = await showDialog<StafLokal>(
        context: context,
        builder: (_) => const DialogPinSupervisor(pesan: 'Buka laci tanpa transaksi perlu persetujuan supervisor.'),
      );
      if (penyetuju == null) {
        return;
      }
    }

    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      await layanan.BukaLaci(shift: widget.shift, pembuka: widget.pembuka, alasan: _alasan.text, penyetuju: penyetuju);
      final sesi = ref.read(penyediaSesi.notifier);
      if (mounted) {
        ScaffoldMessenger.maybeOf(context)?.showSnackBar(const SnackBar(content: Text('Laci dibuka dan dicatat.')));
        widget.saatSelesai();
      }
      await sesi.Sinkronkan();
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } on GalatPrinter catch (galat) {
      if (mounted) {
        setState(() => _galat = '${galat.pesan} Laci tidak terbuka dan tidak dicatat.');
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
      padding: const EdgeInsets.all(TokenJarak.jarak24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Setiap buka laci tanpa transaksi dicatat beserta alasan dan nama kasir, lalu tampil di laporan shift.',
            style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
          ),
          const SizedBox(height: TokenJarak.jarak12),
          TextField(
            controller: _alasan,
            autofocus: true,
            maxLength: 255,
            decoration: InputDecoration(
              labelText: 'Alasan',
              hintText: 'Misal: tukar uang kecil untuk kembalian',
              border: const OutlineInputBorder(),
              errorText: _galat,
              errorMaxLines: 3,
            ),
            onSubmitted: (_) => _sibuk ? null : _Buka(),
          ),
          const SizedBox(height: TokenJarak.jarak8),
          SizedBox(
            height: 56,
            child: FilledButton(
              onPressed: _sibuk ? null : _Buka,
              child: Text(_sibuk ? 'Membuka laci…' : 'Buka laci sekarang'),
            ),
          ),
        ],
      ),
    );
  }
}
