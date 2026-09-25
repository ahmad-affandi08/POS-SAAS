import 'dart:convert';
import 'dart:typed_data';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/PenyimpanRahasia.dart';
import '../../Data/RepositoriKasir.dart';
import '../GalatKasir.dart';

/// Aktivasi perangkat dan data awal (F-02 langkah 5, F-06). Token & kunci PIN offline masuk secure storage;
/// identitas perangkat & outlet (bukan rahasia) masuk tabel `Pengaturan`.
class LayananPerangkat {
  LayananPerangkat({
    required this.klien,
    required this.repositori,
    required this.rahasia,
    required this.platform,
    DateTime Function()? jam,
    this.ubahLogo,
  }) : _jam = jam ?? DateTime.now;

  final KlienPos klien;
  final RepositoriKasir repositori;
  final PenyimpanRahasia rahasia;
  final String platform;
  final DateTime Function() _jam;

  /// PRD v1.79: dekode logo usaha ke 1 bit untuk struk (null = logo tidak diunduh, misal di test domain).
  final Future<GambarMonokrom?> Function(Uint8List byte)? ubahLogo;

  Future<bool> CekSudahAktif() async => (await rahasia.Baca(PenyimpanRahasia.kunciToken)) != null;

  Future<void> Aktifkan(String kode) async {
    if (!RegExp(r'^[A-Za-z0-9]{6,20}$').hasMatch(kode.trim())) {
      throw const GalatKasir('KodeTidakValid', 'Masukkan kode aktivasi dari back-office menu Perangkat.');
    }

    final HasilAktivasi hasil;
    try {
      hasil = await klien.AktifkanPerangkat(kode: kode, platform: platform);
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    } on GalatJaringan {
      throw const GalatKasir('Offline', 'Aktivasi butuh internet. Sambungkan perangkat lalu coba lagi.');
    }

    await rahasia.Tulis(PenyimpanRahasia.kunciToken, hasil.tokenPerangkat);
    if (hasil.kunciPinOffline != null) {
      await rahasia.Tulis(PenyimpanRahasia.kunciPin, hasil.kunciPinOffline!);
    }
    await repositori.SimpanPengaturan(KunciPengaturan.uuidPerangkat, hasil.uuidPerangkat);
    await repositori.SimpanPengaturan(KunciPengaturan.kodePerangkat, hasil.kodePerangkat);
    await repositori.SimpanPengaturan(KunciPengaturan.namaPerangkat, hasil.namaPerangkat);
    await repositori.SimpanPengaturan(KunciPengaturan.namaOutlet, hasil.namaOutlet);
    await repositori.SimpanPengaturan(KunciPengaturan.uuidOutlet, hasil.uuidOutlet);
    await repositori.SimpanPengaturan(KunciPengaturan.namaUsaha, hasil.namaUsaha);
    await repositori.SimpanPengaturan(KunciPengaturan.jenisPerangkat, hasil.jenisPerangkat);
    await SegarkanDataAwal();
  }

  /// Unduh data awal terbaru. Offline = pakai data lokal terakhir (tidak melempar). Perangkat dicabut = hapus data
  /// sensitif lalu lempar `PerangkatDicabut`.
  Future<bool> SegarkanDataAwal() async {
    try {
      final data = await klien.AmbilDataAwal();
      await repositori.SimpanDataAwal(data, _jam());
      await _SegarkanLogo(data.struk);
      return true;
    } on GalatJaringan {
      return false;
    } on GalatApi catch (galat) {
      if (galat.CekPerangkatDitolak()) {
        await CabutLokal();
        throw GalatKasir(galat.kode, galat.pesan);
      }
      rethrow;
    }
  }

  /// Logo struk disimpan 1 bit di `Pengaturan` agar bisa dicetak offline. Gagal unduh/dekode = logo lama dipakai
  /// (struk tetap tercetak tanpa atau dengan logo lama); logo dimatikan/dihapus = logo lokal dihapus.
  Future<void> _SegarkanLogo(StrukPos? struk) async {
    final ubah = ubahLogo;
    if (ubah == null || struk == null) {
      return;
    }
    if (!struk.adaLogo) {
      await repositori.SimpanPengaturan(KunciPengaturan.logoStruk, '');
      return;
    }
    try {
      final byte = await klien.AmbilLogoStruk();
      final gambar = byte == null ? null : await ubah(byte);
      if (byte == null || gambar != null) {
        await repositori.SimpanPengaturan(KunciPengaturan.logoStruk, gambar == null ? '' : jsonEncode(gambar.KeJson()));
      }
    } on GalatJaringan {
      return;
    } on GalatApi {
      return;
    }
  }

  /// Hapus rahasia & data PIN lokal (perangkat dicabut). Transaksi yang belum terkirim tidak dihapus.
  Future<void> CabutLokal() async {
    await rahasia.HapusSemua();
    await repositori.HapusDataSensitif();
  }
}
