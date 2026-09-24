import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/BasisData/BasisDataKasir.dart';
import '../Data/RepositoriPenjualan.dart';
import 'Komponen/FormatAngka.dart';
import 'Komponen/FormatWaktu.dart';
import 'RuangKerja/IsiAreaKerja.dart';

/// Detail baris & pembayaran satu penjualan lokal.
final penyediaDetailPenjualan =
    FutureProvider.family<({List<BarisPenjualanDetail> detail, List<BarisPenjualanPembayaran> pembayaran}), String>((
      ref,
      uuid,
    ) async {
      final repo = ref.watch(penyediaRepositoriPenjualan);
      return (detail: await repo.AmbilDetail(uuid), pembayaran: await repo.AmbilPembayaran(uuid));
    });

/// Riwayat transaksi perangkat hari ini (Rincian F-07c) dengan status sinkron per transaksi: Terkirim, Belum terkirim
/// (masih di outbox), atau Perlu tindakan (ditolak server; kirim ulang dari layar Sinkron). Status selalu berteks.
class LayarRiwayat extends ConsumerWidget {
  const LayarRiwayat({super.key});

  static ({IconData ikon, String teks, NadaStatus nada}) AmbilStatus(StatusSinkronPenjualan status) => switch (status) {
    StatusSinkronPenjualan.Terkirim => (ikon: Icons.cloud_done_outlined, teks: 'Terkirim', nada: NadaStatus.Sukses),
    StatusSinkronPenjualan.BelumTerkirim => (
      ikon: Icons.cloud_upload_outlined,
      teks: 'Belum terkirim',
      nada: NadaStatus.Peringatan,
    ),
    StatusSinkronPenjualan.PerluTindakan => (
      ikon: Icons.error_outline,
      teks: 'Perlu tindakan',
      nada: NadaStatus.Bahaya,
    ),
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final riwayat = ref.watch(penyediaRiwayatHariIni);
    final daftar = riwayat.value ?? const <RiwayatPenjualan>[];
    final total = daftar.fold(Uang.Nol(), (t, r) => t.Tambah(Uang.Dari(r.penjualan.TotalAkhir)));

    return IsiAreaKerja(
      judul: 'Riwayat transaksi hari ini',
      lebarMaksimum: 840,
      anak: [
        if (riwayat.isLoading && riwayat.value == null) const LinearProgressIndicator(),
        if (riwayat.hasError)
          Text('Riwayat tidak bisa dimuat: ${riwayat.error}', style: TextStyle(color: warna.bahaya)),
        Text(
          daftar.isEmpty
              ? 'Belum ada transaksi hari ini di perangkat ini.'
              : '${daftar.length} transaksi · ${total.FormatRupiah()}',
          style: teks.titleMedium,
        ),
        const SizedBox(height: TokenJarak.jarak12),
        if (daftar.isNotEmpty)
          Material(
            color: warna.permukaan,
            shape: RoundedRectangleBorder(
              side: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
              borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (var i = 0; i < daftar.length; i++) ...[
                  if (i > 0) Divider(height: TokenJarak.tebalGaris, color: warna.garis),
                  _BarisRiwayat(riwayat: daftar[i]),
                ],
              ],
            ),
          ),
      ],
    );
  }
}

class _BarisRiwayat extends ConsumerWidget {
  const _BarisRiwayat({required this.riwayat});

  final RiwayatPenjualan riwayat;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final p = riwayat.penjualan;
    final status = LayarRiwayat.AmbilStatus(riwayat.status);
    return Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: ExpansionTile(
        key: ValueKey(p.Uuid),
        tilePadding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16),
        childrenPadding: const EdgeInsets.fromLTRB(TokenJarak.jarak16, 0, TokenJarak.jarak16, TokenJarak.jarak16),
        title: Row(
          children: [
            Expanded(child: TeksKode(p.Nomor, gaya: teks.labelLarge)),
            TeksUang(Uang.Dari(p.TotalAkhir), gaya: teks.labelLarge),
          ],
        ),
        subtitle: Row(
          children: [
            Expanded(
              child: Text(
                '${FormatWaktu.FormatJam(p.DibuatPada)} · ${p.NamaKasir}'
                '${riwayat.metode.isEmpty ? '' : ' · ${riwayat.metode.join(' + ')}'}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: teks.bodySmall,
              ),
            ),
            Icon(status.ikon, size: TokenJarak.ikonKecil, color: BilahStatus.AmbilWarnaNada(warna, status.nada)),
            const SizedBox(width: TokenJarak.jarak4),
            Text(status.teks, style: teks.bodySmall?.copyWith(color: warna.teksUtama)),
          ],
        ),
        children: [
          if (riwayat.status == StatusSinkronPenjualan.PerluTindakan && riwayat.pesanGalat != null)
            Padding(
              padding: const EdgeInsets.only(bottom: TokenJarak.jarak8),
              child: Text(riwayat.pesanGalat!, style: TextStyle(color: warna.bahaya)),
            ),
          ref
              .watch(penyediaDetailPenjualan(p.Uuid))
              .when(
                loading: () => const LinearProgressIndicator(),
                error: (galat, _) => Text('$galat'),
                data: (isi) => Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final d in isi.detail)
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              '${FormatAngka.FormatJumlah(Kuantitas.Dari(d.Jumlah))}× ${d.NamaProduk}',
                              style: teks.bodyMedium,
                            ),
                          ),
                          TeksUang(Uang.Dari(d.TotalBaris)),
                        ],
                      ),
                    const SizedBox(height: TokenJarak.jarak8),
                    for (final b in isi.pembayaran)
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              '${b.NamaMetode}${b.Referensi == null ? '' : ' · ${b.Referensi}'}',
                              style: teks.bodySmall,
                            ),
                          ),
                          TeksUang(Uang.Dari(b.Jumlah), gaya: teks.bodySmall),
                        ],
                      ),
                    if (!Uang.Dari(p.Kembalian).BernilaiNol())
                      Row(
                        children: [
                          Expanded(child: Text('Kembalian', style: teks.bodySmall)),
                          TeksUang(Uang.Dari(p.Kembalian), gaya: teks.bodySmall),
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
