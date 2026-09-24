import 'package:inti/Inti.dart';

import 'DataHarga.dart';

/// Penentu harga satuan lapis 3–5 price engine F-03 (daftar harga → harga bertingkat → harga dasar).
///
/// Identik dengan `App\Domain\Katalog\Harga\Layanan\PenentuHarga` di Backend dan diuji dengan test vector bersama
/// `Spesifikasi/VektorUjiKalkulasi/Harga/` (CLAUDE.md #18). Semua perbandingan desimal (paket `decimal`).
///
/// 1. Daftar harga cocok bila aktif dan setiap kondisinya null atau sama dengan permintaan (outlet ∈ daftar, kanal,
///    tier persis, `mulaiPada ≤ waktu < selesaiPada`).
/// 2. Urut: prioritas menurun, jumlah kondisi terisi menurun, lalu Uuid menaik (perbandingan kode karakter).
/// 3. Daftar pertama yang punya baris satuan itu dengan `jumlahMinimum ≤ jumlah` menang (baris terbesar).
/// 4. Tanpa daftar: baris dasar `jumlahMinimum ≤ jumlah` terbesar (`Bertingkat` bila > 1, selain itu `Dasar`); bila
///    jumlah lebih kecil dari semua baris dasar, baris dasar terkecil (`Dasar`).
/// 5. Tanpa baris dasar: null (`HargaTidakDitemukan`).
final class PenentuHarga {
  const PenentuHarga();

  HasilHarga? Tentukan(KatalogHarga katalog, PermintaanHarga permintaan) {
    if (permintaan.jumlah.Bandingkan(Kuantitas.Nol()) <= 0) {
      throw ArgumentError.value(
        permintaan.jumlah.KeString(),
        'jumlah',
        'Jumlah untuk penentuan harga harus lebih dari 0',
      );
    }

    final barisSatuan = katalog.harga
        .where(
          (baris) => baris.uuidProduk == permintaan.uuidProduk && baris.uuidProdukSatuan == permintaan.uuidProdukSatuan,
        )
        .toList();

    for (final daftar in UrutkanDaftarCocok(katalog.daftarHarga, permintaan)) {
      final baris = PilihBarisBerlaku(barisSatuan.where((b) => b.uuidDaftarHarga == daftar.uuid), permintaan.jumlah);
      if (baris != null) {
        return HasilHarga(
          harga: baris.harga,
          sumber: SumberHarga.DaftarHarga,
          uuidDaftarHarga: daftar.uuid,
          jumlahMinimum: baris.jumlahMinimum,
        );
      }
    }

    final barisDasar = barisSatuan.where((b) => b.uuidDaftarHarga == null).toList();
    if (barisDasar.isEmpty) {
      return null;
    }

    final baris = PilihBarisBerlaku(barisDasar, permintaan.jumlah);
    if (baris == null) {
      var terkecil = barisDasar.first;
      for (final kandidat in barisDasar) {
        if (kandidat.jumlahMinimum.Bandingkan(terkecil.jumlahMinimum) < 0) {
          terkecil = kandidat;
        }
      }
      return HasilHarga(
        harga: terkecil.harga,
        sumber: SumberHarga.Dasar,
        uuidDaftarHarga: null,
        jumlahMinimum: terkecil.jumlahMinimum,
      );
    }

    return HasilHarga(
      harga: baris.harga,
      sumber: baris.jumlahMinimum.Bandingkan(Kuantitas.DariBulat(1)) > 0 ? SumberHarga.Bertingkat : SumberHarga.Dasar,
      uuidDaftarHarga: null,
      jumlahMinimum: baris.jumlahMinimum,
    );
  }

  static List<DaftarHargaResolusi> UrutkanDaftarCocok(
    List<DaftarHargaResolusi> daftarHarga,
    PermintaanHarga permintaan,
  ) {
    final cocok = daftarHarga.where((daftar) => CekCocok(daftar, permintaan)).toList()
      ..sort((a, b) {
        final prioritas = b.prioritas.compareTo(a.prioritas);
        if (prioritas != 0) {
          return prioritas;
        }
        final spesifik = HitungSpesifik(b).compareTo(HitungSpesifik(a));
        if (spesifik != 0) {
          return spesifik;
        }
        return a.uuid.compareTo(b.uuid);
      });
    return cocok;
  }

  static bool CekCocok(DaftarHargaResolusi daftar, PermintaanHarga permintaan) {
    final mulai = daftar.mulaiPada;
    final selesai = daftar.selesaiPada;
    return daftar.aktif &&
        (daftar.uuidOutlet == null ||
            (permintaan.uuidOutlet != null && daftar.uuidOutlet!.contains(permintaan.uuidOutlet))) &&
        (daftar.kanal == null || daftar.kanal == permintaan.kanal) &&
        (daftar.tierPelanggan == null || daftar.tierPelanggan == permintaan.tierPelanggan) &&
        (mulai == null || !mulai.isAfter(permintaan.waktu)) &&
        (selesai == null || permintaan.waktu.isBefore(selesai));
  }

  static int HitungSpesifik(DaftarHargaResolusi daftar) =>
      (daftar.uuidOutlet != null ? 1 : 0) +
      (daftar.kanal != null ? 1 : 0) +
      (daftar.tierPelanggan != null ? 1 : 0) +
      (daftar.mulaiPada != null || daftar.selesaiPada != null ? 1 : 0);

  /// Baris dengan `jumlahMinimum ≤ jumlah` terbesar, atau null.
  static BarisProdukHarga? PilihBarisBerlaku(Iterable<BarisProdukHarga> baris, Kuantitas jumlah) {
    BarisProdukHarga? terpilih;
    for (final kandidat in baris) {
      if (kandidat.jumlahMinimum.Bandingkan(jumlah) > 0) {
        continue;
      }
      if (terpilih == null || kandidat.jumlahMinimum.Bandingkan(terpilih.jumlahMinimum) > 0) {
        terpilih = kandidat;
      }
    }
    return terpilih;
  }
}
