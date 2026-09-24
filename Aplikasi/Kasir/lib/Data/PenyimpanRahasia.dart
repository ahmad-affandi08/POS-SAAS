import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Rahasia perangkat (token perangkat, kunci PIN offline) di secure storage: Android Keystore, iOS Keychain, Windows
/// DPAPI (PRD §17.2.6, §25.2 no. 3). Tidak pernah ditulis ke basis data lokal atau log.
abstract class PenyimpanRahasia {
  static const String kunciToken = 'TokenPerangkat';
  static const String kunciPin = 'KunciPinOffline';

  Future<String?> Baca(String kunci);

  Future<void> Tulis(String kunci, String nilai);

  Future<void> HapusSemua();
}

class PenyimpanRahasiaAman implements PenyimpanRahasia {
  PenyimpanRahasiaAman([FlutterSecureStorage? penyimpan]) : _penyimpan = penyimpan ?? const FlutterSecureStorage();

  final FlutterSecureStorage _penyimpan;

  @override
  Future<String?> Baca(String kunci) => _penyimpan.read(key: kunci);

  @override
  Future<void> Tulis(String kunci, String nilai) => _penyimpan.write(key: kunci, value: nilai);

  @override
  Future<void> HapusSemua() => _penyimpan.deleteAll();
}

/// Untuk test & pratinjau: rahasia di memori.
class PenyimpanRahasiaMemori implements PenyimpanRahasia {
  final Map<String, String> isi = {};

  @override
  Future<String?> Baca(String kunci) async => isi[kunci];

  @override
  Future<void> Tulis(String kunci, String nilai) async => isi[kunci] = nilai;

  @override
  Future<void> HapusSemua() async => isi.clear();
}
