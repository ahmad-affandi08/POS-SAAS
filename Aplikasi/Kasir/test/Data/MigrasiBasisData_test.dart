import 'dart:io';

import 'package:drift/drift.dart' show OrderingTerm, Value, driftRuntimeOptions;
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Data/BasisData/BasisDataKasir.dart';

/// Skema lokal versi 1 (F-06) persis seperti yang terpasang di perangkat sebelum F-07c.
const List<String> skemaVersi1 = [
  'CREATE TABLE "Pengaturan" ("Kunci" TEXT NOT NULL, "Nilai" TEXT NOT NULL, PRIMARY KEY ("Kunci"))',
  'CREATE TABLE "Staf" ("Uuid" TEXT NOT NULL, "Nama" TEXT NOT NULL, "Pemilik" INTEGER NOT NULL CHECK ("Pemilik" IN '
      '(0, 1)), "Izin" TEXT NOT NULL, "PinDiatur" INTEGER NOT NULL CHECK ("PinDiatur" IN (0, 1)), "PinGaram" TEXT NULL, '
      '"PinNonce" TEXT NULL, "PinSandi" TEXT NULL, PRIMARY KEY ("Uuid"))',
  'CREATE TABLE "KategoriKas" ("Uuid" TEXT NOT NULL, "Nama" TEXT NOT NULL, "Jenis" TEXT NOT NULL, PRIMARY KEY '
      '("Uuid"))',
  'CREATE TABLE "Shift" ("Uuid" TEXT NOT NULL, "DibukaOleh" TEXT NOT NULL, "NamaKasir" TEXT NOT NULL, "DibukaPada" '
      'TEXT NOT NULL, "KasAwal" TEXT NOT NULL, "PecahanKasAwal" TEXT NULL, "Bersama" INTEGER NOT NULL CHECK ("Bersama" '
      'IN (0, 1)), "Status" TEXT NOT NULL, PRIMARY KEY ("Uuid"))',
  'CREATE TABLE "MutasiKas" ("Uuid" TEXT NOT NULL, "UuidShift" TEXT NOT NULL REFERENCES Shift (Uuid), "Jenis" TEXT '
      'NOT NULL, "UuidKategori" TEXT NULL, "NamaKategori" TEXT NULL, "Jumlah" TEXT NOT NULL, "Catatan" TEXT NULL, '
      '"DicatatOleh" TEXT NOT NULL, "DicatatPada" TEXT NOT NULL, "DisetujuiOleh" TEXT NULL, PRIMARY KEY ("Uuid"))',
  'CREATE TABLE "Outbox" ("Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "Uuid" TEXT NOT NULL UNIQUE, "Jenis" TEXT '
      'NOT NULL, "Data" TEXT NOT NULL, "Status" TEXT NOT NULL, "Percobaan" INTEGER NOT NULL DEFAULT 0, "KodeGalat" TEXT '
      'NULL, "PesanGalat" TEXT NULL, "DibuatPada" TEXT NOT NULL, "BerikutnyaPada" TEXT NOT NULL)',
  'CREATE TABLE "PercobaanPin" ("UuidPengguna" TEXT NOT NULL, "JumlahGagal" INTEGER NOT NULL, "TerkunciSampai" TEXT '
      'NULL, PRIMARY KEY ("UuidPengguna"))',
];

/// Kolom `Shift` yang ditambahkan skema 3 (F-11).
const List<String> kolomTutupShift = [
  'DitutupOleh',
  'NamaPenutup',
  'DitutupPada',
  'KasSeharusnya',
  'KasAktual',
  'Selisih',
  'PecahanKasAkhir',
  'NonTunaiDilaporkan',
  'AlasanSelisih',
  'UuidPenyetujuSelisih',
];

/// Tabel skema 5 (F-09 fase 1: void & retur penjualan).
const List<String> tabelVoidRetur = [
  'VoidPenjualan',
  'ReturPenjualan',
  'ReturPenjualanDetail',
  'ReturPenjualanPembayaran',
  'NomorUrutReturPenjualan',
];

/// Tabel skema 6 (F-07 mode meja fase 1: meja & pesanan terbuka).
const List<String> tabelMeja = ['AreaMeja', 'Meja', 'PesananTerbuka', 'NomorUrutPesananTerbuka'];

/// Hapus tabel skema 13 (F-16d bagian 1 isi deposit lokal), agar sama dengan perangkat skema ≤ 12.
Future<void> HapusTabelDeposit(BasisDataKasir db) async {
  await db.customStatement('DROP TABLE "NomorUrutIsiDeposit"');
  await db.customStatement('DROP TABLE "IsiDepositLokal"');
}

/// Hapus tabel skema 11 (F-12 bagian 2 pre-order lokal), agar sama dengan perangkat skema ≤ 10.
Future<void> HapusTabelPreOrder(BasisDataKasir db) async {
  await db.customStatement('DROP TABLE "NomorUrutPesananPenjualan"');
  await db.customStatement('DROP TABLE "PesananPenjualanLokal"');
}

/// Hapus tabel skema 10 & 11 (F-18 absensi lokal, F-12 bagian 2 pre-order lokal), agar sama dengan perangkat skema ≤ 9.
Future<void> HapusTabelAbsensi(BasisDataKasir db) async {
  await HapusTabelPreOrder(db);
  await db.customStatement('DROP TABLE "AbsensiLokal"');
}

/// Hapus tabel skema 7 (F-16a pelanggan lokal), agar sama dengan perangkat skema ≤ 6.
Future<void> HapusTabelPelanggan(BasisDataKasir db) => db.customStatement('DROP TABLE "PelangganLokal"');

/// Kolom `PelangganLokal` yang ditambahkan skema 12 (F-16c bagian 3).
const kolomPromoPelanggan = ['HariLahir', 'JumlahTransaksi', 'PemakaianPromo', 'PemakaianPada'];

Future<void> HapusKolomPromoPelanggan(BasisDataKasir db) async {
  for (final kolom in kolomPromoPelanggan) {
    await db.customStatement('ALTER TABLE PelangganLokal DROP COLUMN $kolom');
  }
}

/// Hapus tabel skema 6 & 7 dari basis data yang dibangun dengan skema terbaru, agar sama dengan perangkat skema ≤ 5.
Future<void> HapusTabelMeja(BasisDataKasir db) async {
  await HapusTabelPelanggan(db);
  for (final tabel in tabelMeja.reversed) {
    await db.customStatement('DROP TABLE "$tabel"');
  }
}

/// Hapus tabel skema 5 & 6 dari basis data yang dibangun dengan skema terbaru, agar sama dengan perangkat skema ≤ 4.
Future<void> HapusTabelVoidRetur(BasisDataKasir db) async {
  await HapusTabelMeja(db);
  for (final tabel in tabelVoidRetur.reversed) {
    await db.customStatement('DROP TABLE "$tabel"');
  }
}

/// PRD §18.3 no. 9 & Rincian F-07c: migrasi skema lokal 1 → 2 hanya menambah tabel; outbox yang belum terkirim, shift,
/// dan mutasi kas tetap utuh.
void main() {
  setUpAll(() => driftRuntimeOptions.dontWarnAboutMultipleDatabases = true);

  test('migrasi 1 → 2 mempertahankan outbox tertunda & dokumen lama, lalu tabel F-07c siap dipakai', () async {
    final db = BasisDataKasir(
      NativeDatabase.memory(
        setup: (mentah) {
          for (final sql in skemaVersi1) {
            mentah.execute(sql);
          }
          mentah.execute(
            "INSERT INTO Shift VALUES ('SHIFT1', 'STAF1', 'Rina Wulandari', '2026-09-24T01:00:00.000Z', '500000.00', "
            "NULL, 0, 'Terbuka')",
          );
          mentah.execute(
            'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
            "('SHIFT1', 'Shift.Buka', '{\"KasAwal\":\"500000.00\"}', 'Tertunda', 3, '2026-09-24T01:00:00.000Z', "
            "'2026-09-24T01:05:00.000Z'),"
            "('MUTASI1', 'MutasiKas.Catat', '{}', 'PerluTindakan', 0, '2026-09-24T01:10:00.000Z', "
            "'2026-09-24T01:10:00.000Z')",
          );
          mentah.userVersion = 1;
        },
      ),
    );
    addTearDown(db.close);

    final outbox = await (db.select(db.outbox)..orderBy([(o) => OrderingTerm.asc(o.Id)])).get();
    expect(outbox.map((o) => o.Uuid), ['SHIFT1', 'MUTASI1'], reason: 'Outbox belum terkirim tidak boleh hilang.');
    expect(outbox.first.Percobaan, 3);
    expect(outbox.first.Data, '{"KasAwal":"500000.00"}');
    expect(outbox.last.Status, 'PerluTindakan');
    expect((await db.select(db.shift).get()).single.KasAwal, '500000.00');

    // Migrasi berantai sampai skema terbaru (5, void & retur F-09).
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
    final tabel = await db
        .customSelect("SELECT name FROM sqlite_master WHERE type = 'table'")
        .map((r) => r.read<String>('name'))
        .get();
    expect(
      tabel,
      containsAll([
        'Produk',
        'ProdukSatuan',
        'ProdukBarcode',
        'Kategori',
        'Satuan',
        'KelompokPajak',
        'KelompokPajakDetail',
        'DaftarHarga',
        'ProdukHarga',
        'KelompokPilihan',
        'Pilihan',
        'ProdukKelompokPilihan',
        'TarifPajak',
        'MetodePembayaran',
        'Penjualan',
        'PenjualanDetail',
        'PenjualanPembayaran',
        'PesananTertahan',
        'NomorUrutPenjualan',
        ...tabelVoidRetur,
      ]),
    );
    final indeks = await db
        .customSelect("SELECT name FROM sqlite_master WHERE type = 'index'")
        .map((r) => r.read<String>('name'))
        .get();
    expect(indeks, containsAll(['IndeksProdukBarcodeBarcode', 'IndeksPenjualanTanggalBisnis']));

    // Tabel baru bisa langsung ditulis.
    await db
        .into(db.nomorUrutPenjualan)
        .insert(NomorUrutPenjualanCompanion.insert(KodePerangkat: 'POS-001', Tanggal: '260924', Terakhir: 1));
    expect((await db.select(db.nomorUrutPenjualan).get()).single.Terakhir, 1);
  });

  test('basis data baru langsung skema terbaru (13)', () async {
    final db = BasisDataKasir(NativeDatabase.memory());
    addTearDown(db.close);
    expect(await db.select(db.penjualan).get(), isEmpty);
    expect(await db.select(db.returPenjualan).get(), isEmpty);
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
  });

  test('F-11 migrasi 2 → 3 hanya menambah kolom tutup shift; outbox tertunda, shift, & penjualan tetap utuh', () async {
    final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
    addTearDown(() => folder.deleteSync(recursive: true));
    final berkas = File('${folder.path}/kasir.sqlite');

    // Bangun skema 2: skema terbaru dikurangi kolom F-11, lalu isi data seperti perangkat lama.
    final lama = BasisDataKasir(NativeDatabase(berkas));
    await lama.customSelect('SELECT 1').get();
    // Skema ≤ 9 belum punya tabel absensi lokal (F-18).
    await HapusTabelAbsensi(lama);
    for (final kolom in kolomTutupShift) {
      await lama.customStatement('ALTER TABLE "Shift" DROP COLUMN "$kolom"');
    }
    await lama.customStatement('ALTER TABLE "KelompokPajakDetail" DROP COLUMN "Kategori"');
    await HapusTabelVoidRetur(lama);
    await lama.customStatement(
      "INSERT INTO Shift (Uuid, DibukaOleh, NamaKasir, DibukaPada, KasAwal, PecahanKasAwal, Bersama, Status) VALUES "
      "('SHIFT1', 'STAF1', 'Rina Wulandari', '2026-09-24T01:00:00.000Z', '500000.00', NULL, 0, 'Terbuka')",
    );
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('SHIFT1', 'Shift.Buka', '{\"KasAwal\":\"500000.00\"}', 'Tertunda', 2, '2026-09-24T01:00:00.000Z', '2026-09-24T01:00:00.000Z'),"
      "('JUAL1', 'Penjualan.Buat', '{}', 'PerluTindakan', 0, '2026-09-24T01:10:00.000Z', '2026-09-24T01:10:00.000Z')",
    );
    await HapusTabelDeposit(lama);
    await lama.customStatement('PRAGMA user_version = 2');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);

    final outbox = await (db.select(db.outbox)..orderBy([(o) => OrderingTerm.asc(o.Id)])).get();
    expect(outbox.map((o) => o.Uuid), ['SHIFT1', 'JUAL1'], reason: 'Outbox belum terkirim tidak boleh hilang.');
    expect(outbox.first.Percobaan, 2);
    expect(outbox.last.Status, 'PerluTindakan');
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);

    final shift = (await db.select(db.shift).get()).single;
    expect(shift.KasAwal, '500000.00');
    expect(shift.KasAktual, isNull);
    expect(shift.DitutupPada, isNull);

    // Kolom baru langsung bisa ditulis.
    await (db.update(db.shift)..where((s) => s.Uuid.equals('SHIFT1'))).write(
      const ShiftCompanion(Status: Value('Tertutup'), KasAktual: Value('498000.00'), Selisih: Value('-2000.00')),
    );
    expect((await db.select(db.shift).get()).single.Selisih, '-2000.00');
  });

  test('PRD v1.46 migrasi 3 → 4 hanya menambah kolom Kategori kelompok pajak; outbox & katalog lama utuh', () async {
    final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
    addTearDown(() => folder.deleteSync(recursive: true));
    final berkas = File('${folder.path}/kasir.sqlite');

    final lama = BasisDataKasir(NativeDatabase(berkas));
    await lama.customSelect('SELECT 1').get();
    // Skema ≤ 9 belum punya tabel absensi lokal (F-18).
    await HapusTabelAbsensi(lama);
    await lama.customStatement('ALTER TABLE "KelompokPajakDetail" DROP COLUMN "Kategori"');
    await HapusTabelVoidRetur(lama);
    await lama.customStatement(
      'INSERT INTO KelompokPajakDetail (UuidKelompokPajak, KodeJenisPajak, DasarPengenaan, Urutan) VALUES '
      "('KP1', 'Ppn', 'Subtotal', 1)",
    );
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('JUAL1', 'Penjualan.Buat', '{}', 'Tertunda', 1, '2026-09-24T01:10:00.000Z', '2026-09-24T01:10:00.000Z')",
    );
    await HapusTabelDeposit(lama);
    await lama.customStatement('PRAGMA user_version = 3');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);
    expect(
      (await db.select(db.outbox).get()).single.Uuid,
      'JUAL1',
      reason: 'Outbox belum terkirim tidak boleh hilang.',
    );
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
    final detail = (await db.select(db.kelompokPajakDetail).get()).single;
    expect(detail.KodeJenisPajak, 'Ppn');
    expect(detail.Kategori, isNull, reason: 'Baris lama tanpa kategori → fallback ke kode sampai katalog diperbarui.');

    await db.update(db.kelompokPajakDetail).write(const KelompokPajakDetailCompanion(Kategori: Value('Ppn')));
    expect((await db.select(db.kelompokPajakDetail).get()).single.Kategori, 'Ppn');
  });

  test('F-09 migrasi 4 → 5 hanya menambah tabel void & retur; outbox tertunda & penjualan lama utuh', () async {
    final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
    addTearDown(() => folder.deleteSync(recursive: true));
    final berkas = File('${folder.path}/kasir.sqlite');

    final lama = BasisDataKasir(NativeDatabase(berkas));
    await lama.customSelect('SELECT 1').get();
    // Skema ≤ 9 belum punya tabel absensi lokal (F-18).
    await HapusTabelAbsensi(lama);
    await HapusTabelVoidRetur(lama);
    await lama.customStatement(
      'INSERT INTO Penjualan (Uuid, Nomor, UuidShift, UuidPengguna, NamaKasir, Kanal, DibuatPada, TanggalBisnis, Status, '
      'Subtotal, TotalDiskon, BiayaLayanan, TotalPajak, Pembulatan, TotalAkhir, TotalDibayar, Kembalian) VALUES '
      "('JUAL1', 'INV/SLB/260924/POS-001-0001', 'SHIFT1', 'STAF1', 'Rina Wulandari', 'BawaPulang', "
      "'2026-09-24T01:10:00.000Z', '2026-09-24', 'Lunas', '61000.00', '0.00', '0.00', '6100.00', '0.00', '67100.00', "
      "'100000.00', '32900.00')",
    );
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('JUAL1', 'Penjualan.Buat', '{\"Nomor\":\"INV/SLB/260924/POS-001-0001\"}', 'Tertunda', 4, "
      "'2026-09-24T01:10:00.000Z', '2026-09-24T01:10:00.000Z'),"
      "('TUTUP1', 'Shift.Tutup', '{}', 'PerluTindakan', 0, '2026-09-24T09:00:00.000Z', '2026-09-24T09:00:00.000Z')",
    );
    await HapusTabelDeposit(lama);
    await lama.customStatement('PRAGMA user_version = 4');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);
    final outbox = await (db.select(db.outbox)..orderBy([(o) => OrderingTerm.asc(o.Id)])).get();
    expect(outbox.map((o) => o.Uuid), ['JUAL1', 'TUTUP1'], reason: 'Outbox belum terkirim tidak boleh hilang.');
    expect(outbox.first.Percobaan, 4);
    expect(outbox.first.Data, '{"Nomor":"INV/SLB/260924/POS-001-0001"}');
    expect(outbox.last.Status, 'PerluTindakan');
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
    expect((await db.select(db.penjualan).get()).single.TotalAkhir, '67100.00');

    // Tabel baru langsung bisa ditulis.
    await db
        .into(db.nomorUrutReturPenjualan)
        .insert(NomorUrutReturPenjualanCompanion.insert(KodePerangkat: 'POS-001', Tanggal: '260924', Terakhir: 1));
    await db
        .into(db.voidPenjualan)
        .insert(
          VoidPenjualanCompanion.insert(
            Uuid: 'VOID1',
            UuidPenjualan: 'JUAL1',
            UuidShift: 'SHIFT1',
            UuidPengguna: 'STAF1',
            NamaPengguna: 'Rina Wulandari',
            UuidPenyetuju: 'STAF2',
            NamaPenyetuju: 'Budi Santoso',
            Alasan: 'Salah input pesanan',
            DivoidPada: DateTime.utc(2026, 9, 24, 1, 15),
            Nominal: '67100.00',
            RefundTunai: '67100.00',
            RefundNonTunai: '0.00',
          ),
        );
    expect((await db.select(db.voidPenjualan).get()).single.RefundTunai, '67100.00');
    expect((await db.select(db.nomorUrutReturPenjualan).get()).single.Terakhir, 1);
  });

  test(
    'F-07 mode meja migrasi 5 → 6 hanya menambah tabel meja & pesanan terbuka; outbox tertunda & retur utuh',
    () async {
      final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
      addTearDown(() => folder.deleteSync(recursive: true));
      final berkas = File('${folder.path}/kasir.sqlite');

      final lama = BasisDataKasir(NativeDatabase(berkas));
      await lama.customSelect('SELECT 1').get();
      // Skema ≤ 9 belum punya tabel absensi lokal (F-18).
      await HapusTabelAbsensi(lama);
      await HapusTabelMeja(lama);
      await lama.customStatement(
        "INSERT INTO NomorUrutReturPenjualan (KodePerangkat, Tanggal, Terakhir) VALUES ('POS-001', '260924', 3)",
      );
      await lama.customStatement(
        'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
        "('RETUR1', 'ReturPenjualan.Buat', '{\"Nomor\":\"RJ/SLB/260924/POS-001-0003\"}', 'Tertunda', 2, "
        "'2026-09-24T02:00:00.000Z', '2026-09-24T02:00:00.000Z')",
      );
      await HapusTabelDeposit(lama);
      await lama.customStatement('PRAGMA user_version = 5');
      await lama.close();

      final db = BasisDataKasir(NativeDatabase(berkas));
      addTearDown(db.close);
      final outbox = await db.select(db.outbox).get();
      expect(outbox.single.Uuid, 'RETUR1', reason: 'Outbox belum terkirim tidak boleh hilang.');
      expect(outbox.single.Percobaan, 2);
      expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
      expect((await db.select(db.nomorUrutReturPenjualan).get()).single.Terakhir, 3);

      // Tabel baru langsung bisa ditulis.
      await db.into(db.areaMeja).insert(AreaMejaCompanion.insert(Uuid: 'AREA1', Nama: 'Teras'));
      await db.into(db.meja).insert(MejaCompanion.insert(Uuid: 'MEJA1', Nama: 'T-01', UuidArea: const Value('AREA1')));
      await db
          .into(db.pesananTerbuka)
          .insert(
            PesananTerbukaCompanion.insert(
              Uuid: 'PESAN1',
              Nomor: 'OB/SLB/260924/POS-001-0001',
              UuidMeja: const Value('MEJA1'),
              DibukaPada: DateTime.utc(2026, 9, 24, 3),
              Status: 'Terbuka',
              Baris: '[]',
              DiubahPada: DateTime.utc(2026, 9, 24, 3),
            ),
          );
      await db
          .into(db.nomorUrutPesananTerbuka)
          .insert(NomorUrutPesananTerbukaCompanion.insert(KodePerangkat: 'POS-001', Tanggal: '260924', Terakhir: 1));
      expect((await db.select(db.meja).get()).single.Kapasitas, 4);
      expect((await db.select(db.pesananTerbuka).get()).single.JumlahTamu, 1);
    },
  );

  test('F-16a migrasi 6 → 7 hanya menambah tabel pelanggan lokal; pesanan terbuka & outbox tertunda utuh', () async {
    final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
    addTearDown(() => folder.deleteSync(recursive: true));
    final berkas = File('${folder.path}/kasir.sqlite');

    final lama = BasisDataKasir(NativeDatabase(berkas));
    await lama.customSelect('SELECT 1').get();
    // Skema ≤ 9 belum punya tabel absensi lokal (F-18).
    await HapusTabelAbsensi(lama);
    await HapusTabelPelanggan(lama);
    await lama.customStatement(
      "INSERT INTO PesananTerbuka (Uuid, Nomor, DibukaPada, Status, Baris, DiubahPada) VALUES ('PESAN1', "
      "'OB/SLB/260925/POS-001-0001', '2026-09-25T03:00:00.000Z', 'Terbuka', '[]', '2026-09-25T03:00:00.000Z')",
    );
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('PESAN1', 'PesananTerbuka.Buka', '{}', 'Tertunda', 1, '2026-09-25T03:00:00.000Z', '2026-09-25T03:00:00.000Z')",
    );
    await HapusTabelDeposit(lama);
    await lama.customStatement('PRAGMA user_version = 6');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);
    expect((await db.select(db.outbox).get()).single.Jenis, 'PesananTerbuka.Buka');
    expect((await db.select(db.pesananTerbuka).get()).single.Nomor, 'OB/SLB/260925/POS-001-0001');
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
    await db
        .into(db.pelangganLokal)
        .insert(
          PelangganLokalCompanion.insert(
            Uuid: 'PLG1',
            Nama: 'Ani Rahmawati',
            NoHpSamar: '0812****7890',
            DipakaiPada: DateTime.utc(2026, 9, 25, 3),
          ),
        );
    expect((await db.select(db.pelangganLokal).get()).single.Nama, 'Ani Rahmawati');
  });

  test('F-16b migrasi 7 → 8 menambah kolom tier pelanggan lokal; pelanggan & outbox tertunda utuh', () async {
    final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
    addTearDown(() => folder.deleteSync(recursive: true));
    final berkas = File('${folder.path}/kasir.sqlite');

    final lama = BasisDataKasir(NativeDatabase(berkas));
    await lama.customSelect('SELECT 1').get();
    // Skema ≤ 9 belum punya tabel absensi lokal (F-18).
    await HapusTabelAbsensi(lama);
    // Skema 7 belum punya kolom tier (8) maupun kredit (9).
    for (final kolom in ['KodeTier', 'NamaTier', 'LimitKredit', 'SisaPiutang', 'HariLewatJatuhTempo']) {
      await lama.customStatement('ALTER TABLE PelangganLokal DROP COLUMN $kolom');
    }
    await lama.customStatement(
      "INSERT INTO PelangganLokal (Uuid, Nama, NoHpSamar, DipakaiPada) VALUES ('PLG1', 'Ani Rahmawati', "
      "'0812****7890', '2026-09-25T03:00:00.000Z')",
    );
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('PLG1', 'Pelanggan.Buat', '{}', 'Tertunda', 0, '2026-09-25T03:00:00.000Z', '2026-09-25T03:00:00.000Z')",
    );
    await HapusKolomPromoPelanggan(lama);
    await HapusTabelDeposit(lama);
    await lama.customStatement('PRAGMA user_version = 7');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
    expect((await db.select(db.outbox).get()).single.Jenis, 'Pelanggan.Buat');
    final pelanggan = (await db.select(db.pelangganLokal).get()).single;
    expect(pelanggan.Nama, 'Ani Rahmawati');
    expect(pelanggan.KodeTier, isNull);
    await db.update(db.pelangganLokal).write(const PelangganLokalCompanion(KodeTier: Value('GOLD')));
    expect((await db.select(db.pelangganLokal).get()).single.KodeTier, 'GOLD');
  });

  test('F-12 migrasi 8 → 9 menambah kolom kredit pelanggan lokal; tier & outbox tertunda utuh', () async {
    final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
    addTearDown(() => folder.deleteSync(recursive: true));
    final berkas = File('${folder.path}/kasir.sqlite');

    final lama = BasisDataKasir(NativeDatabase(berkas));
    await lama.customSelect('SELECT 1').get();
    // Skema ≤ 9 belum punya tabel absensi lokal (F-18).
    await HapusTabelAbsensi(lama);
    for (final kolom in ['LimitKredit', 'SisaPiutang', 'HariLewatJatuhTempo']) {
      await lama.customStatement('ALTER TABLE PelangganLokal DROP COLUMN $kolom');
    }
    await lama.customStatement(
      "INSERT INTO PelangganLokal (Uuid, Nama, NoHpSamar, DipakaiPada, KodeTier, NamaTier) VALUES ('PLG1', "
      "'Toko Makmur Jaya', '0813****0001', '2026-09-25T03:00:00.000Z', 'GOLD', 'Gold')",
    );
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('JUAL1', 'Penjualan.Buat', '{}', 'Tertunda', 0, '2026-09-25T03:00:00.000Z', '2026-09-25T03:00:00.000Z')",
    );
    await HapusKolomPromoPelanggan(lama);
    await HapusTabelDeposit(lama);
    await lama.customStatement('PRAGMA user_version = 8');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
    expect((await db.select(db.outbox).get()).single.Jenis, 'Penjualan.Buat');
    final pelanggan = (await db.select(db.pelangganLokal).get()).single;
    expect(pelanggan.KodeTier, 'GOLD');
    expect(pelanggan.SisaPiutang, isNull);
    await db
        .update(db.pelangganLokal)
        .write(const PelangganLokalCompanion(LimitKredit: Value('5000000.00'), SisaPiutang: Value('77000.00')));
    expect((await db.select(db.pelangganLokal).get()).single.LimitKredit, '5000000.00');
  });

  test('F-18 migrasi 9 → 10 menambah tabel absensi lokal; pelanggan & outbox tertunda utuh', () async {
    final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
    addTearDown(() => folder.deleteSync(recursive: true));
    final berkas = File('${folder.path}/kasir.sqlite');

    final lama = BasisDataKasir(NativeDatabase(berkas));
    await lama.customSelect('SELECT 1').get();
    await HapusTabelAbsensi(lama);
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('JUAL1', 'Penjualan.Buat', '{}', 'Tertunda', 0, '2026-09-25T03:00:00.000Z', '2026-09-25T03:00:00.000Z')",
    );
    await HapusKolomPromoPelanggan(lama);
    await HapusTabelDeposit(lama);
    await lama.customStatement('PRAGMA user_version = 9');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
    expect((await db.select(db.outbox).get()).single.Jenis, 'Penjualan.Buat');
    await db
        .into(db.absensiLokal)
        .insert(
          AbsensiLokalCompanion.insert(
            Uuid: 'ABS1',
            UuidPengguna: 'STAF1',
            NamaStaf: 'Rina Wulandari',
            MasukPada: DateTime.utc(2026, 9, 25, 1),
          ),
        );
    expect((await db.select(db.absensiLokal).get()).single.NamaStaf, 'Rina Wulandari');
  });

  test('F-12 bagian 2 migrasi 10 → 11 menambah tabel pre-order lokal; absensi & outbox tertunda utuh', () async {
    final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
    addTearDown(() => folder.deleteSync(recursive: true));
    final berkas = File('${folder.path}/kasir.sqlite');

    final lama = BasisDataKasir(NativeDatabase(berkas));
    await lama.customSelect('SELECT 1').get();
    await HapusTabelPreOrder(lama);
    await lama.customStatement(
      "INSERT INTO AbsensiLokal (Uuid, UuidPengguna, NamaStaf, MasukPada) VALUES ('ABS1', 'STAF1', 'Rina Wulandari', "
      "'2026-09-25T01:00:00.000Z')",
    );
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('JUAL1', 'Penjualan.Buat', '{}', 'Tertunda', 0, '2026-09-25T03:00:00.000Z', '2026-09-25T03:00:00.000Z')",
    );
    await HapusKolomPromoPelanggan(lama);
    await HapusTabelDeposit(lama);
    await lama.customStatement('PRAGMA user_version = 10');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
    expect((await db.select(db.outbox).get()).single.Jenis, 'Penjualan.Buat');
    expect((await db.select(db.absensiLokal).get()).single.NamaStaf, 'Rina Wulandari');
    await db
        .into(db.nomorUrutPesananPenjualan)
        .insert(NomorUrutPesananPenjualanCompanion.insert(KodePerangkat: 'POS-001', Tanggal: '260925', Terakhir: 1));
    expect((await db.select(db.nomorUrutPesananPenjualan).get()).single.Terakhir, 1);
  });

  test('F-16c bagian 3 migrasi 11 → 12 menambah data promo pelanggan; pelanggan & outbox tertunda utuh', () async {
    final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
    addTearDown(() => folder.deleteSync(recursive: true));
    final berkas = File('${folder.path}/kasir.sqlite');

    final lama = BasisDataKasir(NativeDatabase(berkas));
    await lama.customSelect('SELECT 1').get();
    await HapusKolomPromoPelanggan(lama);
    await lama.customStatement(
      "INSERT INTO PelangganLokal (Uuid, Nama, NoHpSamar, DipakaiPada, KodeTier) VALUES ('PLG1', 'Ani Rahmawati', "
      "'0812****7890', '2026-09-25T03:00:00.000Z', 'GOLD')",
    );
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('JUAL1', 'Penjualan.Buat', '{}', 'Tertunda', 0, '2026-09-25T03:00:00.000Z', '2026-09-25T03:00:00.000Z')",
    );
    await HapusTabelDeposit(lama);
    await lama.customStatement('PRAGMA user_version = 11');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
    expect((await db.select(db.outbox).get()).single.Jenis, 'Penjualan.Buat');
    final pelanggan = (await db.select(db.pelangganLokal).get()).single;
    expect(pelanggan.KodeTier, 'GOLD');
    expect(pelanggan.HariLahir, isNull);
    expect(pelanggan.JumlahTransaksi, isNull);
    await db
        .update(db.pelangganLokal)
        .write(const PelangganLokalCompanion(HariLahir: Value('09-26'), JumlahTransaksi: Value(3)));
    expect((await db.select(db.pelangganLokal).get()).single.HariLahir, '09-26');
  });

  test('F-16d bagian 1 migrasi 12 → 13 menambah tabel isi deposit lokal; pre-order & outbox tertunda utuh', () async {
    final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
    addTearDown(() => folder.deleteSync(recursive: true));
    final berkas = File('${folder.path}/kasir.sqlite');

    final lama = BasisDataKasir(NativeDatabase(berkas));
    await lama.customSelect('SELECT 1').get();
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('PO1', 'PesananPenjualan.Buat', '{}', 'Tertunda', 0, '2026-09-25T03:00:00.000Z', '2026-09-25T03:00:00.000Z')",
    );
    await HapusTabelDeposit(lama);
    await lama.customStatement('PRAGMA user_version = 12');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 13);
    expect((await db.select(db.outbox).get()).single.Jenis, 'PesananPenjualan.Buat');
    await db
        .into(db.isiDepositLokal)
        .insert(
          IsiDepositLokalCompanion.insert(
            Uuid: 'DEP1',
            UuidShift: 'SHIFT1',
            Nomor: 'DEP/SLB/260926/POS-001-0001',
            UuidPelanggan: 'PLG1',
            NamaPelanggan: 'Ani Rahmawati',
            Jumlah: '100000.00',
            UuidMetodePembayaran: 'MTD1',
            JenisMetode: 'Tunai',
            NamaMetode: 'Tunai',
            DibuatPada: DateTime.utc(2026, 9, 26, 3),
          ),
        );
    expect((await db.select(db.isiDepositLokal).get()).single.Jumlah, '100000.00');
    expect(await db.select(db.nomorUrutIsiDeposit).get(), isEmpty);
  });
}
