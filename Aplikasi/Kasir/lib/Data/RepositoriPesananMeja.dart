import 'dart:convert';

import 'package:drift/drift.dart';
import 'package:klien_api/KlienApi.dart';

import 'BasisData/BasisDataKasir.dart';
import 'PesananMeja.dart';
import 'RepositoriKasir.dart';

/// Penyimpanan meja & pesanan terbuka lokal (F-07 mode meja fase 1). Setiap perubahan pesanan menulis baris lokal +
/// entri outbox `PesananTerbuka.*` dalam satu transaksi SQLite (PRD §18.3 no. 3). Snapshot server menimpa salinan
/// lokal, kecuali pesanan yang masih punya perubahan tertunda di outbox (perubahan perangkat ini belum sampai server).
class RepositoriPesananMeja {
  RepositoriPesananMeja(this.db, this.repositoriKasir);

  final BasisDataKasir db;
  final RepositoriKasir repositoriKasir;

  // Meja -------------------------------------------------------------------------------------------------------------

  /// Ganti area & meja dengan data server terbaru dan simpan status mode meja outlet.
  Future<void> SimpanDataMeja(DataMejaPos data) => db.transaction(() async {
    await db.delete(db.areaMeja).go();
    await db.delete(db.meja).go();
    await db.batch((b) {
      b.insertAll(db.areaMeja, [
        for (final a in data.area) AreaMejaCompanion.insert(Uuid: a.uuid, Nama: a.nama, Urutan: Value(a.urutan)),
      ]);
      b.insertAll(db.meja, [
        for (final m in data.meja)
          MejaCompanion.insert(
            Uuid: m.uuid,
            Nama: m.nama,
            UuidArea: Value(m.uuidArea),
            Kapasitas: Value(m.kapasitas),
            Bentuk: Value(m.bentuk),
            Urutan: Value(m.urutan),
          ),
      ]);
    });
    await repositoriKasir.SimpanPengaturan(KunciPengaturan.modeMejaAktif, data.modeMejaAktif ? '1' : '0');
    await repositoriKasir.SimpanPengaturan(
      KunciPengaturan.ruteDapur,
      jsonEncode({
        'Stasiun': [
          for (final s in data.stasiunDapur) {'Uuid': s.uuid, 'Nama': s.nama},
        ],
        'Bawaan': data.uuidStasiunBawaan,
        'Kategori': data.kategoriStasiun,
      }),
    );
  });

  Stream<List<BarisAreaMeja>> PantauArea() =>
      (db.select(db.areaMeja)..orderBy([(a) => OrderingTerm.asc(a.Urutan), (a) => OrderingTerm.asc(a.Nama)])).watch();

  Stream<List<BarisMeja>> PantauMeja() =>
      (db.select(db.meja)..orderBy([(m) => OrderingTerm.asc(m.Urutan), (m) => OrderingTerm.asc(m.Nama)])).watch();

  Future<BarisMeja?> CariMeja(String uuid) => (db.select(db.meja)..where((m) => m.Uuid.equals(uuid))).getSingleOrNull();

  // Pesanan ----------------------------------------------------------------------------------------------------------

  /// Pesanan berstatus `Terbuka`, terlama dulu.
  Stream<List<PesananMeja>> PantauPesananTerbuka() =>
      (db.select(db.pesananTerbuka)
            ..where((p) => p.Status.equals(StatusPesananMeja.terbuka))
            ..orderBy([(p) => OrderingTerm.asc(p.DibukaPada)]))
          .watch()
          .map((daftar) => daftar.map(PesananMeja.DariBaris).toList());

  Stream<PesananMeja?> PantauPesanan(String uuid) => (db.select(
    db.pesananTerbuka,
  )..where((p) => p.Uuid.equals(uuid))).watchSingleOrNull().map((b) => b == null ? null : PesananMeja.DariBaris(b));

  Future<PesananMeja?> CariPesanan(String uuid) async {
    final baris = await (db.select(db.pesananTerbuka)..where((p) => p.Uuid.equals(uuid))).getSingleOrNull();
    return baris == null ? null : PesananMeja.DariBaris(baris);
  }

  /// Pesanan terbuka lain di [uuidMeja] (satu meja satu pesanan terbuka di fase 1; gabung meja menyusul).
  Future<PesananMeja?> CariPesananDiMeja(String uuidMeja) async {
    final baris =
        await (db.select(db.pesananTerbuka)
              ..where((p) => p.UuidMeja.equals(uuidMeja) & p.Status.equals(StatusPesananMeja.terbuka))
              ..limit(1))
            .getSingleOrNull();
    return baris == null ? null : PesananMeja.DariBaris(baris);
  }

  /// Ambil nomor urut pesanan berikutnya ([tanggal] `YYMMDD`), lalu simpan pesanan baru + outbox `PesananTerbuka.Buka`.
  Future<PesananMeja> SimpanPesananBaru({
    required String kodePerangkat,
    required String tanggal,
    required DateTime sekarang,
    required ({PesananTerbukaCompanion pesanan, ItemOutbox outbox}) Function(int urut) susun,
  }) => db.transaction(() async {
    final lama = await (db.select(
      db.nomorUrutPesananTerbuka,
    )..where((n) => n.KodePerangkat.equals(kodePerangkat) & n.Tanggal.equals(tanggal))).getSingleOrNull();
    final urut = (lama?.Terakhir ?? 0) + 1;
    await db
        .into(db.nomorUrutPesananTerbuka)
        .insertOnConflictUpdate(
          NomorUrutPesananTerbukaCompanion.insert(KodePerangkat: kodePerangkat, Tanggal: tanggal, Terakhir: urut),
        );
    final dokumen = susun(urut);
    await db.into(db.pesananTerbuka).insert(dokumen.pesanan);
    await repositoriKasir.TambahOutbox(dokumen.outbox, sekarang);
    return PesananMeja.DariBaris(
      await (db.select(db.pesananTerbuka)..where((p) => p.Uuid.equals(dokumen.pesanan.Uuid.value))).getSingle(),
    );
  });

  /// Ubah pesanan lokal lewat [ubah] (baris & header) dan tambahkan [outbox] dalam satu transaksi. Pesanan yang sudah
  /// tidak terbuka ditolak dengan `null`.
  Future<PesananMeja?> UbahPesanan(
    String uuid,
    PesananTerbukaCompanion Function(PesananMeja pesanan) ubah,
    List<ItemOutbox> outbox,
    DateTime sekarang,
  ) => db.transaction(() async {
    final pesanan = await CariPesanan(uuid);
    if (pesanan == null || pesanan.status != StatusPesananMeja.terbuka) {
      return null;
    }
    await (db.update(db.pesananTerbuka)..where((p) => p.Uuid.equals(uuid))).write(ubah(pesanan));
    for (final item in outbox) {
      await repositoriKasir.TambahOutbox(item, sekarang);
    }
    return CariPesanan(uuid);
  });

  static String SusunJsonBaris(List<BarisPesananMeja> baris) => jsonEncode([for (final b in baris) b.KeJson()]);

  /// Uuid pesanan yang masih punya item outbox tertunda (`PesananTerbuka.*`, atau `Penjualan.Buat` yang menutupnya).
  Future<Set<String>> AmbilUuidPesananTertunda() async {
    final baris = await (db.select(
      db.outbox,
    )..where((o) => o.Jenis.like('PesananTerbuka.%') | o.Jenis.equals('Penjualan.Buat'))).get();
    final hasil = <String>{};
    for (final b in baris) {
      if (b.Jenis == 'PesananTerbuka.Buka') {
        hasil.add(b.Uuid);
        continue;
      }
      final data = jsonDecode(b.Data);
      if (data is! Map<String, Object?>) {
        continue;
      }
      final uuid = data['UuidPesanan'] ?? data['UuidPesananTerbuka'];
      if (uuid is String) {
        hasil.add(uuid);
      }
    }
    return hasil;
  }

  /// Terapkan snapshot server: pesanan server menimpa salinan lokal; pesanan lokal `Terbuka` yang tidak ada di server
  /// dan tidak tertunda dihapus (sudah ditutup di perangkat lain lebih dari 12 jam lalu); pesanan `Ditutup` dihapus.
  /// Pesanan dengan perubahan tertunda di outbox dibiarkan sampai perubahan itu terkirim.
  Future<void> TerapkanSnapshot(SnapshotPesananTerbuka snapshot, DateTime sekarang) => db.transaction(() async {
    final tertunda = await AmbilUuidPesananTertunda();
    final diServer = <String>{};
    for (final p in snapshot.pesanan) {
      diServer.add(p.uuid);
      if (tertunda.contains(p.uuid)) {
        continue;
      }
      await db
          .into(db.pesananTerbuka)
          .insertOnConflictUpdate(
            PesananTerbukaCompanion.insert(
              Uuid: p.uuid,
              Nomor: p.nomor,
              UuidMeja: Value(p.uuidMeja),
              NamaMeja: Value(p.namaMeja),
              Label: Value(p.label),
              JumlahTamu: Value(p.jumlahTamu),
              DibukaOleh: Value(p.dibukaOleh),
              DibukaPada: p.dibukaPada ?? sekarang,
              Status: StatusPesananMeja.terbuka,
              DikunciBayar: Value(p.dikunciBayar),
              Baris: SusunJsonBaris(p.baris.map(BarisPesananMeja.DariServer).toList()),
              DiubahPada: sekarang,
            ),
          );
    }
    // Ditutup (dibayar/dibatalkan) di server, termasuk oleh perangkat lain: hapus dari layar. Pesanan yang ditutup di
    // perangkat ini tetap tersembunyi karena `Penjualan.Buat`/`Batal`-nya masih tertunda (dilewati di atas).
    final ditutup = snapshot.ditutup.map((d) => d.uuid).toList();
    if (ditutup.isNotEmpty) {
      await (db.delete(db.pesananTerbuka)..where((p) => p.Uuid.isIn(ditutup))).go();
    }
    final lokal = await (db.select(db.pesananTerbuka)..where((p) => p.Status.equals(StatusPesananMeja.terbuka))).get();
    final usang = [
      for (final p in lokal)
        if (!diServer.contains(p.Uuid) && !tertunda.contains(p.Uuid)) p.Uuid,
    ];
    if (usang.isNotEmpty) {
      await (db.delete(db.pesananTerbuka)..where((p) => p.Uuid.isIn(usang))).go();
    }
    // Pesanan yang sudah ditutup di perangkat ini dan perubahannya sudah terkirim tidak perlu disimpan lagi.
    final tutupLokal = await (db.select(
      db.pesananTerbuka,
    )..where((p) => p.Status.equals(StatusPesananMeja.terbuka).not())).get();
    final selesai = [
      for (final p in tutupLokal)
        if (!tertunda.contains(p.Uuid)) p.Uuid,
    ];
    if (selesai.isNotEmpty) {
      await (db.delete(db.pesananTerbuka)..where((p) => p.Uuid.isIn(selesai))).go();
    }
  });
}
