import 'package:drift/drift.dart';
import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import 'BasisData/BasisDataKasir.dart';
import 'RepositoriKasir.dart';

/// Pelanggan lokal perangkat (F-16a): cache pelanggan yang pernah dipakai + pelanggan baru offline (bersama outbox
/// `Pelanggan.Buat` dalam satu transaksi SQLite).
class RepositoriPelanggan {
  RepositoriPelanggan(this.db, this.repositoriKasir);

  final BasisDataKasir db;
  final RepositoriKasir repositoriKasir;

  /// Catat/perbarui pelanggan yang dipakai (hasil cari online atau baru dibuat), termasuk tier (F-16b) dan posisi
  /// kredit (F-12; null = tidak diketahui, nilai lama di cache dipertahankan).
  Future<void> Simpan(
    String uuid,
    String nama,
    String noHpSamar,
    DateTime sekarang, {
    String? kodeTier,
    String? namaTier,
    ({String? limitKredit, String sisaPiutang, int hariLewatJatuhTempo})? kredit,
  }) => db
      .into(db.pelangganLokal)
      .insert(
        PelangganLokalCompanion.insert(
          Uuid: uuid,
          Nama: nama,
          NoHpSamar: noHpSamar,
          DipakaiPada: sekarang.toUtc(),
          KodeTier: Value(kodeTier),
          NamaTier: Value(namaTier),
          LimitKredit: kredit == null ? const Value.absent() : Value(kredit.limitKredit),
          SisaPiutang: kredit == null ? const Value.absent() : Value(kredit.sisaPiutang),
          HariLewatJatuhTempo: kredit == null ? const Value.absent() : Value(kredit.hariLewatJatuhTempo),
        ),
        onConflict: DoUpdate(
          (_) => PelangganLokalCompanion(
            Nama: Value(nama),
            NoHpSamar: Value(noHpSamar),
            DipakaiPada: Value(sekarang.toUtc()),
            KodeTier: Value(kodeTier),
            NamaTier: Value(namaTier),
            LimitKredit: kredit == null ? const Value.absent() : Value(kredit.limitKredit),
            SisaPiutang: kredit == null ? const Value.absent() : Value(kredit.sisaPiutang),
            HariLewatJatuhTempo: kredit == null ? const Value.absent() : Value(kredit.hariLewatJatuhTempo),
          ),
        ),
      );

  /// F-12: penjualan tempo tersimpan di perangkat menambah sisa piutang cache agar cek BR-12.1 berikutnya (offline)
  /// ikut menghitungnya. Pelanggan yang belum pernah diketahui kreditnya dibiarkan (server tetap memeriksa).
  Future<void> TambahSisaPiutang(String uuid, String jumlah) async {
    final baris = await (db.select(db.pelangganLokal)..where((p) => p.Uuid.equals(uuid))).getSingleOrNull();
    final sisa = baris?.SisaPiutang;
    if (sisa == null) {
      return;
    }
    await (db.update(db.pelangganLokal)..where((p) => p.Uuid.equals(uuid))).write(
      PelangganLokalCompanion(SisaPiutang: Value(Uang.Dari(sisa).Tambah(Uang.Dari(jumlah)).KeString())),
    );
  }

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
