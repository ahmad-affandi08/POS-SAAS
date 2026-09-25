import 'package:drift/drift.dart';

/// Tabel void & retur penjualan lokal (skema 5, Rincian F-09 fase 1). Dokumen + perubahan status penjualan lokal +
/// outbox (`Penjualan.Void` / `ReturPenjualan.Buat`) ditulis dalam satu transaksi SQLite (PRD §18.3 no. 3). Uang &
/// jumlah TEXT desimal. Status sinkron diturunkan dari outbox (Uuid dokumen = Uuid item outbox).

/// Void penjualan di shift yang sama (Uuid = Uuid item outbox `Penjualan.Void` = Uuid `VoidPenjualan` server).
@DataClassName('BarisVoidPenjualan')
class VoidPenjualan extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidPenjualan => text().unique()();

  /// Shift penjualan (= shift yang laci kasnya mengeluarkan refund tunai).
  TextColumn get UuidShift => text()();
  TextColumn get UuidPengguna => text()();
  TextColumn get NamaPengguna => text()();
  TextColumn get UuidPenyetuju => text()();
  TextColumn get NamaPenyetuju => text()();
  TextColumn get Alasan => text()();
  DateTimeColumn get DivoidPada => dateTime()();

  /// `TotalAkhir` penjualan.
  TextColumn get Nominal => text()();

  /// Tunai bersih (diterima − kembalian) yang dikembalikan dari laci.
  TextColumn get RefundTunai => text()();

  /// Non-tunai yang dikembalikan manual (BR-09.2).
  TextColumn get RefundNonTunai => text()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

/// Retur penjualan (Uuid = Uuid item outbox `ReturPenjualan.Buat`). Penjualan asal bisa dari perangkat lain.
@DataClassName('BarisReturPenjualan')
class ReturPenjualan extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Nomor => text().unique()();
  TextColumn get UuidPenjualanAsal => text()();
  TextColumn get NomorPenjualanAsal => text()();

  /// Shift aktif saat retur (laci yang mengeluarkan refund tunai).
  TextColumn get UuidShift => text()();
  TextColumn get UuidPengguna => text()();
  TextColumn get NamaKasir => text()();
  TextColumn get UuidPenyetuju => text()();
  TextColumn get Alasan => text()();
  DateTimeColumn get DibuatPada => dateTime()();

  /// `YYYY-MM-DD` tanggal bisnis outlet saat retur.
  TextColumn get TanggalBisnis => text()();

  /// `Tunai`, `Transfer`, atau `Campuran`.
  TextColumn get MetodeRefund => text()();
  TextColumn get TotalRefund => text()();
  TextColumn get RefundTunai => text()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisReturPenjualanDetail')
class ReturPenjualanDetail extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidReturPenjualan => text().references(ReturPenjualan, #Uuid)();
  TextColumn get UuidPenjualanDetail => text()();
  TextColumn get NamaProduk => text()();
  TextColumn get SimbolSatuan => text()();
  TextColumn get Jumlah => text()();

  /// `LayakJual` atau `Rusak`.
  TextColumn get Kondisi => text()();

  /// Nilai retur baris (bagian proporsional `TotalBaris`).
  TextColumn get NilaiBaris => text()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisReturPenjualanPembayaran')
class ReturPenjualanPembayaran extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidReturPenjualan => text().references(ReturPenjualan, #Uuid)();
  TextColumn get UuidMetodePembayaran => text()();
  TextColumn get Jenis => text()();
  TextColumn get NamaMetode => text()();
  TextColumn get Jumlah => text()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

/// Sekuens nomor retur per perangkat per hari: `RJ/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ4}`.
@DataClassName('BarisNomorUrutReturPenjualan')
class NomorUrutReturPenjualan extends Table {
  TextColumn get KodePerangkat => text()();

  /// `YYMMDD`.
  TextColumn get Tanggal => text()();
  IntColumn get Terakhir => integer()();

  @override
  Set<Column<Object>> get primaryKey => {KodePerangkat, Tanggal};
}
