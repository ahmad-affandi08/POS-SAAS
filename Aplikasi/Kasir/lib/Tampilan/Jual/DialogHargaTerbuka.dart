import 'package:flutter/material.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Komponen/MasukanUang.dart';

/// Hasil dialog harga terbuka: harga per satuan & keterangan baris (opsional, jadi catatan baris di struk).
typedef HargaTerbukaDiketik = ({Uang harga, String? keterangan});

/// K-25 item harga terbuka (§9.3): kasir mengetik harga per satuan saat produk ditambahkan, misal "Barang lain-lain"
/// atau jasa servis. Harga daftar (bila ada) jadi isian awal. Keterangan opsional menjelaskan barangnya di struk.
class DialogHargaTerbuka extends StatefulWidget {
  const DialogHargaTerbuka({super.key, required this.namaProduk, this.namaSatuan, this.saran});

  final String namaProduk;
  final String? namaSatuan;

  /// Harga daftar untuk satuan ini; null = belum diatur.
  final Uang? saran;

  @override
  State<DialogHargaTerbuka> createState() => _DialogHargaTerbukaState();
}

class _DialogHargaTerbukaState extends State<DialogHargaTerbuka> {
  late final TextEditingController _harga = TextEditingController(
    text: switch (widget.saran) {
      final s? when !s.BernilaiNol() => MasukanUang.FormatTeks(s),
      _ => '',
    },
  );
  final TextEditingController _keterangan = TextEditingController();
  String? _galat;

  @override
  void dispose() {
    _harga.dispose();
    _keterangan.dispose();
    super.dispose();
  }

  void _Simpan() {
    final harga = MasukanUang.AmbilNilai(_harga);
    if (harga == null || harga.BernilaiNol()) {
      setState(() => _galat = 'Ketik harga lebih dari Rp0.');
      return;
    }
    final keterangan = _keterangan.text.trim();
    Navigator.of(context).pop<HargaTerbukaDiketik>((harga: harga, keterangan: keterangan.isEmpty ? null : keterangan));
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return AlertDialog(
      title: Text('Harga ${widget.namaProduk}'),
      content: SizedBox(
        width: 400,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              widget.namaSatuan == null ? 'Ketik harga jualnya.' : 'Ketik harga jual per ${widget.namaSatuan}.',
              style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
            ),
            const SizedBox(height: TokenJarak.jarak16),
            MasukanUang(
              key: const ValueKey('HargaTerbuka'),
              pengendali: _harga,
              label: 'Harga',
              galat: _galat,
              autofocus: true,
            ),
            const SizedBox(height: TokenJarak.jarak12),
            TextField(
              key: const ValueKey('KeteranganHargaTerbuka'),
              controller: _keterangan,
              maxLength: 100,
              decoration: const InputDecoration(
                labelText: 'Keterangan (opsional)',
                hintText: 'Contoh: kabel roll 5 m',
                border: OutlineInputBorder(),
              ),
              onSubmitted: (_) => _Simpan(),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Batal')),
        FilledButton(onPressed: _Simpan, child: const Text('Tambah')),
      ],
    );
  }
}
