import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/BasisData/BasisDataKasir.dart';
import '../Domain/Shift/LayananShift.dart';
import 'LembarBukaLaci.dart';
import 'LembarMutasiKas.dart';
import 'RuangKerja/IsiAreaKerja.dart';

/// Area kerja "Kas" (F-06 langkah 4): ringkasan kas non-penjualan shift, tombol kas masuk/keluar/setoran, dan riwayat
/// mutasi, serta tombol buka laci tanpa transaksi. Formulir kas dibuka sebagai panel/lembar oleh bingkai ruang kerja lewat [saatCatat].
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

    final perkiraan = kas.Tambah(tunaiPenjualan).Kurangi(refundTunai);
    Uang Jumlahkan(String jenis) =>
        mutasi.where((m) => m.Jenis == jenis).fold(Uang.Nol(), (total, m) => total.Tambah(Uang.Dari(m.Jumlah)));

    return IsiAreaKerja(
      judul: 'Kas',
      aksi: [
        for (final jenis in const [JenisMutasi.masuk, JenisMutasi.keluar, JenisMutasi.setoran])
          OutlinedButton(onPressed: () => saatCatat(jenis), child: Text(LembarMutasiKas.AmbilJudul(jenis))),
        // Cetak struk bagian 4 (§19.2): buka laci tanpa transaksi, selalu dicatat.
        OutlinedButton.icon(
          onPressed: () => saatCatat(LembarBukaLaci.kunciPanel),
          icon: const Icon(Icons.point_of_sale),
          label: const Text(LembarBukaLaci.judul),
        ),
      ],
      anak: [
        DeretKartuAngka(
          kartu: [
            KartuAngka(
              label: 'Kas awal',
              ikon: Icons.account_balance_wallet_outlined,
              nilai: TeksUang(Uang.Dari(shift.KasAwal)),
            ),
            KartuAngka(label: 'Kas masuk', ikon: Icons.south_west, nilai: TeksUang(Jumlahkan(JenisMutasi.masuk))),
            KartuAngka(
              label: 'Kas keluar & setoran',
              ikon: Icons.north_east,
              nilai: TeksUang(Jumlahkan(JenisMutasi.keluar).Tambah(Jumlahkan(JenisMutasi.setoran))),
            ),
            if (adaPenjualan)
              KartuAngka(
                label: 'Penjualan tunai bersih',
                ikon: Icons.payments_outlined,
                nilai: TeksUang(tunaiPenjualan),
              ),
            if (!refundTunai.BernilaiNol())
              KartuAngka(
                label: 'Uang kembali ke pelanggan (batal & retur)',
                ikon: Icons.undo,
                nilai: TeksUang(Uang.Nol().Kurangi(refundTunai)),
              ),
            KartuAngka(
              label: adaPenjualan ? 'Perkiraan kas di laci' : 'Kas di laci (tanpa penjualan)',
              ikon: Icons.point_of_sale,
              nilai: TeksUang(adaPenjualan ? perkiraan : kas),
              tebal: true,
            ),
          ],
        ),
        if (adaPenjualan) ...[
          const SizedBox(height: TokenJarak.jarak4),
          Text(
            'Penjualan tunai bersih = uang tunai diterima dikurangi kembalian'
            '${refundTunai.BernilaiNol() ? '' : ', termasuk transaksi yang kemudian di-void'}.',
            style: teks.bodySmall,
          ),
        ],
        const SizedBox(height: TokenJarak.jarak16),
        Text('Kas masuk, keluar & setoran', style: teks.titleSmall),
        const SizedBox(height: TokenJarak.jarak4),
        if (mutasi.isEmpty)
          Text(
            'Belum ada kas masuk atau keluar di shift ini.',
            style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
          )
        else
          KotakPanel(
            rapat: true,
            anak: Column(
              children: [
                for (final (i, m) in mutasi.indexed)
                  DecoratedBox(
                    decoration: BoxDecoration(
                      border: i == 0
                          ? null
                          : Border(
                              top: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
                            ),
                    ),
                    child: ListTile(
                      dense: true,
                      contentPadding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak12),
                      minTileHeight: TokenJarak.targetSentuh,
                      title: Text(m.NamaKategori ?? LembarMutasiKas.AmbilJudul(m.Jenis)),
                      subtitle: Text(
                        [
                          LembarMutasiKas.AmbilJudul(m.Jenis),
                          if (m.Catatan != null) m.Catatan!,
                          if (m.DisetujuiOleh != null) 'disetujui supervisor',
                        ].join(' · '),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      trailing: TeksUang(
                        m.Jenis == JenisMutasi.masuk ? Uang.Dari(m.Jumlah) : Uang.Nol().Kurangi(Uang.Dari(m.Jumlah)),
                        gaya: TextStyle(color: m.Jenis == JenisMutasi.masuk ? warna.sukses : warna.teksUtama),
                      ),
                    ),
                  ),
              ],
            ),
          ),
      ],
    );
  }
}
