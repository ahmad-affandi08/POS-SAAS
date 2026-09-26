import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Struk/LayananKirimStruk.dart';

/// Tombol "Kirim struk" (v2.05) di layar selesai bayar dan riwayat: membuka dialog kirim struk digital lewat WhatsApp
/// atau email.
class TombolKirimStruk extends StatelessWidget {
  const TombolKirimStruk({super.key, required this.uuidPenjualan});

  final String uuidPenjualan;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: TokenJarak.targetSentuh,
    child: OutlinedButton.icon(
      onPressed: () => showDialog<void>(
        context: context,
        builder: (_) => DialogKirimStruk(uuidPenjualan: uuidPenjualan),
      ),
      icon: const Icon(Icons.send_outlined),
      label: const Text('Kirim struk'),
    ),
  );
}

/// Dialog kirim struk: pilih kanal, isi nomor WhatsApp/email, kirim, lalu pantau status antrean (tiap [jedaCek],
/// paling lama [batasCek] kali) sampai terkirim/gagal. Menutup dialog tidak membatalkan pengiriman di server.
class DialogKirimStruk extends ConsumerStatefulWidget {
  const DialogKirimStruk({
    super.key,
    required this.uuidPenjualan,
    this.jedaCek = const Duration(seconds: 2),
    this.batasCek = 15,
  });

  final String uuidPenjualan;
  final Duration jedaCek;
  final int batasCek;

  @override
  ConsumerState<DialogKirimStruk> createState() => _DialogKirimStrukState();
}

class _DialogKirimStrukState extends ConsumerState<DialogKirimStruk> {
  final _tujuan = TextEditingController();
  var _kanal = KanalStruk.whatsapp;
  var _mengirim = false;
  PesanKeluarPos? _pesan;
  String? _galat;
  Timer? _pemantau;
  var _jumlahCek = 0;

  @override
  void dispose() {
    _pemantau?.cancel();
    _tujuan.dispose();
    super.dispose();
  }

  Future<void> _Kirim() async {
    setState(() {
      _mengirim = true;
      _galat = null;
    });
    try {
      final pesan = await ref
          .read(penyediaLayananKirimStruk)
          .Kirim(uuidPenjualan: widget.uuidPenjualan, kanal: _kanal, tujuan: _tujuan.text);
      if (!mounted) {
        return;
      }
      setState(() {
        _pesan = pesan;
        _mengirim = false;
      });
      _jumlahCek = 0;
      _pemantau = Timer.periodic(widget.jedaCek, (_) => _Cek());
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() {
          _mengirim = false;
          _galat = galat.pesan;
        });
      }
    }
  }

  Future<void> _Cek() async {
    final pesan = _pesan;
    if (pesan == null) {
      return;
    }
    _jumlahCek++;
    if (_jumlahCek > widget.batasCek) {
      _pemantau?.cancel();
      return;
    }
    try {
      final terbaru = await ref.read(penyediaLayananKirimStruk).AmbilStatus(pesan.uuid);
      if (!mounted) {
        return;
      }
      setState(() => _pesan = terbaru);
      if (terbaru.status != PesanKeluarPos.diantrekan) {
        _pemantau?.cancel();
      }
    } on GalatKasir {
      // Status sesekali gagal diambil: dicoba lagi pada putaran berikutnya.
    }
  }

  Widget _BangunStatus(BuildContext context, PesanKeluarPos pesan) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final (ikon, label, w) = switch (pesan.status) {
      PesanKeluarPos.terkirim => (Icons.check_circle_outline, 'Struk terkirim.', warna.sukses),
      PesanKeluarPos.gagal => (
        Icons.error_outline,
        'Struk gagal dikirim${pesan.pesanGalat == null ? '' : ': ${pesan.pesanGalat}'}.',
        warna.bahaya,
      ),
      _ => (
        Icons.schedule,
        _jumlahCek > widget.batasCek
            ? 'Struk masih dalam antrean. Pengiriman tetap berjalan walau dialog ditutup.'
            : 'Struk dalam antrean pengiriman…',
        warna.teksSekunder,
      ),
    };
    return Row(
      children: [
        Icon(ikon, color: w),
        const SizedBox(width: TokenJarak.jarak8),
        Expanded(
          child: Text(label, style: teks.bodyMedium?.copyWith(color: w)),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final pesan = _pesan;
    final gagal = pesan?.status == PesanKeluarPos.gagal;
    final bisaKirim = !_mengirim && (pesan == null || gagal);
    return AlertDialog(
      title: const Text('Kirim struk digital'),
      content: SizedBox(
        width: 360,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: KanalStruk.whatsapp, label: Text('WhatsApp'), icon: Icon(Icons.chat_outlined)),
                  ButtonSegment(value: KanalStruk.email, label: Text('Email'), icon: Icon(Icons.mail_outline)),
                ],
                selected: {_kanal},
                onSelectionChanged: bisaKirim
                    ? (pilihan) => setState(() {
                        _kanal = pilihan.single;
                        _tujuan.clear();
                        _galat = null;
                        _pesan = null;
                      })
                    : null,
              ),
              const SizedBox(height: TokenJarak.jarak12),
              TextField(
                controller: _tujuan,
                enabled: bisaKirim,
                autofocus: true,
                keyboardType: _kanal == KanalStruk.whatsapp ? TextInputType.phone : TextInputType.emailAddress,
                autocorrect: false,
                onChanged: (_) => setState(() => _galat = null),
                onSubmitted: bisaKirim ? (_) => _Kirim() : null,
                decoration: InputDecoration(
                  labelText: _kanal == KanalStruk.whatsapp ? 'Nomor WhatsApp pelanggan' : 'Email pelanggan',
                  hintText: _kanal == KanalStruk.whatsapp ? '0812 3456 7890' : 'nama@contoh.co.id',
                  border: const OutlineInputBorder(),
                ),
              ),
              if (_galat != null)
                Padding(
                  padding: const EdgeInsets.only(top: TokenJarak.jarak8),
                  child: Text(_galat!, style: TextStyle(color: warna.bahaya)),
                ),
              if (pesan != null) ...[const SizedBox(height: TokenJarak.jarak12), _BangunStatus(context, pesan)],
            ],
          ),
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Tutup')),
        if (pesan == null || gagal)
          FilledButton(
            onPressed: bisaKirim ? _Kirim : null,
            child: Text(_mengirim ? 'Mengirim…' : (gagal ? 'Kirim ulang' : 'Kirim struk')),
          ),
      ],
    );
  }
}
