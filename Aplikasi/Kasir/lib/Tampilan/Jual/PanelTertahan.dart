import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/GalatKasir.dart';

/// Pesanan tertahan di perangkat ini (Rincian F-07b/F-07c): buka kembali ke keranjang atau batalkan. Pesanan tertahan
/// tidak dikirim ke server sampai dibayar.
class PanelTertahan extends ConsumerStatefulWidget {
  const PanelTertahan({super.key, required this.saatDibuka});

  final VoidCallback saatDibuka;

  @override
  ConsumerState<PanelTertahan> createState() => _PanelTertahanState();
}

class _PanelTertahanState extends ConsumerState<PanelTertahan> {
  String? _galat;
  String? _konfirmasiBatal;

  Future<void> _Buka(String uuid) async {
    if (!ref.read(penyediaKeranjang).CekKosong) {
      setState(() => _galat = 'Keranjang masih berisi. Tahan atau selesaikan transaksi ini dulu.');
      return;
    }
    try {
      final keranjang = await ref.read(penyediaLayananPenjualan).BukaPesanan(uuid);
      ref.read(penyediaKeranjang.notifier).Ganti(keranjang);
      widget.saatDibuka();
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    }
  }

  Future<void> _Batalkan(String uuid) async {
    if (_konfirmasiBatal != uuid) {
      setState(() => _konfirmasiBatal = uuid);
      return;
    }
    await ref.read(penyediaRepositoriPenjualan).HapusPesananTertahan(uuid);
    if (mounted) {
      setState(() => _konfirmasiBatal = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final daftar = ref.watch(penyediaPesananTertahan).value ?? const [];
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak8),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_galat != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(TokenJarak.jarak24, 0, TokenJarak.jarak24, TokenJarak.jarak8),
              child: Text(_galat!, style: TextStyle(color: warna.bahaya)),
            ),
          if (daftar.isEmpty)
            Padding(
              padding: const EdgeInsets.all(TokenJarak.jarak24),
              child: Text(
                'Belum ada pesanan tertahan. Tekan Tahan di keranjang untuk menyimpan pesanan yang belum dibayar.',
                style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
              ),
            ),
          for (final p in daftar)
            Container(
              padding: const EdgeInsets.fromLTRB(
                TokenJarak.jarak24,
                TokenJarak.jarak8,
                TokenJarak.jarak16,
                TokenJarak.jarak8,
              ),
              decoration: BoxDecoration(
                border: Border(
                  bottom: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(p.Label, maxLines: 2, overflow: TextOverflow.ellipsis, style: teks.labelLarge),
                      ),
                      TeksUang(Uang.Dari(p.Total), gaya: teks.labelLarge),
                    ],
                  ),
                  Text('${p.JumlahItem} baris', style: teks.bodySmall),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      SizedBox(
                        height: TokenJarak.targetSentuh,
                        child: TextButton(
                          onPressed: () => _Batalkan(p.Uuid),
                          style: TextButton.styleFrom(foregroundColor: warna.bahaya),
                          child: Text(_konfirmasiBatal == p.Uuid ? 'Ya, batalkan' : 'Batalkan'),
                        ),
                      ),
                      const SizedBox(width: TokenJarak.jarak8),
                      SizedBox(
                        height: TokenJarak.targetSentuh,
                        child: OutlinedButton(onPressed: () => _Buka(p.Uuid), child: const Text('Buka')),
                      ),
                    ],
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
