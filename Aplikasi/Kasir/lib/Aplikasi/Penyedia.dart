import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart' show Uang;

import '../Data/BasisData/BasisDataKasir.dart';
import '../Data/PenjagaLayarWakelock.dart';
import '../Data/PenyimpanRahasia.dart';
import '../Data/PesananMeja.dart';
import '../Data/RepositoriKasir.dart';
import '../Data/RepositoriKatalog.dart';
import '../Data/RepositoriPelanggan.dart';
import '../Data/RepositoriPenjualan.dart';
import '../Data/RepositoriPesananMeja.dart';
import '../Domain/Dapur/LayananDapur.dart';
import '../Domain/GalatKasir.dart';
import '../Domain/Katalog/KatalogLokal.dart';
import '../Domain/Katalog/LayananKatalog.dart';
import '../Domain/Meja/LayananPesananMeja.dart';
import '../Domain/Pelanggan/LayananPelanggan.dart';
import '../Domain/Penjualan/Keranjang.dart';
import '../Domain/Penjualan/KonteksPenjualan.dart';
import '../Domain/Penjualan/LayananPenjualan.dart';
import '../Domain/Penjualan/LayananReturPenjualan.dart';
import '../Domain/Penjualan/LayananVoidPenjualan.dart';
import '../Domain/Perangkat/PengaturanPerangkat.dart';
import '../Domain/Perangkat/PenjagaLayarMenyala.dart';
import '../Domain/Pin/PemverifikasiPinOffline.dart';
import '../Domain/Sesi/LayananMasuk.dart';
import '../Domain/Sesi/LayananPerangkat.dart';
import '../Domain/Sesi/StafLokal.dart';
import '../Domain/Shift/LayananShift.dart';
import '../Domain/Shift/LayananTutupShift.dart';
import '../Domain/Sinkron/LayananSinkron.dart';
import 'Lingkungan.dart';

/// Penyedia dependensi (Riverpod). Basis data, secure storage, dan klien HTTP di-override di `Persiapan.dart` dan
/// di test.
final penyediaBasisData = Provider<BasisDataKasir>((ref) => throw UnimplementedError('Override penyediaBasisData'));
final penyediaRahasia = Provider<PenyimpanRahasia>((ref) => PenyimpanRahasiaAman());
final penyediaKlienHttp = Provider<http.Client>((ref) => http.Client());
final penyediaLingkungan = Provider<Lingkungan>((ref) => Lingkungan.Dev);
final penyediaPlatform = Provider<String>((ref) => 'Android');
final penyediaJam = Provider<DateTime Function()>((ref) => DateTime.now);

/// Layar tetap menyala selama shift terbuka (§17.2.7). Test memakai tiruan.
final penyediaPenjagaLayar = Provider<PenjagaLayarMenyala>((ref) => const PenjagaLayarWakelock());

final penyediaRepositori = Provider<RepositoriKasir>((ref) => RepositoriKasir(ref.watch(penyediaBasisData)));

final penyediaKlienPos = Provider<KlienPos>((ref) {
  final rahasia = ref.watch(penyediaRahasia);
  return KlienPos(
    alamatDasar: ref.watch(penyediaLingkungan).AmbilAlamatServer(),
    versiAplikasi: '0.1.0',
    ambilToken: () => rahasia.Baca(PenyimpanRahasia.kunciToken),
    klien: ref.watch(penyediaKlienHttp),
  );
});

final penyediaLayananPerangkat = Provider<LayananPerangkat>(
  (ref) => LayananPerangkat(
    klien: ref.watch(penyediaKlienPos),
    repositori: ref.watch(penyediaRepositori),
    rahasia: ref.watch(penyediaRahasia),
    platform: ref.watch(penyediaPlatform),
    jam: ref.watch(penyediaJam),
  ),
);

/// Verifikasi PIN offline (Argon2id). Test widget menggantinya dengan tiruan; kriptografi asli diuji di test domain.
final penyediaPemverifikasiPin = Provider<PemverifikasiPinOffline>((ref) => const PemverifikasiPinOffline());

final penyediaLayananMasuk = Provider<LayananMasuk>(
  (ref) => LayananMasuk(
    repositori: ref.watch(penyediaRepositori),
    rahasia: ref.watch(penyediaRahasia),
    klien: ref.watch(penyediaKlienPos),
    pemverifikasi: ref.watch(penyediaPemverifikasiPin),
    jam: ref.watch(penyediaJam),
  ),
);

final penyediaLayananShift = Provider<LayananShift>(
  (ref) => LayananShift(repositori: ref.watch(penyediaRepositori), jam: ref.watch(penyediaJam)),
);

final penyediaLayananTutupShift = Provider<LayananTutupShift>(
  (ref) => LayananTutupShift(
    repositori: ref.watch(penyediaRepositori),
    repositoriPenjualan: ref.watch(penyediaRepositoriPenjualan),
    jam: ref.watch(penyediaJam),
  ),
);

final penyediaLayananSinkron = Provider<LayananSinkron>(
  (ref) => LayananSinkron(
    klien: ref.watch(penyediaKlienPos),
    repositori: ref.watch(penyediaRepositori),
    perangkat: ref.watch(penyediaLayananPerangkat),
    jam: ref.watch(penyediaJam),
  ),
);

final penyediaRepositoriKatalog = Provider<RepositoriKatalog>((ref) => RepositoriKatalog(ref.watch(penyediaBasisData)));

final penyediaRepositoriPenjualan = Provider<RepositoriPenjualan>(
  (ref) => RepositoriPenjualan(ref.watch(penyediaBasisData), ref.watch(penyediaRepositori)),
);

final penyediaLayananKatalog = Provider<LayananKatalog>(
  (ref) => LayananKatalog(
    klien: ref.watch(penyediaKlienPos),
    repositori: ref.watch(penyediaRepositori),
    repositoriKatalog: ref.watch(penyediaRepositoriKatalog),
    jam: ref.watch(penyediaJam),
  ),
);

final penyediaLayananPenjualan = Provider<LayananPenjualan>(
  (ref) => LayananPenjualan(
    repositori: ref.watch(penyediaRepositori),
    repositoriPenjualan: ref.watch(penyediaRepositoriPenjualan),
    repositoriPelanggan: ref.watch(penyediaRepositoriPelanggan),
    jam: ref.watch(penyediaJam),
  ),
);

/// Void transaksi di shift yang sama (F-09 fase 1).
final penyediaLayananVoid = Provider<LayananVoidPenjualan>(
  (ref) => LayananVoidPenjualan(
    repositori: ref.watch(penyediaRepositori),
    repositoriPenjualan: ref.watch(penyediaRepositoriPenjualan),
    jam: ref.watch(penyediaJam),
  ),
);

/// Retur penjualan dari struk (F-09 fase 1, cari struk online).
final penyediaLayananRetur = Provider<LayananReturPenjualan>(
  (ref) => LayananReturPenjualan(
    klien: ref.watch(penyediaKlienPos),
    repositori: ref.watch(penyediaRepositori),
    repositoriPenjualan: ref.watch(penyediaRepositoriPenjualan),
    jam: ref.watch(penyediaJam),
  ),
);

/// Katalog lokal di memori (dibangun ulang setelah katalog diperbarui: `ref.invalidate(penyediaKatalog)`).
final penyediaKatalog = FutureProvider<KatalogLokal>(
  (ref) async => KatalogLokal.Bangun(await ref.watch(penyediaRepositoriKatalog).Muat()),
);

/// Pengaturan jual dari data awal tersimpan (outlet, pajak, diskon, pembulatan, metode bayar).
final penyediaKonteksPenjualan = FutureProvider<KonteksPenjualan>(
  (ref) => KonteksPenjualan.Muat(ref.watch(penyediaRepositori), ref.watch(penyediaRepositoriKatalog)),
);

final penyediaPesananTertahan = StreamProvider<List<BarisPesananTertahan>>(
  (ref) => ref.watch(penyediaRepositoriPenjualan).PantauPesananTertahan(),
);

/// Riwayat penjualan perangkat pada tanggal bisnis hari ini beserta status sinkron.
final penyediaRiwayatHariIni = StreamProvider<List<RiwayatPenjualan>>((ref) async* {
  final konteks = await ref.watch(penyediaKonteksPenjualan.future);
  yield* ref.watch(penyediaRepositoriPenjualan).PantauRiwayat(konteks.HitungTanggalBisnis(ref.read(penyediaJam)()));
});

/// Penjualan tunai bersih (uang tunai diterima − kembalian) sebuah shift, untuk perkiraan kas di laci.
final penyediaTunaiShift = StreamProvider.family<Uang, String>(
  (ref, uuidShift) => ref.watch(penyediaRepositoriPenjualan).PantauTunaiBersihShift(uuidShift),
);

/// Refund tunai (void & retur, F-09) yang keluar dari laci sebuah shift.
final penyediaRefundTunaiShift = StreamProvider.family<Uang, String>(
  (ref, uuidShift) => ref.watch(penyediaRepositoriPenjualan).PantauRefundTunaiShift(uuidShift),
);

/// Jumlah dokumen void & retur sebuah shift (pemicu hitung ulang laporan shift).
final penyediaJumlahVoidReturShift = StreamProvider.family<int, String>(
  (ref, uuidShift) => ref.watch(penyediaRepositoriPenjualan).PantauJumlahVoidReturShift(uuidShift),
);

/// Retur perangkat pada tanggal bisnis hari ini beserta status sinkron (F-09).
final penyediaReturHariIni = StreamProvider<List<RiwayatRetur>>((ref) async* {
  final konteks = await ref.watch(penyediaKonteksPenjualan.future);
  yield* ref
      .watch(penyediaRepositoriPenjualan)
      .PantauReturTanggal(konteks.HitungTanggalBisnis(ref.read(penyediaJam)()));
});

/// Laporan shift X/Z (F-11) dari data perangkat; dihitung ulang saat penjualan, kas, void, atau retur shift berubah.
final penyediaLaporanShift = FutureProvider.family<LaporanShift, String>((ref, uuidShift) async {
  ref.watch(penyediaShift(uuidShift));
  ref.watch(penyediaMutasiShift(uuidShift));
  ref.watch(penyediaTunaiShift(uuidShift));
  ref.watch(penyediaRefundTunaiShift(uuidShift));
  ref.watch(penyediaJumlahVoidReturShift(uuidShift));
  return ref.watch(penyediaLayananTutupShift).SusunLaporan(uuidShift);
});

/// Satu shift lokal (status & kolom tutup), untuk menghitung ulang laporan saat shift ditutup.
final penyediaShift = StreamProvider.family<BarisShift?, String>(
  (ref, uuidShift) => ref.watch(penyediaRepositori).PantauShift(uuidShift),
);

/// Shift yang baru ditutup dan laporan Z-nya belum ditutup kasir (layar Laporan Z sebelum buka shift berikutnya).
final penyediaLaporanZTertunda = StreamProvider<String?>(
  (ref) => ref.watch(penyediaLayananTutupShift).PantauLaporanZTertunda(),
);

/// Keranjang yang sedang dibangun di layar Jual (bertahan saat pindah menu atau ganti kasir).
class PengaturKeranjang extends Notifier<Keranjang> {
  @override
  Keranjang build() => Keranjang.kosong;

  void Ganti(Keranjang keranjang) => state = keranjang;

  void Kosongkan() => state = Keranjang.kosong;
}

final penyediaKeranjang = NotifierProvider<PengaturKeranjang, Keranjang>(PengaturKeranjang.new);

// Mode meja (F-07 mode meja & F-10b fase 1) ----------------------------------------------------------------------------

final penyediaRepositoriPesananMeja = Provider<RepositoriPesananMeja>(
  (ref) => RepositoriPesananMeja(ref.watch(penyediaBasisData), ref.watch(penyediaRepositori)),
);

final penyediaLayananPesananMeja = Provider<LayananPesananMeja>(
  (ref) => LayananPesananMeja(
    klien: ref.watch(penyediaKlienPos),
    repositori: ref.watch(penyediaRepositori),
    repositoriMeja: ref.watch(penyediaRepositoriPesananMeja),
    jam: ref.watch(penyediaJam),
  ),
);

/// Mode meja outlet aktif (dari `GET /api/pos/v1/meja`): menampilkan menu Meja di rel navigasi.
final penyediaModeMeja = StreamProvider<bool>(
  (ref) => ref.watch(penyediaRepositori).PantauPengaturan(KunciPengaturan.modeMejaAktif).map((n) => n == '1'),
);

/// Jenis perangkat dari aktivasi: `Kds` membuka layar dapur, selain itu ruang kerja kasir.
final penyediaJenisPerangkat = StreamProvider<String>(
  (ref) => ref.watch(penyediaRepositori).PantauPengaturan(KunciPengaturan.jenisPerangkat).map((n) => n ?? 'Kasir'),
);

final penyediaAreaMeja = StreamProvider<List<BarisAreaMeja>>(
  (ref) => ref.watch(penyediaRepositoriPesananMeja).PantauArea(),
);

final penyediaMeja = StreamProvider<List<BarisMeja>>((ref) => ref.watch(penyediaRepositoriPesananMeja).PantauMeja());

final penyediaPesananTerbuka = StreamProvider<List<PesananMeja>>(
  (ref) => ref.watch(penyediaRepositoriPesananMeja).PantauPesananTerbuka(),
);

final penyediaPesananMeja = StreamProvider.family<PesananMeja?, String>(
  (ref, uuid) => ref.watch(penyediaRepositoriPesananMeja).PantauPesanan(uuid),
);

// Pelanggan (F-16a) ----------------------------------------------------------------------------------------------------

final penyediaRepositoriPelanggan = Provider<RepositoriPelanggan>(
  (ref) => RepositoriPelanggan(ref.watch(penyediaBasisData), ref.watch(penyediaRepositori)),
);

final penyediaLayananPelanggan = Provider<LayananPelanggan>(
  (ref) => LayananPelanggan(
    klien: ref.watch(penyediaKlienPos),
    repositori: ref.watch(penyediaRepositoriPelanggan),
    jam: ref.watch(penyediaJam),
  ),
);

/// Layar dapur (KDS) untuk perangkat berjenis `Kds`.
final penyediaLayananDapur = Provider<LayananDapur>(
  (ref) => LayananDapur(klien: ref.watch(penyediaKlienPos), repositori: ref.watch(penyediaRepositori)),
);

/// Keranjang yang ditampilkan & dibayar: pada pesanan meja = baris tersimpan pesanan + baris baru (draf).
final penyediaKeranjangEfektif = Provider<Keranjang>((ref) {
  final draf = ref.watch(penyediaKeranjang);
  final uuid = draf.pesananMeja?.uuid;
  if (uuid == null) {
    return draf;
  }
  final pesanan = ref.watch(penyediaPesananMeja(uuid)).value;
  final katalog = ref.watch(penyediaKatalog).value ?? KatalogLokal.kosong;
  return LayananPesananMeja.SusunKeranjangEfektif(draf, pesanan, katalog);
});

final penyediaShiftAktif = StreamProvider<BarisShift?>((ref) => ref.watch(penyediaRepositori).PantauShiftAktif());

final penyediaMutasiShift = StreamProvider.family<List<BarisMutasiKas>, String>(
  (ref, uuidShift) => ref.watch(penyediaRepositori).PantauMutasi(uuidShift),
);

final penyediaJumlahTertunda = StreamProvider<int>((ref) => ref.watch(penyediaRepositori).PantauJumlahTertunda());

final penyediaPerluTindakan = StreamProvider<List<BarisOutbox>>(
  (ref) => ref.watch(penyediaRepositori).PantauPerluTindakan(),
);

final penyediaStaf = FutureProvider<List<StafLokal>>(
  (ref) async => (await ref.watch(penyediaRepositori).AmbilStaf()).map(StafLokal.DariBaris).toList(),
);

final penyediaKategori = FutureProvider.family<List<BarisKategoriKas>, String>(
  (ref, jenis) => ref.watch(penyediaRepositori).AmbilKategori(jenis),
);

/// Identitas outlet & perangkat untuk kepala layar.
final penyediaIdentitas = FutureProvider<({String outlet, String perangkat})>((ref) async {
  final repo = ref.watch(penyediaRepositori);
  return (
    outlet: await repo.AmbilPengaturan(KunciPengaturan.namaOutlet) ?? '',
    perangkat: await repo.AmbilPengaturan(KunciPengaturan.kodePerangkat) ?? '',
  );
});

/// Pengaturan lokal perangkat (ukuran tampilan, posisi keranjang, kunci otomatis). Dimuat dari tabel `Pengaturan`;
/// sebelum selesai dimuat memakai nilai bawaan.
class PengaturPengaturanPerangkat extends Notifier<PengaturanPerangkat> {
  var _diubah = false;

  @override
  PengaturanPerangkat build() {
    unawaited(_Muat());
    return const PengaturanPerangkat();
  }

  Future<void> _Muat() async {
    final dimuat = await PengaturanPerangkat.Muat(ref.read(penyediaRepositori));
    if (!_diubah) {
      state = dimuat;
    }
  }

  Future<void> Simpan(PengaturanPerangkat baru) async {
    _diubah = true;
    state = baru;
    await baru.Simpan(ref.read(penyediaRepositori));
  }
}

final penyediaPengaturanPerangkat = NotifierProvider<PengaturPengaturanPerangkat, PengaturanPerangkat>(
  PengaturPengaturanPerangkat.new,
);

/// Koneksi ke server menurut hasil sinkron terakhir (bilah status ruang kerja).
enum StatusKoneksi { BelumDiketahui, Online, Offline }

class PengaturKoneksi extends Notifier<StatusKoneksi> {
  @override
  StatusKoneksi build() => StatusKoneksi.BelumDiketahui;

  void Tandai(StatusKoneksi status) => state = status;
}

final penyediaKoneksi = NotifierProvider<PengaturKoneksi, StatusKoneksi>(PengaturKoneksi.new);

enum TahapSesi { Memuat, BelumAktif, PilihKasir, Masuk }

/// Kunci layar ruang kerja (§17.2.7): `Terkunci` = buka dengan PIN kasir yang sama atau ganti kasir; `GantiKasir` =
/// pilih kasir lain + PIN (dari ketuk nama kasir), bisa dibatalkan. Shift tetap terbuka pada keduanya.
enum KeadaanKunci { Bebas, Terkunci, GantiKasir }

class KeadaanSesi {
  const KeadaanSesi(this.tahap, {this.kasir, this.pesan, this.kunci = KeadaanKunci.Bebas});

  final TahapSesi tahap;
  final StafLokal? kasir;
  final KeadaanKunci kunci;

  /// Pesan penting untuk ditampilkan sekali (misal perangkat dicabut).
  final String? pesan;
}

/// Alur sesi kasir: belum aktif → pilih kasir & PIN → masuk (buka shift / shift berjalan).
class PengaturSesi extends Notifier<KeadaanSesi> {
  @override
  KeadaanSesi build() {
    unawaited(_Muat());
    return const KeadaanSesi(TahapSesi.Memuat);
  }

  Future<void> _Muat() async {
    final aktif = await ref.read(penyediaLayananPerangkat).CekSudahAktif();
    state = KeadaanSesi(aktif ? TahapSesi.PilihKasir : TahapSesi.BelumAktif);
    if (aktif) {
      unawaited(SegarkanData());
    }
  }

  Future<void> Aktifkan(String kode) async {
    await ref.read(penyediaLayananPerangkat).Aktifkan(kode);
    ref.invalidate(penyediaStaf);
    ref.invalidate(penyediaIdentitas);
    ref.invalidate(penyediaKonteksPenjualan);
    state = const KeadaanSesi(TahapSesi.PilihKasir);
    unawaited(PerbaruiKatalog());
    unawaited(PerbaruiDataMeja());
  }

  Future<void> Masuk(StafLokal staf, String pin) async {
    final kasir = await ref.read(penyediaLayananMasuk).Masuk(staf, pin);
    state = KeadaanSesi(TahapSesi.Masuk, kasir: kasir);
  }

  void Keluar() => state = const KeadaanSesi(TahapSesi.PilihKasir);

  /// Kunci ruang kerja tanpa menutup shift (kunci cepat, kunci otomatis, atau ganti kasir).
  void Kunci({bool gantiKasir = false}) {
    if (state.tahap != TahapSesi.Masuk) {
      return;
    }
    if (state.kunci == KeadaanKunci.Terkunci && gantiKasir) {
      return;
    }
    state = KeadaanSesi(
      TahapSesi.Masuk,
      kasir: state.kasir,
      kunci: gantiKasir ? KeadaanKunci.GantiKasir : KeadaanKunci.Terkunci,
    );
  }

  /// Batal ganti kasir (hanya dari ketuk nama kasir; layar terkunci tetap butuh PIN).
  void BatalGantiKasir() {
    if (state.tahap == TahapSesi.Masuk && state.kunci == KeadaanKunci.GantiKasir) {
      state = KeadaanSesi(TahapSesi.Masuk, kasir: state.kasir);
    }
  }

  /// Perbarui data awal (staf, kategori, pengaturan) bila online; perangkat dicabut → kembali ke aktivasi.
  Future<void> SegarkanData() async {
    try {
      final tersambung = await ref.read(penyediaLayananPerangkat).SegarkanDataAwal();
      ref.read(penyediaKoneksi.notifier).Tandai(tersambung ? StatusKoneksi.Online : StatusKoneksi.Offline);
      ref.invalidate(penyediaStaf);
      ref.invalidate(penyediaKategori);
      ref.invalidate(penyediaKonteksPenjualan);
      ref.invalidate(penyediaIdentitas);
      if (tersambung) {
        await PerbaruiKatalog();
        await PerbaruiDataMeja();
      }
    } on GalatKasir catch (galat) {
      _Dicabut(galat.pesan);
    } on GalatApi {
      // Galat server lain: tetap pakai data lokal terakhir.
      ref.read(penyediaKoneksi.notifier).Tandai(StatusKoneksi.Online);
    }
  }

  /// Unduh katalog (lengkap/delta) lalu bangun ulang katalog di memori bila berubah (Rincian F-07c).
  Future<HasilPerbaruiKatalog> PerbaruiKatalog() async {
    final layanan = ref.read(penyediaLayananKatalog);
    final hasil = await layanan.Perbarui();
    if (hasil == HasilPerbaruiKatalog.Lengkap || hasil == HasilPerbaruiKatalog.Delta) {
      await layanan.PerbaruiPromo();
      ref.invalidate(penyediaKatalog);
      // F-16c: promo & kategori produk ikut konteks penjualan.
      ref.invalidate(penyediaKonteksPenjualan);
    }
    if (hasil == HasilPerbaruiKatalog.Offline) {
      ref.read(penyediaKoneksi.notifier).Tandai(StatusKoneksi.Offline);
    }
    return hasil;
  }

  /// Unduh data meja outlet (mode meja). Galat server tidak menghentikan kerja kasir.
  Future<void> PerbaruiDataMeja() async {
    try {
      await ref.read(penyediaLayananPesananMeja).PerbaruiDataMeja();
    } on GalatApi {
      return;
    }
  }

  /// Kirim outbox; perangkat dicabut → kembali ke aktivasi.
  Future<RingkasanSinkron> Sinkronkan() async {
    final hasil = await ref.read(penyediaLayananSinkron).KirimTertunda();
    if (hasil.tersambung != null) {
      ref.read(penyediaKoneksi.notifier).Tandai(hasil.tersambung! ? StatusKoneksi.Online : StatusKoneksi.Offline);
    }
    if (hasil.perangkatDicabut) {
      _Dicabut('Perangkat ini sudah dicabut dari back-office. Data yang belum terkirim tetap tersimpan di perangkat.');
    }
    return hasil;
  }

  void _Dicabut(String pesan) {
    ref.invalidate(penyediaStaf);
    state = KeadaanSesi(TahapSesi.BelumAktif, pesan: pesan);
  }
}

final penyediaSesi = NotifierProvider<PengaturSesi, KeadaanSesi>(PengaturSesi.new);
