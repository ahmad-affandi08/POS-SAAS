import 'package:drift/drift.dart';
import 'package:klien_api/KlienApi.dart';

import 'BasisData/BasisDataKasir.dart';
import 'RepositoriKasir.dart';

/// Pelanggan lokal perangkat (F-16a): cache pelanggan yang pernah dipakai + pelanggan baru offline (bersama outbox
/// `Pelanggan.Buat` dalam satu transaksi SQLite).
class RepositoriPelanggan {
  RepositoriPelanggan(this.db, this.repositoriKasir);

  final BasisDataKasir db;
  final RepositoriKasir repositoriKasir;

  /// Catat/perbarui pelanggan yang dipakai (hasil cari online atau baru dibuat), termasuk tier (F-16b).
  Future<void> Simpan(
    String uuid,
    String nama,
    String noHpSamar,
    DateTime sekarang, {
    String? kodeTier,
    String? namaTier,
  }) => db
      .into(db.pelangganLokal)
      .insertOnConflictUpdate(
        PelangganLokalCompanion.insert(
          Uuid: uuid,
          Nama: nama,
          NoHpSamar: noHpSamar,
          DipakaiPada: sekarang.toUtc(),
          KodeTier: Value(kodeTier),
          NamaTier: Value(namaTier),
        ),
      );

  Future<void> SimpanBaru(String uuid, String nama, String noHpSamar, ItemOutbox outbox, DateTime sekarang) =>
      db.transaction(() async {
        await Simpan(uuid, nama, noHpSamar, sekarang);
        await repositoriKasir.TambahOutbox(outbox, sekarang);
      });

  /// Pelanggan terakhir dipakai (terbaru dulu).
  Future<List<BarisPelangganLokal>> AmbilTerakhir({int batas = 10}) =>
      (db.select(db.pelangganLokal)
            ..orderBy([(p) => OrderingTerm.desc(p.DipakaiPada)])
            ..limit(batas))
          .get();

  /// Cari lokal (offline): nama mengandung [kata], atau 4 digit terakhir nomor HP.
  Future<List<BarisPelangganLokal>> Cari(String kata, {int batas = 20}) {
    final rapi = kata.trim();
    final angka = rapi.replaceAll(RegExp(r'\D'), '');
    return (db.select(db.pelangganLokal)
          ..where(
            (p) =>
                p.Nama.lower().like('%${rapi.toLowerCase()}%') |
                (angka.length >= 3
                    ? p.NoHpSamar.like('%${angka.length > 4 ? angka.substring(angka.length - 4) : angka}%')
                    : const Constant(false)),
          )
          ..orderBy([(p) => OrderingTerm.asc(p.Nama)])
          ..limit(batas))
        .get();
  }
}
