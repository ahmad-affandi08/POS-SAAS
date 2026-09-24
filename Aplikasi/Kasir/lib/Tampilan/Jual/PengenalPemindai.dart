/// Pengenal input pemindai barcode tanpa fokus (PRD §17.2.7): pemindai USB/Bluetooth bertingkah seperti papan ketik
/// yang mengetik sangat cepat lalu menekan Enter. Rangkaian karakter dengan jeda antar-karakter ≤ [jedaMaks] yang
/// diakhiri Enter dan panjangnya ≥ [panjangMin] dianggap hasil pindai; ketikan manusia (lebih lambat) diabaikan.
class PengenalPemindai {
  PengenalPemindai({this.jedaMaks = const Duration(milliseconds: 100), this.panjangMin = 4, Duration Function()? jam})
    : _jam = jam ?? _BuatJamBawaan();

  final Duration jedaMaks;
  final int panjangMin;
  final Duration Function() _jam;

  final StringBuffer _penyangga = StringBuffer();
  Duration? _terakhir;

  static Duration Function() _BuatJamBawaan() {
    final stopwatch = Stopwatch()..start();
    return () => stopwatch.elapsed;
  }

  /// [waktu] = cap waktu event papan ketik; `Duration.zero` (tidak tersedia) → jam internal.
  Duration _Waktu(Duration waktu) => waktu == Duration.zero ? _jam() : waktu;

  void Terima(String karakter, [Duration waktu = Duration.zero]) {
    final sekarang = _Waktu(waktu);
    if (_terakhir != null && sekarang - _terakhir! > jedaMaks) {
      _penyangga.clear();
    }
    _penyangga.write(karakter);
    _terakhir = sekarang;
  }

  /// Enter ditekan: kembalikan kode hasil pindai, atau null bila bukan pindaian. Penyangga selalu dikosongkan.
  String? Selesai([Duration waktu = Duration.zero]) {
    final sekarang = _Waktu(waktu);
    final kode = _penyangga.toString();
    final cepat = _terakhir != null && sekarang - _terakhir! <= jedaMaks;
    Reset();
    return cepat && kode.length >= panjangMin ? kode : null;
  }

  void Reset() {
    _penyangga.clear();
    _terakhir = null;
  }
}
