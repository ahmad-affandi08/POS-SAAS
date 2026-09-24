import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Beranda ruang kerja: layar Jual. Katalog, keranjang, dan pembayaran dibangun di F-07; sampai saat itu area ini
/// menampilkan keadaan kosong yang jelas beserta jalan pintas ke Kas.
class LayarJual extends StatelessWidget {
  const LayarJual({super.key, required this.saatBukaKas});

  final VoidCallback saatBukaKas;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(TokenJarak.jarak24),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.point_of_sale_outlined, size: TokenJarak.ikonBesar, color: warna.teksSekunder),
              const SizedBox(height: TokenJarak.jarak12),
              Text('Layar jual belum tersedia', style: teks.titleMedium, textAlign: TextAlign.center),
              const SizedBox(height: TokenJarak.jarak8),
              Text(
                'Katalog, keranjang, dan pembayaran hadir di pembaruan aplikasi berikutnya. Shift Anda sudah '
                'terbuka, dan kas masuk atau keluar bisa dicatat sekarang.',
                style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: TokenJarak.jarak16),
              OutlinedButton(onPressed: saatBukaKas, child: const Text('Buka kas')),
            ],
          ),
        ),
      ),
    );
  }
}
