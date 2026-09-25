import 'DokumenStruk.dart';

/// Satu baris hasil tata letak: teks sudah selebar kertas (spasi sebagai perataan) atau objek non-teks.
sealed class BarisCetak {
  const BarisCetak();
}

class BarisCetakTeks extends BarisCetak {
  const BarisCetakTeks(this.teks, {this.tebal = false, this.besar = false});

  /// Sudah dirapikan ke ASCII dan dipadatkan ke lebar kolom (setengah kolom bila [besar]).
  final String teks;
  final bool tebal;
  final bool besar;
}

class BarisCetakQr extends BarisCetak {
  const BarisCetakQr(this.data, this.ukuranModul);

  final String data;
  final int ukuranModul;
}

class BarisCetakGambar extends BarisCetak {
  const BarisCetakGambar(this.gambar);

  final GambarMonokrom gambar;
}

/// Tata letak struk ke kolom font printer. Dipakai pengode ESC/POS dan pratinjau di layar, sehingga keduanya sama.
abstract final class TataLetakStruk {
  static List<BarisCetak> Susun(DokumenStruk dokumen, LebarKertas lebar) => [
    for (final baris in dokumen.baris) ...SusunBaris(baris, lebar.kolom),
  ];

  /// Teks pratinjau (QR & gambar sebagai penanda) untuk layar dan test.
  static List<String> KeTeks(DokumenStruk dokumen, LebarKertas lebar) => [
    for (final baris in Susun(dokumen, lebar))
      switch (baris) {
        BarisCetakTeks(:final teks) => teks,
        BarisCetakQr(:final data) => '[QR $data]',
        BarisCetakGambar(:final gambar) => '[GAMBAR ${gambar.lebar}x${gambar.tinggi}]',
      },
  ];

  static List<BarisCetak> SusunBaris(BarisStruk baris, int kolom) => switch (baris) {
    BarisTeks(:final teks, :final rata, :final tebal, :final besar) => [
      for (final potongan in PecahBaris(RapikanTeks(teks), besar ? kolom ~/ 2 : kolom))
        BarisCetakTeks(Ratakan(potongan, besar ? kolom ~/ 2 : kolom, rata), tebal: tebal, besar: besar),
    ],
    BarisDuaKolom(:final kiri, :final kanan, :final tebal) => [
      for (final t in SusunDuaKolom(RapikanTeks(kiri), RapikanTeks(kanan), kolom)) BarisCetakTeks(t, tebal: tebal),
    ],
    BarisGaris(:final karakter) => [
      BarisCetakTeks((RapikanTeks(karakter).isEmpty ? '-' : RapikanTeks(karakter)[0]) * kolom),
    ],
    BarisKosong() => [const BarisCetakTeks('')],
    BarisQr(:final data, :final ukuranModul) => [BarisCetakQr(data, ukuranModul.clamp(1, 16))],
    BarisGambar(:final gambar) => [BarisCetakGambar(gambar)],
  };

  /// Pecah di spasi agar tiap baris ≤ [kolom]; kata yang lebih panjang dari kolom dipotong paksa.
  static List<String> PecahBaris(String teks, int kolom) {
    final hasil = <String>[];
    for (final paragraf in teks.split('\n')) {
      var sisa = paragraf.trim();
      if (sisa.isEmpty) {
        continue;
      }
      while (sisa.length > kolom) {
        final spasi = sisa.lastIndexOf(' ', kolom);
        final titik = spasi > 0 ? spasi : kolom;
        hasil.add(sisa.substring(0, titik).trimRight());
        sisa = sisa.substring(titik).trimLeft();
      }
      if (sisa.isNotEmpty) {
        hasil.add(sisa);
      }
    }
    return hasil;
  }

  static String Ratakan(String teks, int kolom, RataStruk rata) => switch (rata) {
    RataStruk.Kiri => teks,
    RataStruk.Kanan => teks.padLeft(kolom),
    RataStruk.Tengah => ' ' * ((kolom - teks.length) ~/ 2) + teks,
  };

  /// Kiri & kanan dalam satu baris; bila tidak muat, label kiri dipecah dan nilai kanan di baris terakhirnya.
  static List<String> SusunDuaKolom(String kiri, String kanan, int kolom) {
    final ruang = kolom - kanan.length - 1;
    if (ruang < 1) {
      return [...PecahBaris(kiri, kolom), kanan.padLeft(kolom)];
    }
    final potongan = PecahBaris(kiri, ruang);
    if (potongan.isEmpty) {
      return [kanan.padLeft(kolom)];
    }
    final akhir = potongan.removeLast();
    return [...potongan, akhir + ' ' * (kolom - akhir.length - kanan.length) + kanan];
  }

  static const Map<String, String> _gantiHuruf = {
    'á': 'a', 'à': 'a', 'â': 'a', 'ä': 'a', 'ã': 'a', 'å': 'a', 'é': 'e', 'è': 'e', 'ê': 'e', 'ë': 'e', //
    'í': 'i', 'ì': 'i', 'î': 'i', 'ï': 'i', 'ó': 'o', 'ò': 'o', 'ô': 'o', 'ö': 'o', 'õ': 'o', 'ú': 'u', //
    'ù': 'u', 'û': 'u', 'ü': 'u', 'ñ': 'n', 'ç': 'c', 'Á': 'A', 'À': 'A', 'É': 'E', 'È': 'E', 'Í': 'I', //
    'Ó': 'O', 'Ú': 'U', 'Ñ': 'N', 'Ç': 'C', '‘': "'", '’': "'", '“': '"', '”': '"', '–': '-', '—': '-', '−': '-', //
    '…': '...', '×': 'x', '•': '*', ' ': ' ', '\t': ' ',
  };

  /// Printer thermal murah hanya andal untuk ASCII: huruf beraksen diganti padanannya, karakter lain menjadi "?".
  static String RapikanTeks(String teks) {
    final hasil = StringBuffer();
    for (final rune in teks.runes) {
      final huruf = String.fromCharCode(rune);
      if (rune == 10 || (rune >= 32 && rune < 127)) {
        hasil.write(huruf);
      } else {
        hasil.write(_gantiHuruf[huruf] ?? '?');
      }
    }
    return hasil.toString();
  }
}
