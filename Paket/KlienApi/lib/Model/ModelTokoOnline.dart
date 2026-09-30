import 'UraiJson.dart';

/// Baris pesanan toko online dengan harga saat dipesan (F-17 bagian 1), dipakai kasir sebagai harga saat menagih.
class BarisPesananOnlinePos {
  const BarisPesananOnlinePos({
    required this.uuidProduk,
    required this.uuidProdukSatuan,
    required this.namaProduk,
    required this.jumlah,
    required this.hargaSatuan,
    required this.hargaPilihan,
    required this.pilihan,
    required this.catatan,
  });

  final String uuidProduk;
  final String? uuidProdukSatuan;
  final String namaProduk;
  final String jumlah;
  final String hargaSatuan;
  final String hargaPilihan;

  /// `{UuidPilihan, Nama, Harga}`.
  final List<Map<String, Object?>> pilihan;
  final String? catatan;

  static BarisPesananOnlinePos DariJson(Map<String, Object?> json) => BarisPesananOnlinePos(
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

/// Pesanan toko online yang menunggu ditagihkan kasir (`GET /api/pos/v1/pesanan-online`). Uang string desimal.
///
/// [sisaUangMuka] adalah uang pelanggan yang sudah diterima toko (QRIS web, J-17.1) dan belum dipakai penjualan mana
/// pun; nol untuk pesanan bayar saat ambil/COD. [jenisPemenuhan] `AmbilSendiri`/`Kirim` — pesanan kirim tidak selesai
/// di kasir, karena barangnya masih harus diserahkan kurir.
class PesananOnlinePos {
  const PesananOnlinePos({
    required this.uuid,
    required this.nomor,
    required this.namaPelanggan,
    required this.jenisPemenuhan,
    required this.metodePembayaran,
    required this.status,
    required this.subtotal,
    required this.ongkir,
    this.diskonOngkir = '0',
    required this.total,
    required this.sudahDibayar,
    required this.sisaUangMuka,
    required this.catatan,
    required this.dibuatPada,
    required this.baris,
  });

  final String uuid;
  final String nomor;
  final String namaPelanggan;

  /// `AmbilSendiri` / `Kirim`.
  final String jenisPemenuhan;

  /// `BayarSaatAmbil` / `Cod` / `QrisOnline`.
  final String metodePembayaran;

  /// `Dikonfirmasi` / `Diproses` / `Siap`.
  final String status;
  final String subtotal;

  /// Ongkir kotor (tarif zona). Yang dibayar pembeli = [ongkir] − [diskonOngkir] (F-17 bagian 3).
  final String ongkir;

  /// Potongan promo gratis ongkir (F-16c); '0' dari server lama yang belum mengirimnya.
  final String diskonOngkir;
  final String total;
  final bool sudahDibayar;
  final String sisaUangMuka;
  final String? catatan;
  final DateTime? dibuatPada;
  final List<BarisPesananOnlinePos> baris;

  bool get CekKirim => jenisPemenuhan == 'Kirim';

  static PesananOnlinePos DariJson(Map<String, Object?> json) => PesananOnlinePos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nomor: UraiJson.AmbilTeks(json['Nomor']),
    namaPelanggan: UraiJson.AmbilTeks(json['NamaPelanggan']),
    jenisPemenuhan: UraiJson.AmbilTeks(json['JenisPemenuhan']),
    metodePembayaran: UraiJson.AmbilTeks(json['MetodePembayaran']),
    status: UraiJson.AmbilTeks(json['Status']),
    subtotal: UraiJson.AmbilDesimal(json['Subtotal']),
    ongkir: UraiJson.AmbilDesimal(json['Ongkir']),
    diskonOngkir: UraiJson.AmbilDesimal(json['DiskonOngkir']),
    total: UraiJson.AmbilDesimal(json['Total']),
    sudahDibayar: UraiJson.AmbilBenar(json['SudahDibayar']),
    sisaUangMuka: UraiJson.AmbilDesimal(json['SisaUangMuka']),
    catatan: UraiJson.AmbilTeksAtauNull(json['Catatan']),
    dibuatPada: DateTime.tryParse(UraiJson.AmbilTeks(json['DibuatPada'])),
    baris: UraiJson.AmbilDaftarPeta(json['Baris']).map(BarisPesananOnlinePos.DariJson).toList(),
  );
}

/// Hasil `GET /api/pos/v1/pesanan-online`: pesanan aktif outlet perangkat + metode sistem "Uang muka (DP)"
/// (null = tenant belum pernah menerima uang muka, jadi pesanan berbayar belum bisa ditagih).
class HasilPesananOnline {
  const HasilPesananOnline({required this.pesanan, this.uuidMetodeUangMuka, this.namaMetodeUangMuka});

  final List<PesananOnlinePos> pesanan;
  final String? uuidMetodeUangMuka;
  final String? namaMetodeUangMuka;

  static HasilPesananOnline DariJson(Map<String, Object?> json) {
    final metode = UraiJson.AmbilPetaAtauNull(json['MetodeUangMuka']);
    return HasilPesananOnline(
      pesanan: UraiJson.AmbilDaftarPeta(json['Pesanan']).map(PesananOnlinePos.DariJson).toList(),
      uuidMetodeUangMuka: metode == null ? null : UraiJson.AmbilTeks(metode['Uuid']),
      namaMetodeUangMuka: metode == null ? null : UraiJson.AmbilTeks(metode['Nama']),
    );
  }
}
