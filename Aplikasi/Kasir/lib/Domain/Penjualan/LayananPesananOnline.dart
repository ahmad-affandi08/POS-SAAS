import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../GalatKasir.dart';
import '../Katalog/KatalogLokal.dart';
import 'Keranjang.dart';
import 'KonteksPenjualan.dart';
import 'LayananPenjualan.dart';

/// Pesanan toko online di aplikasi kasir (F-17): ambil daftar pesanan aktif outlet ini (perlu online), lalu muat
/// snapshotnya ke keranjang kanal `Online` untuk ditagih lewat alur Bayar biasa.
///
/// Uang muka pesanan yang sudah dibayar di muka (QRIS web, J-17.1) masuk sebagai [PraPesananKeranjang] ber-sumber
/// `pesananOnline`, sehingga panel Bayar otomatis mengisi baris **Uang muka** dan `Penjualan.Buat` membawa
/// `UuidPesananOnline`. Server memakai uang mukanya, menautkan penjualannya, dan menyelesaikan pesanan ambil sendiri
/// dalam transaksi yang sama.
///
/// **Ongkir belum bisa ditagih.** `Penjualan` tidak punya baris biaya kirim dan bagan akun belum punya peran
/// pendapatan pengiriman, jadi pesanan kirim yang berongkir ditolak di sini alih-alih ditagih dengan total yang
/// kurang — kalau dipaksa, uang muka pelanggan akan lebih besar daripada penjualannya dan menyisakan kewajiban yang
/// harus diurus manual di setiap pesanan. Pesanan kirim dengan gratis ongkir tetap bisa ditagih.
class LayananPesananOnline {
  LayananPesananOnline({required this.klien, required this.penjualan});

  final KlienPos klien;
  final LayananPenjualan penjualan;

  Future<HasilPesananOnline> AmbilAktif() async {
    try {
      return await klien.AmbilPesananOnline();
    } on GalatJaringan {
      throw const GalatKasir(
        'PerluOnline',
        'Mengambil pesanan toko online perlu koneksi internet. Coba lagi saat perangkat online.',
      );
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }

  /// Alasan pesanan belum bisa ditagih di kasir, atau null bila boleh.
  static String? AlasanBelumBisaDitagih(PesananOnlinePos pesanan) {
    if (Uang.Dari(pesanan.ongkir).Bandingkan(Uang.Nol()) > 0) {
      return 'Ongkir ${Uang.Dari(pesanan.ongkir).FormatRupiah()} belum bisa ditagih di kasir. Tagih lewat back-office.';
    }
    if (pesanan.CekKirim && pesanan.status != 'Siap') {
      return 'Pesanan kirim ditagih setelah dikemas (status Siap).';
    }
    if (!pesanan.CekKirim && pesanan.status != 'Siap') {
      return 'Tandai pesanan Siap dulu di back-office sebelum ditagihkan.';
    }
    return null;
  }

  /// Keranjang penagihan: harga saat dipesan, kanal `Online`, dan uang muka yang tersisa (bila ada).
  Keranjang MuatKeKeranjang(
    PesananOnlinePos pesanan,
    HasilPesananOnline hasil,
    KatalogLokal katalog,
    KonteksPenjualan k,
  ) {
    final alasan = AlasanBelumBisaDitagih(pesanan);
    if (alasan != null) {
      throw GalatKasir('PesananBelumBisaDitagih', alasan);
    }
    final sisaUangMuka = Uang.Dari(pesanan.sisaUangMuka);
    final uuidMetode = hasil.uuidMetodeUangMuka;
    if (sisaUangMuka.Bandingkan(Uang.Nol()) > 0 && uuidMetode == null) {
      throw const GalatKasir(
        'DataAwalBelumLengkap',
        'Metode uang muka belum tersedia di server, jadi pesanan berbayar belum bisa ditagih. Coba lagi nanti.',
      );
    }
    final baris = <ItemKeranjang>[];
    for (final b in pesanan.baris) {
      final produk = katalog.CariProduk(b.uuidProduk);
      if (produk == null) {
        throw GalatKasir(
          'ProdukTidakDikenal',
          '"${b.namaProduk}" belum ada di katalog perangkat ini. Perbarui katalog, lalu coba lagi.',
        );
      }
      final satuan = produk.satuan.where((s) => s.uuid == b.uuidProdukSatuan).firstOrNull ?? produk.AmbilSatuanBawaan();
      final pilihan = [
        for (final p in b.pilihan)
          PilihanTerpilih(uuid: '${p['UuidPilihan']}', nama: '${p['Nama']}', harga: Uang.Dari('${p['Harga']}')),
      ];
      final item = penjualan.BuatBaris(
        katalog,
        k,
        produk,
        satuan: satuan,
        pilihan: pilihan,
        catatan: b.catatan,
        kanal: KanalPenjualan.Online,
      );
      baris.add(item.Salin(jumlah: Kuantitas.Dari(b.jumlah), hargaSatuan: Uang.Dari(b.hargaSatuan)));
    }
    return Keranjang(
      baris: baris,
      catatan: pesanan.catatan,
      kanal: KanalPenjualan.Online,
      praPesan: PraPesananKeranjang(
        uuid: pesanan.uuid,
        nomor: pesanan.nomor,
        sisaUangMuka: sisaUangMuka,
        uuidMetode: uuidMetode ?? '',
        namaMetode: hasil.namaMetodeUangMuka ?? 'Uang muka (DP)',
        sumber: SumberUangMuka.pesananOnline,
      ),
    );
  }
}
