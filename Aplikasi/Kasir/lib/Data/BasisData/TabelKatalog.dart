import 'package:drift/drift.dart';

/// Tabel katalog lokal (skema 2, Rincian F-07c): salinan bagian `GET /api/pos/v1/katalog` dengan nama tabel & kolom
/// sama dengan server (FK sebagai `Uuid{Tabel}`). Uang & jumlah TEXT desimal. Diganti utuh saat katalog lengkap dan
/// diperbarui per baris saat delta.

@DataClassName('BarisKategori')
class Kategori extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidInduk => text().nullable()();
  TextColumn get Nama => text()();
  IntColumn get Urutan => integer().withDefault(const Constant(0))();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisSatuan')
class Satuan extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Nama => text()();
  TextColumn get Simbol => text().nullable()();
  BoolColumn get BolehDesimal => boolean().withDefault(const Constant(false))();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisKelompokPajak')
class KelompokPajak extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Nama => text()();
  TextColumn get Kategori => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

/// Jenis pajak per kelompok (kode jenis & dasar pengenaan; tarif dari `TarifPajak`).
@DataClassName('BarisKelompokPajakDetail')
class KelompokPajakDetail extends Table {
  TextColumn get UuidKelompokPajak => text()();
  TextColumn get KodeJenisPajak => text()();
  TextColumn get DasarPengenaan => text()();
  IntColumn get Urutan => integer().withDefault(const Constant(0))();

  /// `Ppn`, `Pbjt`, atau `Lainnya` dari atribut `JenisPajak` (skema 4, PRD v1.46); null = server lama.
  TextColumn get Kategori => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {UuidKelompokPajak, KodeJenisPajak};
}

@DataClassName('BarisProduk')
class Produk extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Sku => text().nullable()();
  TextColumn get Nama => text()();
  TextColumn get NamaStruk => text().nullable()();
  TextColumn get Jenis => text()();
  TextColumn get UuidKategori => text().nullable()();
  TextColumn get UuidSatuanDasar => text().nullable()();
  TextColumn get Pelacakan => text()();
  TextColumn get UuidKelompokPajak => text().nullable()();
  BoolColumn get HargaTermasukPajak => boolean().nullable()();
  BoolColumn get TampilDiPos => boolean()();
  TextColumn get UuidInduk => text().nullable()();
  TextColumn get UrlGambarKecil => text().nullable()();
  BoolColumn get Aktif => boolean()();

  /// F-16d bagian 2: jumlah sesi bila produk paket sesi; null = bukan paket sesi.
  IntColumn get JumlahSesiPaket => integer().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisProdukSatuan')
class ProdukSatuan extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidProduk => text()();
  TextColumn get UuidSatuan => text()();
  TextColumn get KonversiKeDasar => text()();
  BoolColumn get DefaultJual => boolean()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisProdukBarcode')
@TableIndex(name: 'IndeksProdukBarcodeBarcode', columns: {#Barcode})
class ProdukBarcode extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidProduk => text()();
  TextColumn get UuidProdukSatuan => text().nullable()();
  TextColumn get Barcode => text()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisDaftarHarga')
class DaftarHarga extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Nama => text()();

  /// JSON daftar Uuid outlet; null = semua outlet.
  TextColumn get UuidOutlet => text().nullable()();
  TextColumn get Kanal => text().nullable()();
  TextColumn get TierPelanggan => text().nullable()();
  DateTimeColumn get MulaiPada => dateTime().nullable()();
  DateTimeColumn get SelesaiPada => dateTime().nullable()();
  IntColumn get Prioritas => integer()();
  BoolColumn get Aktif => boolean()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisProdukHarga')
class ProdukHarga extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidProduk => text()();
  TextColumn get UuidProdukSatuan => text()();
  TextColumn get UuidDaftarHarga => text().nullable()();
  TextColumn get JumlahMinimum => text()();
  TextColumn get Harga => text()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisKelompokPilihan')
class KelompokPilihan extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Nama => text()();
  IntColumn get MinimalPilih => integer()();
  IntColumn get MaksimalPilih => integer().nullable()();
  IntColumn get Urutan => integer()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisPilihan')
class Pilihan extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidKelompokPilihan => text()();
  TextColumn get Nama => text()();
  TextColumn get Harga => text()();
  TextColumn get UuidProdukBahan => text().nullable()();
  TextColumn get Jumlah => text().nullable()();
  BoolColumn get Aktif => boolean()();
  IntColumn get Urutan => integer()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisProdukKelompokPilihan')
class ProdukKelompokPilihan extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidProduk => text()();
  TextColumn get UuidKelompokPilihan => text()();
  IntColumn get Urutan => integer()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

/// Tarif pajak terbit dari data awal (nasional + wilayah outlet), tanggal `YYYY-MM-DD` (CLAUDE.md #12).
@DataClassName('BarisTarifPajak')
class TarifPajak extends Table {
  IntColumn get Id => integer().autoIncrement()();
  TextColumn get KodeJenisPajak => text()();
  TextColumn get Tarif => text()();
  IntColumn get PengaliDppPembilang => integer()();
  IntColumn get PengaliDppPenyebut => integer()();
  TextColumn get BerlakuMulai => text()();
  TextColumn get BerlakuSampai => text().nullable()();
}

/// Metode pembayaran aktif outlet dari data awal (jenis fase 1).
@DataClassName('BarisMetodePembayaran')
class MetodePembayaran extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Jenis => text()();
  TextColumn get Nama => text()();
  TextColumn get NomorRekening => text().nullable()();
  TextColumn get NamaPemilikRekening => text().nullable()();
  BoolColumn get AdaGambarQris => boolean()();
  IntColumn get Urutan => integer()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}
