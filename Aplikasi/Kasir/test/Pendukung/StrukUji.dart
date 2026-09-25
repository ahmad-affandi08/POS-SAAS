/// Respons `GET /api/pos/v1/penjualan/cari` tiruan (Rincian F-09 fase 1): transaksi dari perangkat lain (POS-002) di
/// outlet SLB, empat hari lalu, dibayar tunai. Baris:
/// 1. Kopi Susu Literan 1 L, 3 btl, Rp 100.000 (retur 1 btl = Rp 33.333,33; retur sisa mengambil `NilaiBisaDiretur`);
/// 2. Biji Kopi Arabika Gayo, 2,5 kg (satuan desimal), Rp 187.500;
/// 3. Air mineral bonus, 1 btl, Rp 0.
library;

abstract final class UuidStruk {
  static const String penjualan = '01K5PNJ0000000000000000007';
  static const String kopiLiter = '01K5BRS0000000000000000001';
  static const String bijiKopi = '01K5BRS0000000000000000002';
  static const String airMineral = '01K5BRS0000000000000000003';
  static const String nomor = 'INV/SLB/260920/POS-002-0007';
}

Map<String, Object?> BarisStrukUji(
  String uuid,
  String nama,
  String simbol,
  String jumlah,
  String totalBaris, {
  String sudah = '0.0000',
  String? bisa,
  String? nilaiBisa,
  bool? bolehDesimal,
  String? uuidProdukSatuan,
}) => {
  'Uuid': uuid,
  'UuidProduk': null,
  'NamaProduk': nama,
  'SimbolSatuan': simbol,
  'Jumlah': jumlah,
  'HargaSatuan': totalBaris,
  'HargaPilihan': '0.00',
  'Pilihan': const <Object?>[],
  'Bruto': totalBaris,
  'JumlahDiskon': '0.00',
  'JumlahDiskonPesanan': '0.00',
  'BiayaLayanan': '0.00',
  'JumlahPajak': '0.00',
  'TotalBaris': totalBaris,
  'SnapshotPajak': const <Object?>[],
  'JumlahSudahDiretur': sudah,
  'JumlahBisaDiretur': bisa ?? jumlah,
  'NilaiBisaDiretur': nilaiBisa ?? totalBaris,
  'BolehDesimal': ?bolehDesimal,
  'UuidProdukSatuan': ?uuidProdukSatuan,
};

Map<String, Object?> StrukUji({
  bool bisaDiretur = true,
  String? alasan,
  String status = 'Lunas',
  List<Map<String, Object?>>? baris,
  List<Map<String, Object?>> retur = const [],
}) => {
  'Penjualan': {
    'Uuid': UuidStruk.penjualan,
    'Nomor': UuidStruk.nomor,
    'Status': status,
    'LabelStatus': status == 'Lunas' ? 'Lunas' : status,
    'TanggalBisnis': '2026-09-20',
    'DibuatPada': '2026-09-20T05:12:00Z',
    'NamaKasir': 'Sari Lestari',
    'HargaTermasukPajak': true,
    'Subtotal': '287500.00',
    'TotalDiskon': '0.00',
    'BiayaLayanan': '0.00',
    'TotalPajak': '0.00',
    'Pembulatan': '0.00',
    'TotalAkhir': '287500.00',
    'TotalDibayar': '300000.00',
    'Kembalian': '12500.00',
    'BatasHariRetur': 7,
    'BatasReturSampai': '2026-09-27',
    'BisaDiretur': bisaDiretur,
    'AlasanTidakBisaDiretur': alasan,
  },
  'Baris':
      baris ??
      [
        BarisStrukUji(UuidStruk.kopiLiter, 'Kopi Susu Literan 1 L', 'btl', '3.0000', '100000.00'),
        BarisStrukUji(UuidStruk.bijiKopi, 'Biji Kopi Arabika Gayo', 'kg', '2.5000', '187500.00'),
        BarisStrukUji(UuidStruk.airMineral, 'Air mineral bonus', 'btl', '1.0000', '0.00'),
      ],
  'Pembayaran': [
    {
      'Uuid': '01K5BYR0000000000000000001',
      'UuidMetodePembayaran': '01K5MTD0000000000000000001',
      'JenisMetode': 'Tunai',
      'NamaMetode': 'Tunai',
      'Jumlah': '300000.00',
      'Referensi': null,
    },
  ],
  'Retur': retur,
};
