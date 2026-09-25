import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Penjualan/Keranjang.dart';
import '../../Domain/Penjualan/KonteksPenjualan.dart';
import '../../Domain/Penjualan/LayananPenjualan.dart';
import '../../Domain/Sesi/StafLokal.dart';
import '../Komponen/FormatAngka.dart';
import '../LembarMutasiKas.dart';

/// Hasil pemeriksaan persetujuan diskon.
typedef HasilPersetujuanDiskon = ({bool boleh, PenyetujuDiskon? penyetuju});

/// BR-07.3: periksa diskon terhadap batas memakai persen efektif (diskon hasil mesin [nilaiDiskon] ÷ [dasar]); kasir
/// tanpa izin diskon manual atau di atas batas manual → PIN penyetuju ber-izin `penjualan.diskon.setujui`; di atas
/// batas penyetuju → hanya Pemilik. Satu dialog saja (tidak bertumpuk).
Future<HasilPersetujuanDiskon> PastikanDiskonDisetujui(
  BuildContext context, {
  required Uang dasar,
  required Uang nilaiDiskon,
  required StafLokal kasir,
  required KonteksPenjualan k,
  PenyetujuDiskon? penyetuju,
}) async {
  var status = LayananPenjualan.PeriksaDiskon(
    dasar: dasar,
    diskon: nilaiDiskon,
    kasir: kasir,
    k: k,
    penyetuju: penyetuju,
  );
  if (status == StatusDiskon.Boleh) {
    return (boleh: true, penyetuju: penyetuju);
  }
  final hanyaPemilik = status == StatusDiskon.MelebihiBatas;
  final batasManual = FormatAngka.FormatPersen(k.batasDiskonManual.toString());
  final batasPenyetuju = FormatAngka.FormatPersen(k.batasDiskonPenyetuju.toString());
  final berizinManual = kasir.PunyaIzin(IzinKasir.penjualanDiskonManual);
  final staf = await showDialog<StafLokal>(
    context: context,
    builder: (_) => DialogPinSupervisor(
      izin: IzinKasir.penjualanDiskonSetujui,
      hanyaPemilik: hanyaPemilik,
      pesan: hanyaPemilik
          ? 'Diskon ini di atas $batasPenyetuju. Hanya Pemilik yang bisa menyetujuinya.'
          : berizinManual
          ? 'Diskon ini di atas $batasManual. Pilih penyetuju diskon.'
          : 'Diskon manual perlu persetujuan. Pilih penyetuju diskon.',
    ),
  );
  if (staf == null) {
    return (boleh: false, penyetuju: penyetuju);
  }
  final baru = PenyetujuDiskon(uuid: staf.uuid, nama: staf.nama, pemilik: staf.pemilik);
  status = LayananPenjualan.PeriksaDiskon(dasar: dasar, diskon: nilaiDiskon, kasir: kasir, k: k, penyetuju: baru);
  if (status != StatusDiskon.Boleh) {
    throw GalatKasir(
      'DiskonMelebihiBatas',
      'Diskon melebihi batas yang bisa disetujui ${staf.nama} ($batasPenyetuju).',
    );
  }
  return (boleh: true, penyetuju: baru);
}

/// Isian diskon manual: persen atau nominal Rupiah.
class IsianDiskon extends StatefulWidget {
  const IsianDiskon({super.key, required this.awal, required this.saatBerubah, this.galat});

  final DiskonManual? awal;

  /// Null = isian kosong/tidak valid (tanpa diskon).
  final ValueChanged<DiskonManual?> saatBerubah;
  final String? galat;

  @override
  State<IsianDiskon> createState() => _IsianDiskonState();
}

class _IsianDiskonState extends State<IsianDiskon> {
  late bool _persen = widget.awal?.jumlah == null;
  late final TextEditingController _nilai = TextEditingController(
    text: widget.awal == null
        ? ''
        : widget.awal!.persen != null
        ? FormatAngka.FormatDesimal(widget.awal!.persen!)
        : widget.awal!.jumlah!.KeDesimal().truncate().toString(),
  );

  @override
  void dispose() {
    _nilai.dispose();
    super.dispose();
  }

  void _Kabarkan() {
    final desimal = FormatAngka.UraiDesimal(_nilai.text);
    if (desimal == null || desimal <= Decimal.zero) {
      widget.saatBerubah(null);
      return;
    }
    widget.saatBerubah(
      _persen ? DiskonManual.DariPersen(desimal) : DiskonManual.DariJumlah(Uang.DariDesimal(desimal.truncate())),
    );
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      SegmentedButton<bool>(
        segments: const [
          ButtonSegment(value: true, label: Text('Persen')),
          ButtonSegment(value: false, label: Text('Nominal')),
        ],
        selected: {_persen},
        onSelectionChanged: (pilihan) {
          setState(() => _persen = pilihan.first);
          _Kabarkan();
        },
      ),
      const SizedBox(height: TokenJarak.jarak12),
      TextField(
        controller: _nilai,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')), LengthLimitingTextInputFormatter(13)],
        textAlign: TextAlign.right,
        style: const TextStyle(fontFeatures: [FontFeature.tabularFigures()]),
        onChanged: (_) => _Kabarkan(),
        decoration: InputDecoration(
          labelText: _persen ? 'Diskon (%)' : 'Diskon (Rp)',
          prefixText: _persen ? null : 'Rp ',
          suffixText: _persen ? '%' : null,
          errorText: widget.galat,
          border: const OutlineInputBorder(),
        ),
      ),
    ],
  );
}

/// Panel diskon pesanan (BR-07.3): dasar = subtotal setelah diskon baris.
class PanelDiskonPesanan extends ConsumerStatefulWidget {
  const PanelDiskonPesanan({super.key, required this.kasir, required this.saatSelesai});

  final StafLokal kasir;
  final VoidCallback saatSelesai;

  @override
  ConsumerState<PanelDiskonPesanan> createState() => _PanelDiskonPesananState();
}

class _PanelDiskonPesananState extends ConsumerState<PanelDiskonPesanan> {
  DiskonManual? _diskon;
  String? _galat;

  @override
  void initState() {
    super.initState();
    _diskon = ref.read(penyediaKeranjang).diskonPesanan;
  }

  Future<void> _Simpan() async {
    final keranjang = ref.read(penyediaKeranjang);
    final k = await ref.read(penyediaKonteksPenjualan.future);
    final diskon = _diskon;
    if (diskon == null) {
      setState(() => _galat = 'Isi besar diskon.');
      return;
    }
    final layanan = ref.read(penyediaLayananPenjualan);
    try {
      final subtotal = layanan.Hitung(keranjang.Salin(diskonPesanan: () => null), k).hasil.subtotal;
      LayananPenjualan.ValidasiBentukDiskon(subtotal, diskon);
      final nilai = layanan.HitungDiskonPesanan(keranjang, diskon, k);
      if (!mounted) {
        return;
      }
      final hasil = await PastikanDiskonDisetujui(
        context,
        dasar: nilai.dasar,
        nilaiDiskon: nilai.diskon,
        kasir: widget.kasir,
        k: k,
        penyetuju: keranjang.penyetuju,
      );
      if (!hasil.boleh) {
        return;
      }
      ref
          .read(penyediaKeranjang.notifier)
          .Ganti(ref.read(penyediaKeranjang).Salin(diskonPesanan: () => diskon, penyetuju: () => hasil.penyetuju));
      widget.saatSelesai();
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    }
  }

  void _Hapus() {
    ref.read(penyediaKeranjang.notifier).Ganti(ref.read(penyediaKeranjang).Salin(diskonPesanan: () => null));
    widget.saatSelesai();
  }

  @override
  Widget build(BuildContext context) {
    final adaDiskon = ref.watch(penyediaKeranjang).diskonPesanan != null;
    return Padding(
      padding: const EdgeInsets.all(TokenJarak.jarak24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Diskon dari subtotal. Di atas batas butuh persetujuan PIN.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: TokenJarak.jarak12),
          IsianDiskon(
            awal: _diskon,
            galat: _galat,
            saatBerubah: (d) => setState(() {
              _diskon = d;
              _galat = null;
            }),
          ),
          const SizedBox(height: TokenJarak.jarak16),
          SizedBox(
            height: 56,
            child: FilledButton(onPressed: _Simpan, child: const Text('Terapkan diskon')),
          ),
          if (adaDiskon) ...[
            const SizedBox(height: TokenJarak.jarak8),
            SizedBox(
              height: TokenJarak.targetSentuh,
              child: TextButton(onPressed: _Hapus, child: const Text('Hapus diskon pesanan')),
            ),
          ],
        ],
      ),
    );
  }
}
