import 'package:klien_api/KlienApi.dart';

/// Satu pesanan di layar panggil antrian: nomor besar (`042`) dan nama pemesan bila ada.
class NomorPanggil {
  const NomorPanggil({required this.nomorDokumen, required this.nomor, required this.nama, required this.sejak});

  final String nomorDokumen;

  /// Nomor antrian tanpa `#`; bila penjualan tanpa nomor antrian = nama pemesan/label apa adanya.
  final String nomor;
  final String? nama;

  /// Waktu kirim tiket terbaru pesanan ini (urutan tampil).
  final DateTime sejak;
}

/// K-2 lanjutan (§9.2 QSR): layar panggil antrian dari tiket dapur penjualan bayar-dulu. Tiket dikelompokkan per dokumen
/// penjualan (satu pesanan bisa punya beberapa tiket stasiun). Pesanan **Silakan diambil** bila semua tiketnya Siap (atau
/// sebagian sudah Disajikan); **Sedang disiapkan** bila masih ada yang Antre/Dimasak; hilang setelah semua Disajikan.
/// Pesanan meja (punya nama meja) dan tiket tanpa label tidak ditampilkan karena diantar ke meja.
abstract final class PenyusunLayarAntrian {
  static const int batasTampil = 12;

  static ({List<NomorPanggil> disiapkan, List<NomorPanggil> siap}) Susun(List<TiketDapurPos> tiket) {
    final perDokumen = <String, List<TiketDapurPos>>{};
    for (final t in tiket) {
      final label = t.label?.trim() ?? '';
      if (t.namaMeja != null || label.isEmpty) {
        continue;
      }
      perDokumen.putIfAbsent(t.nomorDokumen, () => []).add(t);
    }
    final disiapkan = <NomorPanggil>[];
    final siap = <NomorPanggil>[];
    for (final MapEntry(key: dokumen, value: daftar) in perDokumen.entries) {
      if (daftar.every((t) => t.status == 'Disajikan')) {
        continue;
      }
      final (nomor, nama) = UraiLabel(daftar.first.label!.trim());
      final sejak = daftar.map((t) => t.dikirimPada).reduce((a, b) => a.isAfter(b) ? a : b);
      final item = NomorPanggil(nomorDokumen: dokumen, nomor: nomor, nama: nama, sejak: sejak);
      if (daftar.every((t) => t.status == 'Siap' || t.status == 'Disajikan')) {
        siap.add(item);
      } else {
        disiapkan.add(item);
      }
    }
    // Disiapkan: terlama dulu (yang akan segera dipanggil); siap: terbaru dulu agar nomor yang baru dipanggil di atas.
    disiapkan.sort((a, b) => a.sejak.compareTo(b.sejak));
    siap.sort((a, b) => b.sejak.compareTo(a.sejak));
    return (disiapkan: disiapkan.take(batasTampil).toList(), siap: siap.take(batasTampil).toList());
  }

  /// `#042 Budi` → (`042`, `Budi`); `#042` → (`042`, null); `Budi` → (`Budi`, null).
  static (String, String?) UraiLabel(String label) {
    final cocok = RegExp(r'^#(\S+)\s*(.*)$').firstMatch(label);
    if (cocok == null) {
      return (label, null);
    }
    final nama = cocok.group(2)!.trim();
    return (cocok.group(1)!, nama.isEmpty ? null : nama);
  }
}
