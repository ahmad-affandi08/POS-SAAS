import 'package:klien_api/KlienApi.dart';

/// Isi aktivasi perangkat dari QR back-office atau ketikan kasir (F-02 langkah 5).
///
/// D-35 edisi Lisensi: satu aplikasi dipakai banyak server toko, jadi QR dari server edisi Lisensi berbentuk
/// `{alamat server}/aktivasi-perangkat?kode={kode}`; QR edisi SaaS tetap berisi kode saja. Alamat server toko juga
/// bisa diketik manual di layar aktivasi.
class IsiAktivasi {
  const IsiAktivasi({required this.kode, this.alamatServer});

  static const String jalurAktivasi = 'aktivasi-perangkat';

  final String kode;

  /// Null = pakai alamat server yang sudah tersimpan/bawaan aplikasi.
  final Uri? alamatServer;

  static IsiAktivasi Uraikan(String isi) {
    final teks = isi.trim();
    final alamat = Uri.tryParse(teks);
    final kode = alamat?.queryParameters['kode'];
    if (alamat == null || kode == null || kode.isEmpty || !CekSkemaWeb(alamat) || alamat.host.isEmpty) {
      return IsiAktivasi(kode: teks);
    }
    final posisi = alamat.path.indexOf(jalurAktivasi);
    final jalurDasar = posisi < 0 ? '/' : alamat.path.substring(0, posisi);
    return IsiAktivasi(
      kode: kode.trim(),
      alamatServer: Uri(
        scheme: alamat.scheme,
        host: alamat.host,
        port: alamat.hasPort ? alamat.port : null,
        path: jalurDasar.endsWith('/') ? jalurDasar : '$jalurDasar/',
      ),
    );
  }

  /// Alamat server yang diketik kasir: `kasir.toko.id` → `https://kasir.toko.id/`. Null bila kosong atau tidak sah.
  static Uri? NormalkanAlamat(String teks) => NormalkanAlamatServer(teks);
}
