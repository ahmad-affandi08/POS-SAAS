import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:kasir/Domain/Katalog/LayananKatalog.dart';

import '../../Pendukung/KatalogUji.dart';
import '../../Pendukung/LingkunganUji.dart';

/// Rincian F-07c: katalog diunduh lengkap lalu delta lewat kursor ke tabel Drift bernama sama dengan server.
void main() {
  late LingkunganUji u;

  setUp(() async {
    u = LingkunganUji.Buat();
    await u.SiapkanAktif();
  });
  tearDown(() => u.Tutup());

  test('pertama kali tanpa kursor → katalog lengkap tersimpan & kursor disimpan', () async {
    u.server.penangan = (_) async => JsonUji(KatalogUji());

    expect(await u.katalog.Perbarui(), HasilPerbaruiKatalog.Lengkap);

    expect(u.server.permintaan.single.url.path, '/api/pos/v1/katalog');
    expect(u.server.permintaan.single.url.queryParameters, isEmpty);
    expect(await u.repositori.AmbilPengaturan(KunciPengaturan.kursorKatalog), 'a3Vyc29yLTE');
    final katalog = await u.MuatKatalog();
    expect(katalog.produk, hasLength(7));
    expect(katalog.CariProduk(UuidUji.kopiSusu)!.kelompokPilihan.map((k) => k.nama), ['Level gula', 'Tambahan']);
    expect(katalog.CariProduk(UuidUji.kopiSusu)!.pajak.single.kode, 'PbjtMakananMinuman');
  });

  test('delta dengan kursor: baris berubah ditimpa, produk Dihapus & bagian Terhapus diterapkan', () async {
    await u.SiapkanKatalog();
    await u.repositori.SimpanPengaturan(KunciPengaturan.kursorKatalog, 'kursor-lama');
    u.server.penangan = (_) async => JsonUji({
      'Skema': 1,
      'Lengkap': false,
      'Kursor': 'kursor-baru',
      'WaktuServer': '2026-09-24T01:01:00Z',
      'Produk': [
        ProdukUji(UuidUji.americano, 'Americano Panas Gula Aren', jenis: 'Resep', sku: 'AMR-01'),
        {...ProdukUji(UuidUji.croissant, 'Croissant'), 'Dihapus': true},
      ],
      'ProdukHarga': [HargaUji('01K5HRG0000000000AMER1CAN0', UuidUji.americano, UuidUji.psAmericano, '16500.00')],
      'Terhapus': [
        {'Entitas': 'ProdukBarcode', 'Uuid': '01K5BC00000000000K0P1SVSV1'},
        {'Entitas': 'Pilihan', 'Uuid': UuidUji.oatMilk},
        {'Entitas': 'PaketProdukDetail', 'Uuid': 'TIDAK-DISIMPAN'},
      ],
    });

    expect(await u.katalog.Perbarui(), HasilPerbaruiKatalog.Delta);

    expect(u.server.permintaan.single.url.queryParameters['sejak'], 'kursor-lama');
    expect(await u.repositori.AmbilPengaturan(KunciPengaturan.kursorKatalog), 'kursor-baru');
    final katalog = await u.MuatKatalog();
    expect(katalog.CariProduk(UuidUji.americano)!.nama, 'Americano Panas Gula Aren');
    expect(katalog.CariProduk(UuidUji.croissant), isNull);
    expect((await u.db.select(u.db.produkSatuan).get()).where((s) => s.UuidProduk == UuidUji.croissant), isEmpty);
    expect((await u.db.select(u.db.produkHarga).get()).where((h) => h.UuidProduk == UuidUji.croissant), isEmpty);
    expect(katalog.CariKode(UuidUji.barcodeKopiSusu), isNull);
    expect(katalog.CariKode(UuidUji.barcodeAmericano)!.produk.nama, 'Americano Panas Gula Aren');
    final tambahan = katalog.CariProduk(UuidUji.kopiSusu)!.kelompokPilihan.last;
    expect(tambahan.pilihan.map((p) => p.nama), ['Extra shot']);
    expect(katalog.CariProduk(UuidUji.roti), isNotNull, reason: 'Produk yang tidak berubah tetap ada.');
  });

  test('kursor ditolak (KursorTidakValid) → ulang tanpa kursor & ganti katalog lengkap', () async {
    await u.SiapkanKatalog();
    await u.repositori.SimpanPengaturan(KunciPengaturan.kursorKatalog, 'kursor-rusak');
    u.server.penangan = (p) async => p.url.queryParameters.containsKey('sejak')
        ? JsonUji({
            'Galat': {'Kode': 'KursorTidakValid', 'Pesan': 'Kursor tidak valid.'},
          }, 422)
        : JsonUji({
            ...KatalogUji(),
            'Produk': [ProdukUji(UuidUji.americano, 'Americano Panas', jenis: 'Resep')],
          });

    expect(await u.katalog.Perbarui(), HasilPerbaruiKatalog.Lengkap);
    expect(u.server.permintaan, hasLength(2));
    expect((await u.MuatKatalog()).produk.map((p) => p.nama), ['Americano Panas']);
  });

  test('offline → katalog lokal tetap dipakai', () async {
    await u.SiapkanKatalog();
    u.server.penangan = (_) async => throw http.ClientException('offline');
    expect(await u.katalog.Perbarui(), HasilPerbaruiKatalog.Offline);
    expect((await u.MuatKatalog()).produk, hasLength(7));
  });
}
