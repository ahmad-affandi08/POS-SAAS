import 'package:drift/drift.dart';

/// Skema 17 (F-05f bagian 2): bahan/menu terbuang yang dicatat di perangkat ini, untuk daftar "hari ini" dan status
/// kirimnya (tetap tampil setelah item outbox `BahanTerbuang.Catat` terkirim dan dihapus). Jumlah string desimal.
@DataClassName('BarisBahanTerbuangLokal')
class BahanTerbuangLokal extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidProduk => text()();
  TextColumn get NamaProduk => text()();

  /// Jumlah pada satuan yang dipilih pencatat dan nama satuannya (tampilan).
  TextColumn get Jumlah => text()();
  TextColumn get NamaSatuan => text()();

  /// Jumlah dalam satuan dasar produk (yang dikirim ke server).
  TextColumn get JumlahDasar => text()();
  TextColumn get Alasan => text()();
  TextColumn get Catatan => text().nullable()();
  TextColumn get UuidPengguna => text()();
  TextColumn get NamaPengguna => text()();

  /// Tanggal bisnis outlet `YYYY-MM-DD` saat dicatat.
  TextColumn get TanggalBisnis => text()();
  DateTimeColumn get DibuatPada => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}
