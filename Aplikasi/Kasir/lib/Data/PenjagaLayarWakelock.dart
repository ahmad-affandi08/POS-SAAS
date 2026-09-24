import 'package:wakelock_plus/wakelock_plus.dart';

import '../Domain/Perangkat/PenjagaLayarMenyala.dart';

/// Implementasi [PenjagaLayarMenyala] dengan paket `wakelock_plus` (Android, iOS, Windows).
class PenjagaLayarWakelock implements PenjagaLayarMenyala {
  const PenjagaLayarWakelock();

  @override
  Future<void> Aktifkan() async {
    try {
      await WakelockPlus.enable();
    } on Object {
      // Platform tanpa dukungan: layar mengikuti pengaturan sistem.
    }
  }

  @override
  Future<void> Nonaktifkan() async {
    try {
      await WakelockPlus.disable();
    } on Object {
      // Abaikan, lihat Aktifkan().
    }
  }
}
