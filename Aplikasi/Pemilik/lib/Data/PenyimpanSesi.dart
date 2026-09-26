import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Sesi Aplikasi Owner di secure storage (Android Keystore, iOS Keychain): user token, tenant aktif, nama pengguna.
/// Tidak pernah ditulis ke log atau basis data lokal (PRD §17.2.6).
abstract class PenyimpanSesi {
  static const String kunciToken = 'TokenPengguna';
  static const String kunciTenant = 'UuidTenant';
  static const String kunciNamaTenant = 'NamaTenant';
  static const String kunciNamaPengguna = 'NamaPengguna';

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
  Future<void> HapusSemua() => _penyimpan.deleteAll();
}

/// Untuk test & pratinjau.
class PenyimpanSesiMemori implements PenyimpanSesi {
  final Map<String, String> isi = {};

  @override
  Future<String?> Baca(String kunci) async => isi[kunci];

  @override
  Future<void> Tulis(String kunci, String nilai) async => isi[kunci] = nilai;

  @override
  Future<void> HapusSemua() async => isi.clear();
}
