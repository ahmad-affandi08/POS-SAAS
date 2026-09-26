import 'dart:convert';

import 'package:klien_api/KlienApi.dart';

import '../../Data/RepositoriKasir.dart';
import '../Struk/ProfilPrinter.dart';

/// Hasil satu langkah Wizard Uji Perangkat.
enum HasilUji { Lolos, Gagal, Dilewati }

/// Hasil Wizard Uji Perangkat (PRD §17.2.5a, v1.96): cetak, potong kertas, laci, pemindai. null = belum diuji.
class HasilUjiPerangkat {
  const HasilUjiPerangkat({this.cetak, this.potong, this.laci, this.pemindai});

  final HasilUji? cetak;
  final HasilUji? potong;
  final HasilUji? laci;
  final HasilUji? pemindai;

  bool get lengkap => cetak != null && potong != null && laci != null && pemindai != null;

  HasilUjiPerangkat Salin({HasilUji? cetak, HasilUji? potong, HasilUji? laci, HasilUji? pemindai}) => HasilUjiPerangkat(
    cetak: cetak ?? this.cetak,
    potong: potong ?? this.potong,
    laci: laci ?? this.laci,
    pemindai: pemindai ?? this.pemindai,
  );

  Map<String, Object?> KeJson() => {
    'Cetak': cetak?.name,
    'Potong': potong?.name,
    'Laci': laci?.name,
    'Pemindai': pemindai?.name,
  };
}

/// Merek, model, sistem operasi, dan adaptor hardware yang terdeteksi (tanpa data pribadi).
class InfoPerangkat {
  const InfoPerangkat({required this.produsen, required this.model, required this.sistem, required this.adaptor});

  final String produsen;
  final String model;
  final String sistem;

  /// `Sunmi`, `iMin`, atau `Generik` (PRD §17.2.5a).
  final String adaptor;
}

/// Sumber info perangkat per platform (implementasi nyata di `Data/Printer/`; test memakai tiruan).
abstract interface class SumberInfoPerangkat {
  Future<InfoPerangkat> Ambil();
}

/// Wizard Uji Perangkat (PRD §17.2.5a, v1.96): menyusun profil hardware (merek/model, printer & sambungannya, hasil uji)
/// lalu menyimpannya di perangkat dan melaporkannya ke server (`Perangkat.ProfilHardware`). Bila offline, laporan
/// ditandai tertunda dan dikirim ulang saat sinkron berikutnya.
class LayananUjiPerangkat {
  LayananUjiPerangkat({required this.repositori, required this.klien, required this.info, DateTime Function()? jam})
    : _jam = jam ?? DateTime.now;

  final RepositoriKasir repositori;
  final KlienPos klien;
  final SumberInfoPerangkat info;
  final DateTime Function() _jam;

  Future<Map<String, Object?>> SusunProfil(HasilUjiPerangkat hasil) async {
    final perangkat = await info.Ambil();
    final printer = await ProfilPrinter.Muat(repositori);
    return {
      'Produsen': perangkat.produsen,
      'Model': perangkat.model,
      'Sistem': perangkat.sistem,
      'Adaptor': perangkat.adaptor,
      'Printer': printer == null
          ? null
          : {'Jenis': printer.jenis.name, 'Nama': printer.nama ?? printer.alamat, 'Lebar': printer.lebar.label},
      'Uji': hasil.KeJson(),
      'DiujiPada': _jam().toUtc().toIso8601String(),
    };
  }

  /// Simpan hasil lalu coba kirim. true = sudah diterima server; false = tersimpan dan dikirim saat online.
  Future<bool> Simpan(HasilUjiPerangkat hasil) async {
    await repositori.SimpanPengaturan(KunciPengaturan.profilHardware, jsonEncode(await SusunProfil(hasil)));
    await repositori.SimpanPengaturan(KunciPengaturan.profilHardwareTertunda, '1');
    return KirimTertunda();
  }

  /// Kirim laporan yang tertunda (dipanggil juga oleh sinkron). true = tidak ada lagi yang tertunda.
  Future<bool> KirimTertunda() async {
    if (await repositori.AmbilPengaturan(KunciPengaturan.profilHardwareTertunda) != '1') {
      return true;
    }
    final teks = await repositori.AmbilPengaturan(KunciPengaturan.profilHardware);
    if (teks == null || teks.isEmpty) {
      return true;
    }
    try {
      await klien.KirimProfilHardware(jsonDecode(teks) as Map<String, Object?>);
    } on GalatJaringan {
      return false;
    } on GalatApi {
      return false;
    }
    await repositori.SimpanPengaturan(KunciPengaturan.profilHardwareTertunda, '');
    return true;
  }

  /// Hasil uji terakhir yang tersimpan di perangkat (untuk ditampilkan), null bila belum pernah diuji.
  Future<Map<String, Object?>?> AmbilTerakhir() async {
    final teks = await repositori.AmbilPengaturan(KunciPengaturan.profilHardware);
    if (teks == null || teks.isEmpty) {
      return null;
    }
    try {
      final json = jsonDecode(teks);
      return json is Map<String, Object?> ? json : null;
    } on FormatException {
      return null;
    }
  }
}
