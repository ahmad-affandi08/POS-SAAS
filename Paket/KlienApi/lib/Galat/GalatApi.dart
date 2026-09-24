/// Galat dari server dengan format seragam `{"Galat": {"Kode", "Pesan", "Detail"}}` (PRD §16.2), atau galat
/// validasi Laravel (`errors`) yang dipetakan ke kode `DataTidakValid`.
class GalatApi implements Exception {
  const GalatApi({
    required this.kode,
    required this.pesan,
    required this.statusHttp,
    this.bidang,
    this.detail = const <String, Object?>{},
  });

  final String kode;
  final String pesan;
  final int statusHttp;
  final String? bidang;
  final Map<String, Object?> detail;

  /// Perangkat sudah tidak berhak (token dicabut/tidak berlaku): aplikasi wajib menghapus data sensitif lokal.
  bool CekPerangkatDitolak() => kode == 'PerangkatDicabut' || kode == 'TokenPerangkatTidakValid';

  @override
  String toString() => 'GalatApi($statusHttp $kode: $pesan)';
}

/// Server tidak bisa dihubungi (offline, DNS, waktu habis) atau membalas galat 5xx: aman untuk dicoba lagi.
class GalatJaringan implements Exception {
  const GalatJaringan(this.pesan);

  final String pesan;

  @override
  String toString() => 'GalatJaringan($pesan)';
}
