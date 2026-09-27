import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Domain/Perangkat/PemindaiQr.dart';

/// Pemindai QR dengan paket `mobile_scanner` (Android & iOS). Paket itu tidak mendukung Windows, jadi di sana
/// [CekTersedia] false dan kode aktivasi diketik manual.
class PemindaiQrPlatform implements PemindaiQr {
  @override
  bool CekTersedia() => Platform.isAndroid || Platform.isIOS;

  @override
  Future<String?> Pindai(BuildContext context) {
    return Navigator.of(context).push<String>(MaterialPageRoute(builder: (_) => const _LayarPindaiQr()));
  }
}

class _LayarPindaiQr extends StatefulWidget {
  const _LayarPindaiQr();

  @override
  State<_LayarPindaiQr> createState() => _LayarPindaiQrState();
}

class _LayarPindaiQrState extends State<_LayarPindaiQr> {
  final _kendali = MobileScannerController(detectionSpeed: DetectionSpeed.noDuplicates);
  bool _sudahTerbaca = false;

  @override
  void dispose() {
    // `MobileScannerController.dispose()` mengembalikan Future, sedangkan `State.dispose()` sinkron. Kameranya
    // dilepas di latar belakang; tidak ada yang perlu ditunggu setelah layar ini ditutup.
    unawaited(_kendali.dispose());
    super.dispose();
  }

  void _Terbaca(BarcodeCapture tangkapan) {
    if (_sudahTerbaca) {
      return;
    }
    final isi = tangkapan.barcodes
        .map((b) => b.rawValue)
        .firstWhere((nilai) => nilai != null && nilai.trim().isNotEmpty, orElse: () => null);
    if (isi == null) {
      return;
    }
    // Dikunci supaya satu pemindaian tidak menutup layar berkali-kali.
    _sudahTerbaca = true;
    Navigator.of(context).pop(isi.trim());
  }

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Pindai kode QR')),
      body: Stack(
        children: [
          MobileScanner(
            controller: _kendali,
            onDetect: _Terbaca,
            errorBuilder: (context, galat) {
              // Izin kamera ditolak atau kamera tidak bisa dibuka: jalan keluarnya ketik manual.
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(
                    'Kamera tidak bisa dibuka. Tutup layar ini lalu ketik kode aktivasinya.',
                    textAlign: TextAlign.center,
                    style: teks.bodyLarge?.copyWith(color: warna.bahaya),
                  ),
                ),
              );
            },
          ),
          Align(
            alignment: Alignment.bottomCenter,
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text(
                'Arahkan kamera ke kode QR di back-office.',
                textAlign: TextAlign.center,
                style: teks.bodyMedium?.copyWith(color: warna.permukaan),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
