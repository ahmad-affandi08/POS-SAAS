import 'dart:async';
import 'dart:io';
import 'dart:ui';

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
import 'Domain/Diagnostik/LogLokal.dart';
import 'Domain/Perangkat/IsiAktivasi.dart';

/// Inisialisasi bersama semua flavor lalu menjalankan aplikasi (PRD §17.2.1): basis data lokal di folder data
/// aplikasi (bukan folder dokumen pengguna), terenkripsi dengan kunci di secure storage (K-7, §17.2.6), lalu
/// `ProviderScope`. K-21: galat Flutter & galat async yang tak tertangkap dicatat ke log lokal (tersaring dari data
/// pribadi) lalu dikirim ke server saat sinkron.
Future<void> JalankanAplikasi(Lingkungan lingkungan) async {
  WidgetsFlutterBinding.ensureInitialized();
  DaftarkanLisensiFont();
  final folder = await getApplicationSupportDirectory();
  final rahasia = PenyimpanRahasiaAman();
  final kunci = await EnkripsiBasisData.AmbilAtauBuatKunci(rahasia);
  // D-35: server toko sendiri (edisi Lisensi) yang tersimpan saat aktivasi sebelumnya.
  final alamatServer = IsiAktivasi.NormalkanAlamat(await rahasia.Baca(PenyimpanRahasia.kunciAlamatServer) ?? '');
  final basisData = BasisDataKasir(EnkripsiBasisData.Buka(File(jalur.join(folder.path, 'Kasir.sqlite')), kunci));
  final log = LogLokal(folder: folder);
  PasangPencatatGalat(log);

  runApp(
    ProviderScope(
      overrides: [
        penyediaBasisData.overrideWithValue(basisData),
        penyediaFolderAplikasi.overrideWithValue(folder),
        penyediaLogLokal.overrideWithValue(log),
        penyediaLingkungan.overrideWithValue(lingkungan),
        penyediaAlamatServerAwal.overrideWithValue(alamatServer),
        penyediaPlatform.overrideWithValue(AmbilPlatform()),
      ],
      child: AplikasiKasir(lingkungan: lingkungan),
    ),
  );
}

/// K-21: catat galat framework Flutter dan galat async tak tertangkap ke [log] (galat tetap ditampilkan seperti biasa
/// di mode debug).
void PasangPencatatGalat(LogLokal log) {
  final sebelumnya = FlutterError.onError;
  FlutterError.onError = (detail) {
    unawaited(log.Catat(TingkatLog.galat, 'Flutter', detail.exceptionAsString(), jejak: detail.stack));
    sebelumnya?.call(detail);
  };
  PlatformDispatcher.instance.onError = (galat, jejak) {
    unawaited(log.Catat(TingkatLog.galat, 'Async', galat, jejak: jejak));
    return true;
  };
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
