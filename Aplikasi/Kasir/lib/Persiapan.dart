import 'dart:io';

import 'package:drift/native.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path/path.dart' as jalur;
import 'package:path_provider/path_provider.dart';
import 'package:sistem_desain/SistemDesain.dart';

import 'Aplikasi/AplikasiKasir.dart';
import 'Aplikasi/Lingkungan.dart';
import 'Aplikasi/Penyedia.dart';
import 'Data/BasisData/BasisDataKasir.dart';

/// Inisialisasi bersama semua flavor lalu menjalankan aplikasi (PRD §17.2.1): basis data lokal di folder data
/// aplikasi (bukan folder dokumen pengguna), lalu `ProviderScope`.
Future<void> JalankanAplikasi(Lingkungan lingkungan) async {
  WidgetsFlutterBinding.ensureInitialized();
  DaftarkanLisensiFont();
  final folder = await getApplicationSupportDirectory();
  final basisData = BasisDataKasir(NativeDatabase.createInBackground(File(jalur.join(folder.path, 'Kasir.sqlite'))));

  runApp(
    ProviderScope(
      overrides: [
        penyediaBasisData.overrideWithValue(basisData),
        penyediaLingkungan.overrideWithValue(lingkungan),
        penyediaPlatform.overrideWithValue(AmbilPlatform()),
      ],
      child: AplikasiKasir(lingkungan: lingkungan),
    ),
  );
}

/// Nama platform untuk aktivasi (`PlatformPerangkat` server).
String AmbilPlatform() {
  if (Platform.isIOS) {
    return 'Ios';
  }
  if (Platform.isWindows) {
    return 'Windows';
  }
  return 'Android';
}
