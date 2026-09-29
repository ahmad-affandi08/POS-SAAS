import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import 'Aplikasi/AplikasiPemilik.dart';
import 'Aplikasi/Lingkungan.dart';
import 'Aplikasi/Penyedia.dart';
import 'Data/NotifikasiPush.dart';

/// Inisialisasi bersama semua flavor lalu menjalankan aplikasi (PRD §17.2.1).
Future<void> JalankanAplikasi(Lingkungan lingkungan) async {
  WidgetsFlutterBinding.ensureInitialized();
  final push = await NotifikasiPushFirebase.Buat();
  DaftarkanLisensiFont();
  runApp(
    ProviderScope(
      overrides: [penyediaLingkungan.overrideWithValue(lingkungan), penyediaNotifikasiPush.overrideWithValue(push)],
      child: AplikasiPemilik(lingkungan: lingkungan),
    ),
  );
}
