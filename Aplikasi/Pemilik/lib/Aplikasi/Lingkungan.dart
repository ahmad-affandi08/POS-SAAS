/// Lingkungan build (flavor) aplikasi (PRD §13.7, §17.2.1). Dipilih lewat entrypoint `Utama*.dart`.
enum Lingkungan {
  Dev('Dev'),
  Staging('Staging'),
  Produksi('Produksi');

  const Lingkungan(this.label);

  final String label;

  /// Selain produksi, aplikasi menampilkan penanda lingkungan yang mencolok.
  bool get tampilkanPenanda => this != Lingkungan.Produksi;

  /// Alamat server API (`--dart-define=ALAMAT_SERVER=https://…/`). Dev bawaan: server lokal dari emulator Android.
  Uri AmbilAlamatServer() {
    const alamat = String.fromEnvironment('ALAMAT_SERVER');
    if (alamat.isNotEmpty) {
      return Uri.parse(alamat.endsWith('/') ? alamat : '$alamat/');
    }
    return Uri.parse(this == Lingkungan.Dev ? 'http://10.0.2.2:8000/' : 'https://alamat-server-belum-diatur.invalid/');
  }
}
