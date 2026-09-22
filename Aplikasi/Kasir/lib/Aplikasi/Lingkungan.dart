/// Lingkungan build (flavor) aplikasi (PRD §13.7, §17.2.1). Dipilih lewat entrypoint `Utama*.dart`.
enum Lingkungan {
  dev('Dev'),
  staging('Staging'),
  produksi('Produksi');

  const Lingkungan(this.label);

  final String label;

  /// Selain produksi, aplikasi menampilkan penanda lingkungan yang mencolok.
  bool get tampilkanPenanda => this != Lingkungan.produksi;
}
