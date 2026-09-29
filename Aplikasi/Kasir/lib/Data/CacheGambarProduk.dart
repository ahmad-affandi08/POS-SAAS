import 'dart:io';
import 'dart:typed_data';

import 'package:klien_api/KlienApi.dart';

/// Cache gambar katalog. Memori dipakai selama aplikasi hidup; bila [folderAplikasi] tersedia, byte juga disimpan
/// antarsesi agar foto yang pernah dibuka tetap tampil saat kasir offline. Versi pada URL menjadi bagian nama berkas.
class CacheGambarProduk {
  CacheGambarProduk({required this.klien, this.folderAplikasi});

  final KlienPos klien;
  final Directory? folderAplikasi;
  final Map<String, Uint8List> _memori = {};

  Future<Uint8List?> Ambil(String url) async {
    final tersimpan = _memori[url];
    if (tersimpan != null) {
      return tersimpan;
    }

    final berkas = _Berkas(url);
    if (berkas != null) {
      try {
        if (await berkas.exists()) {
          final byte = await berkas.readAsBytes();
          _memori[url] = byte;
          return byte;
        }
      } on FileSystemException {
        // Cache rusak/tidak terbaca tidak boleh menghalangi unduhan baru.
      }
    }

    try {
      final byte = await klien.AmbilGambarProduk(url);
      if (byte == null || byte.isEmpty) {
        return null;
      }
      _memori[url] = byte;
      if (berkas != null) {
        try {
          await berkas.parent.create(recursive: true);
          await berkas.writeAsBytes(byte, flush: true);
        } on FileSystemException {
          // Gambar tetap bisa dipakai dari memori walau penyimpanan perangkat sedang tidak dapat ditulis.
        }
      }
      return byte;
    } on GalatApi {
      return null;
    } on GalatJaringan {
      return null;
    }
  }

  File? _Berkas(String url) {
    final folder = folderAplikasi;
    final alamat = Uri.tryParse(url);
    if (folder == null || alamat == null || alamat.pathSegments.isEmpty) {
      return null;
    }
    final produk = _Aman(alamat.pathSegments.last);
    final versi = _Aman(alamat.queryParameters['versi'] ?? 'tanpa-versi');
    return File('${folder.path}${Platform.pathSeparator}GambarProduk${Platform.pathSeparator}$produk-$versi.img');
  }

  static String _Aman(String nilai) => nilai.replaceAll(RegExp('[^A-Za-z0-9_-]'), '_');
}
