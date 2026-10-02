import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// K-15 (§17.2.7 prinsip 4): umpan balik pindai — bunyi pendek + getar. Berhasil = klik + getar ringan; gagal =
/// bunyi peringatan + getar kuat. Platform tanpa bunyi/getar (desktop) mengabaikannya tanpa galat. Diganti di test
/// untuk mencatat panggilan.
class UmpanBalikPindai {
  const UmpanBalikPindai();

  Future<void> Berhasil() async {
    await SystemSound.play(SystemSoundType.click);
    await HapticFeedback.lightImpact();
  }

  Future<void> Gagal() async {
    await SystemSound.play(SystemSoundType.alert);
    await HapticFeedback.heavyImpact();
  }
}

final penyediaUmpanBalikPindai = Provider<UmpanBalikPindai>((ref) => const UmpanBalikPindai());
