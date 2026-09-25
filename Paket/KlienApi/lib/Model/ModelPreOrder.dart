import 'UraiJson.dart';

/// Baris pre-order dengan harga saat dipesan (F-12 bagian 2), dipakai kasir sebagai harga saat diambil.
class BarisPesananPenjualanPos {
  const BarisPesananPenjualanPos({
    required this.uuid,
    required this.uuidProduk,
    required this.uuidProdukSatuan,
    required this.namaProduk,
    required this.jumlah,
    required this.hargaSatuan,
    required this.hargaPilihan,
    required this.pilihan,
    required this.catatan,
  });

  final String uuid;
  final String uuidProduk;
  final String? uuidProdukSatuan;
  final String namaProduk;
  final String jumlah;
  final String hargaSatuan;
  final String hargaPilihan;

  /// `{UuidPilihan, Nama, Harga}`.
  final List<Map<String, Object?>> pilihan;
  final String? catatan;

  static BarisPesananPenjualanPos DariJson(Map<String, Object?> json) => BarisPesananPenjualanPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    uuidProduk: UraiJson.AmbilTeks(json['UuidProduk']),
    uuidProdukSatuan: UraiJson.AmbilTeksAtauNull(json['UuidProdukSatuan']),
    namaProduk: UraiJson.AmbilTeks(json['NamaProduk']),
    jumlah: UraiJson.AmbilDesimal(json['Jumlah']),
    hargaSatuan: UraiJson.AmbilDesimal(json['HargaSatuan']),
    hargaPilihan: UraiJson.AmbilDesimal(json['HargaPilihan']),
    pilihan: UraiJson.AmbilDaftarPeta(json['Pilihan']),
    catatan: UraiJson.AmbilTeksAtauNull(json['Catatan']),
  );
}

/// Pre-order siap diambil (F-12 bagian 2, `GET /api/pos/v1/pesanan-penjualan?kata=`). Uang string desimal.
class PesananPenjualanPos {
  const PesananPenjualanPos({
    required this.uuid,
    required this.nomor,
    required this.status,
    required this.tanggalAmbil,
    required this.catatan,
    required this.totalPesanan,
    required this.uangMuka,
    required this.sisaUangMuka,
    required this.pelanggan,
    required this.baris,
  });

  final String uuid;
  final String nomor;

  /// `Dipesan` / `Siap`.
  final String status;

  /// `YYYY-MM-DD`.
  final String tanggalAmbil;
  final String? catatan;
  final String totalPesanan;
  final String uangMuka;
  final String sisaUangMuka;

  /// `{Uuid, Nama, NoHp (tersamar), KodeTier, NamaTier}`; null = pelanggan tidak ditemukan.
  final Map<String, Object?>? pelanggan;
  final List<BarisPesananPenjualanPos> baris;

  static PesananPenjualanPos DariJson(Map<String, Object?> json) => PesananPenjualanPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nomor: UraiJson.AmbilTeks(json['Nomor']),
    status: UraiJson.AmbilTeks(json['Status']),
    tanggalAmbil: UraiJson.AmbilTeks(json['TanggalAmbil']),
    catatan: UraiJson.AmbilTeksAtauNull(json['Catatan']),
    totalPesanan: UraiJson.AmbilDesimal(json['TotalPesanan']),
    uangMuka: UraiJson.AmbilDesimal(json['UangMuka']),
    sisaUangMuka: UraiJson.AmbilDesimal(json['SisaUangMuka']),
    pelanggan: UraiJson.AmbilPetaAtauNull(json['Pelanggan']),
    baris: UraiJson.AmbilDaftarPeta(json['Baris']).map(BarisPesananPenjualanPos.DariJson).toList(),
  );
}

/// Hasil cari pre-order + metode sistem "Uang muka (DP)" (null = tenant belum pernah menerima pre-order).
class HasilCariPesananPenjualan {
  const HasilCariPesananPenjualan({required this.pesanan, this.uuidMetodeUangMuka, this.namaMetodeUangMuka});

  final List<PesananPenjualanPos> pesanan;
  final String? uuidMetodeUangMuka;
  final String? namaMetodeUangMuka;

  static HasilCariPesananPenjualan DariJson(Map<String, Object?> json) {
    final metode = UraiJson.AmbilPetaAtauNull(json['MetodeUangMuka']);
    return HasilCariPesananPenjualan(
      pesanan: UraiJson.AmbilDaftarPeta(json['Pesanan']).map(PesananPenjualanPos.DariJson).toList(),
      uuidMetodeUangMuka: metode == null ? null : UraiJson.AmbilTeks(metode['Uuid']),
      namaMetodeUangMuka: metode == null ? null : UraiJson.AmbilTeks(metode['Nama']),
    );
  }
}
