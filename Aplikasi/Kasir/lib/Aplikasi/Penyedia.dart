import 'dart:async';
import 'dart:typed_data';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart' show Uang;

import '../Data/KameraSwafotoPlatform.dart';
import '../Data/RepositoriAbsensi.dart';
import '../Domain/Karyawan/LayananAbsensi.dart';
import '../Domain/Perangkat/KameraSwafoto.dart';
import '../Data/BasisData/BasisDataKasir.dart';
import '../Data/PengubahLogoStruk.dart';
import '../Data/Printer/PemindaiPrinterPlatform.dart';
import '../Data/PenjagaLayarWakelock.dart';
import '../Data/PenyimpanRahasia.dart';
import '../Data/PesananMeja.dart';
import '../Data/RepositoriKasir.dart';
import '../Data/RepositoriKatalog.dart';
import '../Data/RepositoriPelanggan.dart';
import '../Data/RepositoriPenjualan.dart';
import '../Data/RepositoriPreOrder.dart';
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
import '../Domain/Penjualan/LayananPreOrder.dart';
import '../Domain/Penjualan/LayananVoucher.dart';
import '../Domain/Penjualan/LayananReturPenjualan.dart';
import '../Domain/Penjualan/LayananVoidPenjualan.dart';
import '../Domain/Perangkat/PengaturanPerangkat.dart';
import '../Domain/Perangkat/PenjagaLayarMenyala.dart';
import '../Domain/Pin/PemverifikasiPinOffline.dart';
import '../Domain/Sesi/LayananMasuk.dart';
import '../Domain/Sesi/LayananPerangkat.dart';
import '../Domain/Sesi/StafLokal.dart';
import '../Domain/Dapur/LayananTiketDapur.dart';
import '../Domain/Shift/LayananBukaLaci.dart';
import '../Domain/Shift/LayananShift.dart';
import '../Domain/Shift/LayananTutupShift.dart';
import '../Domain/Sinkron/LayananSinkron.dart';
import '../Domain/Struk/LayananStruk.dart';
import '../Domain/Struk/PemindaiPrinter.dart';
import '../Domain/Struk/ProfilPrinter.dart';
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
    // P-10 BR-P10.2: server tahu perangkat mana yang masih menyimpan transaksi belum terkirim.
    ambilJumlahOutbox: () => ref.read(penyediaRepositori).HitungJumlahTertunda(),
  );
});

final penyediaLayananPerangkat = Provider<LayananPerangkat>(
  (ref) => LayananPerangkat(
    klien: ref.watch(penyediaKlienPos),
    repositori: ref.watch(penyediaRepositori),
    rahasia: ref.watch(penyediaRahasia),
    platform: ref.watch(penyediaPlatform),
    jam: ref.watch(penyediaJam),
    ubahLogo: ref.watch(penyediaPengubahLogo),
  ),
);

/// PRD v1.79: dekode logo struk (mesin gambar Flutter); test domain boleh menggantinya.
final penyediaPengubahLogo = Provider<Future<GambarMonokrom?> Function(Uint8List byte)?>((ref) => UbahLogoKeMonokrom);

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
    repositoriPreOrder: ref.watch(penyediaRepositoriPreOrder),
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

/// Cetak struk (PRD v1.79): transport printer dari profil (test menggantinya dengan printer tiruan).
final penyediaPemindaiPrinter = Provider<PemindaiPrinter>((ref) => const PemindaiPrinterPlatform());

final penyediaPembuatTransport = Provider<PembuatTransport>((ref) => ref.watch(penyediaPemindaiPrinter).BuatTransport);

final penyediaLayananStruk = Provider<LayananStruk>(
  (ref) => LayananStruk(
    repositori: ref.watch(penyediaRepositori),
    penjualan: ref.watch(penyediaRepositoriPenjualan),
    pembuatTransport: ref.watch(penyediaPembuatTransport),
  ),
);

/// Cetak struk bagian 4: buka laci manual tanpa transaksi yang dicatat (§19.2).
final penyediaLayananBukaLaci = Provider<LayananBukaLaci>(
  (ref) => LayananBukaLaci(
    repositori: ref.watch(penyediaRepositori),
    struk: ref.watch(penyediaLayananStruk),
    jam: ref.watch(penyediaJam),
  ),
);

/// Cetak struk bagian 4c: tiket dapur per stasiun di printer.
final penyediaLayananTiketDapur = Provider<LayananTiketDapur>(
  (ref) => LayananTiketDapur(
    repositori: ref.watch(penyediaRepositori),
    struk: ref.watch(penyediaLayananStruk),
    penjualan: ref.watch(penyediaRepositoriPenjualan),
  ),
);

enum KeadaanPrinter { BelumDiatur, Siap, Mencetak, Gagal }

/// Keadaan printer untuk bilah status & layar (§17.2.7: status printer selalu terlihat).
class StatusPrinter {
  const StatusPrinter({this.profil, this.keadaan = KeadaanPrinter.BelumDiatur, this.pesan});

  final ProfilPrinter? profil;
  final KeadaanPrinter keadaan;

  /// Pesan galat terakhir (keadaan `Gagal`).
  final String? pesan;
}

/// Profil & keadaan printer perangkat ini. Setiap aksi cetak mengembalikan pesan galat (null = berhasil) agar layar
/// bisa menampilkannya di tempat; keadaan `Gagal` bertahan sampai cetak berikutnya berhasil.
class PengaturPrinter extends Notifier<StatusPrinter> {
  var _diubah = false;

  @override
  StatusPrinter build() {
    unawaited(_Muat());
    return const StatusPrinter();
  }

  Future<void> _Muat() async {
    final profil = await ref.read(penyediaLayananStruk).AmbilProfil();
    if (!_diubah) {
      state = StatusPrinter(profil: profil, keadaan: profil == null ? KeadaanPrinter.BelumDiatur : KeadaanPrinter.Siap);
    }
  }

  Future<void> SimpanProfil(ProfilPrinter profil) async {
    _diubah = true;
    await profil.Simpan(ref.read(penyediaRepositori));
    state = StatusPrinter(profil: profil, keadaan: KeadaanPrinter.Siap);
  }

  Future<void> HapusProfil() async {
    _diubah = true;
    await ProfilPrinter.Hapus(ref.read(penyediaRepositori));
    state = const StatusPrinter();
  }

  Future<String?> CetakPenjualan(
    String uuidPenjualan, {
    bool cetakUlang = false,
    String? namaPelanggan,
    String? labelPoin,
  }) => _Jalankan(
    (l) => l.CetakPenjualan(uuidPenjualan, cetakUlang: cetakUlang, namaPelanggan: namaPelanggan, labelPoin: labelPoin),
  );

  /// Cetak otomatis setelah bayar (plus buka laci bila tunai), sekali per transaksi walau layar selesai dibangun ulang.
  /// Printer belum diatur/otomatis mati = tidak mencetak dan keadaan tidak berubah.
  Future<({bool dicetak, String? galat})> CetakSetelahBayar(
    String uuidPenjualan, {
    String? namaPelanggan,
    String? labelPoin,
  }) async {
    if (!_sudahOtomatis.add(uuidPenjualan) || !await ref.read(penyediaLayananStruk).CekCetakOtomatis()) {
      return (dicetak: false, galat: null);
    }
    final galat = await _Jalankan(
      (l) => l.CetakSetelahBayar(uuidPenjualan, namaPelanggan: namaPelanggan, labelPoin: labelPoin),
    );
    return (dicetak: galat == null, galat: galat);
  }

  final Set<String> _sudahOtomatis = {};

  /// Cetak dokumen kasir selain struk penjualan (bukti void, nota retur, laporan shift); galat → pesan untuk kasir.
  Future<String?> CetakDokumen(Future<void> Function(LayananStruk layanan) aksi) => _Jalankan(aksi);

  /// Cetak otomatis sekali per dokumen [kunci] (misal Uuid retur) bila cetak otomatis aktif. Null = tidak dicetak.
  Future<({bool dicetak, String? galat})> CetakDokumenOtomatis(
    String kunci,
    Future<void> Function(LayananStruk layanan) aksi,
  ) async {
    if (!_sudahOtomatis.add(kunci) || !await ref.read(penyediaLayananStruk).CekCetakOtomatis()) {
      return (dicetak: false, galat: null);
    }
    final galat = await _Jalankan(aksi);
    return (dicetak: galat == null, galat: galat);
  }

  /// Cetak uji untuk isian yang mungkin belum disimpan: keadaan bilah status tidak diubah.
  Future<String?> CetakUji(ProfilPrinter profil) async {
    try {
      await ref.read(penyediaLayananStruk).CetakUji(profil);
      return null;
    } on GalatPrinter catch (galat) {
      return galat.pesan;
    }
  }

  Future<String?> _Jalankan(Future<void> Function(LayananStruk layanan) aksi) async {
    state = StatusPrinter(profil: state.profil, keadaan: KeadaanPrinter.Mencetak);
    try {
      await aksi(ref.read(penyediaLayananStruk));
      state = StatusPrinter(
        profil: state.profil,
        keadaan: state.profil == null ? KeadaanPrinter.BelumDiatur : KeadaanPrinter.Siap,
      );
      return null;
    } on GalatPrinter catch (galat) {
      state = StatusPrinter(profil: state.profil, keadaan: KeadaanPrinter.Gagal, pesan: galat.pesan);
      return galat.pesan;
    } on GalatKasir catch (galat) {
      state = StatusPrinter(profil: state.profil, keadaan: KeadaanPrinter.Gagal, pesan: galat.pesan);
      return galat.pesan;
    }
  }
}

final penyediaPrinter = NotifierProvider<PengaturPrinter, StatusPrinter>(PengaturPrinter.new);

/// F-12 bagian 2: pre-order + uang muka dari perangkat ini.
final penyediaRepositoriPreOrder = Provider<RepositoriPreOrder>(
  (ref) => RepositoriPreOrder(ref.watch(penyediaBasisData), ref.watch(penyediaRepositori)),
);

final penyediaLayananPreOrder = Provider<LayananPreOrder>(
  (ref) => LayananPreOrder(
    klien: ref.watch(penyediaKlienPos),
    repositori: ref.watch(penyediaRepositori),
    repositoriPreOrder: ref.watch(penyediaRepositoriPreOrder),
    penjualan: ref.watch(penyediaLayananPenjualan),
    jam: ref.watch(penyediaJam),
  ),
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

/// F-18: kamera swafoto absensi (tiruan di test).
final penyediaKameraSwafoto = Provider<KameraSwafoto>((ref) => KameraSwafotoPlatform());

final penyediaRepositoriAbsensi = Provider<RepositoriAbsensi>(
  (ref) => RepositoriAbsensi(ref.watch(penyediaBasisData), ref.watch(penyediaRepositori)),
);

/// F-18: absen masuk/keluar staf dengan PIN + swafoto.
final penyediaLayananAbsensi = Provider<LayananAbsensi>(
  (ref) => LayananAbsensi(
    repositori: ref.watch(penyediaRepositoriAbsensi),
    kamera: ref.watch(penyediaKameraSwafoto),
    jam: ref.watch(penyediaJam),
  ),
);

final penyediaRepositoriPelanggan = Provider<RepositoriPelanggan>(
  (ref) => RepositoriPelanggan(ref.watch(penyediaBasisData), ref.watch(penyediaRepositori)),
);

/// F-16c bagian 2: voucher keranjang (wajib online).
final penyediaLayananVoucher = Provider<LayananVoucher>((ref) => LayananVoucher(klien: ref.watch(penyediaKlienPos)));

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

/// F-18: staf pelayan baris penjualan (komisi) dari data awal.
final penyediaKaryawanPos = FutureProvider<List<KaryawanPos>>((ref) => ref.watch(penyediaRepositori).AmbilKaryawan());

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

/// P-10 (§14.6): konfigurasi aplikasi terakhir dari server (versi terbaru/minimal, catatan rilis, flag fitur). Null =
/// belum pernah berhasil dibaca (offline): aplikasi tetap berjalan. Diperiksa saat data disegarkan dan paling sering
/// tiap [selangPeriksa] dari putaran sinkron.
class PengaturKonfigurasiAplikasi extends Notifier<KonfigurasiAplikasi?> {
  static const Duration selangPeriksa = Duration(minutes: 15);

  DateTime? _terakhir;

  @override
  KonfigurasiAplikasi? build() => null;

  Future<void> Periksa({bool paksa = false}) async {
    final sekarang = ref.read(penyediaJam)();
    final terakhir = _terakhir;
    if (!paksa && terakhir != null && sekarang.difference(terakhir) < selangPeriksa) {
      return;
    }
    _terakhir = sekarang;
    try {
      state = await ref.read(penyediaKlienPos).AmbilKonfigurasiAplikasi();
    } on GalatJaringan {
      _terakhir = null;
    } on GalatApi {
      // Perangkat dicabut / galat server: ditangani alur sinkron; konfigurasi lama tetap dipakai.
    }
  }
}

final penyediaKonfigurasiAplikasi = NotifierProvider<PengaturKonfigurasiAplikasi, KonfigurasiAplikasi?>(
  PengaturKonfigurasiAplikasi.new,
);

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
    ref.invalidate(penyediaKaryawanPos);
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
      ref.invalidate(penyediaKaryawanPos);
      ref.invalidate(penyediaKategori);
      ref.invalidate(penyediaKonteksPenjualan);
      ref.invalidate(penyediaIdentitas);
      if (tersambung) {
        await PerbaruiKatalog();
        await PerbaruiDataMeja();
        await ref.read(penyediaKonfigurasiAplikasi.notifier).Periksa(paksa: true);
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
    } else if (hasil.tersambung != false) {
      // P-10: versi & flag diperiksa paling sering tiap 15 menit, tidak saat offline.
      await ref.read(penyediaKonfigurasiAplikasi.notifier).Periksa();
    }
    return hasil;
  }

  void _Dicabut(String pesan) {
    ref.invalidate(penyediaStaf);
    ref.invalidate(penyediaKaryawanPos);
    state = KeadaanSesi(TahapSesi.BelumAktif, pesan: pesan);
  }
}

final penyediaSesi = NotifierProvider<PengaturSesi, KeadaanSesi>(PengaturSesi.new);
