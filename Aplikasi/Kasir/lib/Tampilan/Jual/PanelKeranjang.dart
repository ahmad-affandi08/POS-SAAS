import 'package:flutter/material.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Domain/Penjualan/Keranjang.dart';
import '../../Domain/Penjualan/LayananPenjualan.dart';
import '../Komponen/FormatAngka.dart';

/// Isi keranjang layar Jual (PRD §17.2.3, §17.2.7): daftar baris, ringkasan total, dan aksi Diskon · Tahan · BAYAR.
/// Pintasan: F8 bayar, F9 uang pas. Hierarki: TOTAL paling besar, lalu tombol BAYAR, lalu isi keranjang. Hanya tampilan; aksinya lewat callback.
class PanelKeranjang extends StatelessWidget {
  const PanelKeranjang({
    super.key,
    required this.keranjang,
    required this.hitungan,
    required this.saatUbahBaris,
    required this.saatTambah,
    required this.saatKurang,
    required this.saatDiskonPesanan,
    required this.saatTahan,
    required this.saatKosongkan,
    required this.saatBayar,
    this.tampilKepala = true,
  });

  final Keranjang keranjang;
  final HitunganKeranjang? hitungan;
  final ValueChanged<String> saatUbahBaris;
  final ValueChanged<String> saatTambah;
  final ValueChanged<String> saatKurang;
  final VoidCallback saatDiskonPesanan;
  final VoidCallback saatTahan;
  final VoidCallback saatKosongkan;
  final VoidCallback saatBayar;

  /// Kepala "Keranjang" (disembunyikan saat tampil di dalam lembar yang sudah berjudul).
  final bool tampilKepala;

  static List<String> AmbilRincian(ItemKeranjang b) => [
    if (b.namaSatuan != null && b.namaSatuan!.isNotEmpty) '@ ${b.hargaSatuan.FormatRupiah()}/${b.namaSatuan}',
    for (final p in b.pilihan) p.harga.BernilaiNol() ? p.nama : '${p.nama} +${p.harga.FormatRupiah()}',
    if (b.diskon != null)
      b.diskon!.persen != null ? b.diskon!.AmbilLabel() : 'Diskon ${b.diskon!.jumlah!.FormatRupiah()}',
    if (b.catatan != null) 'Catatan: ${b.catatan}',
  ];

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final hasil = hitungan?.hasil;
    final kosong = keranjang.CekKosong;
    final gayaTombolKecil = OutlinedButton.styleFrom(
      padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak8),
    );

    return ColoredBox(
      color: warna.permukaan,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (tampilKepala)
            Container(
              padding: const EdgeInsets.fromLTRB(
                TokenJarak.jarak16,
                TokenJarak.jarak8,
                TokenJarak.jarak8,
                TokenJarak.jarak8,
              ),
              decoration: BoxDecoration(
                border: Border(
                  bottom: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
                ),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Semantics(
                      header: true,
                      child: Text(
                        kosong
                            ? 'Keranjang'
                            : 'Keranjang · ${FormatAngka.FormatJumlah(keranjang.HitungJumlahItem())} item',
                        style: teks.titleMedium,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Batalkan transaksi',
                    onPressed: kosong ? null : saatKosongkan,
                    icon: const Icon(Icons.remove_shopping_cart_outlined),
                  ),
                ],
              ),
            ),
          Expanded(
            child: kosong
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(TokenJarak.jarak24),
                      child: Text(
                        'Keranjang kosong. Ketuk produk atau pindai barcode untuk mulai.',
                        textAlign: TextAlign.center,
                        style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
                      ),
                    ),
                  )
                : ListView(
                    children: [
                      for (var i = 0; i < keranjang.baris.length; i++)
                        BarisKeranjang(
                          key: ValueKey(keranjang.baris[i].uuid),
                          nama: keranjang.baris[i].nama,
                          jumlah: FormatAngka.FormatJumlah(keranjang.baris[i].jumlah),
                          total: hasil == null
                              ? keranjang.baris[i].hargaSatuan
                              : hasil.baris[i].bruto.Kurangi(hasil.baris[i].diskon),
                          rincian: AmbilRincian(keranjang.baris[i]),
                          saatDiketuk: () => saatUbahBaris(keranjang.baris[i].uuid),
                          saatTambah: () => saatTambah(keranjang.baris[i].uuid),
                          saatKurang: () => saatKurang(keranjang.baris[i].uuid),
                        ),
                      for (final peringatan in hitungan?.peringatan ?? const <String>[])
                        Padding(
                          padding: const EdgeInsets.fromLTRB(
                            TokenJarak.jarak16,
                            TokenJarak.jarak8,
                            TokenJarak.jarak16,
                            0,
                          ),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Icon(Icons.warning_amber_outlined, size: TokenJarak.ikonKecil, color: warna.peringatan),
                              const SizedBox(width: TokenJarak.jarak8),
                              Expanded(child: Text(peringatan, style: teks.bodySmall)),
                            ],
                          ),
                        ),
                    ],
                  ),
          ),
          Container(
            padding: const EdgeInsets.fromLTRB(
              TokenJarak.jarak16,
              TokenJarak.jarak8,
              TokenJarak.jarak16,
              TokenJarak.jarak16,
            ),
            decoration: BoxDecoration(
              border: Border(
                top: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (hasil != null) ...RingkasanTotal.BangunBaris(context, hitungan!, keranjang),
                const SizedBox(height: TokenJarak.jarak8),
                Row(
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 56,
                        child: OutlinedButton(
                          style: gayaTombolKecil,
                          onPressed: kosong ? null : saatDiskonPesanan,
                          child: const Text('Diskon', maxLines: 1, overflow: TextOverflow.ellipsis),
                        ),
                      ),
                    ),
                    const SizedBox(width: TokenJarak.jarak8),
                    Expanded(
                      child: SizedBox(
                        height: 56,
                        child: OutlinedButton(
                          style: gayaTombolKecil,
                          onPressed: kosong ? null : saatTahan,
                          child: const Text('Tahan', maxLines: 1, overflow: TextOverflow.ellipsis),
                        ),
                      ),
                    ),
                    const SizedBox(width: TokenJarak.jarak8),
                    Expanded(
                      flex: 2,
                      child: SizedBox(
                        height: 56,
                        child: Tooltip(
                          message: 'Bayar (F8) · uang pas (F9)',
                          child: FilledButton(
                            onPressed: kosong ? null : saatBayar,
                            child: Text('Bayar', style: teks.titleMedium?.copyWith(color: warna.permukaan)),
                          ),
                        ),
                      ),
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

/// Baris ringkasan total (subtotal, diskon, biaya layanan, pajak per jenis, pembulatan, TOTAL).
abstract final class RingkasanTotal {
  static List<Widget> BangunBaris(
    BuildContext context,
    HitunganKeranjang hitungan,
    Keranjang keranjang, {
    bool tampilPembulatan = false,
  }) {
    final teks = Theme.of(context).textTheme;
    final hasil = hitungan.hasil;
    Widget Baris(String label, Uang nilai, {TextStyle? gaya}) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Expanded(child: Text(label, style: gaya ?? teks.bodyMedium)),
          TeksUang(nilai, gaya: gaya ?? teks.bodyMedium),
        ],
      ),
    );

    return [
      Baris('Subtotal', hasil.subtotal),
      if (!hasil.diskonPesanan.BernilaiNol())
        Baris(
          keranjang.diskonPesanan?.persen != null
              ? '${keranjang.diskonPesanan!.AmbilLabel()} pesanan'
              : 'Diskon pesanan',
          Uang.Nol().Kurangi(hasil.diskonPesanan),
        ),
      if (!hasil.biayaLayanan.BernilaiNol()) Baris('Biaya layanan', hasil.biayaLayanan),
      for (final p in hitungan.pajakDokumen)
        if (hasil.pajak[p.kode] != null && !hasil.pajak[p.kode]!.jumlah.BernilaiNol())
          Baris(
            '${hitungan.labelPajak[p.kode] ?? p.kode} ${FormatAngka.FormatPersen(hitungan.tarifDipakai[p.kode]!.tarif)}',
            hasil.pajak[p.kode]!.jumlah,
          ),
      if (tampilPembulatan && !hasil.pembulatan.BernilaiNol()) Baris('Pembulatan tunai', hasil.pembulatan),
      const SizedBox(height: TokenJarak.jarak4),
      Semantics(
        label: 'Total ${hasil.totalAkhir.FormatRupiah()}',
        excludeSemantics: true,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.baseline,
          textBaseline: TextBaseline.alphabetic,
          children: [
            Expanded(child: Text('TOTAL', style: teks.titleMedium)),
            Flexible(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerRight,
                child: TeksUang(hasil.totalAkhir, gaya: teks.displaySmall),
              ),
            ),
          ],
        ),
      ),
    ];
  }
}
