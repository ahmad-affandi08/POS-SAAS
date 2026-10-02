import 'dart:convert';

import 'package:drift/drift.dart';
import 'package:klien_api/KlienApi.dart';

import 'BasisData/BasisDataKasir.dart';
import 'RepositoriKasir.dart';
import 'RepositoriPenjualan.dart';

/// Satu pesanan grosir dari perangkat ini beserta status kirimnya (dari keberadaan item di outbox).
class RiwayatPesananSalesman {
  const RiwayatPesananSalesman({required this.baris, required this.status, this.pesanGalat});

  final BarisPesananGrosirLokal baris;
  final StatusSinkronPenjualan status;
  final String? pesanGalat;
}

/// Satu kunjungan selesai dari perangkat ini beserta status kirimnya.
class RiwayatKunjunganSalesman {
  const RiwayatKunjunganSalesman({required this.baris, required this.status, this.pesanGalat});

  final BarisKunjunganSalesLokal baris;
  final StatusSinkronPenjualan status;
  final String? pesanGalat;
}

/// Data kerja salesman di perangkat (Modul Salesman bagian 2, skema lokal 26). Setiap dokumen yang dikirim ke server
/// ditulis bersama item outbox-nya dalam satu transaksi SQLite (PRD §18 no. 3).
class RepositoriSalesman {
  RepositoriSalesman(this.db, this.repositoriKasir);

  /// Kunjungan & pesanan lokal yang sudah terkirim disimpan sebatas ini (yang lebih lama dibuang saat mencatat).
  static const Duration masaSimpan = Duration(days: 30);

  final BasisDataKasir db;
  final RepositoriKasir repositoriKasir;

  // Pelanggan ------------------------------------------------------------------------------------------------------

  /// Ganti seluruh cache pelanggan dengan hasil unduhan lengkap terbaru, satu transaksi (gagal di tengah = cache lama
  /// tetap utuh).
  Future<void> GantiPelanggan(List<PelangganSalesmanPos> daftar, DateTime sekarang) => db.transaction(() async {
    await db.delete(db.pelangganSalesmanLokal).go();
    await db.batch(
      (b) => b.insertAllOnConflictUpdate(db.pelangganSalesmanLokal, [
        for (final p in daftar)
          if (p.uuid.isNotEmpty)
            PelangganSalesmanLokalCompanion.insert(
              Uuid: p.uuid,
              Nama: p.nama,
              NoHp: Value(p.noHp),
              Alamat: Value(p.alamat),
              KodeTier: Value(p.kodeTier),
              NamaTier: Value(p.namaTier),
              LimitKredit: Value(p.limitKredit?.KeString()),
              TerminHari: Value(p.terminHari),
              SisaPiutang: p.sisaPiutang.KeString(),
              JumlahPiutangJatuhTempo: p.jumlahPiutangJatuhTempo.KeString(),
              HariLewatJatuhTempo: Value(p.hariLewatJatuhTempo),
              TerakhirDikunjungiPada: Value(p.terakhirDikunjungiPada?.toUtc()),
            ),
      ]),
    );
    await repositoriKasir.SimpanPengaturan(
      KunciPengaturan.pelangganSalesmanDiperbaruiPada,
      sekarang.toUtc().toIso8601String(),
    );
  });

  Stream<List<BarisPelangganSalesmanLokal>> PantauPelanggan() =>
      (db.select(db.pelangganSalesmanLokal)..orderBy([(p) => OrderingTerm.asc(p.Nama)])).watch();

  Future<BarisPelangganSalesmanLokal?> CariPelanggan(String uuid) =>
      (db.select(db.pelangganSalesmanLokal)..where((p) => p.Uuid.equals(uuid))).getSingleOrNull();

  // Stok -----------------------------------------------------------------------------------------------------------

  Future<void> SimpanStok(StokSalesmanPos stok, DateTime sekarang) => repositoriKasir.SimpanPengaturan(
    KunciPengaturan.stokSalesman,
    jsonEncode({
      'DiambilPada': (stok.diambilPada ?? sekarang).toUtc().toIso8601String(),
      'Stok': {for (final e in stok.stok.entries) e.key: e.value.KeString()},
    }),
  );

  // Kunjungan ------------------------------------------------------------------------------------------------------

  /// Kunjungan [uuidPengguna] yang sedang berjalan (paling banyak satu).
  Stream<BarisKunjunganSalesLokal?> PantauKunjunganBerjalan(String uuidPengguna) =>
      (db.select(db.kunjunganSalesLokal)
            ..where((k) => k.UuidPengguna.equals(uuidPengguna) & k.KeluarPada.isNull())
            ..orderBy([(k) => OrderingTerm.desc(k.MasukPada)])
            ..limit(1))
          .watchSingleOrNull();

  Future<BarisKunjunganSalesLokal?> AmbilKunjunganBerjalan(String uuidPengguna) =>
      (db.select(db.kunjunganSalesLokal)
            ..where((k) => k.UuidPengguna.equals(uuidPengguna) & k.KeluarPada.isNull())
            ..orderBy([(k) => OrderingTerm.desc(k.MasukPada)])
            ..limit(1))
          .getSingleOrNull();

  Future<BarisKunjunganSalesLokal?> CariKunjungan(String uuid) =>
      (db.select(db.kunjunganSalesLokal)..where((k) => k.Uuid.equals(uuid))).getSingleOrNull();

  /// Catat kunjungan baru yang berjalan. Ditolak (StateError) bila [uuidPengguna] masih punya kunjungan berjalan.
  Future<void> MulaiKunjungan(KunjunganSalesLokalCompanion kunjungan, String uuidPengguna) => db.transaction(() async {
    if (await AmbilKunjunganBerjalan(uuidPengguna) != null) {
      throw StateError('Masih ada kunjungan berjalan.');
    }
    await db.into(db.kunjunganSalesLokal).insert(kunjungan);
  });

  /// Isi lokasi kunjungan berjalan setelah pembacaan lokasi selesai (tanpa outbox; dikirim saat kunjungan selesai).
  Future<void> SimpanLokasiKunjungan(String uuid, {String? latitude, String? longitude, int? akurasiMeter}) =>
      (db.update(db.kunjunganSalesLokal)..where((k) => k.Uuid.equals(uuid) & k.KeluarPada.isNull())).write(
        KunjunganSalesLokalCompanion(
          Latitude: Value(latitude),
          Longitude: Value(longitude),
          AkurasiMeter: Value(akurasiMeter),
        ),
      );

  /// Selesaikan kunjungan berjalan [uuid] + item outbox `Kunjungan.Catat` dalam satu transaksi, lalu buang catatan lokal
  /// lama yang sudah terkirim. Kunjungan yang sudah selesai = StateError (tidak ada item ganda).
  Future<void> SelesaikanKunjungan(
    String uuid,
    KunjunganSalesLokalCompanion selesai,
    ItemOutbox item,
    DateTime sekarang,
  ) => db.transaction(() async {
    final diubah = await (db.update(
      db.kunjunganSalesLokal,
    )..where((k) => k.Uuid.equals(uuid) & k.KeluarPada.isNull())).write(selesai);
    if (diubah != 1) {
      throw StateError('Kunjungan $uuid tidak sedang berjalan.');
    }
    await repositoriKasir.TambahOutbox(item, sekarang);
    await _BuangLama(sekarang);
  });

  // Pesanan --------------------------------------------------------------------------------------------------------

  /// Simpan pesanan + item outbox `PesananGrosir.Buat` dalam satu transaksi. Bila [uuidKunjungan] diisi dan
  /// kunjungannya masih berjalan tanpa pesanan, pesanan ini dicatat sebagai pesanan kunjungan itu.
  Future<void> SimpanPesanan(
    PesananGrosirLokalCompanion pesanan,
    ItemOutbox item,
    DateTime sekarang, {
    String? uuidKunjungan,
  }) => db.transaction(() async {
    await db.into(db.pesananGrosirLokal).insert(pesanan);
    await repositoriKasir.TambahOutbox(item, sekarang);
    if (uuidKunjungan != null) {
      await (db.update(db.kunjunganSalesLokal)
            ..where((k) => k.Uuid.equals(uuidKunjungan) & k.KeluarPada.isNull() & k.UuidPesananGrosir.isNull()))
          .write(KunjunganSalesLokalCompanion(UuidPesananGrosir: Value(pesanan.Uuid.value)));
    }
    await _BuangLama(sekarang);
  });

  // Riwayat --------------------------------------------------------------------------------------------------------

  /// Pesanan [uuidPengguna] sejak [sejak] (UTC), terbaru dulu, dengan status kirim.
  Stream<List<RiwayatPesananSalesman>> PantauPesanan(String uuidPengguna, DateTime sejak) {
    final kueri =
        db.select(db.pesananGrosirLokal).join([
            leftOuterJoin(db.outbox, db.outbox.Uuid.equalsExp(db.pesananGrosirLokal.Uuid)),
          ])
          ..where(
            db.pesananGrosirLokal.UuidPengguna.equals(uuidPengguna) &
                db.pesananGrosirLokal.DibuatPada.isBiggerOrEqualValue(sejak.toUtc()),
          )
          ..orderBy([OrderingTerm.desc(db.pesananGrosirLokal.DibuatPada)]);
    return kueri.watch().map(
      (baris) => [
        for (final b in baris)
          switch (_AmbilStatus(b.readTableOrNull(db.outbox))) {
            final s => RiwayatPesananSalesman(
              baris: b.readTable(db.pesananGrosirLokal),
              status: s.status,
              pesanGalat: s.pesan,
            ),
          },
      ],
    );
  }

  /// Kunjungan selesai [uuidPengguna] sejak [sejak] (UTC), terbaru dulu, dengan status kirim.
  Stream<List<RiwayatKunjunganSalesman>> PantauKunjunganSelesai(String uuidPengguna, DateTime sejak) {
    final kueri =
        db.select(db.kunjunganSalesLokal).join([
            leftOuterJoin(db.outbox, db.outbox.Uuid.equalsExp(db.kunjunganSalesLokal.Uuid)),
          ])
          ..where(
            db.kunjunganSalesLokal.UuidPengguna.equals(uuidPengguna) &
                db.kunjunganSalesLokal.KeluarPada.isNotNull() &
                db.kunjunganSalesLokal.MasukPada.isBiggerOrEqualValue(sejak.toUtc()),
          )
          ..orderBy([OrderingTerm.desc(db.kunjunganSalesLokal.MasukPada)]);
    return kueri.watch().map(
      (baris) => [
        for (final b in baris)
          switch (_AmbilStatus(b.readTableOrNull(db.outbox))) {
            final s => RiwayatKunjunganSalesman(
              baris: b.readTable(db.kunjunganSalesLokal),
              status: s.status,
              pesanGalat: s.pesan,
            ),
          },
      ],
    );
  }

  /// Waktu kunjungan terakhir per pelanggan dari perangkat ini (melengkapi `TerakhirDikunjungiPada` server yang baru
  /// diperbarui saat cache diunduh ulang).
  Stream<Map<String, DateTime>> PantauKunjunganTerakhir() {
    final terakhir = db.kunjunganSalesLokal.MasukPada.max();
    return (db.selectOnly(db.kunjunganSalesLokal)
          ..addColumns([db.kunjunganSalesLokal.UuidPelanggan, terakhir])
          ..groupBy([db.kunjunganSalesLokal.UuidPelanggan]))
        .watch()
        .map(
          (baris) => {
            for (final b in baris)
              if (b.read(terakhir) case final waktu?) b.read(db.kunjunganSalesLokal.UuidPelanggan)!: waktu.toUtc(),
          },
        );
  }

  static ({StatusSinkronPenjualan status, String? pesan}) _AmbilStatus(BarisOutbox? o) => (
    status: o == null
        ? StatusSinkronPenjualan.Terkirim
        : o.Status == StatusOutbox.perluTindakan
        ? StatusSinkronPenjualan.PerluTindakan
        : StatusSinkronPenjualan.BelumTerkirim,
    pesan: o?.PesanGalat,
  );

  /// Buang kunjungan selesai & pesanan lokal yang lebih tua dari [masaSimpan] dan sudah terkirim (item outbox-nya tidak
  /// ada lagi). Kunjungan berjalan dan yang belum terkirim tidak pernah dibuang.
  Future<void> _BuangLama(DateTime sekarang) async {
    final batas = sekarang.toUtc().subtract(masaSimpan);
    await (db.delete(db.pesananGrosirLokal)..where(
          (p) =>
              p.DibuatPada.isSmallerThanValue(batas) &
              notExistsQuery(db.select(db.outbox)..where((o) => o.Uuid.equalsExp(p.Uuid))),
        ))
        .go();
    await (db.delete(db.kunjunganSalesLokal)..where(
          (k) =>
              k.KeluarPada.isNotNull() &
              k.MasukPada.isSmallerThanValue(batas) &
              notExistsQuery(db.select(db.outbox)..where((o) => o.Uuid.equalsExp(k.Uuid))),
        ))
        .go();
  }
}
