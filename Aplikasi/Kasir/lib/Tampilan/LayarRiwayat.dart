import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/BasisData/BasisDataKasir.dart';
import '../Data/RepositoriPenjualan.dart';
import '../Domain/Penjualan/LayananVoidPenjualan.dart';
import 'Komponen/FormatAngka.dart';
import 'Komponen/FormatWaktu.dart';
import 'RuangKerja/IsiAreaKerja.dart';

/// Pesan tetap bila riwayat gagal dimuat; detail galat hanya ke log (tidak menampilkan teks exception ke kasir).
const String pesanGagalMuat = 'Riwayat transaksi tidak bisa dimuat. Coba lagi.';

void CatatGalat(String konteks, Object galat, StackTrace? jejak) =>
    debugPrint('LayarRiwayat: gagal memuat $konteks: $galat${jejak == null ? '' : '\n$jejak'}');

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
/// F-09 fase 1: "Void transaksi" untuk transaksi `Lunas` shift yang masih terbuka ([saatVoid]), "Retur dari struk"
/// ([saatRetur]), dan daftar retur hari ini. Void & retur dibuka sebagai panel tugas oleh bingkai ruang kerja.
class LayarRiwayat extends ConsumerWidget {
  const LayarRiwayat({super.key, this.saatVoid, this.saatRetur, this.saatAmbilPreOrder});

  /// Buka lembar void untuk Uuid penjualan.
  final ValueChanged<String>? saatVoid;

  /// Buka lembar retur dari struk.
  final VoidCallback? saatRetur;

  /// F-12 bagian 2: buka lembar cari & ambil pre-order.
  final VoidCallback? saatAmbilPreOrder;

  /// Label status dokumen penjualan selain `Lunas` (selalu berteks, bukan hanya warna).
  static String? AmbilLabelStatusDokumen(String status) => switch (status) {
    StatusPenjualanLokal.divoid => 'Void',
    StatusPenjualanLokal.direturSebagian => 'Diretur sebagian',
    StatusPenjualanLokal.diretur => 'Diretur',
    _ => null,
  };

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
    final dihitung = daftar.where((r) => r.penjualan.Status != StatusPenjualanLokal.divoid).toList();
    final total = dihitung.fold(Uang.Nol(), (t, r) => t.Tambah(Uang.Dari(r.penjualan.TotalAkhir)));
    final jumlahVoid = daftar.length - dihitung.length;
    final retur = ref.watch(penyediaReturHariIni).value ?? const <RiwayatRetur>[];
    final shiftAktif = ref.watch(penyediaShiftAktif).value;

    return IsiAreaKerja(
      judul: 'Riwayat transaksi hari ini',
      lebarMaksimum: 840,
      anak: [
        if (riwayat.isLoading && riwayat.value == null) const LinearProgressIndicator(),
        if (riwayat.hasError)
          Builder(
            builder: (_) {
              CatatGalat('riwayat hari ini', riwayat.error!, riwayat.stackTrace);
              return Text(pesanGagalMuat, style: TextStyle(color: warna.bahaya));
            },
          ),
        Wrap(
          spacing: TokenJarak.jarak12,
          runSpacing: TokenJarak.jarak8,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Text(
              daftar.isEmpty
                  ? 'Belum ada transaksi hari ini di perangkat ini.'
                  : '${dihitung.length} transaksi · ${total.FormatRupiah()}'
                        '${jumlahVoid == 0 ? '' : ' · $jumlahVoid void'}',
              style: teks.titleMedium,
            ),
            if (saatRetur != null)
              SizedBox(
                height: TokenJarak.targetSentuh,
                child: OutlinedButton.icon(
                  onPressed: saatRetur,
                  icon: const Icon(Icons.assignment_return_outlined),
                  label: const Text('Retur dari struk'),
                ),
              ),
            if (saatAmbilPreOrder != null)
              SizedBox(
                height: TokenJarak.targetSentuh,
                child: OutlinedButton.icon(
                  onPressed: saatAmbilPreOrder,
                  icon: const Icon(Icons.event_available_outlined),
                  label: const Text('Ambil pre-order'),
                ),
              ),
          ],
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
                  _BarisRiwayat(
                    riwayat: daftar[i],
                    saatVoid: saatVoid != null && LayananVoidPenjualan.CekBisaDivoid(daftar[i].penjualan, shiftAktif)
                        ? () => saatVoid!(daftar[i].penjualan.Uuid)
                        : null,
                  ),
                ],
              ],
            ),
          ),
        if (retur.isNotEmpty) ...[
          const SizedBox(height: TokenJarak.jarak24),
          Text('Retur hari ini', style: teks.titleMedium),
          const SizedBox(height: TokenJarak.jarak8),
          Material(
            color: warna.permukaan,
            shape: RoundedRectangleBorder(
              side: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
              borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (var i = 0; i < retur.length; i++) ...[
                  if (i > 0) Divider(height: TokenJarak.tebalGaris, color: warna.garis),
                  _BarisRetur(riwayat: retur[i]),
                ],
              ],
            ),
          ),
        ],
      ],
    );
  }
}

class _BarisRetur extends StatelessWidget {
  const _BarisRetur({required this.riwayat});

  final RiwayatRetur riwayat;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final r = riwayat.retur;
    final status = LayarRiwayat.AmbilStatus(riwayat.status);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16, vertical: TokenJarak.jarak12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: TeksKode(r.Nomor, gaya: teks.labelLarge)),
              TeksUang(Uang.Nol().Kurangi(Uang.Dari(r.TotalRefund)), gaya: teks.labelLarge),
            ],
          ),
          Row(
            children: [
              Expanded(
                child: Text(
                  '${FormatWaktu.FormatJam(r.DibuatPada)} · ${r.NamaKasir} · dari ${r.NomorPenjualanAsal}',
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
          if (riwayat.status == StatusSinkronPenjualan.PerluTindakan && riwayat.pesanGalat != null)
            Text(riwayat.pesanGalat!, style: teks.bodySmall?.copyWith(color: warna.bahaya)),
        ],
      ),
    );
  }
}

class _BarisRiwayat extends ConsumerWidget {
  const _BarisRiwayat({required this.riwayat, this.saatVoid});

  final RiwayatPenjualan riwayat;

  /// Null = transaksi ini tidak bisa di-void di perangkat ini sekarang.
  final VoidCallback? saatVoid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final p = riwayat.penjualan;
    final status = LayarRiwayat.AmbilStatus(riwayat.status);
    final labelDokumen = LayarRiwayat.AmbilLabelStatusDokumen(p.Status);
    final divoid = p.Status == StatusPenjualanLokal.divoid;
    return Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: ExpansionTile(
        key: ValueKey(p.Uuid),
        tilePadding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16),
        childrenPadding: const EdgeInsets.fromLTRB(TokenJarak.jarak16, 0, TokenJarak.jarak16, TokenJarak.jarak16),
        title: Row(
          children: [
            Expanded(child: TeksKode(p.Nomor, gaya: teks.labelLarge)),
            if (labelDokumen != null) ...[
              Icon(
                divoid ? Icons.block : Icons.assignment_return_outlined,
                size: TokenJarak.ikonKecil,
                color: divoid ? warna.bahaya : warna.teksSekunder,
              ),
              const SizedBox(width: TokenJarak.jarak4),
              Text(labelDokumen, style: teks.labelMedium?.copyWith(color: divoid ? warna.bahaya : warna.teksUtama)),
              const SizedBox(width: TokenJarak.jarak8),
            ],
            TeksUang(
              Uang.Dari(p.TotalAkhir),
              gaya: teks.labelLarge?.copyWith(decoration: divoid ? TextDecoration.lineThrough : null),
            ),
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
                error: (galat, jejak) {
                  CatatGalat('detail penjualan ${p.Uuid}', galat, jejak);
                  return Text(pesanGagalMuat, style: TextStyle(color: warna.bahaya));
                },
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
                    if (saatVoid != null) ...[
                      const SizedBox(height: TokenJarak.jarak12),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: SizedBox(
                          height: TokenJarak.targetSentuh,
                          child: OutlinedButton.icon(
                            onPressed: saatVoid,
                            icon: const Icon(Icons.block),
                            label: const Text('Void transaksi'),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
        ],
      ),
    );
  }
}
