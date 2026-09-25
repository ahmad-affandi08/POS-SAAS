import 'dart:convert';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:inti/Inti.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriPenjualan.dart';
import 'IdentitasStruk.dart';

/// Isi satu penjualan untuk dicetak (dari tabel lokal, jadi bisa offline dan dicetak ulang).
class DataStrukPenjualan {
  const DataStrukPenjualan({
    required this.penjualan,
    required this.detail,
    required this.pembayaran,
    this.namaPelanggan,
  });

  final BarisPenjualan penjualan;
  final List<BarisPenjualanDetail> detail;
  final List<BarisPenjualanPembayaran> pembayaran;

  /// Diketahui saat struk dicetak langsung setelah bayar; tidak disimpan lokal, jadi cetak ulang tanpa nama pelanggan.
  final String? namaPelanggan;
}

/// Menyusun struk penjualan (POS-11, PRD v1.79) sesuai pengaturan struk tenant. Angka memakai format Indonesia tanpa
/// "Rp" di baris rincian agar muat di kertas 58 mm; total memakai "Rp". Cetak ulang diberi tanda "CETAK ULANG"
/// (anti-fraud) dan penjualan void diberi tanda "DIBATALKAN". QR struk digital dicetak bila diaktifkan tenant.
abstract final class PenyusunStrukPenjualan {
  static const String penutupBawaan = 'Terima kasih atas kunjungan Anda';
  static const String tandaAir = 'Dibuat dengan PAYOU';

  static const List<String> _bulan = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];

  static DokumenStruk Susun(
    IdentitasStruk identitas,
    DataStrukPenjualan data, {
    bool cetakUlang = false,
    bool bukaLaci = false,
  }) {
    final p = identitas.pengaturan;
    final jual = data.penjualan;
    final baris = <BarisStruk>[...SusunKepala(identitas)];

    if (cetakUlang) {
      baris.add(const BarisTeks('CETAK ULANG', rata: RataStruk.Tengah, tebal: true));
    }
    if (jual.Status == StatusPenjualanLokal.divoid) {
      baris.add(const BarisTeks('DIBATALKAN', rata: RataStruk.Tengah, tebal: true));
    }
    final waktu = jual.DibuatPada.toLocal();
    baris
      ..add(const BarisGaris())
      ..add(BarisTeks(jual.Nomor))
      ..add(
        BarisDuaKolom(
          '${waktu.day} ${_bulan[waktu.month - 1]} ${waktu.year}',
          '${_Dua(waktu.hour)}.${_Dua(waktu.minute)}',
        ),
      );
    if (p.tampilkanKasir) {
      baris.add(BarisTeks('Kasir: ${jual.NamaKasir}'));
    }
    final pelanggan = data.namaPelanggan?.trim();
    if (p.tampilkanPelanggan && pelanggan != null && pelanggan.isNotEmpty) {
      baris.add(BarisTeks('Pelanggan: $pelanggan'));
    }
    baris.add(const BarisGaris());

    for (final d in data.detail) {
      baris.add(BarisTeks(d.NamaSatuan == null ? d.NamaProduk : '${d.NamaProduk} (${d.NamaSatuan})'));
      for (final nama in _NamaPilihan(d.Pilihan)) {
        baris.add(BarisTeks('  + $nama'));
      }
      final harga = Uang.Dari(d.HargaSatuan).Tambah(Uang.Dari(d.HargaPilihan));
      baris.add(BarisDuaKolom('  ${_Jumlah(d.Jumlah)} x ${_Angka(harga)}', _Angka(Uang.Dari(d.Bruto))));
      final diskon = Uang.Dari(d.Diskon);
      if (!diskon.BernilaiNol()) {
        baris.add(BarisDuaKolom('  Diskon', '-${_Angka(diskon)}'));
      }
      final catatan = d.Catatan?.trim();
      if (catatan != null && catatan.isNotEmpty) {
        baris.add(BarisTeks('  Catatan: $catatan'));
      }
    }

    final diskonPesanan = data.detail.fold(Uang.Nol(), (s, d) => s.Tambah(Uang.Dari(d.DiskonPesanan)));
    final pajakEksklusif = data.detail.fold(Uang.Nol(), (s, d) => s.Tambah(Uang.Dari(d.PajakEksklusif)));
    final totalPajak = Uang.Dari(jual.TotalPajak);
    final biayaLayanan = Uang.Dari(jual.BiayaLayanan);
    final pembulatan = Uang.Dari(jual.Pembulatan);
    baris
      ..add(const BarisGaris())
      ..add(BarisDuaKolom('Subtotal', _Angka(Uang.Dari(jual.Subtotal))));
    if (!diskonPesanan.BernilaiNol()) {
      baris.add(BarisDuaKolom('Diskon', '-${_Angka(diskonPesanan)}'));
    }
    if (!biayaLayanan.BernilaiNol()) {
      baris.add(BarisDuaKolom('Biaya layanan', _Angka(biayaLayanan)));
    }
    if (!pajakEksklusif.BernilaiNol()) {
      baris.add(BarisDuaKolom('Pajak', _Angka(pajakEksklusif)));
    }
    if (!pembulatan.BernilaiNol()) {
      baris.add(
        BarisDuaKolom('Pembulatan', pembulatan.BernilaiNegatif() ? '-${_Angka(pembulatan)}' : _Angka(pembulatan)),
      );
    }
    baris.add(BarisDuaKolom('TOTAL', Uang.Dari(jual.TotalAkhir).FormatRupiah(), tebal: true));
    for (final bayar in data.pembayaran) {
      baris.add(BarisDuaKolom(bayar.NamaMetode, _Angka(Uang.Dari(bayar.Jumlah))));
    }
    final kembalian = Uang.Dari(jual.Kembalian);
    if (!kembalian.BernilaiNol()) {
      baris.add(BarisDuaKolom('Kembalian', _Angka(kembalian)));
    }
    final pajakTermasuk = totalPajak.Kurangi(pajakEksklusif);
    if (_Positif(pajakTermasuk)) {
      baris.add(BarisTeks('Harga termasuk pajak ${pajakTermasuk.FormatRupiah()}', rata: RataStruk.Tengah));
    }
    final hemat = Uang.Dari(jual.TotalDiskon);
    if (p.tampilkanHemat && _Positif(hemat)) {
      baris.add(BarisTeks('Anda hemat ${hemat.FormatRupiah()}', rata: RataStruk.Tengah));
    }

    baris.add(const BarisGaris());
    // POS-11: QR & tautan struk digital (bisa dibuat offline; halaman tersedia setelah penjualan terkirim).
    final awalan = p.awalanStrukDigital;
    if (awalan != null && awalan.isNotEmpty) {
      final tautan = TautanStrukDigital(awalan, jual.Uuid);
      baris
        ..add(BarisQr(tautan))
        ..add(const BarisTeks('Struk digital:', rata: RataStruk.Tengah))
        ..add(BarisTeks(tautan, rata: RataStruk.Tengah));
    }
    baris.addAll(SusunKaki(identitas));
    return DokumenStruk(baris, bukaLaci: bukaLaci);
  }

  /// Tautan struk digital `/s/{kodeStruk}` = awalan dari server + Uuid penjualan (huruf besar, format ULID).
  static String TautanStrukDigital(String awalan, String uuidPenjualan) => '$awalan${uuidPenjualan.toUpperCase()}';

  static List<BarisStruk> SusunKepala(IdentitasStruk identitas) {
    final p = identitas.pengaturan;
    final nama = p.namaDicetak ?? identitas.namaUsaha;
    return [
      if (p.adaLogo && identitas.logo != null) BarisGambar(identitas.logo!),
      if (nama.isNotEmpty) BarisTeks(nama, rata: RataStruk.Tengah, tebal: true),
      for (final teks in p.teksKepala) BarisTeks(teks, rata: RataStruk.Tengah),
      if (identitas.namaOutlet != null && identitas.namaOutlet != nama)
        BarisTeks(identitas.namaOutlet!, rata: RataStruk.Tengah),
      if (p.tampilkanAlamat && identitas.alamat != null) BarisTeks(identitas.alamat!, rata: RataStruk.Tengah),
      if (p.tampilkanTelepon && identitas.telepon != null)
        BarisTeks('Telp. ${identitas.telepon}', rata: RataStruk.Tengah),
      if (p.tampilkanNpwp && p.npwp != null) BarisTeks('NPWP ${p.npwp}', rata: RataStruk.Tengah),
    ];
  }

  static List<BarisStruk> SusunKaki(IdentitasStruk identitas) {
    final p = identitas.pengaturan;
    return [
      if (p.catatanKaki != null) BarisTeks(p.catatanKaki!, rata: RataStruk.Tengah),
      BarisTeks(p.teksPenutup ?? penutupBawaan, rata: RataStruk.Tengah),
      if (p.tandaAir) const BarisTeks(tandaAir, rata: RataStruk.Tengah),
    ];
  }

  /// `Rp 18.000` → `18.000` (baris rincian).
  static String _Angka(Uang nilai) => nilai.FormatRupiah().replaceFirst('Rp ', '').replaceFirst('−', '');

  /// `2.0000` → `2`; `1.5000` → `1,5`.
  static String _Jumlah(String jumlah) {
    var teks = (Decimal.tryParse(jumlah) ?? Decimal.zero).toString();
    if (teks.contains('.')) {
      teks = teks.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
    }
    return teks.replaceAll('.', ',');
  }

  static bool _Positif(Uang nilai) => !nilai.BernilaiNol() && !nilai.BernilaiNegatif();

  static String _Dua(int n) => n.toString().padLeft(2, '0');

  static List<String> _NamaPilihan(String json) {
    try {
      final daftar = jsonDecode(json);
      return daftar is List
          ? [
              for (final p in daftar)
                if (p is Map && p['Nama'] is String) p['Nama'] as String,
            ]
          : const [];
    } on FormatException {
      return const [];
    }
  }
}
