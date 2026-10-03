import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Sesi Aplikasi Owner di secure storage (Android Keystore, iOS Keychain): user token, tenant aktif, nama pengguna.
/// Tidak pernah ditulis ke log atau basis data lokal (PRD §17.2.6).
abstract class PenyimpanSesi {
  static const String kunciToken = 'TokenPengguna';
  static const String kunciTenant = 'UuidTenant';
  static const String kunciNamaTenant = 'NamaTenant';
  static const String kunciNamaPengguna = 'NamaPengguna';

  /// D-35 edisi Lisensi: alamat server toko sendiri (kosong = alamat bawaan build). Bertahan saat keluar
  /// ([HapusSemua]) supaya pemilik tidak perlu mengetiknya lagi.
  static const String kunciAlamatServer = 'AlamatServer';

  Future<String?> Baca(String kunci);

  Future<void> Tulis(String kunci, String nilai);

  Future<void> HapusSemua();
}

class PenyimpanSesiAman implements PenyimpanSesi {
  PenyimpanSesiAman([FlutterSecureStorage? penyimpan]) : _penyimpan = penyimpan ?? const FlutterSecureStorage();

  final FlutterSecureStorage _penyimpan;

  @override
  Future<String?> Baca(String kunci) => _penyimpan.read(key: kunci);

  @override
  Future<void> Tulis(String kunci, String nilai) => _penyimpan.write(key: kunci, value: nilai);

  @override
  Future<void> HapusSemua() async {
    for (final kunci in (await _penyimpan.readAll()).keys) {
      if (kunci != PenyimpanSesi.kunciAlamatServer) {
        await _penyimpan.delete(key: kunci);
      }
    }
  }
}

/// Untuk test & pratinjau.
class PenyimpanSesiMemori implements PenyimpanSesi {
  final Map<String, String> isi = {};

  @override
  Future<String?> Baca(String kunci) async => isi[kunci];

  @override
  Future<void> Tulis(String kunci, String nilai) async => isi[kunci] = nilai;

  @override
  Future<void> HapusSemua() async => isi.removeWhere((kunci, _) => kunci != PenyimpanSesi.kunciAlamatServer);
}
