import 'package:drift/drift.dart';
import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import 'BasisData/BasisDataKasir.dart';
import 'RepositoriKasir.dart';

/// Dokumen penjualan siap simpan: header, detail, pembayaran, dan item outbox `Penjualan.Buat`.
class DokumenPenjualan {
  const DokumenPenjualan({
    required this.penjualan,
    required this.detail,
    required this.pembayaran,
    required this.outbox,
  });

  final PenjualanCompanion penjualan;
  final List<PenjualanDetailCompanion> detail;
  final List<PenjualanPembayaranCompanion> pembayaran;
  final ItemOutbox outbox;
}

/// Status sinkron satu penjualan, diturunkan dari outbox.
enum StatusSinkronPenjualan { Terkirim, BelumTerkirim, PerluTindakan }

/// Baris riwayat transaksi perangkat.
class RiwayatPenjualan {
  const RiwayatPenjualan({required this.penjualan, required this.status, required this.metode, this.pesanGalat});

  final BarisPenjualan penjualan;
  final StatusSinkronPenjualan status;

  /// Nama metode pembayaran (urut simpan).
  final List<String> metode;
  final String? pesanGalat;
}

/// Penyimpanan penjualan lokal (Rincian F-07c). Nomor urut, penjualan, detail, pembayaran, dan outbox ditulis dalam
/// satu transaksi SQLite (PRD §18.3 no. 3): bila salah satu gagal, tidak ada yang tersimpan dan nomor tidak terpakai.
class RepositoriPenjualan {
  RepositoriPenjualan(this.db, this.repositoriKasir);

  final BasisDataKasir db;
  final RepositoriKasir repositoriKasir;

  /// Ambil nomor urut berikutnya ([tanggal] `YYMMDD`), susun dokumen dengan nomor itu, lalu simpan semuanya.
  Future<DokumenPenjualan> SimpanPenjualan({
    required String kodePerangkat,
    required String tanggal,
    required DateTime sekarang,
    required DokumenPenjualan Function(int urut) susun,
  }) => db.transaction(() async {
    final lama = await (db.select(
      db.nomorUrutPenjualan,
    )..where((n) => n.KodePerangkat.equals(kodePerangkat) & n.Tanggal.equals(tanggal))).getSingleOrNull();
    final urut = (lama?.Terakhir ?? 0) + 1;
    await db
        .into(db.nomorUrutPenjualan)
        .insertOnConflictUpdate(
          NomorUrutPenjualanCompanion.insert(KodePerangkat: kodePerangkat, Tanggal: tanggal, Terakhir: urut),
        );

    final dokumen = susun(urut);
    await db.into(db.penjualan).insert(dokumen.penjualan);
    await db.batch((b) {
      b.insertAll(db.penjualanDetail, dokumen.detail);
      b.insertAll(db.penjualanPembayaran, dokumen.pembayaran);
    });
    await repositoriKasir.TambahOutbox(dokumen.outbox, sekarang);
    return dokumen;
  });

  Future<BarisPenjualan?> CariPenjualan(String uuid) =>
      (db.select(db.penjualan)..where((p) => p.Uuid.equals(uuid))).getSingleOrNull();

  Future<List<BarisPenjualanDetail>> AmbilDetail(String uuidPenjualan) =>
      (db.select(db.penjualanDetail)
            ..where((d) => d.UuidPenjualan.equals(uuidPenjualan))
            ..orderBy([(d) => OrderingTerm.asc(d.Urutan)]))
          .get();

  Future<List<BarisPenjualanPembayaran>> AmbilPembayaran(String uuidPenjualan) =>
      (db.select(db.penjualanPembayaran)..where((p) => p.UuidPenjualan.equals(uuidPenjualan))).get();

  /// Riwayat penjualan perangkat pada [tanggalBisnis] (`YYYY-MM-DD`), terbaru dulu, dengan status sinkron.
  Stream<List<RiwayatPenjualan>> PantauRiwayat(String tanggalBisnis) {
    final kueri = db.select(db.penjualan).join([leftOuterJoin(db.outbox, db.outbox.Uuid.equalsExp(db.penjualan.Uuid))])
      ..where(db.penjualan.TanggalBisnis.equals(tanggalBisnis))
      ..orderBy([OrderingTerm.desc(db.penjualan.DibuatPada)]);
    return kueri.watch().asyncMap((baris) async {
      final uuid = baris.map((b) => b.readTable(db.penjualan).Uuid).toList();
      final pembayaran = uuid.isEmpty
          ? const <BarisPenjualanPembayaran>[]
          : await (db.select(db.penjualanPembayaran)..where((p) => p.UuidPenjualan.isIn(uuid))).get();
      return [
        for (final b in baris)
          () {
            final p = b.readTable(db.penjualan);
            final o = b.readTableOrNull(db.outbox);
            return RiwayatPenjualan(
              penjualan: p,
              status: o == null
                  ? StatusSinkronPenjualan.Terkirim
                  : o.Status == StatusOutbox.perluTindakan
                  ? StatusSinkronPenjualan.PerluTindakan
                  : StatusSinkronPenjualan.BelumTerkirim,
              metode: pembayaran.where((x) => x.UuidPenjualan == p.Uuid).map((x) => x.NamaMetode).toList(),
              pesanGalat: o?.PesanGalat,
            );
          }(),
      ];
    });
  }

  /// Tunai bersih penjualan sebuah shift: Σ pembayaran tunai − Σ kembalian (kas di laci, F-06).
  Stream<Uang> PantauTunaiBersihShift(String uuidShift) {
    final kueri = db.select(db.penjualan)..where((p) => p.UuidShift.equals(uuidShift));
    return kueri.watch().asyncMap((daftar) async {
      if (daftar.isEmpty) {
        return Uang.Nol();
      }
      final tunai = await (db.select(
        db.penjualanPembayaran,
      )..where((b) => b.UuidPenjualan.isIn(daftar.map((p) => p.Uuid)) & b.Jenis.equals('Tunai'))).get();
      final diterima = tunai.fold(Uang.Nol(), (total, b) => total.Tambah(Uang.Dari(b.Jumlah)));
      return daftar.fold<Uang>(diterima, (total, p) => total.Kurangi(Uang.Dari(p.Kembalian)));
    });
  }

  // Pesanan tertahan (lokal, tidak dikirim) ----------------------------------------------------------------------------

  Future<void> SimpanPesananTertahan(PesananTertahanCompanion pesanan) =>
      db.into(db.pesananTertahan).insertOnConflictUpdate(pesanan);

  Stream<List<BarisPesananTertahan>> PantauPesananTertahan() =>
      (db.select(db.pesananTertahan)..orderBy([(p) => OrderingTerm.asc(p.DibuatPada)])).watch();

  Future<BarisPesananTertahan?> CariPesananTertahan(String uuid) =>
      (db.select(db.pesananTertahan)..where((p) => p.Uuid.equals(uuid))).getSingleOrNull();

  Future<void> HapusPesananTertahan(String uuid) =>
      (db.delete(db.pesananTertahan)..where((p) => p.Uuid.equals(uuid))).go();
}
