import 'dart:async';

import 'package:geolocator/geolocator.dart';

import '../Domain/Perangkat/PenentuLokasi.dart';

/// Lokasi perangkat lewat paket `geolocator` (Android, iOS, Windows). Izin hanya "saat aplikasi dipakai" dan baru
/// diminta saat salesman menekan "Mulai kunjungan" — tidak pernah lokasi latar. Satu pembacaan dengan batas waktu;
/// semua kegagalan (izin ditolak, layanan mati, platform tanpa lokasi, waktu habis, plugin tidak ada) → null.
/// Koordinat tidak pernah ditulis ke log.
class PenentuLokasiPlatform implements PenentuLokasi {
  const PenentuLokasiPlatform({this.batasWaktu = const Duration(seconds: 15)});

  final Duration batasWaktu;

  @override
  Future<LokasiPerangkat?> Ambil() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        return null;
      }
      var izin = await Geolocator.checkPermission();
      if (izin == LocationPermission.denied) {
        izin = await Geolocator.requestPermission();
      }
      if (izin != LocationPermission.whileInUse && izin != LocationPermission.always) {
        return null;
      }
      final posisi = await Geolocator.getCurrentPosition(
        locationSettings: LocationSettings(accuracy: LocationAccuracy.high, timeLimit: batasWaktu),
      ).timeout(batasWaktu + const Duration(seconds: 2));
      return LokasiPerangkat.DariPlatform(posisi.latitude, posisi.longitude, akurasi: posisi.accuracy);
    } on Object {
      return null;
    }
  }
}
