import 'package:klien_api/KlienApi.dart';

import '../../Data/RepositoriKasir.dart';
import '../../Data/RepositoriKatalog.dart';

/// Hasil satu kali perbarui katalog.
enum HasilPerbaruiKatalog { Lengkap, Delta, Offline, Gagal }

/// Unduh katalog dari `/api/pos/v1/katalog` (Rincian F-07c): pertama kali lengkap, selanjutnya delta dengan kursor
/// yang disimpan di `Pengaturan`. Kursor ditolak server (`KursorTidakValid`) → ulang sekali tanpa kursor. Dipanggil
/// saat masuk, lewat tombol "Perbarui katalog", dan berkala 60 detik saat online (PRD §18.3 no. 8).
class LayananKatalog {
  LayananKatalog({
    required this.klien,
    required this.repositori,
    required this.repositoriKatalog,
    DateTime Function()? jam,
  }) : _jam = jam ?? DateTime.now;

  final KlienPos klien;
  final RepositoriKasir repositori;
  final RepositoriKatalog repositoriKatalog;
  final DateTime Function() _jam;

  bool _berjalan = false;

  Future<HasilPerbaruiKatalog> Perbarui() async {
    if (_berjalan) {
      return HasilPerbaruiKatalog.Delta;
    }
    _berjalan = true;
    try {
      final kursor = await repositori.AmbilPengaturan(KunciPengaturan.kursorKatalog);
      KatalogPos katalog;
      try {
        katalog = await klien.AmbilKatalog(kursor: kursor == null || kursor.isEmpty ? null : kursor);
      } on GalatApi catch (galat) {
        if (galat.kode != 'KursorTidakValid' || kursor == null) {
          rethrow;
        }
        katalog = await klien.AmbilKatalog();
      }

      // Tanpa kursor sebelumnya, respons selalu diperlakukan sebagai katalog lengkap.
      final lengkap = katalog.lengkap || kursor == null || kursor.isEmpty;
      if (lengkap) {
        await repositoriKatalog.GantiKatalog(katalog);
      } else {
        await repositoriKatalog.TerapkanDelta(katalog);
      }
      if (katalog.kursor != null) {
        await repositori.SimpanPengaturan(KunciPengaturan.kursorKatalog, katalog.kursor!);
      }
      await repositori.SimpanPengaturan(KunciPengaturan.katalogDiperbaruiPada, _jam().toUtc().toIso8601String());
      return lengkap ? HasilPerbaruiKatalog.Lengkap : HasilPerbaruiKatalog.Delta;
    } on GalatJaringan {
      return HasilPerbaruiKatalog.Offline;
    } on GalatApi {
      return HasilPerbaruiKatalog.Gagal;
    } finally {
      _berjalan = false;
    }
  }
}
