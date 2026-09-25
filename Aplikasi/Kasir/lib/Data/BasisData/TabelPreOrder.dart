import 'package:drift/drift.dart';

/// Skema 11 (F-12 bagian 2): pre-order dengan uang muka yang dibuat di perangkat ini, agar kas shift (DP tunai) dan
/// daftar pre-order tetap benar saat offline. Barisnya dikirim lewat outbox `PesananPenjualan.Buat`; pengambilan dicari
/// online dari server. Uang string desimal.
@DataClassName('BarisPesananPenjualanLokal')
class PesananPenjualanLokal extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidShift => text()();
  TextColumn get Nomor => text()();
  TextColumn get NamaPelanggan => text()();

  /// `YYYY-MM-DD`.
  TextColumn get TanggalAmbil => text()();
  TextColumn get TotalPesanan => text()();
  TextColumn get UangMuka => text()();
  TextColumn get UuidMetodePembayaran => text()();
  TextColumn get JenisMetode => text()();
  TextColumn get NamaMetode => text()();
  DateTimeColumn get DibuatPada => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

/// Sekuens nomor pre-order per perangkat per hari: `SO/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ4}`.
@DataClassName('BarisNomorUrutPesananPenjualan')
class NomorUrutPesananPenjualan extends Table {
  TextColumn get KodePerangkat => text()();

  /// `YYMMDD`.
  TextColumn get Tanggal => text()();
  IntColumn get Terakhir => integer()();

  @override
  Set<Column<Object>> get primaryKey => {KodePerangkat, Tanggal};
}
