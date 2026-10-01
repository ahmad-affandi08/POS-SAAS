import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Domain/Katalog/KatalogLokal.dart';
import 'package:kasir/Domain/Penjualan/KonteksPenjualan.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Pendukung/KatalogUji.dart';
import '../../Pendukung/LingkunganUji.dart';

const String keripik = '01K5PRD0000000000KERIPIK01';
const String psKeripik = '01K5PS00000000000KERIPIK01';

/// F-05i: barang titipan (konsinyasi) dijual seperti produk berstok; server yang menjurnal J-05.7 (Dr HPP, Cr Hutang
/// Konsinyasi). Induk varian & bahan baku tetap tidak bisa dijual.
void main() {
  late LingkunganUji u;
  late KatalogLokal katalog;
  late KonteksPenjualan k;

  setUp(() async {
    u = LingkunganUji.Buat();
    await u.SiapkanAktif();
    final data = KatalogUji();
    data['Produk'] = [
      ...(data['Produk']! as List<Object?>),
      ProdukUji(keripik, 'Keripik Singkong Titipan Bu Sari', jenis: JenisProdukKasir.konsinyasi, sku: 'KRP-01'),
    ];
    data['ProdukSatuan'] = [
      ...(data['ProdukSatuan']! as List<Object?>),
      SatuanProdukUji(psKeripik, keripik, UuidUji.satuanPcs),
    ];
    data['ProdukHarga'] = [
      ...(data['ProdukHarga']! as List<Object?>),
      HargaUji('01K5HRG00000000000KERIPIK1', keripik, psKeripik, '12000.00'),
    ];
    await u.SiapkanKatalog(data);
    katalog = await u.MuatKatalog();
    k = await u.MuatKonteks();
  });
  tearDown(() => u.Tutup());

  test('produk konsinyasi bisa dijual dan masuk keranjang dengan harga jualnya', () {
    final titipan = katalog.CariProduk(keripik)!;
    expect(titipan.jenis, JenisProdukKasir.konsinyasi);
    expect(titipan.AmbilAlasanTidakBisaDijual(), isNull);

    final baris = u.penjualan.BuatBaris(katalog, k, titipan);
    expect(baris.jumlah, Kuantitas.DariBulat(1));
    expect(baris.hargaSatuan, Uang.Dari('12000'));
  });

  test('induk varian dan bahan baku tetap ditolak', () {
    expect(katalog.CariProduk(UuidUji.kaos)!.AmbilAlasanTidakBisaDijual()?.kode, 'ProdukTidakBisaDijual');
  });
}
