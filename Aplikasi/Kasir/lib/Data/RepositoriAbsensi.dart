import 'package:drift/drift.dart';
import 'package:klien_api/KlienApi.dart';

import 'BasisData/BasisDataKasir.dart';
import 'RepositoriKasir.dart';

/// Absensi staf di perangkat ini (F-18): baris lokal + item outbox dalam satu transaksi SQLite (§18.3 no. 3).
class RepositoriAbsensi {
  RepositoriAbsensi(this.db, this.repositoriKasir);

  final BasisDataKasir db;
  final RepositoriKasir repositoriKasir;

  /// Absensi terbuka (belum keluar) terakhir staf ini.
  Future<BarisAbsensiLokal?> AmbilTerbuka(String uuidPengguna) =>
      (db.select(db.absensiLokal)
            ..where((a) => a.UuidPengguna.equals(uuidPengguna) & a.KeluarPada.isNull())
            ..orderBy([(a) => OrderingTerm.desc(a.MasukPada)])
            ..limit(1))
          .getSingleOrNull();

  Future<void> SimpanMasuk(BarisAbsensiLokal baris, ItemOutbox outbox, DateTime sekarang) => db.transaction(() async {
    await db.into(db.absensiLokal).insert(baris);
    await repositoriKasir.TambahOutbox(outbox, sekarang);
  });

  Future<void> SimpanKeluar(String uuid, DateTime keluarPada, ItemOutbox outbox, DateTime sekarang) =>
      db.transaction(() async {
        await (db.update(
          db.absensiLokal,
        )..where((a) => a.Uuid.equals(uuid))).write(AbsensiLokalCompanion(KeluarPada: Value(keluarPada.toUtc())));
        await repositoriKasir.TambahOutbox(outbox, sekarang);
      });

  /// Absensi terbaru di perangkat (untuk layar absensi).
  Future<List<BarisAbsensiLokal>> AmbilTerbaru({int batas = 20}) =>
      (db.select(db.absensiLokal)
            ..orderBy([(a) => OrderingTerm.desc(a.MasukPada)])
            ..limit(batas))
          .get();
}
