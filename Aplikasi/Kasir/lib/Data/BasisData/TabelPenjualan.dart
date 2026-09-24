import 'package:drift/drift.dart';

/// Tabel penjualan lokal (skema 2, Rincian F-07c). Penjualan + detail + pembayaran + outbox `Penjualan.Buat` ditulis
/// dalam satu transaksi SQLite (PRD §18.3 no. 3). Uang & jumlah TEXT desimal. Status sinkron tidak disimpan di sini:
/// diturunkan dari outbox (masih ada = belum terkirim/perlu tindakan, tidak ada = terkirim).

@DataClassName('BarisPenjualan')
@TableIndex(name: 'IndeksPenjualanTanggalBisnis', columns: {#TanggalBisnis})
class Penjualan extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Nomor => text().unique()();
  TextColumn get UuidShift => text()();
  TextColumn get UuidPengguna => text()();
  TextColumn get NamaKasir => text()();
  TextColumn get Kanal => text()();
  DateTimeColumn get DibuatPada => dateTime()();

  /// `YYYY-MM-DD` tanggal bisnis outlet.
  TextColumn get TanggalBisnis => text()();
  TextColumn get Status => text()();
  TextColumn get Subtotal => text()();
  TextColumn get TotalDiskon => text()();
  TextColumn get BiayaLayanan => text()();
  TextColumn get TotalPajak => text()();
  TextColumn get Pembulatan => text()();
  TextColumn get TotalAkhir => text()();
  TextColumn get TotalDibayar => text()();
  TextColumn get Kembalian => text()();
  TextColumn get UuidPenyetujuDiskon => text().nullable()();
  TextColumn get Catatan => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisPenjualanDetail')
class PenjualanDetail extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidPenjualan => text().references(Penjualan, #Uuid)();
  IntColumn get Urutan => integer()();
  TextColumn get UuidProduk => text()();
  TextColumn get UuidProdukSatuan => text().nullable()();
  TextColumn get NamaProduk => text()();
  TextColumn get NamaSatuan => text().nullable()();
  TextColumn get Jumlah => text()();
  TextColumn get HargaSatuan => text()();
  TextColumn get HargaPilihan => text()();

  /// JSON `[{UuidPilihan, Nama, Harga}]`.
  TextColumn get Pilihan => text()();
  TextColumn get Bruto => text()();
  TextColumn get Diskon => text()();
  TextColumn get DiskonPesanan => text()();
  TextColumn get BiayaLayanan => text()();
  TextColumn get JumlahPajak => text()();
  TextColumn get PajakEksklusif => text()();
  TextColumn get TotalBaris => text()();
  TextColumn get Catatan => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisPenjualanPembayaran')
class PenjualanPembayaran extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidPenjualan => text().references(Penjualan, #Uuid)();
  TextColumn get UuidMetodePembayaran => text()();
  TextColumn get Jenis => text()();
  TextColumn get NamaMetode => text()();
  TextColumn get Jumlah => text()();
  TextColumn get Referensi => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

/// Pesanan yang ditahan (parkir) di perangkat ini; tidak dikirim ke server (Rincian F-07b). `Data` = JSON keranjang.
@DataClassName('BarisPesananTertahan')
class PesananTertahan extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Label => text()();
  TextColumn get Data => text()();
  TextColumn get Total => text()();
  IntColumn get JumlahItem => integer()();
  TextColumn get UuidPengguna => text()();
  DateTimeColumn get DibuatPada => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

/// Sekuens nomor penjualan per perangkat per hari (BR-07.1): `INV/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ4}`.
@DataClassName('BarisNomorUrutPenjualan')
class NomorUrutPenjualan extends Table {
  TextColumn get KodePerangkat => text()();

  /// `YYMMDD`.
  TextColumn get Tanggal => text()();
  IntColumn get Terakhir => integer()();

  @override
  Set<Column<Object>> get primaryKey => {KodePerangkat, Tanggal};
}
