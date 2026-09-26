import 'package:drift/drift.dart';
import 'package:klien_api/KlienApi.dart';

import 'BasisData/BasisDataKasir.dart';
import 'RepositoriKasir.dart';

/// Isi deposit pelanggan yang dibuat perangkat ini (F-16d bagian 1, skema lokal 13).
class RepositoriDeposit {
  RepositoriDeposit(this.db, this.repositoriKasir);

  final BasisDataKasir db;
  final RepositoriKasir repositoriKasir;

  /// Ambil nomor urut berikutnya ([tanggal] `YYMMDD`), susun isi deposit dengan nomor itu, lalu simpan baris lokal +
  /// outbox dalam satu transaksi SQLite.
  Future<IsiDepositLokalCompanion> Simpan({
    required String kodePerangkat,
    required String tanggal,
    required DateTime sekarang,
    required ({IsiDepositLokalCompanion isi, ItemOutbox outbox}) Function(int urut) susun,
  }) => db.transaction(() async {
    final lama = await (db.select(
      db.nomorUrutIsiDeposit,
    )..where((n) => n.KodePerangkat.equals(kodePerangkat) & n.Tanggal.equals(tanggal))).getSingleOrNull();
    final urut = (lama?.Terakhir ?? 0) + 1;
    await db
        .into(db.nomorUrutIsiDeposit)
        .insertOnConflictUpdate(
          NomorUrutIsiDepositCompanion.insert(KodePerangkat: kodePerangkat, Tanggal: tanggal, Terakhir: urut),
        );
    final hasil = susun(urut);
    await db.into(db.isiDepositLokal).insert(hasil.isi);
    await repositoriKasir.TambahOutbox(hasil.outbox, sekarang);
    return hasil.isi;
  });

  /// Isi deposit yang diterima di shift [uuidShift] (kas shift).
  Future<List<BarisIsiDepositLokal>> AmbilShift(String uuidShift) =>
      (db.select(db.isiDepositLokal)
            ..where((i) => i.UuidShift.equals(uuidShift))
            ..orderBy([(i) => OrderingTerm.asc(i.DibuatPada)]))
          .get();
}
