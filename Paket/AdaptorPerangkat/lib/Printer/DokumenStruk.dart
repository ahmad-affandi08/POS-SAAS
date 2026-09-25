import 'dart:convert';
import 'dart:typed_data';

/// Lebar kertas thermal: jumlah kolom font printer (font A 12×24) dan lebar area cetak dalam titik (203 dpi).
enum LebarKertas {
  Mm58('58 mm', 32, 384),
  Mm80('80 mm', 48, 576);

  const LebarKertas(this.label, this.kolom, this.titik);

  final String label;
  final int kolom;
  final int titik;
}

enum RataStruk { Kiri, Tengah, Kanan }

/// Satu baris isi struk, netral terhadap printer.
sealed class BarisStruk {
  const BarisStruk();
}

/// Teks bebas; dipecah di spasi bila melebihi lebar kertas. [besar] = lebar & tinggi ganda (kolom jadi setengah).
class BarisTeks extends BarisStruk {
  const BarisTeks(this.teks, {this.rata = RataStruk.Kiri, this.tebal = false, this.besar = false});

  final String teks;
  final RataStruk rata;
  final bool tebal;
  final bool besar;
}

/// Label kiri dan nilai kanan dalam satu baris (misal "Subtotal ... 56.000"). Label panjang turun ke baris sendiri.
class BarisDuaKolom extends BarisStruk {
  const BarisDuaKolom(this.kiri, this.kanan, {this.tebal = false});

  final String kiri;
  final String kanan;
  final bool tebal;
}

/// Garis pemisah selebar kertas.
class BarisGaris extends BarisStruk {
  const BarisGaris([this.karakter = '-']);

  final String karakter;
}

class BarisKosong extends BarisStruk {
  const BarisKosong();
}

/// Kode QR di tengah (misal tautan struk digital).
class BarisQr extends BarisStruk {
  const BarisQr(this.data, {this.ukuranModul = 6});

  final String data;

  /// Ukuran satu modul QR dalam titik (1–16).
  final int ukuranModul;
}

/// Gambar hitam-putih di tengah (logo).
class BarisGambar extends BarisStruk {
  const BarisGambar(this.gambar);

  final GambarMonokrom gambar;
}

/// Isi satu struk. [bukaLaci] = kirim pulsa buka laci kas sebelum mencetak (pembayaran tunai).
class DokumenStruk {
  const DokumenStruk(this.baris, {this.bukaLaci = false, this.potong = true});

  final List<BarisStruk> baris;
  final bool bukaLaci;
  final bool potong;
}

/// Gambar 1 bit per titik: `titik[y * lebar + x]` bernilai 1 = hitam.
class GambarMonokrom {
  GambarMonokrom(this.lebar, this.tinggi, this.titik)
    : assert(lebar > 0 && tinggi > 0 && titik.length == lebar * tinggi);

  final int lebar;
  final int tinggi;
  final Uint8List titik;

  /// Bentuk simpan (JSON): `{Lebar, Tinggi, Titik}` dengan `Titik` = base64 bit terpadatkan (8 titik per byte).
  Map<String, Object?> KeJson() {
    final padat = Uint8List((titik.length + 7) ~/ 8);
    for (var i = 0; i < titik.length; i++) {
      if (titik[i] == 1) {
        padat[i >> 3] |= 0x80 >> (i & 7);
      }
    }
    return {'Lebar': lebar, 'Tinggi': tinggi, 'Titik': base64Encode(padat)};
  }

  /// Kebalikan [KeJson]; data rusak = null.
  static GambarMonokrom? DariJson(Object? json) {
    if (json is! Map<String, Object?>) {
      return null;
    }
    final lebar = json['Lebar'];
    final tinggi = json['Tinggi'];
    final teks = json['Titik'];
    if (lebar is! int || tinggi is! int || teks is! String || lebar <= 0 || tinggi <= 0) {
      return null;
    }
    final Uint8List padat;
    try {
      padat = base64Decode(teks);
    } on FormatException {
      return null;
    }
    if (padat.length != (lebar * tinggi + 7) ~/ 8) {
      return null;
    }
    final titik = Uint8List(lebar * tinggi);
    for (var i = 0; i < titik.length; i++) {
      titik[i] = (padat[i >> 3] >> (7 - (i & 7))) & 1;
    }
    return GambarMonokrom(lebar, tinggi, titik);
  }

  /// Dari piksel RGBA 8 bit (misal hasil dekode PNG): transparan = putih, lalu ambang luminans dengan dithering
  /// Floyd–Steinberg agar logo berwarna tetap terbaca di printer thermal. Diperkecil (tetangga terdekat) bila lebih
  /// lebar dari [lebarMaksimal] titik.
  static GambarMonokrom DariRgba(int lebar, int tinggi, Uint8List rgba, {int lebarMaksimal = 384}) {
    assert(rgba.length == lebar * tinggi * 4);
    final skala = lebar > lebarMaksimal ? lebarMaksimal / lebar : 1.0;
    final lebarBaru = (lebar * skala).floor().clamp(1, lebarMaksimal);
    final tinggiBaru = (tinggi * skala).floor().clamp(1, 1 << 15);
    final terang = List<int>.filled(lebarBaru * tinggiBaru, 255);
    for (var y = 0; y < tinggiBaru; y++) {
      final ySumber = (y / skala).floor().clamp(0, tinggi - 1);
      for (var x = 0; x < lebarBaru; x++) {
        final xSumber = (x / skala).floor().clamp(0, lebar - 1);
        final i = (ySumber * lebar + xSumber) * 4;
        final alfa = rgba[i + 3];
        // Luminans BT.601 dalam bilangan bulat, dicampur ke putih sesuai alfa.
        final luminans = (299 * rgba[i] + 587 * rgba[i + 1] + 114 * rgba[i + 2]) ~/ 1000;
        terang[y * lebarBaru + x] = (luminans * alfa + 255 * (255 - alfa)) ~/ 255;
      }
    }
    final titik = Uint8List(lebarBaru * tinggiBaru);
    for (var y = 0; y < tinggiBaru; y++) {
      for (var x = 0; x < lebarBaru; x++) {
        final i = y * lebarBaru + x;
        final lama = terang[i];
        final hitam = lama < 128;
        titik[i] = hitam ? 1 : 0;
        final galat = lama - (hitam ? 0 : 255);
        void Sebar(int dx, int dy, int bobot) {
          final nx = x + dx;
          final ny = y + dy;
          if (nx >= 0 && nx < lebarBaru && ny < tinggiBaru) {
            terang[ny * lebarBaru + nx] += galat * bobot ~/ 16;
          }
        }

        Sebar(1, 0, 7);
        Sebar(-1, 1, 3);
        Sebar(0, 1, 5);
        Sebar(1, 1, 1);
      }
    }
    return GambarMonokrom(lebarBaru, tinggiBaru, titik);
  }
}
