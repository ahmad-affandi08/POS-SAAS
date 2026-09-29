import 'package:flutter/widgets.dart';

/// Pemindai QR kamera untuk kode aktivasi perangkat (F-02 langkah 5).
///
/// Abstraksi agar kode tampilan tidak memanggil paket platform langsung dan test bisa memakai tiruan, sama seperti
/// [KameraSwafoto]. [CekTersedia] false (Windows, atau perangkat tanpa kamera) = kode aktivasi diketik manual;
/// isian manual tetap jalur utama di semua platform.
abstract class PemindaiQr {
  bool CekTersedia();

  /// Membuka pemindai dan mengembalikan isi barcode/QR pertama yang terbaca; null = dibatalkan pengguna.
  Future<String?> Pindai(
    BuildContext context, {
    String judul = 'Pindai kode QR',
    String petunjuk = 'Arahkan kamera ke kode QR di back-office.',
  });
}
