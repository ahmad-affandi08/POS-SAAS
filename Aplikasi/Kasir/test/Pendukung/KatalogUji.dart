/// Katalog uji "Kopi Senja" berbentuk respons `GET /api/pos/v1/katalog` (lengkap). Harga realistis Rupiah, nama
/// produk panjang, pilihan wajib & opsional, multi-satuan dengan harga bertingkat, dan produk yang belum bisa dijual
/// (induk varian, bahan baku, berpelacakan batch).
abstract final class UuidUji {
  static const String kategoriKopi = '01K5KAT0000000000000K0P101';
  static const String kategoriMakanan = '01K5KAT000000000000MAKAN01';
  static const String satuanCangkir = '01K5SAT00000000000CANGK1R1';
  static const String satuanPcs = '01K5SAT0000000000000PCS001';
  static const String satuanLusin = '01K5SAT000000000001VS1N001';
  static const String kelompokPbjt = '01K5KP00000000000000PBJT01';
  static const String kelompokPpn = '01K5KP000000000000000PPN01';

  static const String kopiSusu = '01K5PRD0000000000K0P1SVSV1';
  static const String americano = '01K5PRD000000000AMER1CAN01';
  static const String croissant = '01K5PRD000000000CR01SSANT1';
  static const String roti = '01K5PRD00000000000000R0T11';
  static const String kaos = '01K5PRD00000000000000KA0S1';
  static const String susuUht = '01K5PRD0000000000000SVSV01';
  static const String gulaAren = '01K5PRD0000000000000GV1A01';

  static const String psKopiSusu = '01K5PS0000000000K0P1SVSV01';
  static const String psAmericano = '01K5PS000000000AMER1CAN001';
  static const String psCroissant = '01K5PS000000000CR01SSANT01';
  static const String psRotiPcs = '01K5PS00000000000R0T1PCS01';
  static const String psRotiLusin = '01K5PS0000000000R0T11VS1N1';
  static const String psKaos = '01K5PS00000000000000KA0S01';
  static const String psSusu = '01K5PS00000000000000SVSV01';

  static const String kelompokGula = '01K5K100000000000000GV1A01';
  static const String kelompokTambahan = '01K5K1000000000000TAMBAH01';
  static const String gulaNormal = '01K5P1000000000GV1AN0RMA11';
  static const String gulaKurang = '01K5P1000000000GV1AKVRANG1';
  static const String extraShot = '01K5P10000000000EXTRASH0T1';
  static const String oatMilk = '01K5P1000000000000ATM11K01';

  static const String barcodeKopiSusu = '8991000000011';
  static const String barcodeAmericano = '8991000000028';
  static const String barcodeRotiLusin = '8991000000035';
}

Map<String, Object?> ProdukUji(
  String uuid,
  String nama, {
  String jenis = 'Stok',
  String? sku,
  String? kategori,
  String? kelompokPajak = UuidUji.kelompokPbjt,
  String pelacakan = 'Tidak',
  bool tampil = true,
}) => {
  'Uuid': uuid,
  'Sku': sku,
  'Nama': nama,
  'NamaStruk': null,
  'Jenis': jenis,
  'UuidKategori': kategori,
  'Merek': null,
  'UuidSatuanDasar': UuidUji.satuanPcs,
  'Pelacakan': pelacakan,
  'UuidKelompokPajak': kelompokPajak,
  'HargaTermasukPajak': null,
  'BolehMinus': true,
  'TampilDiPos': tampil,
  'TampilOnline': false,
  'UuidInduk': null,
  'AtributVarian': null,
  'UrlGambar': null,
  'UrlGambarKecil': null,
  'Aktif': true,
  'Dihapus': false,
  'DiubahPada': '2026-09-20T01:00:00.000000Z',
};

Map<String, Object?> SatuanProdukUji(
  String uuid,
  String produk,
  String satuan, {
  String konversi = '1',
  bool bawaan = true,
}) => {
  'Uuid': uuid,
  'UuidProduk': produk,
  'UuidSatuan': satuan,
  'KonversiKeDasar': konversi,
  'DefaultJual': bawaan,
  'DefaultBeli': bawaan,
};

Map<String, Object?> HargaUji(String uuid, String produk, String satuan, String harga, {String minimum = '1.0000'}) => {
  'Uuid': uuid,
  'UuidProduk': produk,
  'UuidProdukSatuan': satuan,
  'UuidDaftarHarga': null,
  'JumlahMinimum': minimum,
  'Harga': harga,
};

Map<String, Object?> KatalogUji() => {
  'Skema': 1,
  'Lengkap': true,
  'Kursor': 'a3Vyc29yLTE',
  'WaktuServer': '2026-09-24T01:00:00Z',
  'Kategori': [
    {'Uuid': UuidUji.kategoriKopi, 'UuidInduk': null, 'Nama': 'Kopi', 'Urutan': 1},
    {'Uuid': UuidUji.kategoriMakanan, 'UuidInduk': null, 'Nama': 'Makanan', 'Urutan': 2},
  ],
  'Satuan': [
    {'Uuid': UuidUji.satuanCangkir, 'Nama': 'Cangkir', 'Simbol': 'cup', 'BolehDesimal': false},
    {'Uuid': UuidUji.satuanPcs, 'Nama': 'Pcs', 'Simbol': 'pcs', 'BolehDesimal': false},
    {'Uuid': UuidUji.satuanLusin, 'Nama': 'Lusin', 'Simbol': 'lsn', 'BolehDesimal': false},
  ],
  'KelompokPajak': [
    {
      'Uuid': UuidUji.kelompokPbjt,
      'Nama': 'Makanan & minuman',
      'Kategori': 'KenaPbjt',
      'Pajak': [
        {
          'KodeJenisPajak': 'PbjtMakananMinuman',
          'DasarPengenaan': 'SubtotalPlusLayanan',
          'Urutan': 1,
          'Kategori': 'Pbjt',
        },
      ],
    },
    {
      'Uuid': UuidUji.kelompokPpn,
      'Nama': 'Barang kena PPN',
      'Kategori': 'KenaPpn',
      'Pajak': [
        {'KodeJenisPajak': 'Ppn', 'DasarPengenaan': 'Subtotal', 'Urutan': 1},
      ],
    },
  ],
  'Produk': [
    ProdukUji(UuidUji.kopiSusu, 'Es Kopi Susu Aren', jenis: 'Resep', sku: 'KSA-01', kategori: UuidUji.kategoriKopi),
    ProdukUji(UuidUji.americano, 'Americano Panas', jenis: 'Resep', sku: 'AMR-01', kategori: UuidUji.kategoriKopi),
    ProdukUji(
      UuidUji.croissant,
      'Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo',
      sku: 'CRS-01',
      kategori: UuidUji.kategoriMakanan,
    ),
    ProdukUji(
      UuidUji.roti,
      'Roti Tawar Gandum',
      sku: 'RTG-01',
      kategori: UuidUji.kategoriMakanan,
      kelompokPajak: UuidUji.kelompokPpn,
    ),
    ProdukUji(UuidUji.kaos, 'Kaos Kopi Senja', jenis: 'IndukVarian', sku: 'KAOS'),
    ProdukUji(UuidUji.susuUht, 'Susu UHT 1 Liter', sku: 'UHT-1L', pelacakan: 'Batch'),
    ProdukUji(UuidUji.gulaAren, 'Gula Aren Cair', jenis: 'BahanBaku', sku: 'GA-01', tampil: false),
  ],
  'ProdukSatuan': [
    SatuanProdukUji(UuidUji.psKopiSusu, UuidUji.kopiSusu, UuidUji.satuanCangkir),
    SatuanProdukUji(UuidUji.psAmericano, UuidUji.americano, UuidUji.satuanCangkir),
    SatuanProdukUji(UuidUji.psCroissant, UuidUji.croissant, UuidUji.satuanPcs),
    SatuanProdukUji(UuidUji.psRotiPcs, UuidUji.roti, UuidUji.satuanPcs),
    SatuanProdukUji(UuidUji.psRotiLusin, UuidUji.roti, UuidUji.satuanLusin, konversi: '12', bawaan: false),
    SatuanProdukUji(UuidUji.psKaos, UuidUji.kaos, UuidUji.satuanPcs),
    SatuanProdukUji(UuidUji.psSusu, UuidUji.susuUht, UuidUji.satuanPcs),
  ],
  'ProdukBarcode': [
    {
      'Uuid': '01K5BC00000000000K0P1SVSV1',
      'UuidProduk': UuidUji.kopiSusu,
      'UuidProdukSatuan': UuidUji.psKopiSusu,
      'Barcode': UuidUji.barcodeKopiSusu,
    },
    {
      'Uuid': '01K5BC0000000000AMER1CAN01',
      'UuidProduk': UuidUji.americano,
      'UuidProdukSatuan': UuidUji.psAmericano,
      'Barcode': UuidUji.barcodeAmericano,
    },
    {
      'Uuid': '01K5BC000000000R0T11VS1N01',
      'UuidProduk': UuidUji.roti,
      'UuidProdukSatuan': UuidUji.psRotiLusin,
      'Barcode': UuidUji.barcodeRotiLusin,
    },
  ],
  'DaftarHarga': <Object?>[],
  'ProdukHarga': [
    HargaUji('01K5HRG00000000000K0P1SVSV', UuidUji.kopiSusu, UuidUji.psKopiSusu, '18000.00'),
    HargaUji('01K5HRG0000000000AMER1CAN0', UuidUji.americano, UuidUji.psAmericano, '15000.00'),
    HargaUji('01K5HRG0000000000CR01SSANT', UuidUji.croissant, UuidUji.psCroissant, '25000.00'),
    HargaUji('01K5HRG00000000000R0T1PCS1', UuidUji.roti, UuidUji.psRotiPcs, '12000.00'),
    HargaUji('01K5HRG00000000000R0T1PCS2', UuidUji.roti, UuidUji.psRotiPcs, '11000.00', minimum: '10.0000'),
    HargaUji('01K5HRG0000000000R0T11VS1N', UuidUji.roti, UuidUji.psRotiLusin, '130000.00'),
    HargaUji('01K5HRG00000000000000KA0S1', UuidUji.kaos, UuidUji.psKaos, '95000.00'),
    HargaUji('01K5HRG00000000000000SVSV1', UuidUji.susuUht, UuidUji.psSusu, '21500.00'),
  ],
  'KelompokPilihan': [
    {'Uuid': UuidUji.kelompokGula, 'Nama': 'Level gula', 'MinimalPilih': 1, 'MaksimalPilih': 1, 'Urutan': 1},
    {'Uuid': UuidUji.kelompokTambahan, 'Nama': 'Tambahan', 'MinimalPilih': 0, 'MaksimalPilih': 2, 'Urutan': 2},
  ],
  'Pilihan': [
    PilihanUji(UuidUji.gulaNormal, UuidUji.kelompokGula, 'Normal', '0.00', 1),
    PilihanUji(UuidUji.gulaKurang, UuidUji.kelompokGula, 'Kurang manis', '0.00', 2),
    PilihanUji(UuidUji.extraShot, UuidUji.kelompokTambahan, 'Extra shot', '5000.00', 1),
    PilihanUji(UuidUji.oatMilk, UuidUji.kelompokTambahan, 'Oat milk', '7000.00', 2),
  ],
  'ProdukKelompokPilihan': [
    {
      'Uuid': '01K5PKP000000000000GV1A001',
      'UuidProduk': UuidUji.kopiSusu,
      'UuidKelompokPilihan': UuidUji.kelompokGula,
      'Urutan': 1,
    },
    {
      'Uuid': '01K5PKP00000000000TAMBAH01',
      'UuidProduk': UuidUji.kopiSusu,
      'UuidKelompokPilihan': UuidUji.kelompokTambahan,
      'Urutan': 2,
    },
  ],
  'Resep': <Object?>[],
  'PaketProdukDetail': <Object?>[],
  'Terhapus': <Object?>[],
};

Map<String, Object?> PilihanUji(String uuid, String kelompok, String nama, String harga, int urutan) => {
  'Uuid': uuid,
  'UuidKelompokPilihan': kelompok,
  'Nama': nama,
  'Harga': harga,
  'UuidProdukBahan': null,
  'Jumlah': null,
  'Aktif': true,
  'Urutan': urutan,
};
