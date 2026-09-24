import 'package:inti/Inti.dart';

/// Kanal penjualan (PRD §15.3 `Penjualan.Kanal`); padanan `App\Domain\Penjualan\Enum\KanalPenjualan`.
enum KanalPenjualan { MakanDiTempat, BawaPulang, Antar, Online, PesanSendiri, Marketplace }

/// Lapisan price engine yang menghasilkan harga; padanan `App\Domain\Katalog\Harga\Enum\SumberHarga`.
enum SumberHarga { DaftarHarga, Bertingkat, Dasar }

/// Daftar harga (bagian `DaftarHarga` katalog POS). Kondisi null = berlaku untuk semua. Rentang waktu UTC
/// setengah terbuka `[mulaiPada, selesaiPada)`.
final class DaftarHargaResolusi {
  const DaftarHargaResolusi({
    required this.uuid,
    required this.aktif,
    required this.uuidOutlet,
    required this.kanal,
    required this.tierPelanggan,
    required this.mulaiPada,
    required this.selesaiPada,
    required this.prioritas,
  });

  final String uuid;
  final bool aktif;
  final List<String>? uuidOutlet;
  final KanalPenjualan? kanal;
  final String? tierPelanggan;
  final DateTime? mulaiPada;
  final DateTime? selesaiPada;
  final int prioritas;
}

/// Satu baris `ProdukHarga`; `uuidDaftarHarga` null = harga dasar/bertingkat satuan produk.
final class BarisProdukHarga {
  const BarisProdukHarga({
    required this.uuidProduk,
    required this.uuidProdukSatuan,
    required this.uuidDaftarHarga,
    required this.jumlahMinimum,
    required this.harga,
  });

  final String uuidProduk;
  final String uuidProdukSatuan;
  final String? uuidDaftarHarga;
  final Kuantitas jumlahMinimum;
  final Uang harga;
}

/// Data harga yang dibaca `PenentuHarga`: semua daftar harga tenant dan baris harga.
final class KatalogHarga {
  const KatalogHarga({required this.daftarHarga, required this.harga});

  final List<DaftarHargaResolusi> daftarHarga;
  final List<BarisProdukHarga> harga;
}

/// Permintaan harga: produk & satuan, jumlah (> 0, dalam satuan itu), outlet, kanal, tier, dan waktu (UTC).
final class PermintaanHarga {
  const PermintaanHarga({
    required this.uuidProduk,
    required this.uuidProdukSatuan,
    required this.jumlah,
    required this.uuidOutlet,
    required this.kanal,
    required this.tierPelanggan,
    required this.waktu,
  });

  final String uuidProduk;
  final String uuidProdukSatuan;
  final Kuantitas jumlah;
  final String? uuidOutlet;
  final KanalPenjualan? kanal;
  final String? tierPelanggan;
  final DateTime waktu;
}

/// Harga satuan hasil `PenentuHarga`.
final class HasilHarga {
  const HasilHarga({
    required this.harga,
    required this.sumber,
    required this.uuidDaftarHarga,
    required this.jumlahMinimum,
  });

  final Uang harga;
  final SumberHarga sumber;
  final String? uuidDaftarHarga;
  final Kuantitas jumlahMinimum;
}
