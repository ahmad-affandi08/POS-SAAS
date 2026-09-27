import 'package:drift/drift.dart';
import 'package:klien_api/KlienApi.dart';

import 'BasisData/BasisDataKasir.dart';
import 'RepositoriKasir.dart';
import 'RepositoriPenjualan.dart';

/// Satu catatan bahan terbuang perangkat ini beserta status kirimnya (dari keberadaan item di outbox).
class RiwayatBahanTerbuang {
  const RiwayatBahanTerbuang({required this.baris, required this.status, this.pesanGalat});

  final BarisBahanTerbuangLokal baris;
  final StatusSinkronPenjualan status;
  final String? pesanGalat;
}

/// Pencatatan persediaan dari perangkat ini (F-05f bagian 2, skema lokal 17).
class RepositoriPersediaan {
  RepositoriPersediaan(this.db, this.repositoriKasir);

  /// Catatan lokal disimpan sebatas ini (yang lebih lama dihapus saat mencatat); dokumennya tetap di server.
  static const Duration masaSimpan = Duration(days: 30);

  final BasisDataKasir db;
  final RepositoriKasir repositoriKasir;

  /// Simpan baris lokal + item outbox dalam satu transaksi SQLite, lalu buang catatan lokal yang sudah lewat
  /// [masaSimpan] dan sudah terkirim (item outbox-nya tidak ada lagi).
  Future<void> SimpanBahanTerbuang(BahanTerbuangLokalCompanion baris, ItemOutbox item, DateTime sekarang) =>
      db.transaction(() async {
        await db.into(db.bahanTerbuangLokal).insert(baris);
        await repositoriKasir.TambahOutbox(item, sekarang);
        final batas = sekarang.toUtc().subtract(masaSimpan);
        await (db.delete(db.bahanTerbuangLokal)..where(
              (b) =>
                  b.DibuatPada.isSmallerThanValue(batas) &
                  notExistsQuery(db.select(db.outbox)..where((o) => o.Uuid.equalsExp(b.Uuid))),
            ))
            .go();
      });

  /// Catatan bahan terbuang tanggal bisnis [tanggalBisnis] (`YYYY-MM-DD`), terbaru dulu, dengan status kirim.
  Stream<List<RiwayatBahanTerbuang>> PantauBahanTerbuang(String tanggalBisnis) {
    final kueri =
        db.select(db.bahanTerbuangLokal).join([
            leftOuterJoin(db.outbox, db.outbox.Uuid.equalsExp(db.bahanTerbuangLokal.Uuid)),
          ])
          ..where(db.bahanTerbuangLokal.TanggalBisnis.equals(tanggalBisnis))
          ..orderBy([OrderingTerm.desc(db.bahanTerbuangLokal.DibuatPada)]);
    return kueri.watch().map(
      (baris) => [for (final b in baris) _KeRiwayat(b.readTable(db.bahanTerbuangLokal), b.readTableOrNull(db.outbox))],
    );
  }

  static RiwayatBahanTerbuang _KeRiwayat(BarisBahanTerbuangLokal baris, BarisOutbox? o) => RiwayatBahanTerbuang(
    baris: baris,
    status: o == null
        ? StatusSinkronPenjualan.Terkirim
        : o.Status == StatusOutbox.perluTindakan
        ? StatusSinkronPenjualan.PerluTindakan
        : StatusSinkronPenjualan.BelumTerkirim,
    pesanGalat: o?.PesanGalat,
  );
}
