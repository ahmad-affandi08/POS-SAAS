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

    // Migrasi berantai sampai skema terbaru (4, kategori jenis pajak PRD v1.46).
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 4);
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

  test('basis data baru langsung skema terbaru (4)', () async {
    final db = BasisDataKasir(NativeDatabase.memory());
    addTearDown(db.close);
    expect(await db.select(db.penjualan).get(), isEmpty);
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 4);
  });

  test('F-11 migrasi 2 → 3 hanya menambah kolom tutup shift; outbox tertunda, shift, & penjualan tetap utuh', () async {
    final folder = Directory.systemTemp.createTempSync('migrasi_kasir_');
    addTearDown(() => folder.deleteSync(recursive: true));
    final berkas = File('${folder.path}/kasir.sqlite');

    // Bangun skema 2: skema terbaru dikurangi kolom F-11, lalu isi data seperti perangkat lama.
    final lama = BasisDataKasir(NativeDatabase(berkas));
    await lama.customSelect('SELECT 1').get();
    for (final kolom in kolomTutupShift) {
      await lama.customStatement('ALTER TABLE "Shift" DROP COLUMN "$kolom"');
    }
    await lama.customStatement('ALTER TABLE "KelompokPajakDetail" DROP COLUMN "Kategori"');
    await lama.customStatement(
      "INSERT INTO Shift (Uuid, DibukaOleh, NamaKasir, DibukaPada, KasAwal, PecahanKasAwal, Bersama, Status) VALUES "
      "('SHIFT1', 'STAF1', 'Rina Wulandari', '2026-09-24T01:00:00.000Z', '500000.00', NULL, 0, 'Terbuka')",
    );
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('SHIFT1', 'Shift.Buka', '{\"KasAwal\":\"500000.00\"}', 'Tertunda', 2, '2026-09-24T01:00:00.000Z', '2026-09-24T01:00:00.000Z'),"
      "('JUAL1', 'Penjualan.Buat', '{}', 'PerluTindakan', 0, '2026-09-24T01:10:00.000Z', '2026-09-24T01:10:00.000Z')",
    );
    await lama.customStatement('PRAGMA user_version = 2');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);

    final outbox = await (db.select(db.outbox)..orderBy([(o) => OrderingTerm.asc(o.Id)])).get();
    expect(outbox.map((o) => o.Uuid), ['SHIFT1', 'JUAL1'], reason: 'Outbox belum terkirim tidak boleh hilang.');
    expect(outbox.first.Percobaan, 2);
    expect(outbox.last.Status, 'PerluTindakan');
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 4);

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
    await lama.customStatement('ALTER TABLE "KelompokPajakDetail" DROP COLUMN "Kategori"');
    await lama.customStatement(
      'INSERT INTO KelompokPajakDetail (UuidKelompokPajak, KodeJenisPajak, DasarPengenaan, Urutan) VALUES '
      "('KP1', 'Ppn', 'Subtotal', 1)",
    );
    await lama.customStatement(
      'INSERT INTO Outbox (Uuid, Jenis, Data, Status, Percobaan, DibuatPada, BerikutnyaPada) VALUES '
      "('JUAL1', 'Penjualan.Buat', '{}', 'Tertunda', 1, '2026-09-24T01:10:00.000Z', '2026-09-24T01:10:00.000Z')",
    );
    await lama.customStatement('PRAGMA user_version = 3');
    await lama.close();

    final db = BasisDataKasir(NativeDatabase(berkas));
    addTearDown(db.close);
    expect(
      (await db.select(db.outbox).get()).single.Uuid,
      'JUAL1',
      reason: 'Outbox belum terkirim tidak boleh hilang.',
    );
    expect(await db.customSelect('PRAGMA user_version').map((r) => r.read<int>('user_version')).getSingle(), 4);
    final detail = (await db.select(db.kelompokPajakDetail).get()).single;
    expect(detail.KodeJenisPajak, 'Ppn');
    expect(detail.Kategori, isNull, reason: 'Baris lama tanpa kategori → fallback ke kode sampai katalog diperbarui.');

    await db.update(db.kelompokPajakDetail).write(const KelompokPajakDetailCompanion(Kategori: Value('Ppn')));
    expect((await db.select(db.kelompokPajakDetail).get()).single.Kategori, 'Ppn');
  });
}
