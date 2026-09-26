import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Domain/GalatKasir.dart';
import 'GambarQr.dart';

/// Dialog QRIS dinamis (v2.05): minta tagihan ke server, tampilkan QR (juga di layar pelanggan), pantau status tiap
/// [jedaCek] sampai lunas, kedaluwarsa, atau dibatalkan kasir. Hasil = tagihan yang **lunas** (Uuid-nya menjadi
/// `Referensi` pembayaran), atau null bila batal/gagal. Kasir tidak bisa menandai lunas sendiri: status hanya dari
/// server (notifikasi gerbang), sehingga tidak ada "sudah bayar" palsu.
class DialogQrisDinamis extends ConsumerStatefulWidget {
  const DialogQrisDinamis({
    super.key,
    required this.metode,
    required this.jumlah,
    this.keterangan,
    this.jedaCek = const Duration(seconds: 2),
  });

  final BarisMetodePembayaran metode;
  final Uang jumlah;
  final String? keterangan;
  final Duration jedaCek;

  @override
  ConsumerState<DialogQrisDinamis> createState() => _DialogQrisDinamisState();
}

class _DialogQrisDinamisState extends ConsumerState<DialogQrisDinamis> {
  TagihanQrisPos? _tagihan;
  String? _galat;
  bool _terputus = false;
  bool _selesaiTanpaBayar = false;
  bool _membatalkan = false;
  Timer? _pemantau;
  Timer? _detik;
  bool _mengecek = false;

  @override
  void initState() {
    super.initState();
    unawaited(_Buat());
  }

  @override
  void dispose() {
    _pemantau?.cancel();
    _detik?.cancel();
    super.dispose();
  }

  void _AturLayarPelanggan(String? isiQr) {
    // Ditunda satu frame: Notifier tidak boleh diubah saat pohon widget sedang dibangun/dibongkar.
    final pengatur = ref.read(penyediaQrisLayarPelanggan.notifier);
    WidgetsBinding.instance.addPostFrameCallback((_) => pengatur.Atur(isiQr));
  }

  Future<void> _Buat() async {
    setState(() {
      _galat = null;
      _tagihan = null;
      _selesaiTanpaBayar = false;
    });
    try {
      final tagihan = await ref
          .read(penyediaLayananQrisDinamis)
          .Buat(metode: widget.metode, jumlah: widget.jumlah, keterangan: widget.keterangan);
      if (!mounted) {
        return;
      }
      setState(() => _tagihan = tagihan);
      _AturLayarPelanggan(tagihan.isiQr);
      _pemantau = Timer.periodic(widget.jedaCek, (_) => _Cek());
      _detik = Timer.periodic(const Duration(seconds: 1), (_) {
        if (mounted) {
          setState(() {});
        }
      });
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    }
  }

  Future<void> _Cek() async {
    final tagihan = _tagihan;
    if (tagihan == null || _mengecek || _membatalkan) {
      return;
    }
    _mengecek = true;
    try {
      final status = await ref.read(penyediaLayananQrisDinamis).AmbilStatus(tagihan.uuid);
      if (!mounted) {
        return;
      }
      if (status.lunas) {
        _Tutup(tagihan);
      } else if (status.selesaiTanpaBayar) {
        _Berhenti();
        setState(() {
          _selesaiTanpaBayar = true;
          _terputus = false;
          _galat = status.status == TagihanQrisPos.kedaluwarsa
              ? 'QRIS kedaluwarsa sebelum dibayar. Buat QRIS baru atau pakai metode lain.'
              : 'Pembayaran QRIS gagal. Buat QRIS baru atau pakai metode lain.';
        });
      } else if (_terputus) {
        setState(() => _terputus = false);
      }
    } on GalatKasir {
      // Jaringan putus sesaat: tetap memantau; tagihan tetap berlaku di server.
      if (mounted && !_terputus) {
        setState(() => _terputus = true);
      }
    } finally {
      _mengecek = false;
    }
  }

  void _Berhenti() {
    _pemantau?.cancel();
    _detik?.cancel();
    _AturLayarPelanggan(null);
  }

  void _Tutup(TagihanQrisPos? hasil) {
    _Berhenti();
    Navigator.of(context).pop(hasil);
  }

  Future<void> _Batalkan() async {
    final tagihan = _tagihan;
    if (tagihan == null || _selesaiTanpaBayar) {
      _Tutup(null);
      return;
    }
    setState(() => _membatalkan = true);
    try {
      await ref.read(penyediaLayananQrisDinamis).Batalkan(tagihan.uuid);
      if (mounted) {
        _Tutup(null);
      }
    } on GalatKasir catch (galat) {
      if (!mounted) {
        return;
      }
      if (galat.kode == 'SudahLunas') {
        // Pelanggan sudah membayar tepat sebelum dibatalkan: pembayaran dipakai, bukan dibuang.
        _Tutup(tagihan);
        return;
      }
      setState(() {
        _membatalkan = false;
        _galat = 'QRIS belum bisa dibatalkan: ${galat.pesan}';
      });
    }
  }

  String _SisaWaktu(DateTime kedaluwarsa) {
    final sisa = kedaluwarsa.difference(ref.read(penyediaJam)().toUtc());
    if (sisa.isNegative) {
      return '00:00';
    }
    final menit = sisa.inMinutes.toString().padLeft(2, '0');
    final detik = (sisa.inSeconds % 60).toString().padLeft(2, '0');
    return '$menit:$detik';
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final tagihan = _tagihan;
    final Widget isi;
    if (tagihan == null && _galat == null) {
      isi = const Padding(
        padding: EdgeInsets.all(TokenJarak.jarak24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: TokenJarak.jarak12),
            Text('Membuat QRIS…'),
          ],
        ),
      );
    } else {
      isi = Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Text('Jumlah', style: teks.bodyMedium)),
              TeksUang(widget.jumlah, gaya: teks.titleLarge),
            ],
          ),
          if (tagihan != null && !_selesaiTanpaBayar) ...[
            const SizedBox(height: TokenJarak.jarak12),
            Center(
              child: GambarQr(data: tagihan.isiQr, label: 'Kode QRIS ${widget.jumlah.FormatRupiah()}'),
            ),
            const SizedBox(height: TokenJarak.jarak8),
            Text(
              tagihan.halamanBayar
                  ? 'Minta pelanggan memindai kode untuk membuka halaman bayar.'
                  : 'Minta pelanggan memindai dengan aplikasi bank atau e-wallet mana pun.',
              textAlign: TextAlign.center,
              style: teks.bodyMedium,
            ),
            const SizedBox(height: TokenJarak.jarak8),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                const SizedBox(width: TokenJarak.jarak8),
                Flexible(
                  child: Text(
                    _terputus ? 'Koneksi terputus, mencoba lagi…' : 'Menunggu pembayaran',
                    style: teks.bodySmall?.copyWith(color: _terputus ? warna.peringatan : warna.teksSekunder),
                  ),
                ),
              ],
            ),
            if (tagihan.kedaluwarsaPada case final kedaluwarsa?)
              Padding(
                padding: const EdgeInsets.only(top: TokenJarak.jarak4),
                child: Text(
                  'Berlaku ${_SisaWaktu(kedaluwarsa)} lagi',
                  textAlign: TextAlign.center,
                  style: teks.bodySmall?.copyWith(
                    color: warna.teksSekunder,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
              ),
            const SizedBox(height: TokenJarak.jarak4),
            TeksKode(tagihan.nomorPesanan, gaya: teks.bodySmall?.copyWith(color: warna.teksSekunder)),
          ],
          if (_galat != null)
            Padding(
              padding: const EdgeInsets.only(top: TokenJarak.jarak12),
              child: Text(_galat!, style: TextStyle(color: warna.bahaya)),
            ),
        ],
      );
    }
    final bisaUlang = _galat != null && (tagihan == null || _selesaiTanpaBayar);
    return PopScope(
      canPop: false,
      child: AlertDialog(
        title: Text('Bayar dengan ${widget.metode.Nama}'),
        content: SingleChildScrollView(child: SizedBox(width: 320, child: isi)),
        actions: [
          TextButton(
            onPressed: _membatalkan ? null : _Batalkan,
            child: Text(
              _membatalkan ? 'Membatalkan…' : (tagihan == null || _selesaiTanpaBayar ? 'Tutup' : 'Batalkan QRIS'),
            ),
          ),
          if (bisaUlang) FilledButton(onPressed: _Buat, child: const Text('Buat QRIS lagi')),
        ],
      ),
    );
  }
}
