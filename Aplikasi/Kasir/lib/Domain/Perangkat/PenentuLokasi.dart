/// Titik lokasi perangkat untuk kunjungan salesman (Modul Salesman bagian 2). Koordinat **string desimal** maks. 7
/// angka di belakang titik — bentuk yang divalidasi server (`PenanganSinkronCatatKunjungan::POLA_LATITUDE/LONGITUDE`).
///
/// Koordinat bukan uang/kuantitas, tetapi tetap diubah ke teks sekali di batas platform ([DariPlatform]) supaya nilai
/// yang disimpan di Drift, ditampilkan, dan dikirim di outbox persis sama; tidak ada aritmetika pecahan biner setelahnya.
class LokasiPerangkat {
  const LokasiPerangkat({required this.latitude, required this.longitude, this.akurasiMeter});

  /// Batas akurasi yang diterima server (`AkurasiMeter` max 100000).
  static const int akurasiMaksimal = 100000;

  final String latitude;
  final String longitude;
  final int? akurasiMeter;

  /// Ubah nilai mentah platform (derajat & meter) ke bentuk kirim. Null bila koordinat tidak masuk akal (NaN, tak
  /// hingga, di luar rentang) — kunjungan lalu dicatat tanpa lokasi, tidak pernah ditolak.
  static LokasiPerangkat? DariPlatform(double derajatLintang, double derajatBujur, {double? akurasi}) {
    final lintang = FormatKoordinat(derajatLintang, batas: 90);
    final bujur = FormatKoordinat(derajatBujur, batas: 180);
    if (lintang == null || bujur == null) {
      return null;
    }
    final meter = akurasi == null || !akurasi.isFinite || akurasi < 0 ? null : akurasi.round();
    return LokasiPerangkat(
      latitude: lintang,
      longitude: bujur,
      akurasiMeter: meter == null ? null : (meter > akurasiMaksimal ? akurasiMaksimal : meter),
    );
  }

  /// Satu koordinat ke teks tepat 7 desimal (`-7.5666001`), atau null bila bukan angka hingga atau di luar ±[batas].
  /// `-0.0000000` dirapikan menjadi `0.0000000`.
  static String? FormatKoordinat(double derajat, {required int batas}) {
    if (!derajat.isFinite || derajat.abs() > batas) {
      return null;
    }
    final teks = derajat.toStringAsFixed(7);
    return teks == '-0.0000000' ? '0.0000000' : teks;
  }
}

/// Lokasi sekali saat salesman memulai kunjungan. Abstraksi agar kode fitur tidak memanggil paket platform langsung dan
/// test bisa memakai tiruan. Implementasi **tidak pernah melempar**: izin ditolak, layanan lokasi mati, platform tanpa
/// lokasi (mis. Windows), atau waktu habis → null, dan kunjungan tetap tercatat tanpa koordinat.
abstract class PenentuLokasi {
  Future<LokasiPerangkat?> Ambil();
}

/// Penentu lokasi yang selalu tidak tersedia (test, platform tanpa lokasi).
class PenentuLokasiTidakAda implements PenentuLokasi {
  const PenentuLokasiTidakAda();

  @override
  Future<LokasiPerangkat?> Ambil() async => null;
}
