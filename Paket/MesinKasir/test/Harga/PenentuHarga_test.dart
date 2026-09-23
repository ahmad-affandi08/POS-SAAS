import 'package:mesin_kasir/MesinKasir.dart';
import 'package:test/test.dart';

/// Perilaku `PenentuHarga` di luar test vector bersama (F-03).
void main() {
  final katalog = KatalogHarga(
    daftarHarga: const [],
    harga: [
      BarisProdukHarga(
        uuidProduk: 'P-SABUN',
        uuidProdukSatuan: 'PS-SABUN-PCS',
        uuidDaftarHarga: null,
        jumlahMinimum: Kuantitas.DariBulat(1),
        harga: Uang.Dari('5000.00'),
      ),
    ],
  );

  PermintaanHarga Permintaan(String jumlah) => PermintaanHarga(
    uuidProduk: 'P-SABUN',
    uuidProdukSatuan: 'PS-SABUN-PCS',
    jumlah: Kuantitas.Dari(jumlah),
    uuidOutlet: null,
    kanal: null,
    tierPelanggan: null,
    waktu: DateTime.utc(2026, 10, 1, 3),
  );

  test('menolak jumlah nol atau negatif', () {
    expect(() => const PenentuHarga().Tentukan(katalog, Permintaan('0')), throwsArgumentError);
    expect(() => const PenentuHarga().Tentukan(katalog, Permintaan('-1')), throwsArgumentError);
  });

  test('nilai KanalPenjualan dan SumberHarga sama dengan enum PHP', () {
    expect(KanalPenjualan.values.map((kanal) => kanal.name), [
      'MakanDiTempat',
      'BawaPulang',
      'Antar',
      'Online',
      'PesanSendiri',
      'Marketplace',
    ]);
    expect(SumberHarga.values.map((sumber) => sumber.name), ['DaftarHarga', 'Bertingkat', 'Dasar']);
  });
}
