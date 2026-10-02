import 'package:kasir/Domain/Perangkat/PenentuLokasi.dart';

import 'LingkunganUji.dart';

/// Data uji Modul Salesman bagian 2: distributor kecil dengan HP salesman, staf Dewi (peran Salesman: hanya
/// `produk.lihat`, `pelanggan.lihat`, `salesman.kunjungan`; PIN kasus 2 "000000"), dua toko pelanggan.
abstract final class UuidSalesmanUji {
  static const String dewi = '01K5STAF00000000000SALES01';
  static const String tokoMakmur = '01K5PLG0000000000000T0K001';
  static const String warungSri = '01K5PLG0000000000000T0K002';
  static const String piutangLama = '01K5PIU0000000000000000001';
}

/// Data awal uji + Dewi (salesman saja) dan, bila [budiSalesman], Budi juga diberi izin salesman.
Map<String, Object?> DataAwalSalesmanUji({bool budiSalesman = false}) {
  final data = DataAwalUji();
  final staf = [
    for (final s in (data['Staf']! as List<Object?>).cast<Map<String, Object?>>())
      if (budiSalesman && s['Nama'] == 'Budi Santoso')
        {
          ...s,
          'Izin': [...(s['Izin']! as List<Object?>), 'salesman.kunjungan'],
        }
      else
        s,
    StafJson(UuidSalesmanUji.dewi, 'Dewi Kartika Sari', ['produk.lihat', 'pelanggan.lihat', 'salesman.kunjungan'], 2),
  ];
  return {...data, 'Staf': staf};
}

/// Pelanggan bentuk `GET salesman/pelanggan`: Toko Makmur (tier Grosir, limit Rp 25 jt, piutang lewat jatuh tempo 12
/// hari) dan Warung Bu Sri (tanpa piutang, belum pernah dikunjungi).
List<Map<String, Object?>> PelangganSalesmanUji() => [
  {
    'Uuid': UuidSalesmanUji.tokoMakmur,
    'Nama': 'Toko Kelontong Makmur Jaya Abadi Sentosa',
    'NoHp': '6281355550001',
    'Alamat': 'Jl. Slamet Riyadi No. 212, Purwosari, Laweyan, Surakarta',
    'KodeTier': 'GROSIR',
    'NamaTier': 'Grosir',
    'LimitKredit': '25000000.00',
    'TerminHari': 30,
    'SisaPiutang': '12750000.00',
    'JumlahPiutangJatuhTempo': '3250000.00',
    'HariLewatJatuhTempo': 12,
    'TerakhirDikunjungiPada': '2026-09-20T03:00:00Z',
  },
  {
    'Uuid': UuidSalesmanUji.warungSri,
    'Nama': 'Warung Bu Sri',
    'NoHp': '6281299990002',
    'Alamat': null,
    'KodeTier': null,
    'NamaTier': null,
    'LimitKredit': null,
    'TerminHari': 0,
    'SisaPiutang': '0.00',
    'JumlahPiutangJatuhTempo': '0.00',
    'HariLewatJatuhTempo': 0,
    'TerakhirDikunjungiPada': null,
  },
];

Map<String, Object?> PiutangSalesmanUji() => {
  'Piutang': [
    {
      'Uuid': UuidSalesmanUji.piutangLama,
      'Nomor': 'FJ/SLB/2609/0004',
      'Tanggal': '2026-08-13',
      'JatuhTempo': '2026-09-12',
      'Jumlah': '5000000.00',
      'Sisa': '3250000.00',
      'UmurHari': 12,
      'Status': 'DibayarSebagian',
    },
  ],
};

/// Lokasi tiruan: titik tetap (Solo, akurasi 12 m) atau null bila [tersedia] false; mencatat jumlah panggilan.
class PenentuLokasiTiruan implements PenentuLokasi {
  PenentuLokasiTiruan({this.tersedia = true});

  bool tersedia;
  int dipanggil = 0;

  @override
  Future<LokasiPerangkat?> Ambil() async {
    dipanggil++;
    return tersedia ? LokasiPerangkat.DariPlatform(-7.56660012, 110.81660024, akurasi: 12.4) : null;
  }
}
