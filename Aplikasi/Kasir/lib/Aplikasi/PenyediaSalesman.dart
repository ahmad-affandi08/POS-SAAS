import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../Data/BasisData/BasisDataKasir.dart';
import '../Data/PenentuLokasiPlatform.dart';
import '../Data/RepositoriKasir.dart';
import '../Data/RepositoriSalesman.dart';
import '../Domain/Perangkat/PenentuLokasi.dart';
import '../Domain/Salesman/LayananSalesman.dart';
import 'Penyedia.dart';

/// Penyedia Modul Salesman bagian 2 (ruang kerja Salesman). Lokasi & basis data di-override di test.

/// Lokasi sekali saat mulai kunjungan (tiruan di test).
final penyediaPenentuLokasi = Provider<PenentuLokasi>((ref) => const PenentuLokasiPlatform());

final penyediaRepositoriSalesman = Provider<RepositoriSalesman>(
  (ref) => RepositoriSalesman(ref.watch(penyediaBasisData), ref.watch(penyediaRepositori)),
);

final penyediaLayananSalesman = Provider<LayananSalesman>(
  (ref) => LayananSalesman(
    klien: ref.watch(penyediaKlienPos),
    repositori: ref.watch(penyediaRepositoriSalesman),
    penentuLokasi: ref.watch(penyediaPenentuLokasi),
    jam: ref.watch(penyediaJam),
  ),
);

/// Cache pelanggan salesman (urut nama) dengan waktu kunjungan terakhir dari perangkat ini ikut diperhitungkan.
final penyediaPelangganSalesman = StreamProvider<List<PelangganSalesman>>((ref) async* {
  final repo = ref.watch(penyediaRepositoriSalesman);
  final terakhir = ref.watch(penyediaKunjunganTerakhirLokal).value ?? const <String, DateTime>{};
  yield* repo.PantauPelanggan().map(
    (daftar) => [for (final b in daftar) PelangganSalesman.DariBaris(b, kunjunganLokal: terakhir[b.Uuid])],
  );
});

final penyediaKunjunganTerakhirLokal = StreamProvider<Map<String, DateTime>>(
  (ref) => ref.watch(penyediaRepositoriSalesman).PantauKunjunganTerakhir(),
);

/// Waktu unduhan lengkap pelanggan terakhir (null = belum pernah).
final penyediaPelangganSalesmanDiperbaruiPada = StreamProvider<DateTime?>(
  (ref) => ref
      .watch(penyediaRepositori)
      .PantauPengaturan(KunciPengaturan.pelangganSalesmanDiperbaruiPada)
      .map((n) => n == null ? null : DateTime.tryParse(n)?.toUtc()),
);

/// Snapshot stok kantor terakhir.
final penyediaStokSalesman = StreamProvider<StokKantor>(
  (ref) => ref.watch(penyediaRepositori).PantauPengaturan(KunciPengaturan.stokSalesman).map(StokKantor.DariJson),
);

/// Kunjungan berjalan seorang pengguna (Uuid pengguna).
final penyediaKunjunganBerjalan = StreamProvider.family<BarisKunjunganSalesLokal?, String>(
  (ref, uuidPengguna) => ref.watch(penyediaRepositoriSalesman).PantauKunjunganBerjalan(uuidPengguna),
);

/// Riwayat salesman: hari riwayat yang ditampilkan (hari ini + 6 hari sebelumnya).
const int hariRiwayatSalesman = 7;

DateTime _AmbilAwalRiwayat(DateTime sekarang) {
  final lokal = sekarang.toLocal();
  return DateTime(lokal.year, lokal.month, lokal.day).subtract(const Duration(days: hariRiwayatSalesman - 1));
}

final penyediaRiwayatPesananSalesman = StreamProvider.family<List<RiwayatPesananSalesman>, String>(
  (ref, uuidPengguna) =>
      ref.watch(penyediaRepositoriSalesman).PantauPesanan(uuidPengguna, _AmbilAwalRiwayat(ref.read(penyediaJam)())),
);

final penyediaRiwayatKunjunganSalesman = StreamProvider.family<List<RiwayatKunjunganSalesman>, String>(
  (ref, uuidPengguna) => ref
      .watch(penyediaRepositoriSalesman)
      .PantauKunjunganSelesai(uuidPengguna, _AmbilAwalRiwayat(ref.read(penyediaJam)())),
);
