import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:inti/Inti.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/PesananMeja.dart';
import '../Penjualan/LayananPreOrder.dart';
import '../Shift/LayananTutupShift.dart';
import 'IdentitasStruk.dart';
import 'PenyusunStrukPenjualan.dart';

/// Dokumen cetak kasir selain struk penjualan (cetak struk bagian 3b): bukti void, nota retur, dan laporan shift X/Z;
/// bagian 4a: bukti uang muka pre-order; bagian 4c: tiket dapur per stasiun.
/// Kepala & kaki mengikuti pengaturan struk tenant; angka memakai format yang sama dengan struk penjualan. Bukti void
/// dan nota retur bisa membuka laci bila ada refund tunai dari laci.
abstract final class PenyusunDokumenKasir {
  static DokumenStruk SusunVoid(
    IdentitasStruk identitas,
    BarisPenjualan penjualan,
    BarisVoidPenjualan dokumen, {
    bool cetakUlang = false,
    bool bukaLaci = false,
  }) {
    final (tanggal, jam) = PenyusunStrukPenjualan.TanggalJam(dokumen.DivoidPada);
    final refundTunai = Uang.Dari(dokumen.RefundTunai);
    final refundNonTunai = Uang.Dari(dokumen.RefundNonTunai);
    return DokumenStruk([
      ...PenyusunStrukPenjualan.SusunKepala(identitas),
      const BarisGaris(),
      const BarisTeks('BUKTI VOID', rata: RataStruk.Tengah, tebal: true),
      if (cetakUlang) const BarisTeks('CETAK ULANG', rata: RataStruk.Tengah, tebal: true),
      BarisTeks(penjualan.Nomor),
      BarisDuaKolom(tanggal, jam),
      BarisTeks('Kasir: ${dokumen.NamaPengguna}'),
      BarisTeks('Disetujui: ${dokumen.NamaPenyetuju}'),
      BarisTeks('Alasan: ${dokumen.Alasan}'),
      const BarisGaris(),
      BarisDuaKolom('Total dibatalkan', Uang.Dari(dokumen.Nominal).FormatRupiah(), tebal: true),
      if (!refundTunai.BernilaiNol()) BarisDuaKolom('Refund tunai', PenyusunStrukPenjualan.Angka(refundTunai)),
      if (!refundNonTunai.BernilaiNol())
        BarisDuaKolom('Refund non-tunai', PenyusunStrukPenjualan.Angka(refundNonTunai)),
      const BarisGaris(),
      ...PenyusunStrukPenjualan.SusunKaki(identitas),
    ], bukaLaci: bukaLaci);
  }

  static DokumenStruk SusunRetur(
    IdentitasStruk identitas,
    BarisReturPenjualan retur,
    List<BarisReturPenjualanDetail> detail,
    List<BarisReturPenjualanPembayaran> pembayaran, {
    bool cetakUlang = false,
    bool bukaLaci = false,
  }) {
    final (tanggal, jam) = PenyusunStrukPenjualan.TanggalJam(retur.DibuatPada);
    return DokumenStruk([
      ...PenyusunStrukPenjualan.SusunKepala(identitas),
      const BarisGaris(),
      const BarisTeks('NOTA RETUR', rata: RataStruk.Tengah, tebal: true),
      if (cetakUlang) const BarisTeks('CETAK ULANG', rata: RataStruk.Tengah, tebal: true),
      BarisTeks(retur.Nomor),
      BarisTeks('Asal: ${retur.NomorPenjualanAsal}'),
      BarisDuaKolom(tanggal, jam),
      if (identitas.pengaturan.tampilkanKasir) BarisTeks('Kasir: ${retur.NamaKasir}'),
      const BarisGaris(),
      for (final d in detail) ...[
        BarisTeks(d.NamaProduk),
        BarisDuaKolom(
          '  ${PenyusunStrukPenjualan.Jumlah(d.Jumlah)} ${d.SimbolSatuan}${d.Kondisi == 'Rusak' ? ' (rusak)' : ''}'
              .trimRight(),
          PenyusunStrukPenjualan.Angka(Uang.Dari(d.NilaiBaris)),
        ),
      ],
      const BarisGaris(),
      BarisDuaKolom('TOTAL REFUND', Uang.Dari(retur.TotalRefund).FormatRupiah(), tebal: true),
      for (final b in pembayaran) BarisDuaKolom(b.NamaMetode, PenyusunStrukPenjualan.Angka(Uang.Dari(b.Jumlah))),
      BarisTeks('Alasan: ${retur.Alasan}'),
      const BarisGaris(),
      ...PenyusunStrukPenjualan.SusunKaki(identitas),
    ], bukaLaci: bukaLaci);
  }

  /// Bukti uang muka pre-order (cetak struk bagian 4a): nomor pre-order, pelanggan, tanggal ambil, barang pesanan,
  /// total pesanan, uang muka per metode, dan sisa yang dibayar saat diambil (perkiraan: harga dikunci, pajak & promo
  /// dihitung ulang saat pengambilan).
  static DokumenStruk SusunPreOrder(
    IdentitasStruk identitas,
    PreOrderTersimpan preOrder, {
    bool cetakUlang = false,
    bool bukaLaci = false,
  }) {
    final (tanggal, jam) = PenyusunStrukPenjualan.TanggalJam(preOrder.dibuatPada ?? DateTime.now().toUtc());
    final t = preOrder.tanggalAmbil;
    return DokumenStruk([
      ...PenyusunStrukPenjualan.SusunKepala(identitas),
      const BarisGaris(),
      const BarisTeks('BUKTI UANG MUKA', rata: RataStruk.Tengah, tebal: true),
      const BarisTeks('PRE-ORDER', rata: RataStruk.Tengah),
      if (cetakUlang) const BarisTeks('CETAK ULANG', rata: RataStruk.Tengah, tebal: true),
      BarisTeks(preOrder.nomor),
      BarisDuaKolom(tanggal, jam),
      if (identitas.pengaturan.tampilkanKasir && preOrder.namaKasir.isNotEmpty)
        BarisTeks('Kasir: ${preOrder.namaKasir}'),
      BarisTeks('Pelanggan: ${preOrder.namaPelanggan}'),
      BarisTeks('Diambil: ${t.substring(8, 10)}/${t.substring(5, 7)}/${t.substring(0, 4)}', tebal: true),
      const BarisGaris(),
      for (final b in preOrder.baris) ...[
        BarisTeks(b.nama),
        BarisDuaKolom(
          '  ${PenyusunStrukPenjualan.Jumlah(b.jumlah.KeString())} ${b.satuan ?? ''}'.trimRight(),
          PenyusunStrukPenjualan.Angka(b.nilai),
        ),
      ],
      const BarisGaris(),
      BarisDuaKolom('Total pesanan', PenyusunStrukPenjualan.Angka(preOrder.totalPesanan)),
      BarisDuaKolom('UANG MUKA', preOrder.uangMuka.FormatRupiah(), tebal: true),
      BarisDuaKolom(preOrder.namaMetode, PenyusunStrukPenjualan.Angka(preOrder.uangMuka)),
      BarisDuaKolom(
        'Sisa saat diambil',
        PenyusunStrukPenjualan.Angka(preOrder.totalPesanan.Kurangi(preOrder.uangMuka)),
      ),
      const BarisTeks('Sisa dapat berubah bila pajak atau promo berubah saat diambil.'),
      if (preOrder.catatan != null) BarisTeks('Catatan: ${preOrder.catatan}'),
      const BarisTeks('Simpan bukti ini untuk mengambil pesanan.'),
      const BarisGaris(),
      ...PenyusunStrukPenjualan.SusunKaki(identitas),
    ], bukaLaci: bukaLaci);
  }

  /// Tiket dapur satu stasiun untuk satu kiriman (cetak struk bagian 4c): tanpa harga, nama meja & jumlah dicetak
  /// besar agar terbaca dari jauh, pilihan & catatan per item di bawahnya.
  static DokumenStruk SusunTiketDapur({
    required String namaStasiun,
    required PesananMeja pesanan,
    required List<BarisPesananMeja> baris,
    required DateTime waktu,
    String? namaKasir,
    bool cetakUlang = false,
  }) {
    final (tanggal, jam) = PenyusunStrukPenjualan.TanggalJam(waktu);
    final ronde = baris.fold(0, (maks, b) => b.ronde > maks ? b.ronde : maks);
    return DokumenStruk([
      BarisTeks('TIKET ${namaStasiun.toUpperCase()}', rata: RataStruk.Tengah, tebal: true),
      if (cetakUlang) const BarisTeks('CETAK ULANG', rata: RataStruk.Tengah, tebal: true),
      BarisTeks(pesanan.AmbilJudul(), rata: RataStruk.Tengah, tebal: true, besar: true),
      BarisTeks(pesanan.nomor),
      BarisDuaKolom('Ronde $ronde', '$tanggal $jam'),
      if (namaKasir != null && namaKasir.isNotEmpty) BarisTeks('Kasir: $namaKasir'),
      const BarisGaris(),
      for (final b in baris) ...[
        BarisTeks('${PenyusunStrukPenjualan.Jumlah(b.jumlah)} x ${b.namaProduk}', tebal: true, besar: true),
        for (final p in b.pilihan)
          if (p['Nama'] case final String nama when nama.isNotEmpty) BarisTeks('  + $nama'),
        if (b.catatan case final String catatan when catatan.trim().isNotEmpty)
          BarisTeks('  Catatan: ${catatan.trim()}'),
      ],
      const BarisGaris(),
      BarisTeks('${baris.length} item', rata: RataStruk.Kanan),
    ]);
  }

  /// Laporan X (shift berjalan) atau Z (shift tertutup). [tampilkanKasSeharusnya] = false untuk tutup buta.
  static DokumenStruk SusunLaporanShift(
    IdentitasStruk identitas,
    LaporanShift laporan, {
    bool tampilkanKasSeharusnya = true,
  }) {
    final s = laporan.shift;
    String Nilai(Uang nilai) =>
        nilai.BernilaiNegatif() ? '-${PenyusunStrukPenjualan.Angka(nilai)}' : PenyusunStrukPenjualan.Angka(nilai);
    final (tanggalBuka, jamBuka) = PenyusunStrukPenjualan.TanggalJam(s.DibukaPada);
    final ditutup = s.DitutupPada;
    return DokumenStruk([
      ...PenyusunStrukPenjualan.SusunKepala(identitas),
      const BarisGaris(),
      BarisTeks(laporan.tertutup ? 'LAPORAN Z' : 'LAPORAN X', rata: RataStruk.Tengah, tebal: true),
      BarisTeks('Kasir: ${s.NamaKasir}'),
      BarisDuaKolom('Dibuka', '$tanggalBuka $jamBuka'),
      if (ditutup != null)
        BarisDuaKolom('Ditutup', () {
          final (tanggal, jam) = PenyusunStrukPenjualan.TanggalJam(ditutup);
          return '$tanggal $jam';
        }()),
      const BarisGaris(),
      BarisDuaKolom('Jumlah transaksi', '${laporan.jumlahTransaksi}'),
      BarisDuaKolom('Penjualan kotor', Nilai(laporan.penjualanKotor)),
      BarisDuaKolom('Diskon', Nilai(laporan.totalDiskon)),
      BarisDuaKolom('Penjualan bersih', Nilai(laporan.penjualanBersih), tebal: true),
      BarisDuaKolom('Pajak', Nilai(laporan.totalPajak)),
      if (!laporan.biayaLayanan.BernilaiNol()) BarisDuaKolom('Biaya layanan', Nilai(laporan.biayaLayanan)),
      if (!laporan.pembulatan.BernilaiNol()) BarisDuaKolom('Pembulatan', Nilai(laporan.pembulatan)),
      BarisDuaKolom('Total', laporan.totalAkhir.FormatRupiah(), tebal: true),
      BarisDuaKolom('Void ${laporan.jumlahVoid}x', Nilai(laporan.nominalVoid)),
      BarisDuaKolom('Retur ${laporan.jumlahRetur}x', Nilai(laporan.nominalRetur)),
      const BarisGaris(),
      const BarisTeks('Per metode bayar', tebal: true),
      for (final m in laporan.perMetode) BarisDuaKolom(m.nama, Nilai(m.jumlah)),
      const BarisGaris(),
      const BarisTeks('Kas laci', tebal: true),
      BarisDuaKolom('Kas awal', Nilai(laporan.kasAwal)),
      BarisDuaKolom('Tunai bersih', Nilai(laporan.tunaiMasukBersih)),
      BarisDuaKolom('Kas masuk', Nilai(laporan.kasMasuk)),
      BarisDuaKolom('Kas keluar', Nilai(Uang.Nol().Kurangi(laporan.kasKeluar))),
      BarisDuaKolom('Setoran', Nilai(Uang.Nol().Kurangi(laporan.setoran))),
      BarisDuaKolom('Refund tunai', Nilai(Uang.Nol().Kurangi(laporan.refundTunai))),
      if (tampilkanKasSeharusnya)
        BarisDuaKolom(
          'Kas seharusnya',
          Nilai(s.KasSeharusnya == null ? laporan.kasSeharusnya : Uang.Dari(s.KasSeharusnya!)),
          tebal: true,
        ),
      if (s.KasAktual != null) BarisDuaKolom('Kas aktual', Nilai(Uang.Dari(s.KasAktual!)), tebal: true),
      if (s.Selisih != null) BarisDuaKolom('Selisih', Nilai(Uang.Dari(s.Selisih!)), tebal: true),
      if (s.AlasanSelisih != null) BarisTeks('Alasan selisih: ${s.AlasanSelisih}'),
      const BarisGaris(),
      const BarisKosong(),
      const BarisTeks('Tanda tangan kasir', rata: RataStruk.Tengah),
      const BarisKosong(),
      const BarisKosong(),
      const BarisGaris(),
    ]);
  }
}
