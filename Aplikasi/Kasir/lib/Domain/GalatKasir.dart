/// Pelanggaran aturan di aplikasi kasir (ditampilkan apa adanya ke kasir). `kode` sama dengan kode galat server bila
/// aturannya sama (misal `ShiftSudahTerbuka`, `PersetujuanDiperlukan`).
class GalatKasir implements Exception {
  const GalatKasir(this.kode, this.pesan);

  final String kode;
  final String pesan;

  @override
  String toString() => 'GalatKasir($kode: $pesan)';
}
