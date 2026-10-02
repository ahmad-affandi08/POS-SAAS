import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Domain/Salesman/LayananSalesman.dart';
import '../Komponen/FormatWaktu.dart';

/// Pilihan salesman saat menyelesaikan kunjungan.
typedef HasilDialogKunjungan = ({HasilKunjungan hasil, String? catatan});

/// Selesai kunjungan: pilih hasil (Pesanan dibuat bila ada pesanan selama kunjungan) dan catatan opsional.
class DialogSelesaiKunjungan extends StatefulWidget {
  const DialogSelesaiKunjungan({super.key, required this.kunjungan});

  final BarisKunjunganSalesLokal kunjungan;

  @override
  State<DialogSelesaiKunjungan> createState() => _DialogSelesaiKunjunganState();
}

class _DialogSelesaiKunjunganState extends State<DialogSelesaiKunjungan> {
  late HasilKunjungan _hasil = LayananSalesman.AmbilHasilBawaan(widget.kunjungan);
  final _catatan = TextEditingController();

  @override
  void dispose() {
    _catatan.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final k = widget.kunjungan;
    return AlertDialog(
      title: const Text('Selesai kunjungan'),
      content: SizedBox(
        width: 420,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(k.NamaPelanggan, style: teks.titleMedium),
              Text('Masuk ${FormatWaktu.FormatJam(k.MasukPada)}', style: teks.bodySmall),
              const SizedBox(height: TokenJarak.jarak16),
              Text('Hasil kunjungan', style: teks.labelMedium?.copyWith(color: warna.teksSekunder)),
              const SizedBox(height: TokenJarak.jarak8),
              Wrap(
                spacing: TokenJarak.jarak8,
                runSpacing: TokenJarak.jarak8,
                children: [
                  for (final h in HasilKunjungan.values)
                    ChoiceChip(
                      materialTapTargetSize: MaterialTapTargetSize.padded,
                      label: Text(h.label),
                      selected: _hasil == h,
                      onSelected: (_) => setState(() => _hasil = h),
                    ),
                ],
              ),
              if (k.UuidPesananGrosir != null) ...[
                const SizedBox(height: TokenJarak.jarak8),
                Text('Ada pesanan yang diambil selama kunjungan ini.', style: teks.bodySmall),
              ],
              const SizedBox(height: TokenJarak.jarak16),
              TextField(
                key: const ValueKey('CatatanKunjungan'),
                controller: _catatan,
                maxLength: LayananSalesman.panjangCatatanKunjungan,
                maxLines: 3,
                minLines: 1,
                decoration: const InputDecoration(labelText: 'Catatan (opsional)', border: OutlineInputBorder()),
              ),
            ],
          ),
        ),
      ),
      actions: [
        SizedBox(
          height: TokenJarak.targetSentuh,
          child: TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Batal')),
        ),
        SizedBox(
          height: TokenJarak.targetSentuh,
          child: FilledButton(
            onPressed: () {
              final catatan = _catatan.text.trim();
              Navigator.of(context)
                  .pop<HasilDialogKunjungan>((hasil: _hasil, catatan: catatan.isEmpty ? null : catatan));
            },
            child: const Text('Simpan kunjungan'),
          ),
        ),
      ],
    );
  }
}
