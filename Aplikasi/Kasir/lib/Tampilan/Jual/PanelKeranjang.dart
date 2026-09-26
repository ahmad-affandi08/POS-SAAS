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
    this.judul,
    this.statusBaris = const {},
    this.labelTahan = 'Tahan',
    this.labelKosongkan = 'Batalkan transaksi',
    this.saatPelanggan,
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

  /// Judul kepala pengganti "Keranjang" (mode meja: nama meja/pesanan).
  final String? judul;

  /// Mode meja: status baris yang sudah tersimpan di pesanan (Uuid baris → "Dimasak", "Belum dikirim", …). Baris tanpa
  /// status = item baru yang belum disimpan.
  final Map<String, String> statusBaris;

  /// Tombol kedua: "Tahan" (retail) atau "Kirim ke dapur" (mode meja).
  final String labelTahan;
  final String labelKosongkan;

  /// F-16a: buka panel pelanggan (F2). Null = tombol pelanggan tidak ditampilkan.
  final VoidCallback? saatPelanggan;

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
                            ? judul ?? 'Keranjang'
                            : '${judul ?? 'Keranjang'} · ${FormatAngka.FormatJumlah(keranjang.HitungJumlahItem())} item',
                        style: teks.titleMedium,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: labelKosongkan,
                    onPressed: kosong && keranjang.pesananMeja == null ? null : saatKosongkan,
                    icon: const Icon(Icons.remove_shopping_cart_outlined),
                  ),
                ],
              ),
            ),
          if (saatPelanggan != null)
            Material(
              color: warna.permukaan,
              child: InkWell(
                onTap: saatPelanggan,
                child: Container(
                  constraints: const BoxConstraints(minHeight: TokenJarak.targetSentuh),
                  padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16, vertical: TokenJarak.jarak8),
                  decoration: BoxDecoration(
                    border: Border(
                      bottom: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        keranjang.pelanggan == null ? Icons.person_outline : Icons.person,
                        size: TokenJarak.ikonSedang,
                        color: keranjang.pelanggan == null ? warna.teksSekunder : warna.brand,
                      ),
                      const SizedBox(width: TokenJarak.jarak8),
                      Expanded(
                        child: Text(
                          keranjang.pelanggan == null
                              ? 'Pelanggan umum · ketuk untuk memilih (F2)'
                              : [
                                  keranjang.pelanggan!.nama,
                                  keranjang.pelanggan!.noHpSamar,
                                  if (keranjang.pelanggan!.namaTier != null) keranjang.pelanggan!.namaTier!,
                                ].join(' · '),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: teks.bodyMedium,
                        ),
                      ),
                      Icon(Icons.chevron_right, color: warna.teksSekunder),
                    ],
                  ),
                ),
              ),
            ),
          Expanded(
            child: kosong
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(TokenJarak.jarak24),
                      child: Text(
                        keranjang.pesananMeja == null
                            ? 'Keranjang kosong. Ketuk produk atau pindai barcode untuk mulai.'
                            : 'Pesanan masih kosong. Ketuk produk untuk menambah, lalu Kirim ke dapur.',
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
                          rincian: [
                            if (statusBaris[keranjang.baris[i].uuid] case final status?) 'Status: $status',
                            if (keranjang.pesananMeja != null && !statusBaris.containsKey(keranjang.baris[i].uuid))
                              'Item baru',
                            ...AmbilRincian(keranjang.baris[i]),
                            for (final p in hitungan?.promoTerpakai ?? const <PromoTerpakai>[])
                              if (p.diskonBaris[i] case final diskon?)
                                'Promo ${hitungan!.AmbilNamaPromo(p)} −${diskon.FormatRupiah()}',
                          ],
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
                          child: Text(labelTahan, maxLines: 1, overflow: TextOverflow.ellipsis),
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
    // Diskon pesanan manual = total diskon pesanan − tukar poin (F-16b) − promo pesanan (F-16c).
    final diskonManualPesanan = hasil.diskonPesanan
        .Kurangi(hasil.diskonPoin)
        .Kurangi(hitungan.HitungDiskonPromoPesanan());
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
      if (!diskonManualPesanan.BernilaiNol())
        Baris(
          keranjang.diskonPesanan?.persen != null
              ? '${keranjang.diskonPesanan!.AmbilLabel()} pesanan'
              : 'Diskon pesanan',
          Uang.Nol().Kurangi(diskonManualPesanan),
        ),
      for (final p in hitungan.promoTerpakai)
        if (!p.diskonPesanan.BernilaiNol())
          Baris('Promo ${hitungan.AmbilNamaPromo(p)}', Uang.Nol().Kurangi(p.diskonPesanan)),
      if (keranjang.tukarPoin != null)
        Baris('Tukar ${keranjang.tukarPoin!.poin} poin', Uang.Nol().Kurangi(hasil.diskonPoin)),
      if (hitungan.AmbilLabelPoinBerlipat() case final String label)
        Padding(
          padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak4 / 2),
          child: Row(
            children: [
              Icon(Icons.stars_outlined, size: 18, color: TokenWarna.AmbilDari(context).brand),
              const SizedBox(width: TokenJarak.jarak4),
              Expanded(child: Text(label, style: Theme.of(context).textTheme.bodyMedium)),
            ],
          ),
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
