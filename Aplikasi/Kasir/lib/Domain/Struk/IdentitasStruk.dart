import 'dart:convert';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/RepositoriKasir.dart';

/// Semua yang dibutuhkan kepala & kaki struk, dibaca dari data awal lokal (bisa offline): pengaturan struk tenant,
/// nama usaha, identitas outlet, dan logo 1 bit.
class IdentitasStruk {
  const IdentitasStruk({
    required this.pengaturan,
    required this.namaUsaha,
    this.namaOutlet,
    this.alamat,
    this.telepon,
    this.logo,
  });

  final StrukPos pengaturan;
  final String namaUsaha;
  final String? namaOutlet;
  final String? alamat;
  final String? telepon;
  final GambarMonokrom? logo;

  static Future<IdentitasStruk> Muat(RepositoriKasir repositori) async {
    String? Teks(String? nilai) => nilai == null || nilai.trim().isEmpty ? null : nilai.trim();
    Object? Json(String? teks) {
      if (teks == null || teks.isEmpty) {
        return null;
      }
      try {
        return jsonDecode(teks);
      } on FormatException {
        return null;
      }
    }

    final pengaturan =
        StrukPos.DariJson(Json(await repositori.AmbilPengaturan(KunciPengaturan.struk))) ?? const StrukPos();
    return IdentitasStruk(
      pengaturan: pengaturan,
      namaUsaha: Teks(pengaturan.namaUsaha) ?? Teks(await repositori.AmbilPengaturan(KunciPengaturan.namaUsaha)) ?? '',
      namaOutlet: Teks(await repositori.AmbilPengaturan(KunciPengaturan.namaOutlet)),
      alamat: Teks(await repositori.AmbilPengaturan(KunciPengaturan.alamatOutlet)),
      telepon: Teks(await repositori.AmbilPengaturan(KunciPengaturan.teleponOutlet)),
      logo: pengaturan.adaLogo
          ? GambarMonokrom.DariJson(Json(await repositori.AmbilPengaturan(KunciPengaturan.logoStruk)))
          : null,
    );
  }
}
