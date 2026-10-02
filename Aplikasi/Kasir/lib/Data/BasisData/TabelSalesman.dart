import 'package:drift/drift.dart';

/// Skema 26 (Modul Salesman bagian 2, §9.7, SLS-11): data kerja HP salesman.
///
/// - [PelangganSalesmanLokal]: cache offline seluruh pelanggan aktif dari `GET salesman/pelanggan` (diganti utuh setiap
///   unduhan lengkap berhasil). Nomor HP **penuh** dan alamat ikut disimpan (K30) karena salesman perlu menghubungi &
///   mendatangi toko; basis data lokal terenkripsi (K-7) dan cache ini dihapus saat perangkat dicabut. Uang TEXT desimal.
/// - [KunjunganSalesLokal]: kunjungan dari perangkat ini. Baris tanpa [KunjunganSalesLokal.KeluarPada] = kunjungan
///   yang sedang berjalan (bertahan bila aplikasi tertutup); saat selesai, baris diperbarui bersama item outbox
///   `Kunjungan.Catat` dalam satu transaksi.
/// - [PesananGrosirLokal]: pesanan grosir yang diambil salesman, untuk riwayat & status kirim (tetap tampil setelah item
///   outbox `PesananGrosir.Buat` terkirim dan dihapus). Harga hanya **perkiraan** dari katalog lokal; harga final dari
///   server.
@DataClassName('BarisPelangganSalesmanLokal')
class PelangganSalesmanLokal extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Nama => text()();
  TextColumn get NoHp => text().nullable()();
  TextColumn get Alamat => text().nullable()();
  TextColumn get KodeTier => text().nullable()();
  TextColumn get NamaTier => text().nullable()();

  /// Null = tanpa limit kredit.
  TextColumn get LimitKredit => text().nullable()();
  IntColumn get TerminHari => integer().withDefault(const Constant(0))();
  TextColumn get SisaPiutang => text()();
  TextColumn get JumlahPiutangJatuhTempo => text()();
  IntColumn get HariLewatJatuhTempo => integer().withDefault(const Constant(0))();
  DateTimeColumn get TerakhirDikunjungiPada => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisKunjunganSalesLokal')
class KunjunganSalesLokal extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidPelanggan => text()();
  TextColumn get NamaPelanggan => text()();
  TextColumn get UuidPengguna => text()();
  TextColumn get NamaPengguna => text()();
  DateTimeColumn get MasukPada => dateTime()();

  /// Null = kunjungan masih berjalan.
  DateTimeColumn get KeluarPada => dateTime().nullable()();

  /// Koordinat string desimal (maks. 7 angka di belakang titik), dibentuk sekali di batas platform. Bukan uang/jumlah,
  /// tetapi tetap teks supaya nilai yang dikirim persis nilai yang tersimpan. Keduanya null = lokasi tidak tersedia.
  TextColumn get Latitude => text().nullable()();
  TextColumn get Longitude => text().nullable()();
  IntColumn get AkurasiMeter => integer().nullable()();

  /// `PesananDibuat` / `TidakPesan` / `TokoTutup` / `Lainnya`; null selama berjalan.
  TextColumn get Hasil => text().nullable()();
  TextColumn get Catatan => text().nullable()();

  /// Pesanan pertama yang diambil selama kunjungan ini.
  TextColumn get UuidPesananGrosir => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisPesananGrosirLokal')
class PesananGrosirLokal extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidPelanggan => text()();
  TextColumn get NamaPelanggan => text()();
  TextColumn get UuidPengguna => text()();
  TextColumn get NamaPengguna => text()();
  TextColumn get UuidKunjungan => text().nullable()();
  TextColumn get Catatan => text().nullable()();

  /// JSON `[{UuidProduk, NamaProduk, UuidSatuan, NamaSatuan, Jumlah, PerkiraanHarga|null}]` (tampilan riwayat).
  TextColumn get Baris => text()();
  IntColumn get JumlahBaris => integer()();

  /// Σ perkiraan dari harga katalog lokal (bukan harga final).
  TextColumn get PerkiraanTotal => text()();
  DateTimeColumn get DibuatPada => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}
