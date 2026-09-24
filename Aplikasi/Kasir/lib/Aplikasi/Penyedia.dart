import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:klien_api/KlienApi.dart';

import '../Data/BasisData/BasisDataKasir.dart';
import '../Data/PenyimpanRahasia.dart';
import '../Data/RepositoriKasir.dart';
import '../Domain/GalatKasir.dart';
import '../Domain/Pin/PemverifikasiPinOffline.dart';
import '../Domain/Sesi/LayananMasuk.dart';
import '../Domain/Sesi/LayananPerangkat.dart';
import '../Domain/Sesi/StafLokal.dart';
import '../Domain/Shift/LayananShift.dart';
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

final penyediaLayananSinkron = Provider<LayananSinkron>(
  (ref) => LayananSinkron(
    klien: ref.watch(penyediaKlienPos),
    repositori: ref.watch(penyediaRepositori),
    perangkat: ref.watch(penyediaLayananPerangkat),
    jam: ref.watch(penyediaJam),
  ),
);

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

enum TahapSesi { Memuat, BelumAktif, PilihKasir, Masuk }

class KeadaanSesi {
  const KeadaanSesi(this.tahap, {this.kasir, this.pesan});

  final TahapSesi tahap;
  final StafLokal? kasir;

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
    state = const KeadaanSesi(TahapSesi.PilihKasir);
  }

  Future<void> Masuk(StafLokal staf, String pin) async {
    final kasir = await ref.read(penyediaLayananMasuk).Masuk(staf, pin);
    state = KeadaanSesi(TahapSesi.Masuk, kasir: kasir);
  }

  void Keluar() => state = const KeadaanSesi(TahapSesi.PilihKasir);

  /// Perbarui data awal (staf, kategori, pengaturan) bila online; perangkat dicabut → kembali ke aktivasi.
  Future<void> SegarkanData() async {
    try {
      await ref.read(penyediaLayananPerangkat).SegarkanDataAwal();
      ref.invalidate(penyediaStaf);
      ref.invalidate(penyediaKategori);
    } on GalatKasir catch (galat) {
      _Dicabut(galat.pesan);
    } on GalatApi {
      // Galat server lain: tetap pakai data lokal terakhir.
    }
  }

  /// Kirim outbox; perangkat dicabut → kembali ke aktivasi.
  Future<RingkasanSinkron> Sinkronkan() async {
    final hasil = await ref.read(penyediaLayananSinkron).KirimTertunda();
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
