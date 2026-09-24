import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/BasisData/BasisDataKasir.dart';
import '../Domain/Sesi/StafLokal.dart';
import '../Domain/Shift/LayananShift.dart';
import 'LayarStatusSinkron.dart';
import 'LembarMutasiKas.dart';

/// Shift berjalan (F-06): ringkasan kas non-penjualan, tombol kas masuk/keluar/setoran, riwayat mutasi, dan status
/// sinkron. Outbox dikirim otomatis tiap 30 detik saat layar terbuka. Layar jualan menyusul di F-07.
class LayarShift extends ConsumerStatefulWidget {
  const LayarShift({super.key, required this.shift, required this.kasir});

  final BarisShift shift;
  final StafLokal kasir;

  @override
  ConsumerState<LayarShift> createState() => _LayarShiftState();
}

class _LayarShiftState extends ConsumerState<LayarShift> {
  Timer? _pewaktu;

  @override
  void initState() {
    super.initState();
    _pewaktu = Timer.periodic(
      const Duration(seconds: 30),
      (_) => unawaited(ref.read(penyediaSesi.notifier).Sinkronkan()),
    );
  }

  @override
  void dispose() {
    _pewaktu?.cancel();
    super.dispose();
  }

  Future<void> _BukaLembar(String jenis) => showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    builder: (_) => LembarMutasiKas(shift: widget.shift, jenis: jenis, pencatat: widget.kasir),
  );

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final mutasi = ref.watch(penyediaMutasiShift(widget.shift.Uuid)).value ?? const <BarisMutasiKas>[];
    final tertunda = ref.watch(penyediaJumlahTertunda).value ?? 0;
    final perluTindakan = ref.watch(penyediaPerluTindakan).value?.length ?? 0;
    final kas = LayananShift.HitungKasNonPenjualan(widget.shift, mutasi);

    return Scaffold(
      appBar: AppBar(
        title: Text('Shift ${widget.shift.NamaKasir}'),
        actions: [
          TextButton.icon(
            onPressed: () =>
                Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const LayarStatusSinkron())),
            icon: Icon(
              perluTindakan > 0
                  ? Icons.error_outline
                  : (tertunda > 0 ? Icons.cloud_upload_outlined : Icons.cloud_done_outlined),
              color: perluTindakan > 0 ? warna.bahaya : null,
            ),
            label: Text(
              perluTindakan > 0
                  ? '$perluTindakan perlu tindakan'
                  : (tertunda > 0 ? '$tertunda belum terkirim' : 'Tersinkron'),
            ),
          ),
          TextButton(
            onPressed: () => ref.read(penyediaSesi.notifier).Keluar(),
            child: Text('Ganti kasir (${widget.kasir.nama})'),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _Baris('Kas awal', Uang.Dari(widget.shift.KasAwal)),
                  _Baris('Kas di laci (tanpa penjualan)', kas, tebal: true),
                  const SizedBox(height: 4),
                  Text(
                    'Penjualan tunai akan ditambahkan setelah layar jualan tersedia.',
                    style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              for (final jenis in const [JenisMutasi.masuk, JenisMutasi.keluar, JenisMutasi.setoran])
                SizedBox(
                  height: 56,
                  child: FilledButton.tonal(
                    onPressed: () => _BukaLembar(jenis),
                    child: Text(LembarMutasiKas.AmbilJudul(jenis)),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 24),
          Text('Kas masuk, keluar & setoran', style: teks.titleMedium),
          const SizedBox(height: 8),
          if (mutasi.isEmpty)
            Text(
              'Belum ada kas masuk atau keluar di shift ini.',
              style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
            )
          else
            for (final m in mutasi)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(m.NamaKategori ?? LembarMutasiKas.AmbilJudul(m.Jenis)),
                subtitle: Text(
                  [
                    LembarMutasiKas.AmbilJudul(m.Jenis),
                    if (m.Catatan != null) m.Catatan!,
                    if (m.DisetujuiOleh != null) 'disetujui supervisor',
                  ].join(' · '),
                ),
                trailing: TeksUang(
                  m.Jenis == JenisMutasi.masuk ? Uang.Dari(m.Jumlah) : Uang.Nol().Kurangi(Uang.Dari(m.Jumlah)),
                  gaya: TextStyle(color: m.Jenis == JenisMutasi.masuk ? warna.sukses : warna.teksUtama),
                ),
              ),
        ],
      ),
    );
  }

  Widget _Baris(String label, Uang nilai, {bool tebal = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      children: [
        Expanded(child: Text(label)),
        TeksUang(
          nilai,
          gaya: TextStyle(fontWeight: tebal ? FontWeight.w700 : FontWeight.w400, fontSize: tebal ? 20 : null),
        ),
      ],
    ),
  );
}
