import 'package:drift/drift.dart';

/// Skema 7 (F-16a): pelanggan yang pernah dipilih atau dibuat di perangkat ini, agar bisa dicari lagi saat offline.
/// Nomor HP hanya disimpan tersamar (`0812****7890`): data pribadi tidak disimpan utuh di perangkat.
@DataClassName('BarisPelangganLokal')
class PelangganLokal extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Nama => text()();
  TextColumn get NoHpSamar => text()();
  DateTimeColumn get DipakaiPada => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}
