import 'package:drift/drift.dart';

import 'TabelAbsensi.dart';
import 'TabelDeposit.dart';
import 'TabelKatalog.dart';
import 'TabelMeja.dart';
import 'TabelPelanggan.dart';
import 'TabelPascaPenjualan.dart';
import 'TabelPenjualan.dart';
import 'TabelPreOrder.dart';

export 'TabelAbsensi.dart';
export 'TabelDeposit.dart';
export 'TabelKatalog.dart';
export 'TabelMeja.dart';
export 'TabelPelanggan.dart';
export 'TabelPascaPenjualan.dart';
export 'TabelPenjualan.dart';
export 'TabelPreOrder.dart';

part 'BasisDataKasir.g.dart';

/// Basis data lokal aplikasi kasir (Drift/SQLite, PRD §18). Nama tabel & kolom sama dengan server. Uang disimpan
/// sebagai TEXT desimal (bukan REAL). Migrasi skema tidak boleh menghapus outbox yang belum terkirim.

/// Pengaturan & identitas perangkat non-rahasia (outlet, perangkat, batas kas keluar, shift bersama).
@DataClassName('BarisPengaturan')
class Pengaturan extends Table {
  TextColumn get Kunci => text()();
  TextColumn get Nilai => text()();

  @override
  Set<Column<Object>> get primaryKey => {Kunci};
}

/// Staf yang boleh masuk di perangkat ini + verifier PIN offline terbungkus (dari data awal).
@DataClassName('BarisStaf')
class Staf extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Nama => text()();
  BoolColumn get Pemilik => boolean()();
  TextColumn get Izin => text()();
  BoolColumn get PinDiatur => boolean()();
  TextColumn get PinGaram => text().nullable()();
  TextColumn get PinNonce => text().nullable()();
  TextColumn get PinSandi => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisKategoriKas')
class KategoriKas extends Table {
  TextColumn get Uuid => text()();
  TextColumn get Nama => text()();
  TextColumn get Jenis => text()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisShift')
class Shift extends Table {
  TextColumn get Uuid => text()();
  TextColumn get DibukaOleh => text()();
  TextColumn get NamaKasir => text()();
  DateTimeColumn get DibukaPada => dateTime()();
  TextColumn get KasAwal => text()();
  TextColumn get PecahanKasAwal => text().nullable()();
  BoolColumn get Bersama => boolean()();
  TextColumn get Status => text()();

  // Skema 3 (F-11): hasil tutup shift di perangkat. Semua nullable (diisi saat shift ditutup).
  TextColumn get DitutupOleh => text().nullable()();
  TextColumn get NamaPenutup => text().nullable()();
  DateTimeColumn get DitutupPada => dateTime().nullable()();
  TextColumn get KasSeharusnya => text().nullable()();
  TextColumn get KasAktual => text().nullable()();
  TextColumn get Selisih => text().nullable()();

  /// JSON `[{Nominal, Jumlah}]`.
  TextColumn get PecahanKasAkhir => text().nullable()();

  /// JSON `[{UuidMetodePembayaran, Jumlah}]` (hitungan non-tunai kasir).
  TextColumn get NonTunaiDilaporkan => text().nullable()();
  TextColumn get AlasanSelisih => text().nullable()();
  TextColumn get UuidPenyetujuSelisih => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

@DataClassName('BarisMutasiKas')
class MutasiKas extends Table {
  TextColumn get Uuid => text()();
  TextColumn get UuidShift => text().references(Shift, #Uuid)();
  TextColumn get Jenis => text()();
  TextColumn get UuidKategori => text().nullable()();
  TextColumn get NamaKategori => text().nullable()();
  TextColumn get Jumlah => text()();
  TextColumn get Catatan => text().nullable()();
  TextColumn get DicatatOleh => text()();
  DateTimeColumn get DicatatPada => dateTime()();
  TextColumn get DisetujuiOleh => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {Uuid};
}

/// Antrean kirim FIFO per perangkat (PRD §18 no. 4). Satu baris per item; `Status` Tertunda/PerluTindakan.
@DataClassName('BarisOutbox')
class Outbox extends Table {
  IntColumn get Id => integer().autoIncrement()();
  TextColumn get Uuid => text().unique()();
  TextColumn get Jenis => text()();
  TextColumn get Data => text()();
  TextColumn get Status => text()();
  IntColumn get Percobaan => integer().withDefault(const Constant(0))();
  TextColumn get KodeGalat => text().nullable()();
  TextColumn get PesanGalat => text().nullable()();
  DateTimeColumn get DibuatPada => dateTime()();
  DateTimeColumn get BerikutnyaPada => dateTime()();
}

/// Penguncian PIN lokal: 5 kali salah → kunci 5 menit (PRD §20.2, §25.2 no. 3), berlaku juga offline.
@DataClassName('BarisPercobaanPin')
class PercobaanPin extends Table {
  TextColumn get UuidPengguna => text()();
  IntColumn get JumlahGagal => integer()();
  DateTimeColumn get TerkunciSampai => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {UuidPengguna};
}

@DriftDatabase(
  tables: [
    Pengaturan,
    Staf,
    KategoriKas,
    Shift,
    MutasiKas,
    Outbox,
    PercobaanPin,
    // Skema 2 (F-07c): katalog, pajak & metode bayar, penjualan.
    Kategori,
    Satuan,
    KelompokPajak,
    KelompokPajakDetail,
    Produk,
    ProdukSatuan,
    ProdukBarcode,
    DaftarHarga,
    ProdukHarga,
    KelompokPilihan,
    Pilihan,
    ProdukKelompokPilihan,
    TarifPajak,
    MetodePembayaran,
    Penjualan,
    PenjualanDetail,
    PenjualanPembayaran,
    PesananTertahan,
    NomorUrutPenjualan,
    // Skema 5 (F-09 fase 1): void & retur penjualan.
    VoidPenjualan,
    ReturPenjualan,
    ReturPenjualanDetail,
    ReturPenjualanPembayaran,
    NomorUrutReturPenjualan,
    // Skema 6 (F-07 mode meja fase 1): meja & pesanan terbuka.
    AreaMeja,
    Meja,
    PesananTerbuka,
    NomorUrutPesananTerbuka,
    // Skema 7 (F-16a): pelanggan yang pernah dipakai perangkat.
    PelangganLokal,
    // Skema 10 (F-18): absensi staf di perangkat ini.
    AbsensiLokal,
    // Skema 11 (F-12 bagian 2): pre-order + uang muka dari perangkat ini.
    PesananPenjualanLokal,
    NomorUrutPesananPenjualan,
    // Skema 13 (F-16d bagian 1): isi deposit pelanggan dari perangkat ini.
    IsiDepositLokal,
    NomorUrutIsiDeposit,
  ],
)
class BasisDataKasir extends _$BasisDataKasir {
  BasisDataKasir(super.executor);

  /// Riwayat skema: 1 = F-06 (shift, kas, outbox); 2 = F-07c (katalog, pajak, metode bayar, penjualan); 3 = F-11
  /// (kolom tutup shift); 4 = F-07 tindak lanjut v1.46 (kategori jenis pajak di kelompok pajak); 5 = F-09 fase 1 (void
  /// & retur penjualan); 6 = F-07 mode meja fase 1 (meja & pesanan terbuka); 7 = F-16a (pelanggan lokal); 8 = F-16b
  /// (tier pelanggan lokal); 9 = F-12 (posisi kredit pelanggan lokal); 10 = F-18 (absensi lokal); 11 = F-12 bagian 2
  /// (pre-order lokal); 12 = F-16c bagian 3 (data promo pelanggan); 13 = F-16d bagian 1 (isi deposit lokal); 14 = F-16d
  /// bagian 2 (produk paket sesi).
  @override
  int get schemaVersion => 14;

  @override
  MigrationStrategy get migration => MigrationStrategy(
    onCreate: (m) => m.createAll(),
    onUpgrade: (m, dari, ke) async {
      // Migrasi hanya MENAMBAH tabel/indeks. Outbox & dokumen lama tidak disentuh (PRD §18.3 no. 9).
      if (dari < 2) {
        for (final tabel in <TableInfo<Table, Object?>>[
          kategori,
          satuan,
          kelompokPajak,
          kelompokPajakDetail,
          produk,
          produkSatuan,
          produkBarcode,
          daftarHarga,
          produkHarga,
          kelompokPilihan,
          pilihan,
          produkKelompokPilihan,
          tarifPajak,
          metodePembayaran,
          penjualan,
          penjualanDetail,
          penjualanPembayaran,
          pesananTertahan,
          nomorUrutPenjualan,
        ]) {
          await m.createTable(tabel);
        }
        await m.createIndex(indeksProdukBarcodeBarcode);
        await m.createIndex(indeksPenjualanTanggalBisnis);
      }
      if (dari < 3) {
        for (final kolom in <GeneratedColumn<Object>>[
          shift.DitutupOleh,
          shift.NamaPenutup,
          shift.DitutupPada,
          shift.KasSeharusnya,
          shift.KasAktual,
          shift.Selisih,
          shift.PecahanKasAkhir,
          shift.NonTunaiDilaporkan,
          shift.AlasanSelisih,
          shift.UuidPenyetujuSelisih,
        ]) {
          await m.addColumn(shift, kolom);
        }
      }
      // Dari skema 1, tabel katalog baru dibuat di atas sudah berkolom lengkap.
      if (dari >= 2 && dari < 4) {
        await m.addColumn(kelompokPajakDetail, kelompokPajakDetail.Kategori);
      }
      if (dari < 5) {
        for (final tabel in <TableInfo<Table, Object?>>[
          voidPenjualan,
          returPenjualan,
          returPenjualanDetail,
          returPenjualanPembayaran,
          nomorUrutReturPenjualan,
        ]) {
          await m.createTable(tabel);
        }
      }
      if (dari < 6) {
        for (final tabel in <TableInfo<Table, Object?>>[areaMeja, meja, pesananTerbuka, nomorUrutPesananTerbuka]) {
          await m.createTable(tabel);
        }
      }
      if (dari < 7) {
        await m.createTable(pelangganLokal);
      }
      // Dari skema ≤ 6, tabel di atas sudah dibuat berkolom lengkap.
      if (dari == 7) {
        await m.addColumn(pelangganLokal, pelangganLokal.KodeTier);
        await m.addColumn(pelangganLokal, pelangganLokal.NamaTier);
      }
      if (dari >= 7 && dari < 9) {
        await m.addColumn(pelangganLokal, pelangganLokal.LimitKredit);
        await m.addColumn(pelangganLokal, pelangganLokal.SisaPiutang);
        await m.addColumn(pelangganLokal, pelangganLokal.HariLewatJatuhTempo);
      }
      if (dari < 10) {
        await m.createTable(absensiLokal);
      }
      if (dari < 11) {
        await m.createTable(pesananPenjualanLokal);
        await m.createTable(nomorUrutPesananPenjualan);
      }
      // Skema 12 (F-16c bagian 3): data promo pelanggan; tabel yang dibuat di skema < 7 sudah berkolom lengkap.
      if (dari >= 7 && dari < 12) {
        await m.addColumn(pelangganLokal, pelangganLokal.HariLahir);
        await m.addColumn(pelangganLokal, pelangganLokal.JumlahTransaksi);
        await m.addColumn(pelangganLokal, pelangganLokal.PemakaianPromo);
        await m.addColumn(pelangganLokal, pelangganLokal.PemakaianPada);
      }
      if (dari < 13) {
        await m.createTable(isiDepositLokal);
        await m.createTable(nomorUrutIsiDeposit);
      }
      // Skema 14 (F-16d bagian 2): tanda paket sesi di produk. Tabel produk dari skema < 2 sudah berkolom lengkap.
      // Kursor katalog dihapus agar sinkron berikutnya lengkap dan produk paket yang sudah ada ikut tertandai.
      if (dari >= 2 && dari < 14) {
        final kolom = await customSelect(
          "SELECT COUNT(*) AS Jumlah FROM pragma_table_info('Produk') WHERE name = 'JumlahSesiPaket'",
        ).map((r) => r.read<int>('Jumlah')).getSingle();
        if (kolom == 0) {
          await m.addColumn(produk, produk.JumlahSesiPaket);
        }
        await (delete(pengaturan)..where((p) => p.Kunci.equals('KursorKatalog'))).go();
      }
    },
    beforeOpen: (detail) async {
      await customStatement('PRAGMA foreign_keys = ON');
    },
  );
}
