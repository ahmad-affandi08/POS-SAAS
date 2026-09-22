import 'package:flutter/widgets.dart';
import 'package:sistem_desain/SistemDesain.dart';

import 'Aplikasi/AplikasiPemilik.dart';
import 'Aplikasi/Lingkungan.dart';

/// Inisialisasi bersama semua flavor lalu menjalankan aplikasi (PRD §17.2.1).
void JalankanAplikasi(Lingkungan lingkungan) {
  WidgetsFlutterBinding.ensureInitialized();
  DaftarkanLisensiFont();
  runApp(AplikasiPemilik(lingkungan: lingkungan));
}
