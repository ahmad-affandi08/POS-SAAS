import 'package:drift/drift.dart';
import 'package:klien_api/KlienApi.dart';

import 'BasisData/BasisDataKasir.dart';
import 'RepositoriKasir.dart';

/// Pre-order + uang muka yang dibuat perangkat ini (F-12 bagian 2, skema lokal 11).
class RepositoriPreOrder {
  RepositoriPreOrder(this.db, this.repositoriKasir);

  final BasisDataKasir db;
  final RepositoriKasir repositoriKasir;

  /// Ambil nomor urut berikutnya ([tanggal] `YYMMDD`), susun pesanan dengan nomor itu, lalu simpan baris lokal + outbox
  /// dalam satu transaksi SQLite.
  Future<PesananPenjualanLokalCompanion> Simpan({
    required String kodePerangkat,
    required String tanggal,
    required DateTime sekarang,
    required ({PesananPenjualanLokalCompanion pesanan, ItemOutbox outbox}) Function(int urut) susun,
  }) => db.transaction(() async {
    final lama = await (db.select(
      db.nomorUrutPesananPenjualan,
    )..where((n) => n.KodePerangkat.equals(kodePerangkat) & n.Tanggal.equals(tanggal))).getSingleOrNull();
    final urut = (lama?.Terakhir ?? 0) + 1;
    await db
        .into(db.nomorUrutPesananPenjualan)
        .insertOnConflictUpdate(
          NomorUrutPesananPenjualanCompanion.insert(KodePerangkat: kodePerangkat, Tanggal: tanggal, Terakhir: urut),
        );
    final hasil = susun(urut);
    await db.into(db.pesananPenjualanLokal).insert(hasil.pesanan);
    await repositoriKasir.TambahOutbox(hasil.outbox, sekarang);
    return hasil.pesanan;
  });

  /// Pre-order yang uang mukanya diterima di shift [uuidShift] (kas shift).
  Future<List<BarisPesananPenjualanLokal>> AmbilShift(String uuidShift) =>
      (db.select(db.pesananPenjualanLokal)
            ..where((p) => p.UuidShift.equals(uuidShift))
            ..orderBy([(p) => OrderingTerm.asc(p.DibuatPada)]))
          .get();
}
