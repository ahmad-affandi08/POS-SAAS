import 'package:drift/drift.dart';

/// Skema 10 (F-18): absensi staf di perangkat ini, agar status masuk/keluar diketahui saat offline. Swafoto tidak
/// disimpan di tabel ini (hanya di item outbox sampai terkirim).
@DataClassName('BarisAbsensiLokal')
class AbsensiLokal extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidPengguna => text()();
  TextColumn get NamaStaf => text()();
  DateTimeColumn get MasukPada => dateTime()();
  DateTimeColumn get KeluarPada => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}
