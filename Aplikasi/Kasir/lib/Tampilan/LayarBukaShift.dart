import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Domain/GalatKasir.dart';
import '../Domain/Sesi/StafLokal.dart';
import '../Domain/Shift/LayananShift.dart';
import 'Komponen/MasukanUang.dart';

/// F-06 langkah 2: layar Buka Shift. Modal awal diketik langsung atau dihitung per pecahan (opsional); keduanya
/// harus sama. Bisa tanpa internet (BR-06.3); data dikirim lewat outbox saat online.
class LayarBukaShift extends ConsumerStatefulWidget {
  const LayarBukaShift({super.key, required this.kasir});

  final StafLokal kasir;

  @override
  ConsumerState<LayarBukaShift> createState() => _LayarBukaShiftState();
}

class _LayarBukaShiftState extends ConsumerState<LayarBukaShift> {
  final _kasAwal = TextEditingController();
  final Map<int, int> _pecahan = {for (final n in daftarPecahanRupiah) n: 0};
  bool _hitungPecahan = false;
  bool _sibuk = false;
  String? _galat;

  @override
  void dispose() {
    _kasAwal.dispose();
    super.dispose();
  }

  void _UbahPecahan(int nominal, int jumlahBaru) => setState(() {
    _pecahan[nominal] = jumlahBaru;
    _kasAwal.text = HitungPecahan.HitungTotal(_pecahan).KeDesimal().toBigInt().toString();
  });

  Future<void> _Buka() async {
    final kasAwal = MasukanUang.AmbilNilai(_kasAwal) ?? Uang.Nol();
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      await ref
          .read(penyediaLayananShift)
          .BukaShift(
            kasir: widget.kasir,
            kasAwal: kasAwal,
            pecahan: _hitungPecahan ? [for (final e in _pecahan.entries) BarisPecahan(e.key, e.value)] : null,
          );
      await ref.read(penyediaSesi.notifier).Sinkronkan();
    } on GalatKasir catch (galat) {
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
    return Scaffold(
      appBar: AppBar(
        title: Text('Buka shift · ${widget.kasir.nama}'),
        actions: [
          TextButton(onPressed: () => ref.read(penyediaSesi.notifier).Keluar(), child: const Text('Ganti kasir')),
        ],
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: ListView(
            padding: const EdgeInsets.all(24),
            children: [
              Text('Hitung uang di laci sebelum mulai berjualan.', style: teks.bodyLarge),
              const SizedBox(height: 16),
              MasukanUang(pengendali: _kasAwal, label: 'Modal awal (kas awal)', autofocus: true, galat: _galat),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _hitungPecahan,
                onChanged: (nilai) => setState(() => _hitungPecahan = nilai),
                title: const Text('Hitung per pecahan'),
                subtitle: const Text('Opsional. Jumlahnya otomatis mengisi modal awal.'),
              ),
              if (_hitungPecahan)
                HitungPecahan(nominal: daftarPecahanRupiah, jumlah: _pecahan, saatBerubah: _UbahPecahan),
              const SizedBox(height: 24),
              SizedBox(
                height: 56,
                child: FilledButton(
                  onPressed: _sibuk ? null : _Buka,
                  child: Text(_sibuk ? 'Membuka shift…' : 'Buka shift'),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Shift tetap bisa dibuka tanpa internet dan akan terkirim otomatis saat online.',
                style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
