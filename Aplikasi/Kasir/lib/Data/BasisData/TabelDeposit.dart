import 'package:drift/drift.dart';

/// Skema 13 (F-16d bagian 1): isi saldo deposit pelanggan di perangkat ini, agar kas shift (isi tunai) tetap benar saat
/// offline. Barisnya dikirim lewat outbox `Deposit.Isi`; saldo deposit dibaca online dari server. Uang string desimal.
@DataClassName('BarisIsiDepositLokal')
class IsiDepositLokal extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidShift => text()();
  TextColumn get Nomor => text()();
  TextColumn get UuidPelanggan => text()();
  TextColumn get NamaPelanggan => text()();
  TextColumn get Jumlah => text()();
  TextColumn get UuidMetodePembayaran => text()();
  TextColumn get JenisMetode => text()();
  TextColumn get NamaMetode => text()();
  TextColumn get Referensi => text().nullable()();
  DateTimeColumn get DibuatPada => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

/// Sekuens nomor isi deposit per perangkat per hari: `DEP/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ4}`.
@DataClassName('BarisNomorUrutIsiDeposit')
class NomorUrutIsiDeposit extends Table {
  TextColumn get KodePerangkat => text()();

  /// `YYMMDD`.
  TextColumn get Tanggal => text()();
  IntColumn get Terakhir => integer()();

  @override
  Set<Column<Object>> get primaryKey => {KodePerangkat, Tanggal};
}
