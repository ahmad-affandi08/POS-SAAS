import 'dart:typed_data';

/// Kamera belakang untuk foto bukti kas masuk/keluar (K-18, misal nota belanja). Abstraksi agar kode tampilan tidak
/// memanggil paket platform langsung dan test bisa memakai tiruan. [CekTersedia] false (mis. Windows tanpa kamera) =
/// tombol foto tidak ditampilkan; kas tetap bisa dicatat tanpa foto.
abstract class KameraBukti {
  bool CekTersedia();

  /// JPEG terkompres (sisi terpanjang ≤ 1280 px); null = dibatalkan pengguna.
  Future<Uint8List?> Ambil();
}
