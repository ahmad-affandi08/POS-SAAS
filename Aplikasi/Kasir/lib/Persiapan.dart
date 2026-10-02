import 'dart:io';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path/path.dart' as jalur;
import 'package:path_provider/path_provider.dart';
import 'package:sistem_desain/SistemDesain.dart';

import 'Aplikasi/AplikasiKasir.dart';
import 'Aplikasi/Lingkungan.dart';
import 'Aplikasi/Penyedia.dart';
import 'Data/BasisData/BasisDataKasir.dart';
import 'Data/BasisData/EnkripsiBasisData.dart';
import 'Data/PenyimpanRahasia.dart';

/// Inisialisasi bersama semua flavor lalu menjalankan aplikasi (PRD §17.2.1): basis data lokal di folder data
/// aplikasi (bukan folder dokumen pengguna), terenkripsi dengan kunci di secure storage (K-7, §17.2.6), lalu
/// `ProviderScope`.
Future<void> JalankanAplikasi(Lingkungan lingkungan) async {
  WidgetsFlutterBinding.ensureInitialized();
  DaftarkanLisensiFont();
  final folder = await getApplicationSupportDirectory();
  final kunci = await EnkripsiBasisData.AmbilAtauBuatKunci(PenyimpanRahasiaAman());
  final basisData = BasisDataKasir(EnkripsiBasisData.Buka(File(jalur.join(folder.path, 'Kasir.sqlite')), kunci));

  runApp(
    ProviderScope(
      overrides: [
        penyediaBasisData.overrideWithValue(basisData),
        penyediaFolderAplikasi.overrideWithValue(folder),
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
