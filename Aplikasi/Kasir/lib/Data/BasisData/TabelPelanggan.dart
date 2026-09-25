import 'package:drift/drift.dart';

/// Skema 7 (F-16a): pelanggan yang pernah dipilih atau dibuat di perangkat ini, agar bisa dicari lagi saat offline.
/// Nomor HP hanya disimpan tersamar (`0812****7890`): data pribadi tidak disimpan utuh di perangkat.
@DataClassName('BarisPelangganLokal')
class PelangganLokal extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Nama => text()();
  TextColumn get NoHpSamar => text()();
  DateTimeColumn get DipakaiPada => dateTime()();

  // Skema 8 (F-16b): tier untuk harga per tier saat offline.
  TextColumn get KodeTier => text().nullable()();
  TextColumn get NamaTier => text().nullable()();

  // Skema 9 (F-12): posisi kredit terakhir yang diketahui (cek BR-12.1 offline); null = belum diketahui.
  TextColumn get LimitKredit => text().nullable()();
  TextColumn get SisaPiutang => text().nullable()();
  IntColumn get HariLewatJatuhTempo => integer().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}
