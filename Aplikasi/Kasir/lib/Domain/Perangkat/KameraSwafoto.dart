import 'dart:typed_data';

/// Kamera depan untuk swafoto absensi (F-18). Abstraksi agar kode tampilan tidak memanggil paket platform langsung
/// dan test bisa memakai tiruan. [CekTersedia] false (mis. Windows tanpa kamera) = absen cukup dengan PIN.
abstract class KameraSwafoto {
  bool CekTersedia();

  /// JPEG terkompres (lebar ≤ 480 px); null = dibatalkan pengguna.
  Future<Uint8List?> Ambil();
}
