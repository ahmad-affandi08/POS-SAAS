import 'package:adaptor_perangkat/AdaptorPerangkat.dart';

import '../../Data/RepositoriKasir.dart';
import '../../Data/RepositoriPenjualan.dart';
import '../GalatKasir.dart';
import '../Penjualan/KonteksPenjualan.dart';
import 'IdentitasStruk.dart';
import 'PenyusunStrukPenjualan.dart';
import 'ProfilPrinter.dart';

/// Membuat transport dari profil printer (diganti tiruan di test).
typedef PembuatTransport = TransportPrinter Function(ProfilPrinter profil);

/// Cetak struk penjualan (plus buka laci untuk tunai), cetak ulang, dan cetak uji (POS-11, POS-17, PRD v1.79). Buka laci
/// manual tanpa transaksi belum ada karena wajib dicatat (§19.2). Semua dari data lokal,
/// jadi tetap jalan saat offline (§18). Galat printer dilempar sebagai [GalatPrinter] berpesan untuk kasir; penjualan
/// tetap tersimpan walau struk gagal dicetak.
class LayananStruk {
  LayananStruk({required this.repositori, required this.penjualan, PembuatTransport? pembuatTransport})
    : _pembuatTransport = pembuatTransport ?? ((profil) => profil.BuatTransport());

  final RepositoriKasir repositori;
  final RepositoriPenjualan penjualan;
  final PembuatTransport _pembuatTransport;

  Future<ProfilPrinter?> AmbilProfil() => ProfilPrinter.Muat(repositori);

  /// Cetak struk [uuidPenjualan]. [bukaLaci] hanya dipakai bila profil mengizinkan dan ada pembayaran tunai.
  Future<void> CetakPenjualan(
    String uuidPenjualan, {
    bool cetakUlang = false,
    bool bukaLaci = false,
    String? namaPelanggan,
  }) async {
    final profil = await _WajibProfil();
    final baris = await penjualan.CariPenjualan(uuidPenjualan);
    if (baris == null) {
      throw const GalatKasir('PenjualanTidakDitemukan', 'Transaksi ini tidak ada di perangkat.');
    }
    final pembayaran = await penjualan.AmbilPembayaran(uuidPenjualan);
    final data = DataStrukPenjualan(
      penjualan: baris,
      detail: await penjualan.AmbilDetail(uuidPenjualan),
      pembayaran: pembayaran,
      namaPelanggan: namaPelanggan,
    );
    final laci = bukaLaci && profil.bukaLaciTunai && pembayaran.any((b) => b.Jenis == JenisMetodeBayar.tunai);
    final dokumen = PenyusunStrukPenjualan.Susun(
      await IdentitasStruk.Muat(repositori),
      data,
      cetakUlang: cetakUlang,
      bukaLaci: laci,
    );
    await PrinterStruk(_pembuatTransport(profil), profil.lebar).Cetak(dokumen);
  }

  /// Printer diatur dan cetak otomatis aktif.
  Future<bool> CekCetakOtomatis() async => (await AmbilProfil())?.cetakOtomatis ?? false;

  /// Dipanggil setelah pembayaran tersimpan: cetak (plus buka laci bila tunai) bila [CekCetakOtomatis]. true = dicetak.
  Future<bool> CetakSetelahBayar(String uuidPenjualan, {String? namaPelanggan}) async {
    if (!await CekCetakOtomatis()) {
      return false;
    }
    await CetakPenjualan(uuidPenjualan, bukaLaci: true, namaPelanggan: namaPelanggan);
    return true;
  }

  Future<void> CetakUji(ProfilPrinter profil) async {
    final identitas = await IdentitasStruk.Muat(repositori);
    await PrinterStruk(_pembuatTransport(profil), profil.lebar).CetakUji(
      namaUsaha: identitas.pengaturan.namaDicetak ?? identitas.namaUsaha,
      keterangan: 'Printer ${profil.alamat}:${profil.port}',
    );
  }

  Future<ProfilPrinter> _WajibProfil() async =>
      await AmbilProfil() ??
      (throw const GalatPrinter('Printer belum diatur. Buka Pengaturan, lalu atur printer struk.'));
}
