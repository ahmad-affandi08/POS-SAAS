import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Pelanggan/LayananDeposit.dart';
import '../../Domain/Penjualan/Keranjang.dart';
import '../../Domain/Penjualan/KonteksPenjualan.dart';
import '../../Domain/Sesi/StafLokal.dart';
import '../Struk/BagianCetakDokumen.dart';

/// F-16d bagian 1: isi saldo deposit pelanggan terpilih (bisa offline). Saldo terkini dibaca online bila bisa (hanya
/// informasi); uang masuk kas shift dan bukti isi deposit dicetak (laci dibuka bila tunai).
class PanelIsiDeposit extends ConsumerStatefulWidget {
  const PanelIsiDeposit({
    super.key,
    required this.pelanggan,
    required this.kasir,
    required this.saatSelesai,
    required this.saatKembali,
  });

  /// Nominal cepat (Rupiah).
  static const List<int> nominalCepat = [50000, 100000, 200000, 500000];

  final PelangganTerpilih pelanggan;
  final StafLokal kasir;
  final VoidCallback saatSelesai;
  final VoidCallback saatKembali;

  @override
  ConsumerState<PanelIsiDeposit> createState() => _PanelIsiDepositState();
}

class _PanelIsiDepositState extends ConsumerState<PanelIsiDeposit> {
  final _jumlah = TextEditingController();
  final _referensi = TextEditingController();
  BarisMetodePembayaran? _metode;
  Uang? _saldo;
  bool _memuatSaldo = true;
  String? _pesanSaldo;
  String? _galat;
  bool _sibuk = false;
  IsiDepositTersimpan? _selesai;

  @override
  void initState() {
    super.initState();
    unawaited(_MuatSaldo());
  }

  @override
  void dispose() {
    _jumlah.dispose();
    _referensi.dispose();
    super.dispose();
  }

  Future<void> _MuatSaldo() async {
    try {
      final saldo = await ref.read(penyediaLayananDeposit).AmbilSaldo(widget.pelanggan.uuid);
      if (mounted) {
        setState(() => _saldo = saldo);
      }
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _pesanSaldo = galat.kode == 'PerluOnline' ? 'Saldo belum bisa dicek (offline).' : galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _memuatSaldo = false);
      }
    }
  }

  Future<void> _Simpan(KonteksPenjualan k) async {
    final metode = _metode;
    final teks = _jumlah.text.trim();
    if (metode == null) {
      setState(() => _galat = 'Pilih metode pembayaran.');
      return;
    }
    if (teks.isEmpty) {
      setState(() => _galat = 'Isi jumlah deposit.');
      return;
    }
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      final hasil = await ref
          .read(penyediaLayananDeposit)
          .Isi(
            pelanggan: widget.pelanggan,
            jumlah: Uang.Dari(teks),
            metode: metode,
            kasir: widget.kasir,
            k: k,
            referensi: _referensi.text,
            saldoSebelum: _saldo,
          );
      unawaited(ref.read(penyediaSesi.notifier).Sinkronkan());
      if (mounted) {
        setState(() => _selesai = hasil);
      }
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
    final selesai = _selesai;
    if (selesai != null) {
      return TampilanIsiDepositSelesai(hasil: selesai, saatSelesai: widget.saatSelesai);
    }
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final k = ref.watch(penyediaKonteksPenjualan).value;
    if (k == null) {
      return const Padding(padding: EdgeInsets.all(TokenJarak.jarak24), child: LinearProgressIndicator());
    }
    final metodeBoleh = k.metodePembayaran.where((m) => JenisMetodeBayar.bolehIsiDeposit.contains(m.Jenis)).toList();
    final metode = _metode;
    final saldo = _saldo;

    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.all(TokenJarak.jarak16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Isi deposit ${widget.pelanggan.nama}', style: teks.titleMedium),
            const SizedBox(height: TokenJarak.jarak4),
            Text(
              'Saldo bisa dipakai untuk membayar belanja berikutnya. Bisa dicatat saat offline.',
              style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
            ),
            const SizedBox(height: TokenJarak.jarak12),
            Row(
              children: [
                Expanded(child: Text('Saldo sekarang', style: teks.bodyMedium)),
                if (_memuatSaldo)
                  const SizedBox(width: 80, child: LinearProgressIndicator())
                else if (saldo != null)
                  TeksUang(saldo, gaya: teks.titleMedium)
                else
                  Flexible(
                    child: Text(
                      _pesanSaldo ?? '-',
                      textAlign: TextAlign.right,
                      style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: TokenJarak.jarak12),
            Text('Metode pembayaran', style: teks.labelLarge),
            const SizedBox(height: TokenJarak.jarak8),
            if (metodeBoleh.isEmpty)
              Text(
                'Belum ada metode tunai/QRIS/EDC/transfer yang aktif.',
                style: teks.bodySmall?.copyWith(color: warna.bahaya),
              ),
            Wrap(
              spacing: TokenJarak.jarak8,
              runSpacing: TokenJarak.jarak8,
              children: [
                for (final m in metodeBoleh)
                  ChoiceChip(
                    label: Text(m.Nama),
                    selected: metode?.Uuid == m.Uuid,
                    onSelected: _sibuk ? null : (_) => setState(() => _metode = m),
                  ),
              ],
            ),
            const SizedBox(height: TokenJarak.jarak12),
            TextField(
              controller: _jumlah,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(11)],
              textAlign: TextAlign.right,
              style: const TextStyle(fontFeatures: [FontFeature.tabularFigures()]),
              onChanged: (_) => setState(() => _galat = null),
              decoration: InputDecoration(
                labelText: 'Jumlah isi',
                prefixText: 'Rp ',
                helperText:
                    'Rupiah bulat ${Uang.Dari(k.deposit.minimalIsi).FormatRupiah()} – '
                    '${Uang.Dari(k.deposit.maksimalIsi).FormatRupiah()}',
                border: const OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: TokenJarak.jarak8),
            Wrap(
              spacing: TokenJarak.jarak8,
              runSpacing: TokenJarak.jarak8,
              children: [
                for (final n in PanelIsiDeposit.nominalCepat)
                  ActionChip(
                    label: Text(Uang.Dari('$n').FormatRupiah()),
                    onPressed: _sibuk
                        ? null
                        : () => setState(() {
                            _jumlah.text = '$n';
                            _galat = null;
                          }),
                  ),
              ],
            ),
            if (metode != null && metode.Jenis != JenisMetodeBayar.tunai) ...[
              const SizedBox(height: TokenJarak.jarak12),
              TextField(
                controller: _referensi,
                maxLength: 60,
                decoration: const InputDecoration(labelText: 'Referensi (opsional)', border: OutlineInputBorder()),
              ),
            ],
            if (_galat != null)
              Padding(
                padding: const EdgeInsets.only(top: TokenJarak.jarak8),
                child: Text(_galat!, style: TextStyle(color: warna.bahaya)),
              ),
            const SizedBox(height: TokenJarak.jarak16),
            SizedBox(
              height: 56,
              child: FilledButton(
                onPressed: _sibuk || metodeBoleh.isEmpty ? null : () => unawaited(_Simpan(k)),
                child: Text(_sibuk ? 'Menyimpan…' : 'Simpan isi deposit'),
              ),
            ),
            const SizedBox(height: TokenJarak.jarak8),
            SizedBox(
              height: TokenJarak.targetSentuh,
              child: TextButton(onPressed: _sibuk ? null : widget.saatKembali, child: const Text('Kembali')),
            ),
          ],
        ),
      ),
    );
  }
}

/// Setelah isi deposit tersimpan: nomor, jumlah, saldo sesudah (bila diketahui), dan cetak bukti.
class TampilanIsiDepositSelesai extends StatelessWidget {
  const TampilanIsiDepositSelesai({super.key, required this.hasil, required this.saatSelesai});

  final IsiDepositTersimpan hasil;
  final VoidCallback saatSelesai;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final saldo = hasil.saldoSesudah;
    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.all(TokenJarak.jarak16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(Icons.check_circle_outline, color: warna.sukses),
                const SizedBox(width: TokenJarak.jarak8),
                Expanded(child: Text('Deposit ${hasil.namaPelanggan} tersimpan', style: teks.titleMedium)),
              ],
            ),
            const SizedBox(height: TokenJarak.jarak4),
            TeksKode(hasil.nomor, gaya: teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
            const SizedBox(height: TokenJarak.jarak16),
            Row(
              children: [
                Expanded(child: Text('Isi deposit (${hasil.namaMetode})', style: teks.bodyMedium)),
                TeksUang(hasil.jumlah, gaya: teks.titleMedium),
              ],
            ),
            if (saldo != null)
              Row(
                children: [
                  Expanded(child: Text('Saldo sesudah', style: teks.bodyMedium)),
                  TeksUang(saldo),
                ],
              ),
            const SizedBox(height: TokenJarak.jarak16),
            BagianCetakDokumen(
              kunci: 'IsiDeposit:${hasil.uuid}',
              namaDokumen: 'bukti isi deposit',
              cetak: (layanan, cetakUlang, otomatis) =>
                  layanan.CetakIsiDeposit(hasil, cetakUlang: cetakUlang, bukaLaci: otomatis),
            ),
            const SizedBox(height: TokenJarak.jarak24),
            SizedBox(
              height: 56,
              child: FilledButton(autofocus: true, onPressed: saatSelesai, child: const Text('Selesai')),
            ),
          ],
        ),
      ),
    );
  }
}
