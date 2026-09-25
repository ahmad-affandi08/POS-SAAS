import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/BasisData/BasisDataKasir.dart';
import '../Domain/Shift/LayananShift.dart';
import 'LembarMutasiKas.dart';
import 'RuangKerja/IsiAreaKerja.dart';

/// Area kerja "Kas" (F-06 langkah 4): ringkasan kas non-penjualan shift, tombol kas masuk/keluar/setoran, dan riwayat
/// mutasi. Formulir kas dibuka sebagai panel/lembar oleh bingkai ruang kerja lewat [saatCatat].
class LayarKas extends ConsumerWidget {
  const LayarKas({super.key, required this.shift, required this.saatCatat});

  final BarisShift shift;
  final ValueChanged<String> saatCatat;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final mutasi = ref.watch(penyediaMutasiShift(shift.Uuid)).value ?? const <BarisMutasiKas>[];
    final kas = LayananShift.HitungKasNonPenjualan(shift, mutasi);
    final tunaiPenjualan = ref.watch(penyediaTunaiShift(shift.Uuid)).value ?? Uang.Nol();
    final refundTunai = ref.watch(penyediaRefundTunaiShift(shift.Uuid)).value ?? Uang.Nol();
    final adaPenjualan = !tunaiPenjualan.BernilaiNol() || !refundTunai.BernilaiNol();

    return IsiAreaKerja(
      judul: 'Kas',
      anak: [
        KotakPanel(
          anak: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _BarisNilai(label: 'Kas awal', nilai: Uang.Dari(shift.KasAwal)),
              _BarisNilai(label: 'Kas di laci (tanpa penjualan)', nilai: kas, tebal: !adaPenjualan),
              if (adaPenjualan) ...[
                _BarisNilai(label: 'Penjualan tunai bersih', nilai: tunaiPenjualan),
                if (!refundTunai.BernilaiNol())
                  _BarisNilai(label: 'Refund tunai (void & retur)', nilai: Uang.Nol().Kurangi(refundTunai)),
                _BarisNilai(
                  label: 'Perkiraan kas di laci',
                  nilai: kas.Tambah(tunaiPenjualan).Kurangi(refundTunai),
                  tebal: true,
                ),
                const SizedBox(height: TokenJarak.jarak4),
                Text(
                  'Penjualan tunai bersih = uang tunai diterima dikurangi kembalian'
                  '${refundTunai.BernilaiNol() ? '' : ', termasuk transaksi yang kemudian di-void'}.',
                  style: teks.bodySmall,
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: TokenJarak.jarak16),
        Wrap(
          spacing: TokenJarak.jarak12,
          runSpacing: TokenJarak.jarak12,
          children: [
            for (final jenis in const [JenisMutasi.masuk, JenisMutasi.keluar, JenisMutasi.setoran])
              SizedBox(
                height: 56,
                child: OutlinedButton(
                  onPressed: () => saatCatat(jenis),
                  child: Text(LembarMutasiKas.AmbilJudul(jenis)),
                ),
              ),
          ],
        ),
        const SizedBox(height: TokenJarak.jarak24),
        Text('Kas masuk, keluar & setoran', style: teks.titleMedium),
        const SizedBox(height: TokenJarak.jarak8),
        if (mutasi.isEmpty)
          Text(
            'Belum ada kas masuk atau keluar di shift ini.',
            style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
          )
        else
          for (final m in mutasi)
            DecoratedBox(
              decoration: BoxDecoration(
                border: Border(
                  bottom: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
                ),
              ),
              child: ListTile(
                contentPadding: EdgeInsets.zero,
                minTileHeight: 56,
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
            ),
      ],
    );
  }
}

class _BarisNilai extends StatelessWidget {
  const _BarisNilai({required this.label, required this.nilai, this.tebal = false});

  final String label;
  final Uang nilai;
  final bool tebal;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak4),
      child: Row(
        children: [
          Expanded(child: Text(label, style: tebal ? teks.titleMedium : teks.bodyMedium)),
          TeksUang(nilai, gaya: tebal ? teks.titleMedium : teks.bodyMedium),
        ],
      ),
    );
  }
}
